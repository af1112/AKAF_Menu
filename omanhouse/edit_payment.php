<?php
include 'db.php';

// Check if payment ID is provided
if (!isset($_GET['id'])) {
    header("Location: payments.php");
    exit();
}

$payment_id = $_GET['id'];

// Fetch payment details
$payment = $conn->query("SELECT * FROM payments WHERE id = $payment_id");
if (!$payment || $payment->num_rows == 0) {
    header("Location: payments.php");
    exit();
}
$payment_data = $payment->fetch_assoc();

// Handle form submission for updating payment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'];
    $family = $_POST['family'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("UPDATE payments SET date = ?, family = ?, amount = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssdsi", $date, $family, $amount, $description, $payment_id);
    $stmt->execute();
    $stmt->close();

    header("Location: payments.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش پرداخت</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.0/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
        }
        .container {
            max-width: 600px;
            width: 100%;
            padding: 1.5rem;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
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
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">ویرایش پرداخت</h2>
        <button onclick="location.href='payments.php'" class="btn bg-gray-600 text-white hover:bg-gray-700 mb-4">بازگشت به مدیریت پرداخت‌ها</button>

        <form action="edit_payment.php?id=<?php echo $payment_id; ?>" method="POST">
            <div class="form-group">
                <label>تاریخ:</label>
                <input type="date" name="date" value="<?php echo $payment_data['date']; ?>" required>
            </div>

            <div class="form-group">
                <label>خانواده پرداخت‌کننده:</label>
                <select name="family" required>
                    <option value="خانواده علی" <?php echo $payment_data['family'] == 'خانواده علی' ? 'selected' : ''; ?>>خانواده علی</option>
                    <option value="خانواده مجتبی" <?php echo $payment_data['family'] == 'خانواده مجتبی' ? 'selected' : ''; ?>>خانواده مجتبی</option>
                    <option value="خانواده محمود" <?php echo $payment_data['family'] == 'خانواده محمود' ? 'selected' : ''; ?>>خانواده محمود</option>
                    <option value="خانواده محمدامین" <?php echo $payment_data['family'] == 'خانواده محمدامین' ? 'selected' : ''; ?>>خانواده محمدامین</option>
                </select>
            </div>

            <div class="form-group">
                <label>مبلغ پرداختی:</label>
                <input type="number" name="amount" step="0.001" value="<?php echo $payment_data['amount']; ?>" required>
            </div>

            <div class="form-group">
                <label>توضیحات:</label>
                <input type="text" name="description" value="<?php echo $payment_data['description']; ?>" placeholder="مثلاً: پرداخت هزینه ثابت ماه">
            </div>

            <button type="submit" class="btn bg-green-600 text-white hover:bg-green-700 w-full">ذخیره تغییرات</button>
        </form>
    </div>
</div>
</body>
</html>