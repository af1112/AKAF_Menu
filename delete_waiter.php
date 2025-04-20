<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $waiter_id = $_POST['id'] ?? 0;

    $stmt = $conn->prepare("DELETE FROM waiters WHERE id = ?");
    $stmt->bind_param("i", $waiter_id);
    $stmt->execute();

    header("Location: admin_dashboard.php");
    exit();
}
?>