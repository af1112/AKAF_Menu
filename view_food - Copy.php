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

// Fetch food details
$food_id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM foods WHERE id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$food) {
    header("Location: manage_foods.php");
    exit();
}

// Fetch category name
$category_id = $food['category_id'] ?? 0;
$category = $conn->query("SELECT name_" . $_SESSION['lang'] . " AS name FROM categories WHERE id = $category_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['view_food'] ?? 'View Food'; ?> - <?php echo htmlspecialchars($food['name_' . $_SESSION['lang']]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['view_food'] ?? 'View Food'; ?> - <?php echo htmlspecialchars($food['name_' . $_SESSION['lang']]); ?></h1>
        <div class="controls">
            <select onchange="window.location='view_food.php?id=<?php echo $food_id; ?>&lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="view_food.php?id=<?php echo $food_id; ?>&theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
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
                <a href="manage_foods.php" class="active">
                    <i class="fas fa-utensils"></i> <?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?>
                </a>
            </li>
            <li>
                <a href="manage_categories.php">
                    <i class="fas fa-list"></i> <?php echo $lang['manage_categories'] ?? 'Manage Categories'; ?>
                </a>
            </li>
            <li>
                <a href="manage_orders.php">
                    <i class="fas fa-shopping-cart"></i> <?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?>
                </a>
            </li>
            <li>
                <a href="manage_hero_texts.php">
                    <i class="fas fa-heading"></i> <?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?>
                </a>
            </li>
        </ul>
    </aside>

    <main class="admin-content">
        <div class="admin-section">
            <h3><?php echo $lang['food_details'] ?? 'Food Details'; ?></h3>
            <p><strong><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</strong> <?php echo htmlspecialchars($food['name_en']); ?></p>
            <p><strong><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</strong> <?php echo htmlspecialchars($food['name_fa']); ?></p>
            <p><strong><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</strong> <?php echo htmlspecialchars($food['name_fr']); ?></p>
            <p><strong><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</strong> <?php echo htmlspecialchars($food['name_ar']); ?></p>
            <p><strong><?php echo $lang['category'] ?? 'Category'; ?>:</strong> <?php echo htmlspecialchars($category['name'] ?? ($lang['no_category'] ?? 'No Category')); ?></p>
            <p><strong><?php echo $lang['price'] ?? 'Price'; ?>:</strong> <?php echo number_format($food['price'], 2); ?> <?php echo $lang['currency'] ?? '$'; ?></p>
            <p><strong><?php echo $lang['is_available'] ?? 'Available'; ?>:</strong> <?php echo $food['is_available'] ? ($lang['yes'] ?? 'Yes') : ($lang['no'] ?? 'No'); ?></p>
            <p><strong><?php echo $lang['prep_time'] ?? 'Preparation Time (minutes)'; ?>:</strong> <?php echo isset($food['prep_time']) && $food['prep_time'] !== '' ? $food['prep_time'] : 'N/A'; ?></p>
            <p><strong><?php echo $lang['ingredients'] ?? 'Ingredients'; ?>:</strong> <?php echo isset($food['ingredients']) && $food['ingredients'] !== '' ? htmlspecialchars($food['ingredients']) : 'N/A'; ?></p>
            <p><strong><?php echo $lang['main_image'] ?? 'Main Image'; ?>:</strong>
                <?php if ($food['main_image']): ?>
                    <img src="<?php echo htmlspecialchars($food['main_image']); ?>" alt="Main Image" style="max-width: 200px;">
                <?php else: ?>
                    <?php echo $lang['no_image'] ?? 'No Image'; ?>
                <?php endif; ?>
            </p>
            <p><strong><?php echo $lang['gallery_images'] ?? 'Gallery Images'; ?>:</strong>
                <?php
                $gallery_images = isset($food['gallery_images']) && $food['gallery_images'] !== '' && $food['gallery_images'] !== null ? json_decode($food['gallery_images'], true) : [];
                if ($gallery_images && is_array($gallery_images)):
                    foreach ($gallery_images as $image): ?>
                        <img src="<?php echo htmlspecialchars($image); ?>" alt="Gallery Image" style="max-width: 100px; margin-right: 10px;">
                    <?php endforeach;
                else: ?>
                    <?php echo $lang['no_images'] ?? 'No Images'; ?>
                <?php endif; ?>
            </p>
            <a href="edit_food.php?id=<?php echo $food['id']; ?>" class="button">
                <i class="fas fa-edit"></i> <?php echo $lang['edit'] ?? 'Edit'; ?>
            </a>
            <a href="manage_foods.php" class="button">
                <i class="fas fa-arrow-left"></i> <?php echo $lang['back'] ?? 'Back'; ?>
            </a>
        </div>
    </main>
</body>
</html>