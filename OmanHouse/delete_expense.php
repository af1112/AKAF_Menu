<?php
include 'db.php';

$id = intval($_GET['id']);
$conn->query("DELETE FROM expenses WHERE id = $id");

header("Location: expenses.php");
?>
