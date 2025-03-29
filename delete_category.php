<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? 0;
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: manage_categories.php");
exit();
?>