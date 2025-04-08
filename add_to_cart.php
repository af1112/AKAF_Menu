<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

// اگر درخواست POST نباشد یا food_id وجود نداشته باشد
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['food_id'])) {
    file_put_contents('debug.txt', "Invalid request\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$food_id = intval($_POST['food_id']);
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

// دیباگ: بررسی ورودی‌ها
file_put_contents('debug.txt', "Received food_id: " . $food_id . ", quantity: " . $quantity . "\n", FILE_APPEND);

if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    file_put_contents('debug.txt', "User not logged in\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];

// بررسی اتصال به دیتابیس
if (!$conn) {
    file_put_contents('debug.txt', "Database connection failed!\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// دریافت قیمت غذا از جدول foods
$stmt_price = $conn->prepare("SELECT price FROM foods WHERE id = ? AND is_available = 1");
$stmt_price->bind_param("i", $food_id);
$stmt_price->execute();
$food = $stmt_price->get_result()->fetch_assoc();

if (!$food) {
    file_put_contents('debug.txt', "Food not found or unavailable: " . $food_id . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Food not found or unavailable']);
    exit;
}

$price = $food['price'];

try {
    // بررسی اینکه غذا در سبد وجود دارد یا نه
    $stmt_check = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND food_id = ?");
    $stmt_check->bind_param("ii", $user_id, $food_id);
    $stmt_check->execute();
    $existing_item = $stmt_check->get_result()->fetch_assoc();

    if ($existing_item) {
        // اگر وجود داشت، مقدار را افزایش بده
        $new_quantity = $existing_item['quantity'] + $quantity;
        $stmt_update = $conn->prepare("UPDATE cart SET quantity = ?, price = ? WHERE user_id = ? AND food_id = ?");
        $stmt_update->bind_param("idii", $new_quantity, $price, $user_id, $food_id);
        $stmt_update->execute();
        file_put_contents('debug.txt', "Updated cart: user_id=$user_id, food_id=$food_id, new_quantity=$new_quantity\n", FILE_APPEND);
    } else {
        // اگر وجود نداشت، آیتم جدید اضافه کن
        $stmt_insert = $conn->prepare("INSERT INTO cart (user_id, food_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("iiid", $user_id, $food_id, $quantity, $price);
        $stmt_insert->execute();
        file_put_contents('debug.txt', "Inserted into cart: user_id=$user_id, food_id=$food_id, quantity=$quantity\n", FILE_APPEND);
    }

    // بازگرداندن تعداد جدید آیتم‌ها
    $stmt_count = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt_count->bind_param("i", $user_id);
    $stmt_count->execute();
    $result = $stmt_count->get_result()->fetch_assoc();
    $new_count = $result['count'] ?? 0;

    echo json_encode(['success' => true, 'count' => $new_count, 'message' => 'Added to cart']);
} catch (Exception $e) {
    file_put_contents('debug.txt', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Error adding to cart: ' . $e->getMessage()]);
}

?>