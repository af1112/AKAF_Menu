<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order_id = $_POST['order_id'] ?? 0;
    $status = $_POST['status'] ?? '';

    if ($order_id > 0 && in_array($status, ['pending', 'confirmed', 'preparing', 'canceled', 'serving', 'completed'])) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: manage_orders.php");
exit();
?>