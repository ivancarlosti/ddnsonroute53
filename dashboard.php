<?php
session_start();
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true){
    header('location: index.php');
    exit;
}

// Get the current domain of the hosted page
$domain = $_SERVER['HTTP_HOST']; // This will automatically get the domain of the hosted page

// Construct the cURL command
$curlCommand = "https://[USERNAME]:[PASSWORD]@$domain/update.php?hostname=[DOMAIN]&myip=[IP]";
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

    <!-- Display the cURL command -->
    <h2>DDNS Update cURL Command</h2>
    <p>Use the following cURL command to update your DDNS entry:</p>
    <pre><?php echo htmlspecialchars($curlCommand); ?></pre>
</body>
</html>
