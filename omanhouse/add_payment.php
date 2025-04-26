<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'];
    $family = $_POST['family'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO payments (date, family, amount, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssds", $date, $family, $amount, $description);
    $stmt->execute();
    $stmt->close();

    header("Location: payments.php");
    exit();
}
?>