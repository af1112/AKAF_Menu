<?php
session_start();
include 'db.php';

// بررسی ورود کاربر و دریافت تم و زبان پیش‌فرض
$user_theme = 'light';
$user_language = 'fa'; // مقدار پیش‌فرض
if (isset($_SESSION['user']['id'])) {
    $user_id = $_SESSION['user']['id'];
    $stmt = $pdo->prepare("SELECT theme, language FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_theme = $user['theme'] ?? 'light';
    $user_language = $user['language'] ?? 'fa';
}

// Load language
// ابتدا زبان پیش‌فرض کاربر از دیتابیس تنظیم می‌شود
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = $user_language;
}
// اگر کاربر از نوار زبان تغییر داد، زبان سشن به‌روزرسانی می‌شود
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$language_file = "languages/" . $_SESSION['lang'] . ".php";
if (!file_exists($language_file)) {
    $language_file = "languages/en.php";
}
include $language_file;

// Detect language direction
$rtl_languages = ['fa', 'ar'];
$is_rtl = in_array($_SESSION['lang'], $rtl_languages);
$direction = $is_rtl ? 'rtl' : 'ltr';

// بررسی وضعیت ورود کاربر
$is_logged_in = isset($_SESSION['user']['id']);

// تعیین کلاس تم برای body
$theme_class = $user_theme === 'dark' ? 'dark-theme' : 'light-theme';
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['mobile_menu_title'] ?? 'Menu'; ?></title>
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
        body.light-theme .menu-container {
            background: #fff;
        }
        body.light-theme .menu-list .list-group-item {
            background-color: #f8f9fa;
        }
        body.light-theme .menu-header h2 {
            color: #ff5722;
        }
        /* تم تیره */
        body.dark-theme {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }
        body.dark-theme .menu-container {
            background: #2a2a2a;
            color: #e0e0e0;
        }
        body.dark-theme .menu-list .list-group-item {
            background-color: #333;
            color: #e0e0e0;
        }
        body.dark-theme .menu-list .list-group-item:hover {
            background-color: #ff7043;
            color: #000;
        }
        body.dark-theme .menu-header h2 {
            color: #ff7043;
        }
        .language-bar {
            background-color: #ff5722;
            padding: 10px 0;
            text-align: center;
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
        .menu-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .menu-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .menu-list .list-group-item {
            border: none;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: background-color 0.3s;
        }
        .menu-list .list-group-item:hover {
            background-color: #ff5722;
            color: white;
        }
        .menu-list .list-group-item a {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
        }
        .menu-list .list-group-item i {
            font-size: 20px;
        }
        /* راست‌چین کردن کامل برای زبان‌های RTL */
        [dir="rtl"] .menu-header {
            text-align: right;
        }
        [dir="rtl"] .menu-list .list-group-item a {
            flex-direction: row-reverse;
            text-align: right;
            justify-content: flex-end;
        }
        [dir="rtl"] .menu-list .list-group-item i {
            order: 1;
            margin-left: 0;
            margin-right: 10px;
        }
        [dir="rtl"] .menu-list .list-group-item span {
            order: 0;
        }
        [dir="rtl"] .flag-icon {
            margin-right: 0;
            margin-left: 5px;
        }
        [dir="rtl"] .language-switcher {
            direction: rtl;
        }
        /* بهینه‌سازی برای موبایل */
        @media (max-width: 768px) {
            .menu-container {
                margin: 10px;
                padding: 15px;
            }
            .menu-header h2 {
                font-size: 24px;
            }
            .menu-list .list-group-item {
                font-size: 16px;
                padding: 12px;
            }
            .menu-list .list-group-item a {
                padding: 8px;
            }
            .language-switcher a {
                font-size: 14px;
                margin: 0 5px;
            }
            .flag-icon {
                width: 18px;
            }
        }
    </style>
</head>
<body class="<?php echo $theme_class; ?>">
    <!-- Language Bar -->
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher">
                <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="mobile_menu.php?lang=en">
                    <img src="images/flags/en.png" alt="English" class="flag-icon"> EN
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="mobile_menu.php?lang=fa">
                    <img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="mobile_menu.php?lang=ar">
                    <img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="mobile_menu.php?lang=fr">
                    <img src="images/flags/fr.png" alt="French" class="flag-icon"> FR
                </a>
            </div>
        </div>
    </div>

    <div class="menu-container">
        <!-- هدر منو -->
        <div class="menu-header">
            <h2><?php echo $lang['mobile_menu_title'] ?? 'Menu'; ?></h2>
        </div>

        <!-- لیست گزینه‌ها -->
        <div class="menu-list">
            <ul class="list-group">
                <?php if ($is_logged_in): ?>
                    <!-- گزینه‌های کاربر وارد شده -->
                    <li class="list-group-item">
                        <a href="profile.php">
                            <i class="bi bi-person-circle"></i>
                            <span><?php echo $lang['profile'] ?? 'Profile'; ?></span>
                        </a>
                    </li>
                    <li class="list-group-item">
                        <a href="cart.php">
                            <i class="bi bi-cart-fill"></i>
                            <span><?php echo $lang['cart'] ?? 'Cart'; ?></span>
                        </a>
                    </li>
                    <li class="list-group-item">
                        <a href="logout.php">
                            <i class="bi bi-box-arrow-right"></i>
                            <span><?php echo $lang['logout'] ?? 'Logout'; ?></span>
                        </a>
                    </li>
                <?php else: ?>
                    <!-- گزینه‌های کاربر وارد نشده -->
                    <li class="list-group-item">
                        <a href="user_login.php">
                            <i class="bi bi-box-arrow-in-left"></i>
                            <span><?php echo $lang['login'] ?? 'Login'; ?></span>
                        </a>
                    </li>
                    <li class="list-group-item">
                        <a href="register.php">
                            <i class="bi bi-person-plus-fill"></i>
                            <span><?php echo $lang['register'] ?? 'Register'; ?></span>
                        </a>
                    </li>
                <?php endif; ?>
                <!-- گزینه‌های عمومی -->
                <li class="list-group-item">
                    <a href="index.php">
                        <i class="bi bi-house-door-fill"></i>
                        <span><?php echo $lang['home'] ?? 'Home'; ?></span>
                    </a>
                </li>
                <li class="list-group-item">
                    <a href="menu.php">
                        <i class="bi bi-list-ul"></i>
                        <span><?php echo $lang['menu'] ?? 'Menu'; ?></span>
                    </a>
                </li>
                <li class="list-group-item">
                    <a href="contact.php">
                        <i class="bi bi-envelope-fill"></i>
                        <span><?php echo $lang['contact_us'] ?? 'Contact Us'; ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>