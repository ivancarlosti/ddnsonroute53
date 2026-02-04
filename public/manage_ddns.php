<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: index.php');
    exit;
}

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
        'version' => 'latest',
        'region' => $region,
        'credentials' => [
            'key' => $access_key_id,
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
    $initial_ip = $_POST['initial_ip'];
    $ttl = $_POST['ttl'];

    // Validate input
    if (empty($ddns_fqdn) || empty($ddns_password) || empty($initial_ip) || empty($ttl)) {
        $error = "DDNS FQDN, password, initial IP, and TTL are required.";
    } elseif (!filter_var($initial_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $error = "Invalid IPv4 address.";
    } else {
        // Check if the DDNS FQDN is a subdomain of the approved FQDN
        if (strpos($ddns_fqdn, $approved_fqdn) === false || !preg_match('/^[a-zA-Z0-9-]+\.' . preg_quote($approved_fqdn, '/') . '$/', $ddns_fqdn)) {
            $error = "DDNS FQDN must be a subdomain of $approved_fqdn.";
        } else {
            // Check if the DDNS entry already exists
            $check_sql = "SELECT id FROM ddns_entries WHERE ddns_fqdn = ?";
            if ($check_stmt = $link->prepare($check_sql)) {
                $check_stmt->bind_param("s", $ddns_fqdn);
                $check_stmt->execute();
                $check_stmt->store_result();

                if ($check_stmt->num_rows > 0) {
                    $error = "DDNS entry with this FQDN already exists.";
                } else {
                    // Prepare the DNS record
                    $changeBatch = [
                        'Changes' => [
                            [
                                'Action' => 'UPSERT',
                                'ResourceRecordSet' => [
                                    'Name' => $ddns_fqdn . '.',
                                    'Type' => 'A',
                                    'TTL' => (int) $ttl,
                                    'ResourceRecords' => [
                                        [
                                            'Value' => $initial_ip,
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
                            'ChangeBatch' => $changeBatch,
                        ]);

                        // Insert the new DDNS entry into the database
                        $insert_sql = "INSERT INTO ddns_entries (ddns_fqdn, ddns_password, last_ipv4, ttl) VALUES (?, ?, ?, ?)";
                        if ($insert_stmt = $link->prepare($insert_sql)) {
                            $insert_stmt->bind_param("sssi", $ddns_fqdn, $ddns_password, $initial_ip, $ttl);
                            if ($insert_stmt->execute()) {
                                $ddns_entry_id = $insert_stmt->insert_id;

                                // Log the action
                                $action = 'add';
                                $ip_address = $_SERVER['REMOTE_ADDR'];
                                $details = "Added DDNS entry with FQDN: $ddns_fqdn, Initial IP: $initial_ip, TTL: $ttl";
                                $log_sql = "INSERT INTO ddns_logs (ddns_entry_id, action, ip_address, details) VALUES (?, ?, ?, ?)";
                                if ($log_stmt = $link->prepare($log_sql)) {
                                    $log_stmt->bind_param("isss", $ddns_entry_id, $action, $ip_address, $details);
                                    $log_stmt->execute();
                                    $log_stmt->close();
                                }

                                $success = "DDNS entry '$ddns_fqdn' added successfully!";
                            } else {
                                $error = "Error adding DDNS entry: " . $insert_stmt->error;
                            }
                            $insert_stmt->close();
                        }
                    } catch (AwsException $e) {
                        $error = "Error updating Route53: " . $e->getAwsErrorMessage();
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
    $new_ttl = $_POST['new_ttl'];

    // Validate input
    if (empty($new_ip) || empty($new_ttl)) {
        $error = "IP and TTL are required.";
    } elseif (!filter_var($new_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $error = "Invalid IPv4 address.";
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
                            'Name' => $ddns_fqdn . '.',
                            'Type' => 'A',
                            'TTL' => (int) $new_ttl,
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
                    'ChangeBatch' => $changeBatch,
                ]);

                // Update the IP and TTL in the database
                $update_sql = "UPDATE ddns_entries SET last_ipv4 = ?, ttl = ?, last_update = NOW() WHERE id = ?";
                if ($update_stmt = $link->prepare($update_sql)) {
                    $update_stmt->bind_param("sii", $new_ip, $new_ttl, $ddns_id);
                    if ($update_stmt->execute()) {
                        // Log the action
                        $action = 'update';
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        $details = "Updated IP: $new_ip, TTL: $new_ttl";
                        $log_sql = "INSERT INTO ddns_logs (ddns_entry_id, action, ip_address, details) VALUES (?, ?, ?, ?)";
                        if ($log_stmt = $link->prepare($log_sql)) {
                            $log_stmt->bind_param("isss", $ddns_id, $action, $ip_address, $details);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }

                        $success = "IP and TTL updated successfully for '$ddns_fqdn'!";
                    } else {
                        $error = "Error updating IP and TTL: " . $update_stmt->error;
                    }
                    $update_stmt->close();
                }
            } catch (AwsException $e) {
                $error = "Error updating Route53: " . $e->getAwsErrorMessage();
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
                        'Name' => $ddns_fqdn . '.',
                        'Type' => 'A',
                        'TTL' => (int) $ttl,
                        'ResourceRecords' => [
                            [
                                'Value' => $last_ipv4,
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
                'ChangeBatch' => $changeBatch,
            ]);

            // Delete the DDNS entry from the database
            $delete_sql = "DELETE FROM ddns_entries WHERE id = ?";
            if ($delete_stmt = $link->prepare($delete_sql)) {
                $delete_stmt->bind_param("i", $ddns_id);
                if ($delete_stmt->execute()) {
                    $success = "DDNS entry deleted successfully and Route53 record removed!";
                } else {
                    $error = "Error deleting DDNS entry: " . $delete_stmt->error;
                }
                $delete_stmt->close();
            }
        } catch (AwsException $e) {
            $error = "Error updating Route53: " . $e->getAwsErrorMessage();
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage DDNS Entries</title>
    <link rel="stylesheet" href="style.css">
    <script src="table_sort.js"></script>
    <style>
        th.sortable {
            cursor: pointer;
            position: relative;
        }

        th.sortable:hover {
            background-color: #f0f0f0;
        }

        th.sortable::after {
            content: '↕';
            position: absolute;
            right: 8px;
            opacity: 0.3;
        }

        th.sortable.asc::after {
            content: '↑';
            opacity: 1;
        }

        th.sortable.desc::after {
            content: '↓';
            opacity: 1;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Manage DDNS Entries</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Add New DDNS Entry</h2>
            <form method="post">
                <label>DDNS FQDN:</label>
                <input type="text" name="ddns_fqdn" required
                    placeholder="subdomain.<?php echo htmlspecialchars($approved_fqdn); ?>">

                <label>DDNS Password:</label>
                <input type="password" name="ddns_password" required>

                <label>Initial IP:</label>
                <input type="text" name="initial_ip" required value="<?php echo $_SERVER['REMOTE_ADDR']; ?>">

                <label>TTL (Time to Live):</label>
                <input type="number" name="ttl" min="1" required value="300">

                <input type="submit" name="add_ddns" value="Add DDNS Entry">
            </form>
        </div>

        <div class="card">
            <h2>DDNS Entries</h2>
            <div class="table-responsive">
                <table id="ddnsTable">
                    <thead>
                        <tr>
                            <th class="sortable" data-type="string">FQDN</th>
                            <th class="sortable" data-type="string">Password</th>
                            <th class="sortable" data-type="string">Last IPv4</th>
                            <th class="sortable" data-type="number">TTL</th>
                            <th class="sortable" data-type="string">Last Update</th>
                            <th>Update IP/TTL</th>
                            <th>Logs</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ddns_entries as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['ddns_fqdn']); ?></td>
                                <td><?php echo htmlspecialchars($entry['ddns_password']); ?></td>
                                <td><?php echo htmlspecialchars($entry['last_ipv4']); ?></td>
                                <td><?php echo htmlspecialchars($entry['ttl']); ?></td>
                                <td><?php echo htmlspecialchars($entry['last_update']); ?></td>
                                <td>
                                    <form method="post" style="display:inline; max-width: none;">
                                        <input type="hidden" name="ddns_id" value="<?php echo $entry['id']; ?>">
                                        <div class="flex gap-2">
                                            <input type="text" name="new_ip" placeholder="New IP" required
                                                style="width: 120px;">
                                            <input type="number" name="new_ttl" placeholder="TTL" min="1" required
                                                style="width: 80px;">
                                            <input type="submit" name="update_ip" value="Update" style="padding: 0.5rem;">
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    <a href="view_logs.php?ddns_id=<?php echo $entry['id']; ?>" class="btn"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Logs</a>
                                </td>
                                <td>
                                    <a href="manage_ddns.php?delete=<?php echo $entry['id']; ?>"
                                        onclick="return confirm('Are you sure you want to delete this DDNS entry?');"
                                        class="btn btn-danger"
                                        style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
</body>

</html>