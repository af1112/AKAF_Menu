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

// Fetch categories for dropdown
$categories = $conn->query("SELECT id, name_" . $_SESSION['lang'] . " AS name FROM categories");
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['add_food'] ?? 'Add Food'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['add_food'] ?? 'Add Food'; ?></h1>
        <div class="controls">
            <select onchange="window.location='add_food.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="add_food.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
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
                <a href="manage_foods.php">
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
            <h3><?php echo $lang['add_food'] ?? 'Add Food'; ?></h3>
            <form action="add_food.php" method="POST" enctype="multipart/form-data">
                <label for="name_en"><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</label>
                <input type="text" name="name_en" required>

                <label for="name_fa"><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</label>
                <input type="text" name="name_fa" required>

                <label for="name_fr"><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</label>
                <input type="text" name="name_fr" required>

                <label for="name_ar"><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</label>
                <input type="text" name="name_ar" required>

                <label for="desc_en"><?php echo $lang['description_en'] ?? 'Description (English)'; ?>:</label>
                <textarea name="desc_en"></textarea>

                <label for="desc_fa"><?php echo $lang['description_fa'] ?? 'Description (Persian)'; ?>:</label>
                <textarea name="desc_fa"></textarea>

                <label for="desc_fr"><?php echo $lang['description_fr'] ?? 'Description (French)'; ?>:</label>
                <textarea name="desc_fr"></textarea>

                <label for="desc_ar"><?php echo $lang['description_ar'] ?? 'Description (Arabic)'; ?>:</label>
                <textarea name="desc_ar"></textarea>

                <label for="category"><?php echo $lang['category'] ?? 'Category'; ?>:</label>
                <select name="category_id" required>
                    <option value=""><?php echo $lang['select_category'] ?? 'Select Category'; ?></option>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endwhile; ?>
                </select>

                <label for="price"><?php echo $lang['price'] ?? 'Price'; ?>:</label>
                <input type="number" name="price" step="0.01" required>

                <label for="prep_time"><?php echo $lang['prep_time'] ?? 'Preparation Time (minutes)'; ?>:</label>
                <input type="number" name="prep_time">

                <label for="is_available"><?php echo $lang['is_available'] ?? 'Available'; ?>:</label>
                <input type="checkbox" name="is_available" checked>

                <label for="ingredients"><?php echo $lang['ingredients'] ?? 'Ingredients'; ?>:</label>
                <textarea name="ingredients"></textarea>

                <label for="main_image"><?php echo $lang['main_image'] ?? 'Main Image'; ?>:</label>
                <input type="file" name="main_image">

                <label for="gallery_images"><?php echo $lang['gallery_images'] ?? 'Gallery Images'; ?>:</label>
                <input type="file" name="gallery_images[]" multiple>

                <button type="submit"><?php echo $lang['add'] ?? 'Add'; ?></button>
            </form>
        </div>
    </main>
</body>
</html>