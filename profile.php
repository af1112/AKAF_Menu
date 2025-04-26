<?php
session_start();
include 'db.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user']['id'];

// دریافت اطلاعات کاربر از دیتابیس
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Load language
// ابتدا زبان پیش‌فرض کاربر از دیتابیس تنظیم می‌شود
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = $user['language'] ?? 'fa';
}
// اگر کاربر از نوار زبان تغییر داد، زبان سشن به‌روزرسانی می‌شود
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
include "languages/" . $_SESSION['lang'] . ".php";

// Detect language direction
$rtl_languages = ['fa', 'ar'];
$is_rtl = in_array($_SESSION['lang'], $rtl_languages);
$direction = $is_rtl ? 'rtl' : 'ltr';

// دریافت امتیازات کاربر
$stmt = $pdo->prepare("SELECT SUM(points) as total_points FROM user_points WHERE user_id = ?");
$stmt->execute([$user_id]);
$points = $stmt->fetch(PDO::FETCH_ASSOC)['total_points'] ?? 0;

// آپلود تصویر پروفایل
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_filename = "profile_" . $user_id . "_" . time() . "." . $ext;
            $upload_dir = "uploads/profiles/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_filename)) {
                $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $stmt->execute([$upload_dir . $new_filename, $user_id]);
                header("Location: profile.php");
                exit;
            }
        }
    }
}

// حذف تصویر پروفایل
if (isset($_POST['delete_image'])) {
    if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
        unlink($user['profile_image']);
    }
    $stmt = $pdo->prepare("UPDATE users SET profile_image = NULL WHERE id = ?");
    $stmt->execute([$user_id]);
    header("Location: profile.php");
    exit;
}

// ویرایش اطلاعات پروفایل
if (isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $nationality = $_POST['nationality'];

    $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, dob = ?, nationality = ? WHERE id = ?");
    $stmt->execute([$fullname, $email, $phone, $dob, $nationality, $user_id]);
    header("Location: profile.php");
    exit;
}

// تغییر زبان پیش‌فرض
if (isset($_POST['default_language'])) {
    $new_lang = $_POST['language'];
    $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE id = ?");
    $stmt->execute([$new_lang, $user_id]);
    $_SESSION['lang'] = $new_lang;
    header("Location: profile.php");
    exit;
}

// تغییر تم
if (isset($_POST['update_theme'])) {
    $new_theme = $_POST['theme'];
    $stmt = $pdo->prepare("UPDATE users SET theme = ? WHERE id = ?");
    $stmt->execute([$new_theme, $user_id]);
    // به‌روزرسانی اطلاعات کاربر
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    header("Location: profile.php");
    exit;
}

// بررسی مقدار dob برای جلوگیری از خطا
$dob_value = $user['dob'] === '0000-00-00' || empty($user['dob']) ? '' : htmlspecialchars($user['dob']);

// تعیین کلاس تم برای body
$theme_class = $user['theme'] === 'dark' ? 'dark-theme' : 'light-theme';
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['profile_title'] ?? 'Profile'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: <?php echo $is_rtl ? "'IRANSans', sans-serif" : "'Roboto', sans-serif"; ?>;
            transition: all 0.3s ease;
        }
        /* تم روشن (پیش‌فرض) */
        body.light-theme {
            background-color: #f8f9fa;
        }
        body.light-theme .profile-container {
            background: #fff;
        }
        body.light-theme .points-box {
            background: #ff5722;
            color: white;
        }
        body.light-theme .nav-tabs .nav-link.active {
            background: #ff5722;
            color: white;
            border-color: #ff5722;
        }
        body.light-theme .list-group-item {
            background-color: #f8f9fa;
        }
        /* تم تیره */
        body.dark-theme {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }
        body.dark-theme .profile-container {
            background: #2a2a2a;
            color: #e0e0e0;
        }
        body.dark-theme .points-box {
            background: #ff7043;
            color: #000;
        }
        body.dark-theme .nav-tabs .nav-link {
            color: #e0e0e0;
        }
        body.dark-theme .nav-tabs .nav-link.active {
            background: #ff7043;
            color: #000;
            border-color: #ff7043;
        }
        body.dark-theme .tab-content {
            border-color: #444;
        }
        body.dark-theme .list-group-item {
            background-color: #333;
            color: #e0e0e0;
        }
        body.dark-theme .text-muted {
            color: #aaa !important;
        }
        body.dark-theme .btn-primary {
            background-color: #ff7043;
            border-color: #ff7043;
        }
        body.dark-theme .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        body.dark-theme .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .language-bar {
            background-color: #ff5722;
            padding: 10px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        .language-switcher {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .language-switcher a {
            color: white;
            margin: 0 10px;
            text-decoration: none;
        }
        .language-switcher a.active {
            font-weight: bold;
            border-bottom: 2px solid white;
        }
        .flag-icon {
            width: 20px;
            margin-right: 5px;
        }
        .home-link {
            position: absolute;
            <?php echo $is_rtl ? 'right: 20px;' : 'left: 20px;'; ?>
            color: white;
            font-size: 24px;
            text-decoration: none;
        }
        .profile-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ff5722;
        }
        .points-box {
            padding: 10px 20px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 10px;
        }
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            margin-right: 5px;
        }
        .tab-content {
            border: 1px solid #dee2e6;
            border-top: none;
            padding: 20px;
            border-radius: 0 0 10px 10px;
        }
        .list-group-item {
            border-radius: 10px;
            margin-bottom: 10px;
        }
        [dir="rtl"] .text-start, [dir="rtl"] h2, [dir="rtl"] h4, [dir="rtl"] p, [dir="rtl"] ul, [dir="rtl"] li {
            text-align: right !important;
        }
        [dir="rtl"] .flag-icon {
            margin-right: 0;
            margin-left: 5px;
        }
        .btn-add {
            background-color: #ff5722;
            color: white;
            margin-bottom: 15px;
        }
        @media (max-width: 768px) {
            .profile-container {
                margin: 20px 10px;
                padding: 15px;
            }
            .profile-img {
                width: 120px;
                height: 120px;
            }
            .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                white-space: nowrap;
            }
            .nav-tabs .nav-link {
                font-size: 14px;
                padding: 8px 12px;
            }
            .list-group-item {
                font-size: 14px;
                padding: 10px;
            }
            .language-switcher a {
                font-size: 14px;
                margin: 0 5px;
            }
            .home-link {
                font-size: 20px;
                <?php echo $is_rtl ? 'right: 10px;' : 'left: 10px;'; ?>
            }
        }
    </style>
</head>
<body class="<?php echo $theme_class; ?>">
    <!-- Language Bar -->
    <div class="language-bar">
        <a href="index.php" class="home-link" title="<?php echo $lang['home'] ?? 'Home'; ?>">
            <i class="bi bi-house-door-fill"></i>
        </a>
        <div class="language-switcher">
            <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="profile.php?lang=en">
                <img src="images/flags/en.png" alt="English" class="flag-icon"> EN
            </a>
            <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="profile.php?lang=fa">
                <img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
            </a>
            <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="profile.php?lang=ar">
                <img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
            </a>
            <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="profile.php?lang=fr">
                <img src="images/flags/fr.png" alt="French" class="flag-icon"> FR
            </a>
        </div>
    </div>

    <div class="container profile-container">
        <!-- هدر پروفایل -->
        <div class="profile-header">
            <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'default_profile.jpg'); ?>" alt="Profile Image" class="profile-img">
            <h2 class="mt-3"><?php echo htmlspecialchars($user['fullname']); ?></h2>
            <p class="text-muted"><?php echo htmlspecialchars($user['username']); ?></p>
            <div class="points-box">
                <i class="bi bi-star-fill"></i> <?php echo $lang['points'] ?? 'Points'; ?>: <?php echo $points; ?>
            </div>
            <!-- آپلود و حذف تصویر -->
            <div class="mt-3">
                <form action="profile.php" method="post" enctype="multipart/form-data">
                    <div class="mb-2">
                        <input type="file" name="profile_image" accept="image/*" class="form-control form-control-sm">
                    </div>
                    <button type="submit" name="upload_image" class="btn btn-primary btn-sm"><?php echo $lang['upload_image'] ?? 'Upload Image'; ?></button>
                    <?php if (!empty($user['profile_image'])): ?>
                        <button type="submit" name="delete_image" class="btn btn-danger btn-sm"><?php echo $lang['delete_image'] ?? 'Delete Image'; ?></button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- اطلاعات شخصی و ویرایش -->
        <div class="mb-4">
            <h4><?php echo $lang['personal_info'] ?? 'Personal Information'; ?></h4>
            <form action="profile.php" method="post">
                <ul class="list-group">
                    <li class="list-group-item">
                        <strong><?php echo $lang['fullname'] ?? 'Full Name'; ?>:</strong>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" class="form-control mt-1" required>
                    </li>
                    <li class="list-group-item">
                        <strong><?php echo $lang['email'] ?? 'Email'; ?>:</strong>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control mt-1" required>
                    </li>
                    <li class="list-group-item">
                        <strong><?php echo $lang['phone'] ?? 'Phone'; ?>:</strong>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="form-control mt-1">
                    </li>
                    <li class="list-group-item">
                        <strong><?php echo $lang['dob'] ?? 'Date of Birth'; ?>:</strong>
                        <input type="date" name="dob" value="<?php echo $dob_value; ?>" class="form-control mt-1">
                    </li>
                    <li class="list-group-item">
                        <strong><?php echo $lang['nationality'] ?? 'Nationality'; ?>:</strong>
                        <input type="text" name="nationality" value="<?php echo htmlspecialchars($user['nationality']); ?>" class="form-control mt-1">
                    </li>
                </ul>
                <button type="submit" name="update_profile" class="btn btn-success mt-3"><?php echo $lang['save_changes'] ?? 'Save Changes'; ?></button>
            </form>
        </div>

        <!-- انتخاب زبان پیش‌فرض -->
        <div class="mb-4">
            <h4><?php echo $lang['default_language'] ?? 'Default Language'; ?></h4>
            <form action="profile.php" method="post">
                <select name="language" class="form-select mb-2" required>
                    <option value="en" <?php echo $user['language'] == 'en' ? 'selected' : ''; ?>>English</option>
                    <option value="fa" <?php echo $user['language'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                    <option value="ar" <?php echo $user['language'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
                    <option value="fr" <?php echo $user['language'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                </select>
                <button type="submit" name="default_language" class="btn btn-primary"><?php echo $lang['set_default'] ?? 'Set as Default'; ?></button>
            </form>
        </div>

        <!-- انتخاب تم -->
        <div class="mb-4">
            <h4><?php echo $lang['theme'] ?? 'Theme'; ?></h4>
            <form action="profile.php" method="post">
                <select name="theme" class="form-select mb-2" required>
                    <option value="light" <?php echo $user['theme'] == 'light' ? 'selected' : ''; ?>><?php echo $lang['light_theme'] ?? 'Light Theme'; ?></option>
                    <option value="dark" <?php echo $user['theme'] == 'dark' ? 'selected' : ''; ?>><?php echo $lang['dark_theme'] ?? 'Dark Theme'; ?></option>
                </select>
                <button type="submit" name="update_theme" class="btn btn-primary"><?php echo $lang['set_theme'] ?? 'Set Theme'; ?></button>
            </form>
        </div>

        <!-- تب‌ها -->
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="cards-tab" data-bs-toggle="tab" data-bs-target="#cards" type="button" role="tab">
                    <?php echo $lang['cards'] ?? 'Bank Cards'; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cars-tab" data-bs-toggle="tab" data-bs-target="#cars" type="button" role="tab">
                    <?php echo $lang['cars'] ?? 'Cars'; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses" type="button" role="tab">
                    <?php echo $lang['addresses'] ?? 'Delivery Addresses'; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
                    <?php echo $lang['past_orders'] ?? 'Past Orders'; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="profileTabContent">
            <!-- تب کارت‌های بانکی -->
            <div class="tab-pane fade show active" id="cards" role="tabpanel">
                <a href="add_card.php" class="btn btn-add"><?php echo $lang['add_card'] ?? 'Add Card'; ?></a>
                <ul class="list-group">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM user_cards WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cards as $card) {
                        echo '<li class="list-group-item text-start">' . htmlspecialchars($card['card_number']) . ' (' . htmlspecialchars($card['card_type']) . ')</li>';
                    }
                    if (empty($cards)) {
                        echo '<li class="list-group-item text-start">' . ($lang['no_cards'] ?? 'No cards registered.') . '</li>';
                    }
                    ?>
                </ul>
            </div>

            <!-- تب ماشین‌ها -->
            <div class="tab-pane fade" id="cars" role="tabpanel">
                <a href="add_car.php" class="btn btn-add"><?php echo $lang['add_car'] ?? 'Add Car'; ?></a>
                <ul class="list-group">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM user_cars WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cars as $car) {
                        echo '<li class="list-group-item text-start">' . htmlspecialchars($car['car_model']) . ' - ' . htmlspecialchars($car['license_plate']) . '</li>';
                    }
                    if (empty($cars)) {
                        echo '<li class="list-group-item text-start">' . ($lang['no_cars'] ?? 'No cars registered.') . '</li>';
                    }
                    ?>
                </ul>
            </div>

            <!-- تب آدرس‌های دلیوری -->
            <div class="tab-pane fade" id="addresses" role="tabpanel">
                <a href="add_address.php" class="btn btn-add"><?php echo $lang['add_address'] ?? 'Add Address'; ?></a>
                <ul class="list-group">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($addresses as $address) {
                        echo '<li class="list-group-item text-start">' . htmlspecialchars($address['address']) . ', ' . htmlspecialchars($address['city']) . '</li>';
                    }
                    if (empty($addresses)) {
                        echo '<li class="list-group-item text-start">' . ($lang['no_addresses'] ?? 'No addresses registered.') . '</li>';
                    }
                    ?>
                </ul>
            </div>

            <!-- تب سفارشات گذشته -->
            <div class="tab-pane fade" id="orders" role="tabpanel">
                <ul class="list-group">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$user_id]);
                    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($orders as $order) {
                        echo '<li class="list-group-item text-start">Order #' . $order['id'] . ' - ' . $order['created_at'] . ' - ' . $order['grand_total'] . ' OMR</li>';
                    }
                    if (empty($orders)) {
                        echo '<li class="list-group-item text-start">' . ($lang['no_orders'] ?? 'No past orders.') . '</li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>