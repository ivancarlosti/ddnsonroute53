<?php
session_start();
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true){
    header('location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <p><a href="manage_users.php">Manage Users</a></p>
    <p><a href="manage_aws.php">Manage AWS Credentials</a></p>
    <p><a href="manage_ddns.php">Manage DDNS Entries</a></p>
    <p><a href="index.php">Logout</a></p>
</body>
</html>