<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_en = $_POST['name_en'] ?? '';
    $name_fa = $_POST['name_fa'] ?? '';
    $name_fr = $_POST['name_fr'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';
    $desc_en = $_POST['desc_en'] ?? '';
    $desc_fa = $_POST['desc_fa'] ?? '';
    $desc_fr = $_POST['desc_fr'] ?? '';
    $desc_ar = $_POST['desc_ar'] ?? '';
    $category = $_POST['category'] ?? '';
    $price = $_POST['price'] ?? 0;
    $prep_time = $_POST['prep_time'] ?? 0;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $ingredients = $_POST['ingredients'] ?? '';

    // Handle main image upload
    $main_image = 'images/default.jpg';
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'images/';
        $main_image = $upload_dir . basename($_FILES['main_image']['name']);
        move_uploaded_file($_FILES['main_image']['tmp_name'], $main_image);
    }

    // Insert food details
    $stmt = $conn->prepare("INSERT INTO foods (name_en, name_fa, name_fr, name_ar, desc_en, desc_fa, desc_fr, desc_ar, category, price, prep_time, is_available, ingredients, main_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssdiss", $name_en, $name_fa, $name_fr, $name_ar, $desc_en, $desc_fa, $desc_fr, $desc_ar, $category, $price, $prep_time, $is_available, $ingredients, $main_image);
    if ($stmt->execute()) {
        $food_id = $conn->insert_id;

        // Handle gallery images upload
        if (isset($_FILES['gallery_images'])) {
            $upload_dir = 'images/';
            foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $image_path = $upload_dir . basename($_FILES['gallery_images']['name'][$key]);
                    move_uploaded_file($tmp_name, $image_path);
                    $stmt = $conn->prepare("INSERT INTO food_images (food_id, image_path) VALUES (?, ?)");
                    $stmt->bind_param("is", $food_id, $image_path);
                    $stmt->execute();
                }
            }
        }
        header("Location: admin_dashboard.php");
        exit();
    } else {
        echo "Failed to add food.";
    }
}
?>