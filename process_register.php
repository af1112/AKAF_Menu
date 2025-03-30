<?php
include "db.php"; // Database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT); // Encrypt password

    // Insert into database
    $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $email, $password);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! Please login.'); window.location.href='user_login.php';</script>";
    } else {
        echo "<script>alert('Error: Username or email may already exist.'); window.location.href='register.php';</script>";
    }
}
?>
