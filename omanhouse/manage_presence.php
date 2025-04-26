<?php
include 'db.php';

// Define families
$families = ['خانواده علی', 'خانواده مجتبی', 'خانواده محمود', 'خانواده محمدامین'];

// Get selected month (default to current month if not set)
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Initialize presence for the selected month
$presence = array_fill_keys($families, true);

// Check if presence data already exists for the selected month
$existing = $conn->query("SELECT family, is_present FROM family_presence WHERE month = '$selected_month'");
if ($existing && $existing->num_rows > 0) {
    while ($row = $existing->fetch_assoc()) {
        $presence[$row['family']] = (bool)$row['is_present'];
    }
}

// Handle form submission for adding/updating presence
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $month = $_POST['month'];
    $presence_data = $_POST['presence'] ?? [];

    // First, delete existing records for this month
    $conn->query("DELETE FROM family_presence WHERE month = '$month'");

    // Insert new records
    $stmt = $conn->prepare("INSERT INTO family_presence (month, family, is_present) VALUES (?, ?, ?)");
    foreach ($families as $family) {
        $is_present = isset($presence_data[$family]) ? 1 : 0;
        $stmt->bind_param("ssi", $month, $family, $is_present);
        $stmt->execute();
    }
    $stmt->close();

    header("Location: manage_presence.php?month=$month");
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
        $_SESSION['password_verified_manage_presence'] = true;
    } else {
        $password_error = 'رمز عبور اشتباه است';
    }
}

// Check if password is already verified
session_start();
if (isset($_SESSION['password_verified_manage_presence']) && $_SESSION['password_verified_manage_presence'] === true) {
    $password_verified = true;
}

// Handle delete action after password verification
if ($password_verified && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $month = $_POST['month'];
    $conn->query("DELETE FROM family_presence WHERE month = '$month'");
    header("Location: manage_presence.php");
    exit();
}

// Fetch all recorded months for history
$history = [];
$history_result = $conn->query("SELECT DISTINCT month FROM family_presence ORDER BY month DESC");
if ($history_result && $history_result->num_rows > 0) {
    while ($row = $history_result->fetch_assoc()) {
        $month = $row['month'];
        $month_data = [];
        $month_presence = $conn->query("SELECT family, is_present FROM family_presence WHERE month = '$month'");
        while ($presence_row = $month_presence->fetch_assoc()) {
            $month_data[$presence_row['family']] = $presence_row['is_present'];
        }
        $history[$month] = $month_data;
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت حضور خانواده‌ها</title>
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
        .form-group input[type="text"] {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
        }
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .form-check input {
            margin-left: 0.5rem;
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
            .form-group input[type="text"] {
                font-size: 0.875rem;
                padding: 0.375rem;
            }
            .form-check label {
                font-size: 0.875rem;
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
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">مدیریت حضور خانواده‌ها</h2>
        <button onclick="location.href='index.php'" class="btn bg-gray-600 text-white hover:bg-gray-700 mb-4">بازگشت به داشبورد</button>

        <form action="manage_presence.php" method="POST">
            <input type="hidden" name="action" value="update">
            <div class="form-group">
                <label>ماه:</label>
                <input type="text" name="month" value="<?php echo $selected_month; ?>" required>
            </div>

            <?php foreach ($families as $family): ?>
                <div class="form-check">
                    <input type="checkbox" name="presence[<?php echo $family; ?>]" value="1" <?php echo $presence[$family] ? 'checked' : ''; ?>>
                    <label><?php echo $family; ?> حضور دارد</label>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn bg-green-600 text-white hover:bg-green-700 w-full">ذخیره</button>
        </form>
    </div>

    <?php if (!empty($history)): ?>
        <div class="card">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">تاریخچه حضور خانواده‌ها</h2>

            <?php if (!$password_verified): ?>
                <form action="manage_presence.php" method="POST">
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
                            <th>ماه</th>
                            <?php foreach ($families as $family): ?>
                                <th><?php echo $family; ?></th>
                            <?php endforeach; ?>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $month => $month_data): ?>
                            <tr>
                                <td><?php echo $month; ?></td>
                                <?php foreach ($families as $family): ?>
                                    <td><?php echo isset($month_data[$family]) && $month_data[$family] ? 'حاضر' : 'غایب'; ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <a href="manage_presence.php?month=<?php echo $month; ?>" class="btn bg-blue-600 text-white hover:bg-blue-700 action-btn">ویرایش</a>
                                    <form action="manage_presence.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="month" value="<?php echo $month; ?>">
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