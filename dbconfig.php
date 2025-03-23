<?php
// dbconfig.php
define('DB_SERVER', 'YOURDBHOST');
define('DB_USERNAME', 'YOURDBUSERNAME');
define('DB_PASSWORD', 'YOURDBPASSWORD');
define('DB_NAME', 'YOURDBNAME');

// Suppress errors for the connection attempt
error_reporting(0);
ini_set('display_errors', 0);

// Create the connection
$link = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check for connection errors
if ($link->connect_error) {
    // Do not die here; let the calling script handle the error
    $link = null;
}
?>