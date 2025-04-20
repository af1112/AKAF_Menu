<?php
$host = "localhost";
$user = "root"; 
$password = ""; 
$dbname = "house_management";

$conn = new mysqli($host, $user, $password, $dbname);

// بررسی اتصال
if ($conn->connect_error) {
    die("اتصال به دیتابیس ناموفق بود: " . $conn->connect_error);
}

mysqli_set_charset($conn, "utf8");
?>
