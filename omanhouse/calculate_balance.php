<?php
include 'db.php';

$current_month = date('Y-m');
$monthly_food_share = 20; // OMR per person for food and consumables
$cooking_fee = 15; // OMR per person except for Parvaneh

// Get all payers
$payers_result = $conn->query("SELECT id, name FROM payers");
$payers = [];
if ($payers_result && $payers_result->num_rows > 0) {
    while ($row = $payers_result->fetch_assoc()) {
        $payers[$row['id']] = [
            'name' => $row['name'],
            'paid_daily' => 0,
            'paid_furniture' => 0,
            'share_daily' => $monthly_food_share, // Base share for food and consumables
            'share_furniture' => $monthly_food_share, // Base share for furniture (will be adjusted)
            'cooking_fee' => $row['name'] === 'پروانه' ? 0 : $cooking_fee, // Cooking fee (0 for Parvaneh)
            'expenses_daily' => [],
            'expenses_furniture' => []
        ];
    }
}

// Get expenses for the current month with details
$expenses_result = $conn->query("SELECT e.*, p.name as payer_name FROM expenses e LEFT JOIN payers p ON e.payer_id = p.id WHERE DATE_FORMAT(e.date, '%Y-%m') = '$current_month'");
$expenses_daily = [];
$expenses_furniture = [];
if ($expenses_result && $expenses_result->num_rows > 0) {
    while ($row = $expenses_result->fetch_assoc()) {
        if ($row['type'] == 'daily') {
            $expenses_daily[] = $row;
            if (isset($payers[$row['payer_id']])) {
                $payers[$row['payer_id']]['paid_daily'] += $row['amount'];
                $payers[$row['payer_id']]['expenses_daily'][] = $row;
            }
        } else {
            $expenses_furniture[] = $row;
            if (isset($payers[$row['payer_id']])) {
                $payers[$row['payer_id']]['paid_furniture'] += $row['amount'];
                $payers[$row['payer_id']]['expenses_furniture'][] = $row;
            }
        }
    }
}

// Calculate per-person share for daily expenses based on the number of people specified
foreach ($expenses_daily as $expense) {
    $per_person_share = $expense['amount'] / $expense['people'];
    foreach ($payers as $payer_id => $payer) {
        if ($payer_id != $expense['payer_id']) {
            $payers[$payer_id]['share_daily'] += $per_person_share;
        }
    }
}

// Calculate per-person share for furniture expenses (fixed at 4 people)
foreach ($expenses_furniture as $expense) {
    $per_person_share = $expense['amount'] / 4; // Fixed at 4 people as per request
    foreach ($payers as $payer_id => $payer) {
        if ($payer_id != $expense['payer_id']) {
            $payers[$payer_id]['share_furniture'] += $per_person_share;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تراز مالی - نرم‌افزار مدیریت خانه صفا</title>
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
        .balance-item {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .balance-item:last-child {
            border-bottom: none;
        }
        .positive {
            color: #16a34a; /* Green */
        }
        .negative {
            color: #dc2626; /* Red */
        }
        .expense-details {
            margin-top: 10px;
            padding: 10px;
            background: #f9fafb;
            border-radius: 4px;
        }
        .tab {
            background: #e5e7eb;
            padding: 10px 20px;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
        }
        .tab.active {
            background: white;
            border-bottom: 2px solid #4b5563;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        @media (max-width: 640px) {
            .container {
                padding: 10px;
            }
            h2 {
                font-size: 1.5rem;
            }
            .balance-item, .expense-details {
                font-size: 0.9rem;
            }
        }
    </style>
    <script>
        function openTab(tabName) {
            const tabs = document.querySelectorAll('.tab');
            const contents = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            contents.forEach(content => content.classList.remove('active'));
            document.getElementById(`tab-${tabName}`).classList.add('active');
            document.getElementById(`content-${tabName}`).classList.add('active');
        }
    </script>
</head>
<body onload="openTab('daily')">
<div class="container">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">تراز مالی ماه <?php echo $current_month; ?></h2>
        <a href="index.php" class="btn bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">بازگشت به صفحه اصلی</a>
    </div>

    <div class="flex space-x-2 mb-4">
        <div id="tab-daily" class="tab" onclick="openTab('daily')">هزینه‌های جاری</div>
        <div id="tab-furniture" class="tab" onclick="openTab('furniture')">هزینه‌های اثاثیه</div>
    </div>

    <!-- Daily Expenses Tab -->
    <div id="content-daily" class="tab-content">
        <div class="card">
            <h3 class="text-lg font-semibold mb-4">هزینه‌های جاری ثبت‌شده</h3>
            <?php if (empty($expenses_daily)): ?>
                <p class="text-gray-600">هیچ هزینه جاری برای این ماه ثبت نشده است.</p>
            <?php else: ?>
                <?php foreach ($expenses_daily as $expense): ?>
                    <div class="expense-details">
                        <p>پرداخت‌کننده: <?php echo $expense['payer_name']; ?> | مبلغ: <?php echo number_format($expense['amount'], 3); ?> ریال | تاریخ: <?php echo $expense['date']; ?> | تعداد نفرات: <?php echo $expense['people']; ?> | نوع: هزینه‌های جاری</p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 class="text-lg font-semibold mb-4">تراز مالی (هزینه‌های جاری)</h3>
            <?php if (empty($payers)): ?>
                <p class="text-gray-600">هیچ پرداخت‌کننده‌ای ثبت نشده است. لطفاً ابتدا پرداخت‌کنندگان را در دیتابیس اضافه کنید.</p>
            <?php else: ?>
                <?php foreach ($payers as $payer): ?>
                    <?php
                    $balance = $payer['paid_daily'] - ($payer['share_daily'] + $payer['cooking_fee']);
                    $status = $balance >= 0
                        ? "<span class='positive'>باید دریافت کند: " . number_format($balance, 3) . " ریال عمان</span>"
                        : "<span class='negative'>باید پرداخت کند: " . number_format(abs($balance), 3) . " ریال عمان</span>";
                    ?>
                    <div class="balance-item">
                        <p class="text-gray-800"><?php echo $payer['name'] . ": " . $status; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Furniture Expenses Tab -->
    <div id="content-furniture" class="tab-content">
        <div class="card">
            <h3 class="text-lg font-semibold mb-4">هزینه‌های اثاثیه ثبت‌شده</h3>
            <?php if (empty($expenses_furniture)): ?>
                <p class="text-gray-600">هیچ هزینه اثاثیه برای این ماه ثبت نشده است.</p>
            <?php else: ?>
                <?php foreach ($expenses_furniture as $expense): ?>
                    <div class="expense-details">
                        <p>پرداخت‌کننده: <?php echo $expense['payer_name']; ?> | مبلغ: <?php echo number_format($expense['amount'], 3); ?> ریال | تاریخ: <?php echo $expense['date']; ?> | تعداد نفرات: <?php echo $expense['people']; ?> | نوع: اثاثیه منزل</p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 class="text-lg font-semibold mb-4">تراز مالی (هزینه‌های اثاثیه)</h3>
            <?php if (empty($payers)): ?>
                <p class="text-gray-600">هیچ پرداخت‌کننده‌ای ثبت نشده است. لطفاً ابتدا پرداخت‌کنندگان را در دیتابیس اضافه کنید.</p>
            <?php else: ?>
                <?php foreach ($payers as $payer): ?>
                    <?php
                    $balance = $payer['paid_furniture'] - $payer['share_furniture'];
                    $status = $balance >= 0
                        ? "<span class='positive'>باید دریافت کند: " . number_format($balance, 3) . " ریال عمان</span>"
                        : "<span class='negative'>باید پرداخت کند: " . number_format(abs($balance), 3) . " ریال عمان</span>";
                    ?>
                    <div class="balance-item">
                        <p class="text-gray-800"><?php echo $payer['name'] . ": " . $status; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>