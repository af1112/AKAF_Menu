<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $payer_id = $_POST['payer_id'];
    $description = $_POST['description'];
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $people = $_POST['people'];

    $family_share = $amount;
    $per_person = $amount / $people;

    $stmt = $conn->prepare("INSERT INTO expenses (date, payer_id, description, type, amount, people, family_share, per_person) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssidd", $date, $payer_id, $description, $type, $amount, $people, $family_share, $per_person);

    if ($stmt->execute()) {
        header("Location: index.php");
        exit;
    } else {
        echo "خطا در ثبت هزینه: " . $conn->error;
    }
}
?>