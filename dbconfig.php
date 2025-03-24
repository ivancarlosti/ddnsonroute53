<?php
// dbconfig.php
define('DB_SERVER', 'YOURDBHOST');
define('DB_USERNAME', 'YOURDBUSERNAME');
define('DB_PASSWORD', 'YOURDBPASSWORD');
define('DB_NAME', 'YOURDBNAME');

// Create the connection
$link = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
?>
