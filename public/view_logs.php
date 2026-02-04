<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: index.php');
    exit;
}

include '../dbconfig.php';

// Clean up logs older than 30 days
$cleanup_sql = "CALL CleanupOldLogs()";
if ($cleanup_stmt = $link->prepare($cleanup_sql)) {
    $cleanup_stmt->execute();
    $cleanup_stmt->close();
}

// Initialize variables
$ddns_id = isset($_GET['ddns_id']) ? (int) $_GET['ddns_id'] : null;
$where_clause = "";
$params = [];
$types = "";

// Build WHERE clause if ddns_id is specified
if ($ddns_id !== null) {
    $where_clause = " WHERE l.ddns_entry_id = ?";
    $params[] = $ddns_id;
    $types = "i";
}

// Pagination setup
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Main query with conditional filtering
$query = "SELECT l.*, d.ddns_fqdn 
          FROM ddns_logs l 
          LEFT JOIN ddns_entries d ON l.ddns_entry_id = d.id
          $where_clause
          ORDER BY l.timestamp DESC
          LIMIT ?, ?";

// Count query with same filtering
$count_query = "SELECT COUNT(*) as total 
                FROM ddns_logs l
                $where_clause";

// Prepare and execute count query
$count_stmt = $link->prepare($count_query);
if ($ddns_id !== null) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Calculate total pages
$pages = ceil($total / $per_page);

// Prepare main query
$stmt = $link->prepare($query);
if ($ddns_id !== null) {
    $params[] = $offset;
    $params[] = $per_page;
    $stmt->bind_param($types . "ii", ...$params);
} else {
    $stmt->bind_param("ii", $offset, $per_page);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $ddns_id ? "Logs for DDNS #$ddns_id" : "All DDNS Logs" ?></title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1><?= $ddns_id ? "Logs for DDNS Entry #$ddns_id" : "All DDNS Logs" ?></h1>

        <div class="card">
            <!-- Logs Table -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>FQDN</th>
                            <th>Action</th>
                            <th>IP</th>
                            <th>Details</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['ddns_fqdn'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td><?= htmlspecialchars($log['details']) ?></td>
                                <td><?= htmlspecialchars($log['timestamp']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex gap-2 mt-4" style="justify-content: center;">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn">Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                        class="btn <?= $i == $page ? 'btn-primary' : '' ?>"
                        style="<?= $i == $page ? 'background-color: var(--primary-hover);' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn">Next</a>
                <?php endif; ?>
            </div>
        </div>

        <p class="mt-4">
            <a href="<?= $ddns_id ? 'manage_ddns.php' : 'dashboard.php' ?>">
                Back to <?= $ddns_id ? 'DDNS Management' : 'Dashboard' ?>
            </a>
        </p>
    </div>
</body>

</html>