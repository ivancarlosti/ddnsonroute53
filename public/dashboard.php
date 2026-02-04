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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <div class="flex gap-2 mt-4">
                <a href="manage_users.php" class="btn">Manage Users</a>
                <a href="manage_aws.php" class="btn">Manage AWS Credentials</a>
                <a href="manage_ddns.php" class="btn">Manage DDNS Entries</a>
                <a href="view_logs.php" class="btn">View All Logs</a>
                <a href="index.php?logout=true" class="btn btn-danger">Logout</a>
            </div>
        </div>

        <div class="card">
            <h2>DDNS Update cURL Command</h2>
            <p>Use the following cURL command to update your DDNS entry:</p>
            <pre style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 0.5rem; overflow-x: auto;"><?php echo htmlspecialchars($curlCommand); ?></pre>
        </div>
    </div>
</body>
</html>
