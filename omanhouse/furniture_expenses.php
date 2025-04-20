<?php
include 'db.php';

// Fetch payers for dropdown
$payers_result = $conn->query("SELECT id, name FROM payers");
$payers = [];
while ($row = $payers_result->fetch_assoc()) {
    $payers[] = $row;
}

// Calculate totals for furniture expenses only
$current_month = date('Y-m');
$last_month = date('Y-m', strtotime('first day of last month'));

$current_total = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = '$current_month' AND type = 'furniture'")->fetch_assoc()['total'] ?? 0;
$last_total = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = '$last_month' AND type = 'furniture'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت هزینه‌های اثاثیه منزل</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.0/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f3f4f6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom과의 20px;
        }
        .btn {
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: right;
        }
        th {
            background: #4b5563;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
    </style>
    <script>
        const ADMIN_PASSWORD = "parvane123456"; // Change this to your desired password

        function promptPassword(action, id) {
            const password = prompt("لطفاً رمز عبور را وارد کنید:");
            if (password === ADMIN_PASSWORD) {
                if (action === 'delete') {
                    window.location.href = `delete_expense.php?id=${id}`;
                } else if (action === 'edit') {
                    window.location.href = `edit_expense.php?id=${id}`;
                }
            } else {
                alert("رمز عبور نادرست است!");
            }
        }
    </script>
</head>
<body>
<div class="container">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">مدیریت هزینه‌های اثاثیه منزل</h2>
        <a href="index.php" class="btn bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">بازگشت به صفحه اصلی</a>
    </div>

    <!-- Monthly Totals -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="card">
            <h3 class="text-lg font-semibold text-gray-700">جمع هزینه‌های اثاثیه این ماه</h3>
            <p class="text-2xl text-green-600"><?php echo number_format($current_total, 3); ?> ریال عمان</p>
        </div>
        <div class="card">
            <h3 class="text-lg font-semibold text-gray-700">جمع هزینه‌های اثاثیه ماه قبل</h3>
            <p class="text-2xl text-blue-600"><?php echo number_format($last_total, 3); ?> ریال عمان</p>
        </div>
    </div>

    <!-- Expense Form -->
    <div class="card">
        <h3 class="text-lg font-semibold mb-4">افزودن هزینه جدید</h3>
        <form action="add_expense.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700">تاریخ</label>
                <input type="date" name="date" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-gray-700">پرداخت‌کننده</label>
                <select name="payer_id" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">انتخاب کنید</option>
                    <?php foreach ($payers as $payer): ?>
                        <option value="<?php echo $payer['id']; ?>"><?php echo $payer['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700">شرح هزینه</label>
                <textarea name="description" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" rows="4"></textarea>
            </div>
            <div>
                <label class="block text-gray-700">نوع هزینه</label>
                <select name="type" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="furniture" selected>اثاثیه منزل</option>
                    <option value="daily">هزینه‌های جاری</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700">مبلغ هزینه (ریال عمان)</label>
                <input type="number" name="amount" step="0.001" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-gray-700">تعداد نفرات</label>
                <input type="number" name="people" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="btn bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">افزودن هزینه</button>
            </div>
        </form>
    </div>

    <!-- Expenses Table -->
    <div class="card">
        <h3 class="text-lg font-semibold mb-4">لیست هزینه‌های اثاثیه</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>تاریخ</th>
                        <th>پرداخت‌کننده</th>
                        <th>شرح</th>
                        <th>نوع</th>
                        <th>مبلغ</th>
                        <th>سهم خانواده</th>
                        <th>سهم هر نفر</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT e.*, p.name as payer_name FROM expenses e LEFT JOIN payers p ON e.payer_id = p.id WHERE e.type = 'furniture' ORDER BY e.date DESC");
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['date']}</td>
                            <td>{$row['payer_name']}</td>
                            <td>{$row['description']}</td>
                            <td>" . ($row['type'] == 'furniture' ? 'اثاثیه منزل' : 'هزینه‌های جاری') . "</td>
                            <td>" . number_format($row['amount'], 3) . "</td>
                            <td>" . number_format($row['family_share'], 3) . "</td>
                            <td>" . number_format($row['per_person'], 3) . "</td>
                            <td>
                                <button onclick=\"promptPassword('edit', {$row['id']})\" class='btn bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700 mr-2'>📝</button>
                                <button onclick=\"promptPassword('delete', {$row['id']})\" class='btn bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700'>🗑️</button>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>