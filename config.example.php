<?php
// Example configuration file (do NOT use in production)
// Copy this file to config.php and fill with your real database credentials

$host = "localhost";     // usually 'localhost' or hosting DB hostname
$dbname = "inventory_db"; // your database name
$username = "your_username"; // your DB username
$password = "your_password"; // your DB password

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
