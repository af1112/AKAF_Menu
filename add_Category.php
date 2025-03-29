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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name_en = $_POST['name_en'] ?? '';
    $name_fa = $_POST['name_fa'] ?? '';
    $name_fr = $_POST['name_fr'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';

    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image = 'uploads/categories/' . time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $image);
    }

    // Insert category
    $stmt = $conn->prepare("INSERT INTO categories (name_en, name_fa, name_fr, name_ar, image) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name_en, $name_fa, $name_fr, $name_ar, $image);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_categories.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['add_category'] ?? 'Add Category'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['add_category'] ?? 'Add Category'; ?></h1>
        <div class="controls">
            <select onchange="window.location='add_category.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="add_category.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
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
                <a href="manage_categories.php" class="active">
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
            <h3><?php echo $lang['add_category'] ?? 'Add Category'; ?></h3>
            <form action="add_category.php" method="POST" enctype="multipart/form-data">
                <label for="name_en"><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</label>
                <input type="text" name="name_en" id="name_en" required>

                <label for="name_fa"><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</label>
                <input type="text" name="name_fa" id="name_fa" required>

                <label for="name_fr"><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</label>
                <input type="text" name="name_fr" id="name_fr" required>

                <label for="name_ar"><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</label>
                <input type="text" name="name_ar" id="name_ar" required>

                <label for="image"><?php echo $lang['image'] ?? 'Image'; ?>:</label>
                <input type="file" name="image">

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit"><?php echo $lang['add'] ?? 'Add'; ?></button>
                    <a href="manage_categories.php" class="button cancel-btn">
                        <i class="fas fa-times"></i> <?php echo $lang['cancel'] ?? 'Cancel'; ?>
                    </a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Real-time translation for food names
        const nameInputs = {
            en: document.getElementById('name_en'),
            fa: document.getElementById('name_fa'),
            fr: document.getElementById('name_fr'),
            ar: document.getElementById('name_ar')
        };

        const editedFields = {
            en: false,
            fa: false,
            fr: false,
            ar: false
        };

        Object.keys(nameInputs).forEach(lang => {
            nameInputs[lang].addEventListener('input', function() {
                editedFields[lang] = true;
                const value = this.value;

                Object.keys(nameInputs).forEach(targetLang => {
                    if (!editedFields[targetLang] && targetLang !== lang) {
                        nameInputs[targetLang].value = value;
                    }
                });
            });
        });
    </script>

</body>
</html>