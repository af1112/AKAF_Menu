<?php
$pdo = new PDO("mysql:host=localhost;dbname=expenses_db;charset=utf8", "root", "");

$stmt = $pdo->prepare("INSERT INTO expenses (date, description, type, amount, family_share, people, per_person_share) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $_POST['date'],
    $_POST['description'],
    $_POST['type'],
    $_POST['amount'],
    $_POST['familyShare'],
    $_POST['people'],
    $_POST['perPersonShare']
]);

echo "هزینه با موفقیت ثبت شد!";
?>
