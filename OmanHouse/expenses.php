<?php include 'db.php'; ?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت هزینه‌ها</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vazirmatn.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <h2 class="title">مدیریت هزینه‌ها</h2>
    <button class="btn btn-dark back-btn" onclick="location.href='index.php'">بازگشت به صفحه اصلی</button>

    <form action="add_expense.php" method="POST" class="expense-form">
        <label>تاریخ:</label>
        <input type="date" name="date" required>

        <label>شرح هزینه:</label>
        <textarea name="description" required></textarea>

        <label>نوع هزینه:</label>
        <select name="type">
            <option value="furniture">اثاثیه منزل</option>
            <option value="daily">هزینه‌های جاری</option>
        </select>

        <label>مبلغ هزینه (ریال عمان):</label>
        <input type="number" name="amount" step="0.001" required>

        <label>تعداد نفرات:</label>
        <input type="number" name="people" required>

        <button type="submit" class="btn btn-success">افزودن</button>
    </form>

    <h3>لیست هزینه‌ها</h3>
    <table class="table">
        <thead>
            <tr>
                <th>ردیف</th>
                <th>تاریخ</th>
                <th>شرح</th>
                <th>نوع</th>
                <th>مبلغ</th>
                <th>سهم خانواده</th>
                <th>سهم هر نفر</th>
                <th>حذف</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $result = $conn->query("SELECT * FROM expenses ORDER BY date DESC");
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['date']}</td>
                    <td>{$row['description']}</td>
                    <td>{$row['type']}</td>
                    <td>{$row['amount']}</td>
                    <td>{$row['family_share']}</td>
                    <td>{$row['per_person']}</td>
                    <td><a href='delete_expense.php?id={$row['id']}' class='btn btn-danger'>🗑️</a></td>
                </tr>";
            }
            ?>
        </tbody>
    </table>
</div>
</body>
</html>
