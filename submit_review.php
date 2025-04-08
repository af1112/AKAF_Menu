<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header("Location: user_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $food_id = $_POST['food_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    $username = $_SESSION['user']['username'];
    $user_id = $_SESSION['user']['id'];

    $stmt = $conn->prepare("INSERT INTO food_reviews (food_id, user_id, username, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisis", $food_id, $user_id, $username, $rating, $comment);
    $stmt->execute();

    header("Location: food_details.php?id=$food_id");
    exit();
}
?>