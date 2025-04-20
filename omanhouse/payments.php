<?php include 'db.php'; ?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پرداخت‌ها</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vazirmatn.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <h2 class="title">مدیریت پرداخت‌ها</h2>
    <button class="btn btn-dark back-btn" onclick="location.href='index.php'">بازگشت به صفحه اصلی</button>

    <form action="add_payment.php" method="POST" class="expense-form">
        <label>تاریخ:</label>
        <input type="date" name="date" required>

        <label>شخص پرداخت‌کننده:</label>
        <input type="text" name="payer" required>

        <label>مبلغ پرداختی:</label>
        <input type="number" name="amount" step="0.001" required>

        <button type="submit" class="btn btn-success">افزودن</button>
    </form>
</div>
</body>
</html>
