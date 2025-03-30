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

// Handle form submission for hero texts
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_hero_texts'])) {
    $title_en = $_POST['title_en'];
    $title_fa = $_POST['title_fa'];
    $title_ar = $_POST['title_ar'];
    $title_fr = $_POST['title_fr'];
    $description_en = $_POST['description_en'];
    $description_fa = $_POST['description_fa'];
    $description_ar = $_POST['description_ar'];
    $description_fr = $_POST['description_fr'];

    $stmt = $conn->prepare("INSERT INTO hero_texts (id, title_en, title_fa, title_ar, title_fr, description_en, description_fa, description_ar, description_fr) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title_en = ?, title_fa = ?, title_ar = ?, title_fr = ?, description_en = ?, description_fa = ?, description_ar = ?, description_fr = ?");
    $stmt->bind_param("ssssssssssssssss", $title_en, $title_fa, $title_ar, $title_fr, $description_en, $description_fa, $description_ar, $description_fr, $title_en, $title_fa, $title_ar, $title_fr, $description_en, $description_fa, $description_ar, $description_fr);
    $stmt->execute();
    $stmt->close();

    $success_message = $lang['hero_texts_updated'] ?? "Hero texts updated successfully!";
}

// Fetch current hero texts
$hero_texts = $conn->query("SELECT * FROM hero_texts LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></h1>
        <div class="controls">
            <select onchange="window.location='manage_hero_texts.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="manage_hero_texts.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
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
                <a href="manage_hero_texts.php" class="active">
                    <i class="fas fa-heading"></i> <?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?>
                </a>
            </li>
        </ul>
    </aside>

    <main class="admin-content">
        <div class="admin-section">
            <h3><?php echo $lang['update_hero_texts'] ?? 'Update Hero Texts'; ?></h3>
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="update_hero_texts" value="1">
                <label for="title_en"><?php echo $lang['title_en'] ?? 'Title (English)'; ?>:</label>
                <input type="text" id="title_en" name="title_en" value="<?php echo htmlspecialchars($hero_texts['title_en'] ?? ''); ?>" required>

                <label for="title_fa"><?php echo $lang['title_fa'] ?? 'Title (Persian)'; ?>:</label>
                <input type="text" id="title_fa" name="title_fa" value="<?php echo htmlspecialchars($hero_texts['title_fa'] ?? ''); ?>" required>

                <label for="title_ar"><?php echo $lang['title_ar'] ?? 'Title (Arabic)'; ?>:</label>
                <input type="text" id="title_ar" name="title_ar" value="<?php echo htmlspecialchars($hero_texts['title_ar'] ?? ''); ?>" required>

                <label for="title_fr"><?php echo $lang['title_fr'] ?? 'Title (French)'; ?>:</label>
                <input type="text" id="title_fr" name="title_fr" value="<?php echo htmlspecialchars($hero_texts['title_fr'] ?? ''); ?>" required>

                <label for="description_en"><?php echo $lang['description_en'] ?? 'Description (English)'; ?>:</label>
                <textarea id="description_en" name="description_en" required><?php echo htmlspecialchars($hero_texts['description_en'] ?? ''); ?></textarea>

                <label for="description_fa"><?php echo $lang['description_fa'] ?? 'Description (Persian)'; ?>:</label>
                <textarea id="description_fa" name="description_fa" required><?php echo htmlspecialchars($hero_texts['description_fa'] ?? ''); ?></textarea>

                <label for="description_ar"><?php echo $lang['description_ar'] ?? 'Description (Arabic)'; ?>:</label>
                <textarea id="description_ar" name="description_ar" required><?php echo htmlspecialchars($hero_texts['description_ar'] ?? ''); ?></textarea>

                <label for="description_fr"><?php echo $lang['description_fr'] ?? 'Description (French)'; ?>:</label>
                <textarea id="description_fr" name="description_fr" required><?php echo htmlspecialchars($hero_texts['description_fr'] ?? ''); ?></textarea>

                <button type="submit"><?php echo $lang['update'] ?? 'Update'; ?></button>
            </form>
        </div>
    </main>
</body>
</html>