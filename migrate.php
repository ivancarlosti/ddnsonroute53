<?php
// migrate.php
// Run this script once to update your database schema for Keycloak support.

include 'dbconfig.php';

if ($link === null || $link->connect_error) {
    die("Database connection failed: " . $link->connect_error);
}

echo "<h1>Database Migration</h1>";

// 1. Modify users table to allow NULL passwords
$sql = "ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL";
if ($link->query($sql) === TRUE) {
    echo "<p style='color: green;'>Successfully updated 'users' table to allow NULL passwords.</p>";
} else {
    echo "<p style='color: red;'>Error updating 'users' table: " . $link->error . "</p>";
}

echo "<p>Migration completed. You can delete this file now.</p>";
echo "<p><a href='index.php'>Go to Login</a></p>";

$link->close();
?>
