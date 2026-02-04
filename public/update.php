<?php
include '../dbconfig.php';
require '../vendor/autoload.php';

use Aws\Route53\Route53Client;
use Aws\Exception\AwsException;

// Clean up logs older than 30 days
$cleanup_sql = "CALL CleanupOldLogs()";
if ($cleanup_stmt = $link->prepare($cleanup_sql)) {
    $cleanup_stmt->execute();
    $cleanup_stmt->close();
}

// Extract the hostname and IP from the request
$ddns_fqdn = $_GET['hostname'];
$myip = $_GET['myip'];
$ddns_password = $_SERVER['PHP_AUTH_PW'];

// Validate the request
if (empty($ddns_fqdn) || empty($myip) || empty($ddns_password)) {
    die("badauth");
}

// Fetch the DDNS entry from the database
$sql = "SELECT id, ddns_fqdn, ddns_password, last_ipv4, ttl FROM ddns_entries WHERE ddns_fqdn = ? AND ddns_password = ?";
if ($stmt = $link->prepare($sql)) {
    $stmt->bind_param("ss", $ddns_fqdn, $ddns_password);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $ddns_fqdn, $ddns_password, $last_ipv4, $ttl);
        $stmt->fetch();

        // Check if the IP has changed
        if ($last_ipv4 !== $myip) {
            // Fetch AWS credentials from the database
            $aws_sql = "SELECT region, access_key_id, secret_access_key, hosted_zone_id FROM aws_credentials LIMIT 1";
            if ($aws_stmt = $link->prepare($aws_sql)) {
                $aws_stmt->execute();
                $aws_stmt->store_result();
                $aws_stmt->bind_result($region, $access_key_id, $secret_access_key, $hosted_zone_id);
                $aws_stmt->fetch();
                $aws_stmt->close();

                // Initialize the Route53 client
                $route53 = new Route53Client([
                    'version'     => 'latest',
                    'region'      => $region,
                    'credentials' => [
                        'key'    => $access_key_id,
                        'secret' => $secret_access_key,
                    ],
                ]);

                // Prepare the DNS record update
                $changeBatch = [
                    'Changes' => [
                        [
                            'Action' => 'UPSERT',
                            'ResourceRecordSet' => [
                                'Name' => $ddns_fqdn,
                                'Type' => 'A',
                                'TTL'  => $ttl,
                                'ResourceRecords' => [
                                    [
                                        'Value' => $myip,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                try {
                    // Update the DNS record in Route53
                    $result = $route53->changeResourceRecordSets([
                        'HostedZoneId' => $hosted_zone_id,
                        'ChangeBatch' => $changeBatch,
                    ]);

                    // Update the database with the new IP
                    $update_sql = "UPDATE ddns_entries SET last_ipv4 = ?, last_update = NOW() WHERE id = ?";
                    if ($update_stmt = $link->prepare($update_sql)) {
                        $update_stmt->bind_param("si", $myip, $id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }

                    // Log the action
                    $action = 'update';
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $details = "Updated IP: $myip";
                    $log_sql = "INSERT INTO ddns_logs (ddns_entry_id, action, ip_address, details) VALUES (?, ?, ?, ?)";
                    if ($log_stmt = $link->prepare($log_sql)) {
                        $log_stmt->bind_param("isss", $id, $action, $ip_address, $details);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }

                    echo "good"; // Success
                } catch (AwsException $e) {
                    echo "dnserror"; // DNS update failed
                }
            } else {
                echo "badauth"; // AWS credentials not found
            }
        } else {
            echo "nochg"; // IP hasn't changed
        }
    } else {
        echo "badauth"; // Invalid DDNS credentials
    }
    $stmt->close();
} else {
    echo "badauth"; // Database error
}
$link->close();
?>
