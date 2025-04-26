<?php
header('Content-Type: application/json');
include 'db.php';

$stmt_total_unread = $conn->prepare("
    SELECT COUNT(*) AS total_unread 
    FROM order_messages om 
    JOIN orders o ON om.order_id = o.id 
    WHERE om.sender_type = 'customer' AND om.is_read = 0
");
$stmt_total_unread->execute();
$total_unread = $stmt_total_unread->get_result()->fetch_assoc()['total_unread'];

echo json_encode(['total_unread' => $total_unread]);
?>