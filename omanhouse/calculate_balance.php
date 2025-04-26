<?php
include 'db.php';

// Get selected month (default to current month if not set)
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Define families
$families = [
    'خانواده علی' => [],
    'خانواده مجتبی' => [],
    'خانواده محمود' => [],
    'خانواده متینه' => []
];

// Get fixed shares for the selected month
$fixed_shares = [];
$fixed_shares_result = $conn->query("SELECT family, food_share, cooking_fee FROM monthly_fixed_shares WHERE month = '$selected_month'");
if ($fixed_shares_result && $fixed_shares_result->num_rows > 0) {
    while ($row = $fixed_shares_result->fetch_assoc()) {
        $fixed_shares[$row['family']] = [
            'food_share' => $row['food_share'],
            'cooking_fee' => $row['cooking_fee']
        ];
    }
}

// Get family presence for the selected month
$present_families = [];
$presence_result = $conn->query("SELECT family, is_present FROM family_presence WHERE month = '$selected_month'");
if ($presence_result && $presence_result->num_rows > 0) {
    while ($row = $presence_result->fetch_assoc()) {
        if ($row['is_present']) {
            $present_families[] = $row['family'];
        }
    }
}
$num_present_families = count($present_families);

// Initialize families data
$family_data = [];
foreach ($families as $family => $data) {
    $food_share = isset($fixed_shares[$family]) ? $fixed_shares[$family]['food_share'] : 0.000;
    $cooking_fee = isset($fixed_shares[$family]) ? $fixed_shares[$family]['cooking_fee'] : 0.000;
    $family_data[$family] = [
        'paid_daily' => 0,
        'paid_furniture' => 0,
        'paid_fixed' => 0,
        'share_daily' => 0,
        'share_furniture' => 0,
        'fixed_food_share' => $food_share,
        'fixed_cooking_fee' => $cooking_fee,
        'expenses_daily' => [],
        'expenses_furniture' => [],
        'expenses_fixed' => []
    ];
}

// Get expenses for the selected month with details
$expenses_result = $conn->query("SELECT e.*, p.name as payer_name FROM expenses e LEFT JOIN payers p ON e.payer_id = p.id WHERE DATE_FORMAT(e.date, '%Y-%m') = '$selected_month'");
$expenses_daily = [];
$expenses_furniture = [];
$family_mapping = [
    'علی' => 'خانواده علی',
    'مجتبی' => 'خانواده مجتبی',
    'محمود' => 'خانواده محمود',
    'متینه' => 'خانواده متینه',
    'پروانه' => 'خانواده متینه',
    'محمدامین' => 'خانواده متینه'
];
if ($expenses_result && $expenses_result->num_rows > 0) {
    while ($row = $expenses_result->fetch_assoc()) {
        $family = $family_mapping[$row['payer_name']] ?? null;
        if ($family) {
            if ($row['type'] == 'daily') {
                $expenses_daily[] = $row;
                $family_data[$family]['paid_daily'] += $row['amount'];
                $family_data[$family]['expenses_daily'][] = $row;
            } else {
                $expenses_furniture[] = $row;
                $family_data[$family]['paid_furniture'] += $row['amount'];
                $family_data[$family]['expenses_furniture'][] = $row;
            }
        }
    }
}

// Get fixed payments from payments table for the selected month
$payments_result = $conn->query("SELECT * FROM payments WHERE DATE_FORMAT(date, '%Y-%m') = '$selected_month'");
if ($payments_result && $payments_result->num_rows > 0) {
    while ($row = $payments_result->fetch_assoc()) {
        $family = $row['family'];
        if (isset($family_data[$family])) {
            $family_data[$family]['paid_fixed'] += $row['amount'];
            $family_data[$family]['expenses_fixed'][] = $row;
        }
    }
}

// Calculate per-family share for daily expenses based on the number of present families
foreach ($expenses_daily as $expense) {
    if ($num_present_families > 0) {
        $per_family_share = $expense['amount'] / $num_present_families;
        $payer_family = $family_mapping[$expense['payer_name']] ?? null;
        foreach ($family_data as $family => $data) {
            if ($family != $payer_family && in_array($family, $present_families)) {
                $family_data[$family]['share_daily'] += $per_family_share;
            }
        }
    }
}

// Calculate per-family share for furniture expenses (fixed at 4 families)
foreach ($expenses_furniture as $expense) {
    $per_family_share = $expense['amount'] / 4; // Fixed at 4 families
    $payer_family = $family_mapping[$expense['payer_name']] ?? null;
    foreach ($family_data as $family => $data) {
        if ($family != $payer_family) {
            $family_data[$family]['share_furniture'] += $per_family_share;
        }
    }
}

// Fetch available months for selection
$available_months = [];
$month_query = $conn->query("SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') as month FROM expenses 
                             UNION 
                             SELECT DISTINCT month FROM monthly_fixed_shares 
                             UNION 
                             SELECT DISTINCT month FROM family_presence 
                             ORDER BY month DESC");
if ($month_query && $month_query->num_rows > 0) {
    while ($row = $month_query->fetch_assoc()) {
        $available_months[] = $row['month'];
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
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
        }
        .btn {
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 1rem;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .balance-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .balance-item:last-child {
            border-bottom: none;
        }
        .positive {
            color: #16a34a;
        }
        .negative {
            color: #dc2626;
        }
        .expense-details {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 0.25rem;
        }
        .tab {
            background: #e5e7eb;
            padding: 0.75rem 1.5rem;
            border-radius: 0.25rem 0.25rem 0 0;
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
        h2 {
            font-size: 1.5rem;
            line-height: 2rem;
        }
        h3 {
            font-size: 1.125rem;
            line-height: 1.75rem;
        }
        p {
            font-size: 1rem;
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
            h3 {
                font-size: 1rem;
                line-height: 1.5rem;
            }
            p {
                font-size: 0.875rem;
            }
            .btn {
                font-size: 0.875rem;
                padding: 0.375rem 0.75rem;
            }
            .tab {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
            .balance-item {
                padding: 0.5rem;
            }
            .expense-details {
                padding: 0.5rem;
            }
            .form-group select {
                font-size: 0.875rem;
                padding: 0.375rem;
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
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">تراز مالی ماه <?php echo $selected_month; ?></h2>
        <div class="flex space-x-2 mt-4 sm:mt-0">
            <form action="calculate_balance.php" method="GET" class="form-group">
                <select name="month" onchange="this.form.submit()">
                    <?php foreach ($available_months as $month): ?>
                        <option value="<?php echo $month; ?>" <?php echo $month == $selected_month ? 'selected' : ''; ?>><?php echo $month; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="set_fixed_shares.php?month=<?php echo $selected_month; ?>" class="btn bg-blue-600 text-white hover:bg-blue-700">تنظیم مقادیر ثابت</a>
            <a href="index.php" class="btn bg-gray-600 text-white hover:bg-gray-700">بازگشت به داشبورد</a>
        </div>
    </div>

    <div class="flex space-x-2 mb-4">
        <div id="tab-daily" class="tab" onclick="openTab('daily')">هزینه‌های جاری</div>
        <div id="tab-furniture" class="tab" onclick="openTab('furniture')">هزینه‌های اثاثیه</div>
    </div>

    <!-- Daily Expenses Tab -->
    <div id="content-daily" class="tab-content">
        <div class="card">
            <h3 class="text-lg font-semibold mb-4">پرداخت‌های ثابت هزینه‌های جاری</h3>
            <?php if (empty(array_filter($family_data, fn($family) => !empty($family['expenses_fixed'])))): ?>
                <p class="text-gray-600">هیچ پرداخت ثابتی برای این ماه ثبت نشده است.</p>
            <?php else: ?>
                <?php foreach ($family_data as $family => $data): ?>
                    <?php foreach ($data['expenses_fixed'] as $expense): ?>
                        <div class="expense-details">
                            <p>خانواده: <?php echo $family; ?> | مبلغ: <?php echo number_format($expense['amount'], 3); ?> ریال | تاریخ: <?php echo $expense['date']; ?> | توضیح: <?php echo $expense['description']; ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 class="text-lg font-semibold mb-4">هزینه‌های جاری ثبت‌شده (غیر ثابت)</h3>
            <?php if (empty($expenses_daily)): ?>
                <p class="text-gray-600">هیچ هزینه جاری برای این ماه ثبت نشده است.</p>
            <?php else: ?>
                <?php foreach ($expenses_daily as $expense): ?>
                    <div class="expense-details">
                        <p>پرداخت‌کننده: <?php echo $expense['payer_name']; ?> | مبلغ: <?php echo number_format($expense['amount'], 3); ?> ریال | تاریخ: <?php echo $expense['date']; ?> | تعداد نفرات: <?php echo $expense['people']; ?> | نوع: هزینه‌های جاری | توضیح: <?php echo $expense['description']; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 class="text-lg font-semibold mb-4">تراز مالی (هزینه‌های جاری)</h3>
            <?php if (empty($family_data)): ?>
                <p class="text-gray-600">هیچ خانواده‌ای ثبت نشده است.</p>
            <?php else: ?>
                <?php foreach ($family_data as $family => $data): ?>
                    <?php
                    $total_fixed_share = $data['fixed_food_share'] + $data['fixed_cooking_fee'];
                    $unpaid_fixed = max(0, $total_fixed_share - $data['paid_fixed']);
                    $food_balance = $data['share_daily'] - $data['fixed_food_share'];
                    $total_balance = $unpaid_fixed + $food_balance - $data['paid_daily'];
                    $status = $total_balance >= 0
                        ? "<span class='positive'>باید دریافت کند: " . number_format($total_balance, 3) . " ریال عمان</span>"
                        : "<span class='negative'>باید پرداخت کند: " . number_format(abs($total_balance), 3) . " ریال عمان</span>";
                    ?>
                    <div class="balance-item">
                        <p class="text-gray-800"><?php echo $family . ": " . $status; ?></p>
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
            <?php if (empty($family_data)): ?>
                <p class="text-gray-600">هیچ خانواده‌ای ثبت نشده است.</p>
            <?php else: ?>
                <?php foreach ($family_data as $family => $data): ?>
                    <?php
                    $balance = $data['paid_furniture'] - $data['share_furniture'];
                    $status = $balance >= 0
                        ? "<span class='positive'>باید دریافت کند: " . number_format($balance, 3) . " ریال عمان</span>"
                        : "<span class='negative'>باید پرداخت کند: " . number_format(abs($balance), 3) . " ریال عمان</span>";
                    ?>
                    <div class="balance-item">
                        <p class="text-gray-800"><?php echo $family . ": " . $status; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>