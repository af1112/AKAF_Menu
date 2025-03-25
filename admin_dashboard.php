<?php
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

// Fetch all categories and store them in an array for quick lookup
$categories = [];
$category_result = $conn->query("SELECT id, name_" . $_SESSION['lang'] . " AS name FROM categories");
while ($cat = $category_result->fetch_assoc()) {
    $categories[$cat['id']] = $cat['name'];
}

// Fetch all foods
$foods = $conn->query("SELECT * FROM foods");
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['admin_dashboard'] ?? 'Admin Dashboard'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?php echo $theme; ?>">
    <div class="header">
        <h1><?php echo $lang['admin_dashboard'] ?? 'Admin Dashboard'; ?></h1>
        <div class="controls">
            <select onchange="window.location='admin_dashboard.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="admin_dashboard.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Manage Foods -->
        <div class="manage-foods">
            <h3><?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?></h3>
            <table class="foods-table">
                <thead>
                    <tr>
                        <th><?php echo $lang['name'] ?? 'Name'; ?></th>
                        <th><?php echo $lang['category'] ?? 'Category'; ?></th>
                        <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                        <th><?php echo $lang['is_available'] ?? 'Available'; ?></th>
                        <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($food = $foods->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($food['name_' . $_SESSION['lang']]); ?></td>
                            <td>
                                <?php
                                $category_id = $food['category_id'] ?? 0;
                                echo htmlspecialchars($categories[$category_id] ?? 'دسته‌بندی مشخص نشده');
                                ?>
                            </td>
                            <td>$<?php echo number_format($food['price'], 2); ?></td>
                            <td><?php echo $food['is_available'] ? ($lang['yes'] ?? 'Yes') : ($lang['no'] ?? 'No'); ?></td>
                            <td>
                                <a href="edit_food.php?id=<?php echo $food['id']; ?>" class="button">
                                    <i class="fas fa-edit"></i> <?php echo $lang['edit'] ?? 'Edit'; ?>
                                </a>
                                <form action="delete_food.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $food['id']; ?>">
                                    <button type="submit" class="delete-btn" onclick="return confirm('<?php echo $lang['confirm_delete'] ?? 'Are you sure you want to delete this food?'; ?>')">
                                        <i class="fas fa-trash"></i> <?php echo $lang['delete'] ?? 'Delete'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Food -->
        <div class="add-food">
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
                <input type="text" name="category" required>

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
    </div>
</body>
</html>