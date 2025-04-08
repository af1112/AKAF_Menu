<?php

// Database connection settings
$servername = "localhost";
$username = "root";  // Change if needed
$password = "";  // Change if needed
$dbname = "restaurant_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Ensure UTF-8 encoding is used
$conn->set_charset("utf8mb4");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
define('DB_HOST', $servername); // هاست نام از هاستینگ
define('DB_NAME', $dbname); // نام پایگاه داده
define('DB_USER', $username); // نام کاربری
define('DB_PASS', $password); // رمز عبور
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
