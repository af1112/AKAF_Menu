<?php
$pdo = new PDO("mysql:host=localhost;dbname=expenses_db;charset=utf8", "root", "");
$stmt = $pdo->query("SELECT * FROM expenses");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
