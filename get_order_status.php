<?php
include 'db.php';
header('Content-Type: application/json');

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo json_encode(['error' => 'Invalid order_id']);
    exit();
}

$order_id = intval($_GET['order_id']);
$stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if ($order) {
    echo json_encode(['status' => $order['status'] ?? 'Confirmed']);
} else {
    echo json_encode(['error' => 'Order not found']);
}
?>