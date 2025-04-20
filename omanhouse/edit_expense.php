<?php
include 'db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = $conn->query("SELECT * FROM expenses WHERE id = $id");
    $expense = $result->fetch_assoc();

    if (!$expense) {
        die("هزینه یافت نشد!");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $date = $_POST['date'];
    $payer_id = $_POST['payer_id'];
    $description = $_POST['description'];
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $people = $_POST['people'];

    $family_share = $amount;
    $per_person = $amount / $people;

    $stmt = $conn->prepare("UPDATE expenses SET date = ?, payer_id = ?, description = ?, type = ?, amount = ?, people = ?, family_share = ?, per_person = ? WHERE id = ?");
    $stmt->bind_param("sisssiddi", $date, $payer_id, $description, $type, $amount, $people, $family_share, $per_person, $id);

    if ($stmt->execute()) {
        header("Location: index.php");
        exit;
    } else {
        echo "خطا در ویرایش هزینه: " . $conn->error;
    }
}

// Fetch payers for dropdown
$payers_result = $conn->query("SELECT id, name FROM payers");
$payers = [];
while ($row = $payers_result->fetch_assoc()) {
    $payers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش هزینه</title>
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
            margin-bottom: 20px;
        }
        .btn {
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">ویرایش هزینه</h2>
        <a href="index.php" class="btn bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">بازگشت به مدیریت هزینه‌ها</a>
    </div>

    <div class="card">
        <h3 class="text-lg font-semibold mb-4">ویرایش هزینه</h3>
        <form action="edit_expense.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
            <div>
                <label class="block text-gray-700">تاریخ</label>
                <input type="date" name="date" value="<?php echo $expense['date']; ?>" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-gray-700">پرداخت‌کننده</label>
                <select name="payer_id" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">انتخاب کنید</option>
                    <?php foreach ($payers as $payer): ?>
                        <option value="<?php echo $payer['id']; ?>" <?php echo $payer['id'] == $expense['payer_id'] ? 'selected' : ''; ?>><?php echo $payer['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700">شرح هزینه</label>
                <textarea name="description" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" rows="4"><?php echo $expense['description']; ?></textarea>
            </div>
            <div>
                <label class="block text-gray-700">نوع هزینه</label>
                <select name="type" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="daily" <?php echo $expense['type'] == 'daily' ? 'selected' : ''; ?>>هزینه‌های جاری</option>
                    <option value="furniture" <?php echo $expense['type'] == 'furniture' ? 'selected' : ''; ?>>اثاثیه منزل</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700">مبلغ هزینه (ریال عمان)</label>
                <input type="number" name="amount" step="0.001" value="<?php echo $expense['amount']; ?>" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-gray-700">تعداد نفرات</label>
                <input type="number" name="people" value="<?php echo $expense['people']; ?>" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="btn bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>