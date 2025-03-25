<?php
header("Cache-Control: no-cache, must-revalidate"); // غیرفعال کردن کش
session_start();
include 'db.php';

// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];

// Load language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'fa'; // زبان پیش‌فرض رو فارسی می‌کنیم
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
// Force language to Persian for testing
$_SESSION['lang'] = 'fa';
include "languages/" . $_SESSION['lang'] . ".php";

// Detect language direction
$rtl_languages = ['fa', 'ar'];
$is_rtl = in_array($_SESSION['lang'], $rtl_languages);
$direction = $is_rtl ? 'rtl' : 'ltr';

// Fetch categories
$categories = $conn->query("SELECT * FROM categories");
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <title><?php echo $lang['welcome'] ?? 'Welcome'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>"> <!-- اضافه کردن نسخه برای جلوگیری از کش -->
</head>
<body class="<?php echo $theme; ?>">
    <div class="header">
        <h1><?php echo $lang['welcome'] ?? 'Welcome to My Restaurant'; ?></h1>
        <div class="controls">
            <select onchange="window.location='index.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="index.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i> <?php echo $lang['cart'] ?? 'Cart'; ?></a>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="profile.php"><i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?></a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?></a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero-section">
        <div class="hero-content">
            <h2><?php echo $lang['welcome_message'] ?? 'Welcome to Our Restaurant!'; ?></h2>
            <p><?php echo $lang['welcome_description'] ?? 'Discover our delicious menu and enjoy a great dining experience.'; ?></p>
        </div>
    </div>

    <div class="container">
        <div class="categories-section">
            <h2><?php echo $lang['categories'] ?? 'Categories'; ?></h2>
            <div class="category-cards">
                <?php while ($category = $categories->fetch_assoc()): ?>
                    <a href="menu.php?category_id=<?php echo $category['id']; ?>" class="category-card">
                        <?php if ($category['image']): ?>
                            <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?>">
                        <?php else: ?>
                            <img src="images/default_category.jpg" alt="<?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?>">
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?></h3>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
	<footer class="footer">
		<p>© 2025 رستوران من - تمامی حقوق محفوظ است.</p>
		<p>تماس با ما: <a href="mailto:info@myrestaurant.com">info@myrestaurant.com</a></p>
	</footer>
</body>
</html>