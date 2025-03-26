<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: index.php');
    exit;
}

include 'dbconfig.php';

// Clean up logs older than 30 days
$cleanup_sql = "CALL CleanupOldLogs()";
if ($cleanup_stmt = $link->prepare($cleanup_sql)) {
    $cleanup_stmt->execute();
    $cleanup_stmt->close();
}

// Initialize variables
$ddns_id = isset($_GET['ddns_id']) ? (int)$_GET['ddns_id'] : null;
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
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
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
<html>
<head>
    <title><?= $ddns_id ? "Logs for DDNS #$ddns_id" : "All DDNS Logs" ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1><?= $ddns_id ? "Logs for DDNS Entry #$ddns_id" : "All DDNS Logs" ?></h1>
        
        <!-- Logs Table -->
        <table class="table table-striped">
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

        <!-- Pagination -->
        <nav>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <p class="mt-3">
            <a href="<?= $ddns_id ? 'manage_ddns.php' : 'dashboard.php' ?>" class="btn btn-secondary">
                Back to <?= $ddns_id ? 'DDNS Management' : 'Dashboard' ?>
            </a>
        </p>
    </div>
</body>
</html>
