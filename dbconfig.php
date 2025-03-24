<?php
// dbconfig.php
define('DB_SERVER', getenv('DB_SERVER') ?: 'your_database_host');
define('DB_NAME', getenv('DB_NAME') ?: 'your_database_name');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'your_database_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'your_database_password');


// Create the connection (EXACTLY as in your original)
$link = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Optional: Add error logging if connection fails
if ($link->connect_error) {
    error_log("Database connection failed: " . $link->connect_error);
}
?>
