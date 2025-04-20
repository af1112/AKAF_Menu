<?php
include 'db.php';

$id = intval($_GET['id']);
$conn->query("DELETE FROM payments WHERE id = $id");

header("Location: payments.php");
?>
