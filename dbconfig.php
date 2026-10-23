<?php
// dbconfig.php
define('DB_SERVER', getenv('DB_SERVER') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'dns');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'dns');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'password');

// Keycloak Configuration
define('KEYCLOAK_BASE_URL', getenv('KEYCLOAK_BASE_URL') ?: 'https://keycloak.example.com/auth');
define('KEYCLOAK_REALM', getenv('KEYCLOAK_REALM') ?: 'myrealm');
define('KEYCLOAK_CLIENT_ID', getenv('KEYCLOAK_CLIENT_ID') ?: 'myclient');
define('KEYCLOAK_CLIENT_SECRET', getenv('KEYCLOAK_CLIENT_SECRET') ?: 'mysecret');
define('KEYCLOAK_REDIRECT_URI', getenv('KEYCLOAK_REDIRECT_URI') ?: 'http://localhost/index.php');


// Create the connection; mysqli throws since PHP 8.1, so catch it and keep
// $link null-safe for the guards in the calling scripts.
$link = null;
try {
    $link = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

// Optional: Add error logging if connection fails
if ($link !== null && $link->connect_error) {
    error_log("Database connection failed: " . $link->connect_error);
}
?>