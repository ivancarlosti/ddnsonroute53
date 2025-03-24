<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: index.php');
    exit;
}

include 'dbconfig.php';

$ddns_id = $_GET['ddns_id'];

// Clean up logs older than 30 days
$cleanup_sql = "CALL CleanupOldLogs()";
if ($cleanup_stmt = $link->prepare($cleanup_sql)) {
    $cleanup_stmt->execute();
    $cleanup_stmt->close();
}

// Fetch logs for the specified DDNS entry
$sql = "SELECT action, ip_address, details, timestamp FROM ddns_logs WHERE ddns_entry_id = ? ORDER BY timestamp DESC";
if ($stmt = $link->prepare($sql)) {
    $stmt->bind_param("i", $ddns_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($action, $ip_address, $details, $timestamp);

    $logs = [];
    while ($stmt->fetch()) {
        $logs[] = [
            'action' => $action,
            'ip_address' => $ip_address,
            'details' => $details,
            'timestamp' => $timestamp
        ];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Logs</title>
</head>
<body>
    <h1>Logs for DDNS Entry #<?php echo $ddns_id; ?></h1>
    <table border="1">
        <tr>
            <th>Action</th>
            <th>IP Address</th>
            <th>Details</th>
            <th>Timestamp</th>
        </tr>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?php echo htmlspecialchars($log['action']); ?></td>
            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
            <td><?php echo htmlspecialchars($log['details']); ?></td>
            <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a href="manage_ddns.php">Back to Manage DDNS Entries</a></p>
</body>
</html>