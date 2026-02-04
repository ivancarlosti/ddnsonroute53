<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: index.php');
    exit;
}

include '../dbconfig.php';

// Handle form submission to update AWS credentials
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $region = $_POST['region'];
    $access_key_id = $_POST['access_key_id'];
    $secret_access_key = $_POST['secret_access_key'];
    $hosted_zone_id = $_POST['hosted_zone_id'];
    $approved_fqdn = $_POST['approved_fqdn'];

    // Check if there's already data in the table
    $check_sql = "SELECT id FROM aws_credentials LIMIT 1";
    $check_result = $link->query($check_sql);

    if ($check_result->num_rows > 0) {
        // Update existing record
        $sql = "UPDATE aws_credentials SET region = ?, access_key_id = ?, secret_access_key = ?, hosted_zone_id = ?, approved_fqdn = ? WHERE id = 1";
    } else {
        // Insert new record
        $sql = "INSERT INTO aws_credentials (region, access_key_id, secret_access_key, hosted_zone_id, approved_fqdn) VALUES (?, ?, ?, ?, ?)";
    }

    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param("sssss", $region, $access_key_id, $secret_access_key, $hosted_zone_id, $approved_fqdn);
        if ($stmt->execute()) {
            $success = "AWS credentials updated successfully!";
        } else {
            $error = "Error updating AWS credentials: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Error preparing SQL statement: " . $link->error;
    }
}

// Fetch current AWS credentials from the database
$sql = "SELECT region, access_key_id, secret_access_key, hosted_zone_id, approved_fqdn FROM aws_credentials LIMIT 1";
$current_credentials = [];
if ($result = $link->query($sql)) {
    if ($result->num_rows > 0) {
        $current_credentials = $result->fetch_assoc();
    }
    $result->free();
}

$link->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage AWS Credentials</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1>Manage AWS Credentials</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if (!empty($current_credentials)): ?>
                <h2>Current AWS Credentials</h2>
                <ul>
                    <li><strong>Region:</strong> <?php echo htmlspecialchars($current_credentials['region']); ?></li>
                    <li><strong>Access Key ID:</strong>
                        <?php echo htmlspecialchars($current_credentials['access_key_id']); ?></li>
                    <li><strong>Secret Access Key:</strong>
                        <?php echo htmlspecialchars($current_credentials['secret_access_key']); ?></li>
                    <li><strong>Hosted Zone ID:</strong>
                        <?php echo htmlspecialchars($current_credentials['hosted_zone_id']); ?></li>
                    <li><strong>Approved FQDN:</strong>
                        <?php echo htmlspecialchars($current_credentials['approved_fqdn']); ?></li>
                </ul>
            <?php else: ?>
                <p>No AWS credentials found in the database.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Update AWS Credentials</h2>
            <form method="post">
                <label>Region:</label>
                <input type="text" name="region"
                    value="<?php echo htmlspecialchars($current_credentials['region'] ?? ''); ?>" required>

                <label>Access Key ID:</label>
                <input type="text" name="access_key_id"
                    value="<?php echo htmlspecialchars($current_credentials['access_key_id'] ?? ''); ?>" required>

                <label>Secret Access Key:</label>
                <input type="text" name="secret_access_key"
                    value="<?php echo htmlspecialchars($current_credentials['secret_access_key'] ?? ''); ?>" required>

                <label>Hosted Zone ID:</label>
                <input type="text" name="hosted_zone_id"
                    value="<?php echo htmlspecialchars($current_credentials['hosted_zone_id'] ?? ''); ?>" required>

                <label>Approved FQDN:</label>
                <input type="text" name="approved_fqdn"
                    value="<?php echo htmlspecialchars($current_credentials['approved_fqdn'] ?? ''); ?>" required>

                <input type="submit" value="Update Credentials">
            </form>
        </div>

        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
</body>

</html>