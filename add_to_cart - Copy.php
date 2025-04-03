<?php
session_start();
include 'db.php'; // اتصال به دیتابیس

// تنظیم هدر برای پاسخ JSON
header('Content-Type: application/json');

// پاسخ پیش‌فرض
$response = ['success' => false];

// چک کردن اینکه کاربر لاگین کرده یا نه
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

if (!$is_logged_in) {
    $response['message'] = 'Please log in to add items to your cart.';
    echo json_encode($response);
    exit();
}

// گرفتن داده‌ها از درخواست (به صورت application/x-www-form-urlencoded)
$food_id = isset($_POST['food_id']) ? intval($_POST['food_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

// اعتبارسنجی اولیه
if ($food_id <= 0 || $quantity <= 0) {
    $response['message'] = 'Invalid food ID or quantity.';
    echo json_encode($response);
    exit();
}

// چک کردن اینکه غذا وجود داره و در دسترس هست یا نه
$stmt = $conn->prepare("SELECT * FROM foods WHERE id = ? AND is_available = 1");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc();

if (!$food) {
    $response['message'] = 'Food not found or not available.';
    echo json_encode($response);
    exit();
}

// افزودن به سبد خرید (ذخیره توی سشن و دیتابیس)
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_item = [
    'id' => $food['id'],
    'name' => $food['name_' . $_SESSION['lang']],
    'price' => $food['price'],
    'quantity' => $quantity
];

$found = false;
foreach ($_SESSION['cart'] as &$item) {
    if ($item['id'] == $food_id) {
        $item['quantity'] += $quantity;
        $found = true;
        break;
    }
}
if (!$found) {
    $_SESSION['cart'][] = $cart_item;
}

// ذخیره توی جدول cart
$stmt = $conn->prepare("INSERT INTO cart (user_id, food_id, quantity, price) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
$stmt->bind_param("iiidi", $user_id, $food_id, $quantity, $food['price'], $quantity);
if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Added to cart successfully.';
} else {
    $response['message'] = 'Failed to add to cart: ' . $conn->error;
}

echo json_encode($response);
exit();
?>