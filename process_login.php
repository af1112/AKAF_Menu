<?php
session_start();
include "db.php"; // Database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    // Check user in database
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Successful login, store user info in session
		$_SESSION["user"] = [
			"id" => $user["id"],
			"username" => $user["username"]
		];
        header("Location: index.php"); // Redirect to index.php
        exit();
    } else {
        // Invalid login
        echo "<script>alert('Invalid username or password!'); window.location.href='user_login.php';</script>";
    }
}
?>
