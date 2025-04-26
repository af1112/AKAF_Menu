<?php
session_start();
include 'db.php';


// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

// Load language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
include "languages/" . $_SESSION['lang'] . ".php";

// Detect language direction
$rtl_languages = ['fa', 'ar'];
$is_rtl = in_array($_SESSION['lang'], $rtl_languages);
$direction = $is_rtl ? 'rtl' : 'ltr';

// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];
// Determine which page to load
$page = $_GET['page'] ?? 'dashboard';
$page_file = "pages/{$page}.php";

if (file_exists($page_file)) {
    include $page_file;
} else {
    echo "<p>" . ($lang['page_not_found'] ?? 'Page not found.') . "</p>";
}
// Fetch total unread messages for sidebar
$stmt_total_unread = $conn->prepare("
    SELECT COUNT(*) AS total_unread 
    FROM order_messages om 
    JOIN orders o ON om.order_id = o.id 
    WHERE om.sender_type = 'customer' AND om.is_read = 0
");
$stmt_total_unread->execute();
$total_unread = $stmt_total_unread->get_result()->fetch_assoc()['total_unread'];

// Determine current page
$current_page = isset($_GET['page']) ? $_GET['page'] : 'overview';
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['admin_dashboard'] ?? 'Admin Dashboard'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="/myrestaurant/style.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="/myrestaurant/mobile.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="/myrestaurant/css/admin_styles.css?v=123456">
    <style>
        /* Updated style for sidebar-unread-count to match unread-count */
        .sidebar-unread-count {
            display: inline-block;
            min-width: 20px;
            height: 20px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            font-size: 12px;
            margin-left: 5px;
        }
    </style>
</head>
<body class="admin-body <?php echo $theme; ?>">
    <!-- Language Bar with Flags -->
    <div class="language-bar">
        <a href="admin_dashboard.php?page=<?php echo $current_page; ?>&lang=en" class="<?php echo $_SESSION['lang'] == 'en' ? 'active-lang' : ''; ?>">
            <img src="images/flags/en.png" alt="English" class="flag-icon">
        </a>
        <a href="admin_dashboard.php?page=<?php echo $current_page; ?>&lang=fa" class="<?php echo $_SESSION['lang'] == 'fa' ? 'active-lang' : ''; ?>">
            <img src="images/flags/fa.png" alt="فارسی" class="flag-icon">
        </a>
        <a href="admin_dashboard.php?page=<?php echo $current_page; ?>&lang=fr" class="<?php echo $_SESSION['lang'] == 'fr' ? 'active-lang' : ''; ?>">
            <img src="images/flags/fr.png" alt="Français" class="flag-icon">
        </a>
        <a href="admin_dashboard.php?page=<?php echo $current_page; ?>&lang=ar" class="<?php echo $_SESSION['lang'] == 'ar' ? 'active-lang' : ''; ?>">
            <img src="images/flags/ar.png" alt="العربية" class="flag-icon">
        </a>
    </div>

    <header class="admin-header">
        <h1><?php echo $lang['admin_dashboard'] ?? 'Admin Dashboard'; ?></h1>
        <div class="controls">
            <a href="admin_dashboard.php?page=<?php echo $current_page; ?>&theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </header>

    <aside class="admin-sidebar">
        <ul>
            <li>
                <a href="admin_dashboard.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="menu-text"><?php echo $lang['overview'] ?? 'Overview'; ?></span>
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?page=foods" class="<?php echo $current_page === 'foods' ? 'active' : ''; ?>">
                    <i class="fas fa-utensils"></i>
                    <span class="menu-text"><?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?></span>
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?page=categories" class="<?php echo $current_page === 'categories' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span class="menu-text"><?php echo $lang['manage_categories'] ?? 'Manage Categories'; ?></span>
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?page=orders" class="<?php echo $current_page === 'orders' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="menu-text"><?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?></span>
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?page=hero_texts" class="<?php echo $current_page === 'hero_texts' ? 'active' : ''; ?>">
                    <i class="fas fa-heading"></i>
                    <span class="menu-text"><?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></span>
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?page=messages" class="<?php echo $current_page === 'messages' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i>
                    <span class="menu-text"><?php echo $lang['manage_messages'] ?? 'Manage Messages'; ?></span>
                    <?php if ($total_unread > 0): ?>
                        <span class="sidebar-unread-count"><?php echo $total_unread; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </aside>

    <main class="admin-content">