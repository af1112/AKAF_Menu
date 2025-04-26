<?php
header('Content-Type: application/json');
include 'db.php';

$stmt_orders = $conn->prepare("
    SELECT DISTINCT o.id AS order_id, o.user_id, u.username AS customer_name, o.table_number,
    (SELECT COUNT(*) FROM order_messages om2 WHERE om2.order_id = o.id AND om2.sender_type = 'customer' AND om2.is_read = 0) AS unread_count
    FROM orders o 
    JOIN order_messages om ON o.id = om.order_id 
    JOIN users u ON o.user_id = u.id 
    WHERE om.sender_type = 'customer' 
    ORDER BY om.created_at DESC
");
$stmt_orders->execute();
$orders = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['orders' => $orders]);
?>