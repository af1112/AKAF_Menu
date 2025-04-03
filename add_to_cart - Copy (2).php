<?php

session_start();
include 'db.php';
header('Content-Type: application/json');
$response = ['status' => 'success', 'message' => 'Added to cart'];
echo json_encode($response);
exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['food_id'])) {
    $food_id = intval($_POST['food_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
// دیباگ: بررسی ورودی‌ها
    file_put_contents('debug.txt', "Received food_id: " . $food_id . ", quantity: " . $quantity . "\n", FILE_APPEND);

    if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id'])) {
        $user_id = $_SESSION['user']['id'];
// بررسی اتصال به دیتابیس
        if (!$conn) {
            file_put_contents('debug.txt', "Database connection failed!\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit();
        }
        // بررسی اینکه غذا در سبد وجود دارد یا نه
        $stmt_check = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND food_id = ?");
        $stmt_check->bind_param("ii", $user_id, $food_id);
        $stmt_check->execute();
        $existing_item = $stmt_check->get_result()->fetch_assoc();
if (!$food) {
            file_put_contents('debug.txt', "Food not found or unavailable: " . $food_id . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Food not found or unavailable']);
            exit();
        }
        if ($existing_item) {
            // اگر وجود داشت، مقدار را افزایش بده
            $new_quantity = $existing_item['quantity'] + $quantity;
            $stmt_update = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND food_id = ?");
            $stmt_update->bind_param("iii", $new_quantity, $user_id, $food_id);
            $stmt_update->execute();
        } else {
            // اگر وجود نداشت، آیتم جدید اضافه کن
            $stmt_insert = $conn->prepare("INSERT INTO cart (user_id, food_id, quantity, price) VALUES (?, ?, ?, ?)");
            $price = 0; // قیمت باید از جدول foods گرفته شود
            $stmt_price = $conn->prepare("SELECT price FROM foods WHERE id = ?");
            $stmt_price->bind_param("i", $food_id);
            $stmt_price->execute();
            $food = $stmt_price->get_result()->fetch_assoc();
            if ($food) {
                $price = $food['price'];
            }
            $stmt_insert->bind_param("iiid", $user_id, $food_id, $quantity, $price);
            $stmt_insert->execute();
        }

        // بازگرداندن تعداد جدید آیتم‌ها
        $stmt_count = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
        $stmt_count->bind_param("i", $user_id);
        $stmt_count->execute();
        $result = $stmt_count->get_result()->fetch_assoc();
        $new_count = $result['count'] ?? 0;

        echo json_encode(['success' => true, 'count' => $new_count]);
        } catch (Exception $e) {
            file_put_contents('debug.txt', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Error adding to cart: ' . $e->getMessage()]);
        }
	} else {
        file_put_contents('debug.txt', "User not logged in\n", FILE_APPEND);
		echo json_encode(['success' => false, 'message' => 'User not logged in']);
    }
} else {
    file_put_contents('debug.txt', "Invalid request\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

?>