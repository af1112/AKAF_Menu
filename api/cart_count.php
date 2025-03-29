<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // اجازه دسترسی از فرانت‌اند

// اتصال به دیتابیس
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=restaurant_db;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// فرضاً user_id از سشن میاد (باید سیستم لاگین داشته باشید)
session_start();
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$user_id) {
    echo json_encode(['count' => 0]); // اگه کاربر لاگین نکرده، ۰ برگردون
    exit;
}

// گرفتن تعداد آیتم‌ها از جدول cart
$query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['count' => $result['count']]);
?>