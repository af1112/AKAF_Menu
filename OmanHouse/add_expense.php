<?php
include 'db.php';

$date = $_POST['date'];
$description = $_POST['description'];
$type = $_POST['type'];
$amount = floatval($_POST['amount']);
$people = intval($_POST['people']);

$family_share = ($type == 'furniture') ? round($amount / 4, 3) : 0;
$per_person = ($type == 'daily' && $people > 0) ? round($amount / $people, 3) : 0;

$sql = "INSERT INTO expenses (date, description, type, amount, people, family_share, per_person) 
        VALUES ('$date', '$description', '$type', '$amount', '$people', '$family_share', '$per_person')";

if ($conn->query($sql) === TRUE) {
    header("Location: expenses.php");
} else {
    echo "خطا: " . $conn->error;
}
?>
