<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// اتصال به دیتابیس
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=restaurant_db;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// گرفتن داده از درخواست POST
$data = json_decode(file_get_contents("php://input"), true);
$food_id = $data['food_id'] ?? null;
$quantity = $data['quantity'] ?? 1;

session_start();
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$user_id || !$food_id) {
    echo json_encode(['error' => 'Missing user_id or food_id']);
    exit;
}

// اضافه کردن به جدول cart
$query = "INSERT INTO cart (user_id, food_id, quantity) VALUES (?, ?, ?)";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id, $food_id, $quantity]);

echo json_encode(['success' => true]);
?>