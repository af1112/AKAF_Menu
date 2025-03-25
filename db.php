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
?>
