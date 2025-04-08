<?php
session_start();
include 'db.php';

// Restrict access to logged-in users
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header("Location: user_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user']['id'];
    $order_type = $_POST['order_type'];
    $table_number = $order_type === 'dine-in' ? $_POST['table_number'] : null;
    $address = $order_type === 'delivery' ? $_POST['address'] : null;
    $contact_info = $order_type === 'delivery' ? $_POST['contact_info'] : null;
    $payment_method = $_POST['payment_method'];

    // Calculate total price from cart
    $stmt = $conn->prepare("SELECT c.food_id, c.quantity, f.price FROM cart c JOIN foods f ON c.food_id = f.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();

    $total_price = 0;
    $cart_items = [];
    while ($cart = $cart_result->fetch_assoc()) {
        $subtotal = $cart['price'] * $cart['quantity'];
        $total_price += $subtotal;
        $cart_items[] = $cart;
    }

    if (empty($cart_items)) {
        header("Location: menu.php");
        exit();
    }

    // Insert order
    $status = 'pending';
    $payment_status = $payment_method === 'online' ? 'paid' : 'pending';
    $stmt = $conn->prepare("INSERT INTO orders (user_id, type, table_number, address, contact_info, status, total_price, payment_status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isisssds", $user_id, $order_type, $table_number, $address, $contact_info, $status, $total_price, $payment_status);
    $stmt->execute();
    $order_id = $conn->insert_id;

    // Insert order items
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($cart_items as $item) {
        $price = $item['price'] * $item['quantity'];
        $stmt->bind_param("iiid", $order_id, $item['food_id'], $item['quantity'], $price);
        $stmt->execute();
    }

    // Clear cart
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    header("Location: order_confirmation.php?order_id=$order_id");
    exit();
}
?>﻿<?php
session_start();
include 'db.php';

// Restrict access to logged-in users
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header("Location: user_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user']['id'];
    $order_type = $_POST['order_type'];
    $table_number = $order_type === 'dine-in' ? $_POST['table_number'] : null;
    $address = $order_type === 'delivery' ? $_POST['address'] : null;
    $contact_info = $order_type === 'delivery' ? $_POST['contact_info'] : null;
    $payment_method = $_POST['payment_method'];

    // Calculate total price from cart
    $stmt = $conn->prepare("SELECT c.food_id, c.quantity, f.price FROM cart c JOIN foods f ON c.food_id = f.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();

    $total_price = 0;
    $cart_items = [];
    while ($cart = $cart_result->fetch_assoc()) {
        $subtotal = $cart['price'] * $cart['quantity'];
        $total_price += $subtotal;
        $cart_items[] = $cart;
    }

    if (empty($cart_items)) {
        header("Location: menu.php");
        exit();
    }

    // Insert order
    $status = 'pending';
    $payment_status = $payment_method === 'online' ? 'paid' : 'pending';
    $stmt = $conn->prepare("INSERT INTO orders (user_id, type, table_number, address, contact_info, status, total_price, payment_status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isisssds", $user_id, $order_type, $table_number, $address, $contact_info, $status, $total_price, $payment_status);
    $stmt->execute();
    $order_id = $conn->insert_id;

    // Insert order items
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($cart_items as $item) {
        $price = $item['price'] * $item['quantity'];
        $stmt->bind_param("iiid", $order_id, $item['food_id'], $item['quantity'], $price);
        $stmt->execute();
    }

    // Clear cart
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    header("Location: order_confirmation.php?order_id=$order_id");
    exit();
}
?>