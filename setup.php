<?php
// Suppress fatal errors for database connection issues
error_reporting(0);
ini_set('display_errors', 0);

// Function to handle fatal errors
function handleFatalError() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        die("A fatal error occurred. Please check your configuration and try again.");
    }
}

// Register the shutdown function to catch fatal errors
register_shutdown_function('handleFatalError');

// Check if dbconfig.php exists
if (!file_exists('dbconfig.php')) {
    die("The database configuration file (dbconfig.php) is missing. Please create it with the correct database credentials.");
}

// Include the database configuration
include 'dbconfig.php';

// Check if the database connection is successful
if ($link === null || $link->connect_error) {
    die("Database connection failed. Please check the dbconfig.php file and ensure the database credentials are correct.");
}

// Check if the database is empty
$tables = [];
$result = $link->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    $result->free();
}

// If the database is not empty, warn the user
if (!empty($tables)) {
    die("An installation already exists in this database. Please clean up the database or update the dbconfig.php file to use a new database.");
}

// Handle form submission to create the first admin user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_admin'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate input
    if (empty($username) || empty($password)) {
        echo "Username and password are required.";
    } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        echo "Username must be a valid email address.";
    } else {
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Create the required tables
        $create_tables_sql = [
            "CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL
            )",
            "CREATE TABLE aws_credentials (
                id INT AUTO_INCREMENT PRIMARY KEY,
                region VARCHAR(50) NOT NULL,
                access_key_id VARCHAR(255) NOT NULL,
                secret_access_key VARCHAR(255) NOT NULL,
                hosted_zone_id VARCHAR(255) NOT NULL,
                approved_fqdn VARCHAR(255) NOT NULL
            )",
            "CREATE TABLE ddns_entries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ddns_fqdn VARCHAR(255) NOT NULL UNIQUE,
                ddns_password VARCHAR(255) NOT NULL,
                last_ipv4 VARCHAR(15),
                ttl INT NOT NULL DEFAULT 300,
                last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ];

        // Execute the table creation queries
        $success = true;
        foreach ($create_tables_sql as $sql) {
            if (!$link->query($sql)) {
                $success = false;
                echo "Error creating table: " . $link->error;
                break;
            }
        }

        if ($success) {
            // Insert the first admin user
            $insert_sql = "INSERT INTO users (username, password_hash) VALUES (?, ?)";
            if ($stmt = $link->prepare($insert_sql)) {
                $stmt->bind_param("ss", $username, $password_hash);
                if ($stmt->execute()) {
                    echo "First admin user created successfully! You can now log in.";
                    echo '<p><a href="index.php">Go to Login Page</a></p>';
                    exit;
                } else {
                    echo "Error creating admin user: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup</title>
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
    <h1>Setup</h1>
    <p>Welcome to the setup wizard. This script will help you prepare a new installation.</p>

    <?php if (empty($tables)): ?>
        <h2>Create First Admin User</h2>
        <form method="post">
            <label>Username (Email):</label>
            <input type="email" name="username" required><br>
            <label>Password:</label>
            <input type="password" name="password" required><br>
            <input type="submit" name="create_admin" value="Create Admin User">
        </form>
    <?php endif; ?>
</body>
</html>