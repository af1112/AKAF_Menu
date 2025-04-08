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
    $comment = $_POST['comment'] ?? '';

    if ($order_id > 0) {
        $stmt = $conn->prepare("UPDATE orders SET comment = ? WHERE id = ?");
        $stmt->bind_param("si", $comment, $order_id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: manage_orders.php");
exit();
?>