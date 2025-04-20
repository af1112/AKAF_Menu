<?php
include 'db.php';

$date = $_POST['date'];
$payer = $_POST['payer'];
$amount = floatval($_POST['amount']);

$sql = "INSERT INTO payments (date, payer, amount) VALUES ('$date', '$payer', '$amount')";

if ($conn->query($sql) === TRUE) {
    header("Location: payments.php");
} else {
    echo "خطا: " . $conn->error;
}
?>
