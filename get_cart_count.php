<?php
session_start();
include 'db.php';

header('Content-Type: application/json'); // تنظیم هدر JSON

$count = 0;
if (isset($_SESSION['user']['id'])) {
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $count = $result['total'] ?? 0;
}

echo json_encode(['count' => (int)$count], JSON_NUMERIC_CHECK);
exit();