<?php
include 'db.php';
header('Content-Type: application/json');

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo json_encode(['error' => 'Invalid order_id']);
    exit();
}

$order_id = intval($_GET['order_id']);
$stmt = $conn->prepare("SELECT message_text, sender_type, created_at FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['messages' => $messages]);
?>