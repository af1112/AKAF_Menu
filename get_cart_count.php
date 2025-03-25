<?php
session_start();
header('Content-Type: application/json');

$cart_items = $_SESSION['cart'] ?? [];
$count = 0;
foreach ($cart_items as $quantity) {
    $count += $quantity;
}

echo json_encode(['count' => $count]);
?>