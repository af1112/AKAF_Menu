<?php
include 'db.php';

// Handle form submission for adding a new payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
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

// Handle password verification for edit/delete
$password_verified = false;
$password_error = '';
$correct_password = '12345'; // رمز عبور ثابت

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'verify_password') {
    $password = $_POST['password'];
    if ($password === $correct_password) {
        $password_verified = true;
        session_start();
        $_SESSION['password_verified'] = true;
    } else {
        $password_error = 'رمز عبور اشتباه است';
    }
}

// Check if password is already verified
session_start();
if (isset($_SESSION['password_verified']) && $_SESSION['password_verified'] === true) {
    $password_verified = true;
}

// Handle delete action after password verification
if ($password_verified && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $payment_id = $_POST['payment_id'];
    $conn->query("DELETE FROM payments WHERE id = $payment_id");
    header("Location: payments.php");
    exit();
}

// Fetch all payments for history
$payments = [];
$payments_result = $conn->query("SELECT * FROM payments ORDER BY date DESC");
if ($payments_result && $payments_result->num_rows > 0) {
    while ($row = $payments_result->fetch_assoc()) {
        $payments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پرداخت‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.0/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            width: 100%;
            padding: 1.5rem;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
        }
        .btn {
            transition: all 0.3s ease;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            font-size: 1rem;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        h2 {
            font-size: 1.5rem;
            line-height: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        th {
            background-color: #f3f4f6;
            font-weight: 600;
        }
        .action-btn {
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
        }
        .error {
            color: #dc2626;
            margin-bottom: 1rem;
        }
        @media (max-width: 640px) {
            .container {
                padding: 1rem;
            }
            .card {
                padding: 1rem;
            }
            h2 {
                font-size: 1.25rem;
                line-height: 1.75rem;
            }
            .form-group label {
                font-size: 0.875rem;
            }
            .form-group input, .form-group select {
                font-size: 0.875rem;
                padding: 0.375rem;
            }
            .btn {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }
            th, td {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
            table {
                font-size: 0.75rem;
            }
            .action-btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">مدیریت پرداخت‌ها</h2>
        <button onclick="location.href='index.php'" class="btn bg-gray-600 text-white hover:bg-gray-700 mb-4">بازگشت به داشبورد</button>

        <form action="payments.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>تاریخ:</label>
                <input type="date" name="date" required>
            </div>

            <div class="form-group">
                <label>خانواده پرداخت‌کننده:</label>
                <select name="family" required>
                    <option value="خانواده علی">خانواده علی</option>
                    <option value="خانواده مجتبی">خانواده مجتبی</option>
                    <option value="خانواده محمود">خانواده محمود</option>
                    <option value="خانواده محمدامین">خانواده محمدامین</option>
                </select>
            </div>

            <div class="form-group">
                <label>مبلغ پرداختی:</label>
                <input type="number" name="amount" step="0.001" required>
            </div>

            <div class="form-group">
                <label>توضیحات:</label>
                <input type="text" name="description" placeholder="مثلاً: پرداخت هزینه ثابت ماه">
            </div>

            <button type="submit" class="btn bg-green-600 text-white hover:bg-green-700 w-full">افزودن</button>
        </form>
    </div>

    <?php if (!empty($payments)): ?>
        <div class="card">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">تاریخچه پرداخت‌ها</h2>

            <?php if (!$password_verified): ?>
                <form action="payments.php" method="POST">
                    <input type="hidden" name="action" value="verify_password">
                    <div class="form-group">
                        <label>رمز عبور:</label>
                        <input type="password" name="password" required>
                    </div>
                    <?php if (!empty($password_error)): ?>
                        <p class="error"><?php echo $password_error; ?></p>
                    <?php endif; ?>
                    <button type="submit" class="btn bg-blue-600 text-white hover:bg-blue-700 w-full">تأیید</button>
                </form>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>خانواده</th>
                            <th>مبلغ (ریال)</th>
                            <th>توضیحات</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo $payment['date']; ?></td>
                                <td><?php echo $payment['family']; ?></td>
                                <td><?php echo number_format($payment['amount'], 3); ?></td>
                                <td><?php echo $payment['description']; ?></td>
                                <td>
                                    <a href="edit_payment.php?id=<?php echo $payment['id']; ?>" class="btn bg-blue-600 text-white hover:bg-blue-700 action-btn">ویرایش</a>
                                    <form action="payments.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                        <button type="submit" class="btn bg-red-600 text-white hover:bg-red-700 action-btn">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>