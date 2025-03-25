<?php
session_start();
include 'db.php';

// Restrict access
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header("Location: user_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];

    // Update food details
    $name_en = $_POST['name_en'];
    $name_fr = $_POST['name_fr'];
    $name_ar = $_POST['name_ar'];
    $desc_en = $_POST['description_en'];
    $desc_fr = $_POST['description_fr'];
    $desc_ar = $_POST['description_ar'];

    $sql = "UPDATE foods SET 
            name_en='$name_en', name_fr='$name_fr', name_ar='$name_ar',
            description_en='$desc_en', description_fr='$desc_fr', description_ar='$desc_ar'
            WHERE id=$id";
    $conn->query($sql);

    // Add new images
    if (!empty($_FILES['new_images']['name'][0])) {
        $upload_dir = 'images/';
        foreach ($_FILES['new_images']['name'] as $key => $name) {
            if ($_FILES['new_images']['error'][$key] == 0) {
                $tmp_name = $_FILES['new_images']['tmp_name'][$key];
                $file_name = uniqid() . '_' . basename($name);
                move_uploaded_file($tmp_name, $upload_dir . $file_name);
                $conn->query("INSERT INTO food_images (food_id, image) VALUES ($id, '$file_name')");
            }
        }
    }

    header("Location: menu.php");
    exit();
}
?>