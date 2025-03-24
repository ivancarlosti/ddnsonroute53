<?php
// dbconfig.php
define('DB_SERVER', getenv('DB_SERVER') ?: 'YOURDBHOST');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'YOURDBUSERNAME');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'YOURDBPASSWORD');
define('DB_NAME', getenv('DB_NAME') ?: 'YOURDBNAME');

// Create the connection (EXACTLY as in your original)
$link = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Optional: Add error logging if connection fails
if ($link->connect_error) {
    error_log("Database connection failed: " . $link->connect_error);
}
?>
