<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: index.php');
    exit;
}

include 'dbconfig.php';
require 'aws/aws-autoloader.php';

use Aws\Route53\Route53Client;
use Aws\Exception\AwsException;

// Fetch the approved FQDN from the database
$approved_fqdn = '';
$aws_sql = "SELECT approved_fqdn, region, access_key_id, secret_access_key, hosted_zone_id FROM aws_credentials LIMIT 1";
if ($aws_result = $link->query($aws_sql)) {
    if ($aws_result->num_rows > 0) {
        $row = $aws_result->fetch_assoc();
        $approved_fqdn = $row['approved_fqdn'];
        $region = $row['region'];
        $access_key_id = $row['access_key_id'];
        $secret_access_key = $row['secret_access_key'];
        $hosted_zone_id = $row['hosted_zone_id'];
    } else {
        die("No AWS credentials found in the database.");
    }
    $aws_result->free();
} else {
    die("Error fetching AWS credentials: " . $link->error);
}

// Initialize the Route53 client
try {
    $route53 = new Route53Client([
        'version'     => 'latest',
        'region'      => $region,
        'credentials' => [
            'key'    => $access_key_id,
            'secret' => $secret_access_key,
        ],
    ]);
} catch (AwsException $e) {
    die("Error initializing Route53 client: " . $e->getMessage());
}

// Handle form submission to add a new DDNS entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_ddns'])) {
    $ddns_fqdn = $_POST['ddns_fqdn'];
    $ddns_password = $_POST['ddns_password'];
    $initial_ip = $_POST['initial_ip']; // Initial IP is required
    $ttl = $_POST['ttl']; // TTL is required

    // Validate input
    if (empty($ddns_fqdn) || empty($ddns_password) || empty($initial_ip) || empty($ttl)) {
        echo "DDNS FQDN, password, initial IP, and TTL are required.";
    } elseif (!filter_var($initial_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        echo "Invalid IPv4 address.";
    } else {
        // Check if the DDNS FQDN is a subdomain of the approved FQDN (only for new entries)
        if (strpos($ddns_fqdn, $approved_fqdn) === false || !preg_match('/^[a-zA-Z0-9-]+\.' . preg_quote($approved_fqdn, '/') . '$/', $ddns_fqdn)) {
            echo "DDNS FQDN must be a subdomain of $approved_fqdn.";
        } else {
            // Check if the DDNS entry already exists
            $check_sql = "SELECT id FROM ddns_entries WHERE ddns_fqdn = ?";
            if ($check_stmt = $link->prepare($check_sql)) {
                $check_stmt->bind_param("s", $ddns_fqdn);
                $check_stmt->execute();
                $check_stmt->store_result();

                if ($check_stmt->num_rows > 0) {
                    echo "DDNS entry with this FQDN already exists.";
                } else {
                    // Prepare the DNS record
                    $changeBatch = [
                        'Changes' => [
                            [
                                'Action' => 'UPSERT',
                                'ResourceRecordSet' => [
                                    'Name' => $ddns_fqdn . '.', // Add trailing dot for FQDN
                                    'Type' => 'A',
                                    'TTL'  => (int)$ttl, // Use the provided TTL
                                    'ResourceRecords' => [
                                        [
                                            'Value' => $initial_ip, // Use the provided IP
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];

                    try {
                        // Create the DNS record in Route53
                        $result = $route53->changeResourceRecordSets([
                            'HostedZoneId' => $hosted_zone_id,
                            'ChangeBatch'  => $changeBatch,
                        ]);

                        // Insert the new DDNS entry into the database
                        $insert_sql = "INSERT INTO ddns_entries (ddns_fqdn, ddns_password, last_ipv4, ttl) VALUES (?, ?, ?, ?)";
                        if ($insert_stmt = $link->prepare($insert_sql)) {
                            $insert_stmt->bind_param("sssi", $ddns_fqdn, $ddns_password, $initial_ip, $ttl);
                            if ($insert_stmt->execute()) {
                                echo "DDNS entry '$ddns_fqdn' added successfully!";
                            } else {
                                echo "Error adding DDNS entry: " . $insert_stmt->error;
                            }
                            $insert_stmt->close();
                        }
                    } catch (AwsException $e) {
                        echo "Error updating Route53: " . $e->getAwsErrorMessage();
                    }
                }
                $check_stmt->close();
            }
        }
    }
}

// Handle IP and TTL update for a DDNS entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_ip'])) {
    $ddns_id = $_POST['ddns_id'];
    $new_ip = $_POST['new_ip'];
    $new_ttl = $_POST['new_ttl']; // New TTL is required

    // Validate input
    if (empty($new_ip) || empty($new_ttl)) {
        echo "IP and TTL are required.";
    } elseif (!filter_var($new_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        echo "Invalid IPv4 address.";
    } else {
        // Fetch the DDNS entry
        $fetch_sql = "SELECT ddns_fqdn FROM ddns_entries WHERE id = ?";
        if ($fetch_stmt = $link->prepare($fetch_sql)) {
            $fetch_stmt->bind_param("i", $ddns_id);
            $fetch_stmt->execute();
            $fetch_stmt->store_result();
            $fetch_stmt->bind_result($ddns_fqdn);
            $fetch_stmt->fetch();
            $fetch_stmt->close();

            // Prepare the DNS record update
            $changeBatch = [
                'Changes' => [
                    [
                        'Action' => 'UPSERT',
                        'ResourceRecordSet' => [
                            'Name' => $ddns_fqdn . '.', // Add trailing dot for FQDN
                            'Type' => 'A',
                            'TTL'  => (int)$new_ttl, // Use the new TTL
                            'ResourceRecords' => [
                                [
                                    'Value' => $new_ip,
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
                    'ChangeBatch'  => $changeBatch,
                ]);

                // Update the IP and TTL in the database
                $update_sql = "UPDATE ddns_entries SET last_ipv4 = ?, ttl = ?, last_update = NOW() WHERE id = ?";
                if ($update_stmt = $link->prepare($update_sql)) {
                    $update_stmt->bind_param("sii", $new_ip, $new_ttl, $ddns_id);
                    if ($update_stmt->execute()) {
                        echo "IP and TTL updated successfully for '$ddns_fqdn'!";
                    } else {
                        echo "Error updating IP and TTL: " . $update_stmt->error;
                    }
                    $update_stmt->close();
                }
            } catch (AwsException $e) {
                echo "Error updating Route53: " . $e->getAwsErrorMessage();
            }
        }
    }
}

// Handle DDNS entry deletion
if (isset($_GET['delete'])) {
    $ddns_id = $_GET['delete'];

    // Fetch the DDNS entry to get the FQDN and last IP
    $fetch_sql = "SELECT ddns_fqdn, last_ipv4, ttl FROM ddns_entries WHERE id = ?";
    if ($fetch_stmt = $link->prepare($fetch_sql)) {
        $fetch_stmt->bind_param("i", $ddns_id);
        $fetch_stmt->execute();
        $fetch_stmt->store_result();
        $fetch_stmt->bind_result($ddns_fqdn, $last_ipv4, $ttl);
        $fetch_stmt->fetch();
        $fetch_stmt->close();

        // Prepare the DNS record deletion
        $changeBatch = [
            'Changes' => [
                [
                    'Action' => 'DELETE',
                    'ResourceRecordSet' => [
                        'Name' => $ddns_fqdn . '.', // Add trailing dot for FQDN
                        'Type' => 'A',
                        'TTL'  => (int)$ttl, // Use the current TTL
                        'ResourceRecords' => [
                            [
                                'Value' => $last_ipv4, // Use the current IP address
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            // Delete the DNS record in Route53
            $result = $route53->changeResourceRecordSets([
                'HostedZoneId' => $hosted_zone_id,
                'ChangeBatch'  => $changeBatch,
            ]);

            // Delete the DDNS entry from the database
            $delete_sql = "DELETE FROM ddns_entries WHERE id = ?";
            if ($delete_stmt = $link->prepare($delete_sql)) {
                $delete_stmt->bind_param("i", $ddns_id);
                if ($delete_stmt->execute()) {
                    echo "DDNS entry deleted successfully and Route53 record removed!";
                } else {
                    echo "Error deleting DDNS entry: " . $delete_stmt->error;
                }
                $delete_stmt->close();
            }
        } catch (AwsException $e) {
            echo "Error updating Route53: " . $e->getAwsErrorMessage();
        }
    }
}

// Fetch all DDNS entries from the database
$sql = "SELECT id, ddns_fqdn, ddns_password, last_ipv4, ttl, last_update FROM ddns_entries";
$ddns_entries = [];
if ($result = $link->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $ddns_entries[] = $row;
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage DDNS Entries</title>
    <!-- Add jQuery for input masking -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Add jQuery Mask Plugin -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function() {
            // Mask for email address
            $('input[name="username"]').on('input', function() {
                const value = $(this).val();
                if (!/^[^@]+@[^@]+\.[^@]+$/.test(value)) {
                    $(this).val(value.replace(/[^a-zA-Z0-9@._-]/g, ''));
                }
            });
        });
    </script>
</head>
<body>
    <h1>Manage DDNS Entries</h1>
    <h2>Add New DDNS Entry</h2>
    <form method="post">
        <label>DDNS FQDN:</label>
        <input type="text" name="ddns_fqdn" required><br>
        <label>DDNS Password:</label>
        <input type="password" name="ddns_password" required><br>
        <label>Initial IP:</label>
        <input type="text" name="initial_ip" required><br>
        <label>TTL (Time to Live):</label>
        <input type="number" name="ttl" min="1" required><br>
        <input type="submit" name="add_ddns" value="Add DDNS Entry">
    </form>

    <h2>DDNS Entries</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>FQDN</th>
            <th>Password</th>
            <th>Last IPv4</th>
            <th>TTL</th>
            <th>Last Update</th>
            <th>Update IP/TTL</th>
            <th>Action</th>
        </tr>
        <?php foreach ($ddns_entries as $entry): ?>
        <tr>
            <td><?php echo $entry['id']; ?></td>
            <td><?php echo htmlspecialchars($entry['ddns_fqdn']); ?></td>
            <td><?php echo htmlspecialchars($entry['ddns_password']); ?></td>
            <td><?php echo htmlspecialchars($entry['last_ipv4']); ?></td>
            <td><?php echo htmlspecialchars($entry['ttl']); ?></td>
            <td><?php echo htmlspecialchars($entry['last_update']); ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="ddns_id" value="<?php echo $entry['id']; ?>">
                    <input type="text" name="new_ip" placeholder="New IP" required><br>
                    <input type="number" name="new_ttl" placeholder="New TTL" min="1" required><br>
                    <input type="submit" name="update_ip" value="Update IP/TTL">
                </form>
            </td>
            <td>
                <a href="manage_ddns.php?delete=<?php echo $entry['id']; ?>" onclick="return confirm('Are you sure you want to delete this DDNS entry?');">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>