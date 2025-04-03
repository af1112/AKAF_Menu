<?php
session_start();
include 'db.php';

if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id'])) {
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo json_encode(['count' => $result['count'] ?? 0]);
} else {
    echo json_encode(['count' => 0]);
}
?>