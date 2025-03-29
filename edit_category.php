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

// Fetch category
$category_id = $_GET['id'] ?? 0;
$category = $conn->query("SELECT * FROM categories WHERE id = $category_id")->fetch_assoc();
if (!$category) {
    header("Location: manage_categories.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name_en = $_POST['name_en'] ?? '';
    $name_fa = $_POST['name_fa'] ?? '';
    $name_fr = $_POST['name_fr'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';

    // Handle image upload
    $image = $category['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image = 'images\Categories' . time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $image);
    }

    // Update category
    $stmt = $conn->prepare("UPDATE categories SET name_en = ?, name_fa = ?, name_fr = ?, name_ar = ?, image = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $name_en, $name_fa, $name_fr, $name_ar, $image, $category_id);
    $stmt->execute();
    $stmt->close();

	// Set success message
    $_SESSION['success_message'] = $lang['category_updated'] ?? 'Category updated successfully!';
	
    header("Location: manage_categories.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['edit_category'] ?? 'Edit Category'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['edit_category'] ?? 'Edit Category'; ?></h1>
        <div class="controls">
            <select onchange="window.location='edit_category.php?id=<?php echo $category_id; ?>&lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="edit_category.php?id=<?php echo $category_id; ?>&theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
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
            <h3><?php echo $lang['edit_category'] ?? 'Edit Category'; ?></h3>
            <form action="edit_category.php?id=<?php echo $category_id; ?>" method="POST" enctype="multipart/form-data">
                <label for="name_en"><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</label>
                <input type="text" name="name_en" id="name_en" value="<?php echo htmlspecialchars($category['name_en']); ?>" required oninput="translateFields('name_en', 'name')">

                <label for="name_fa"><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</label>
                <input type="text" name="name_fa" id="name_fa" value="<?php echo htmlspecialchars($category['name_fa']); ?>" required oninput="translateFields('name_fa', 'name')">

                <label for="name_fr"><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</label>
                <input type="text" name="name_fr" id="name_fr" value="<?php echo htmlspecialchars($category['name_fr']); ?>" required oninput="translateFields('name_fr', 'name')">

                <label for="name_ar"><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</label>
                <input type="text" name="name_ar" id="name_ar" value="<?php echo htmlspecialchars($category['name_ar']); ?>" required oninput="translateFields('name_ar', 'name')">

                <label for="image"><?php echo $lang['image'] ?? 'Image'; ?>:</label>
                <input type="file" name="image">
                <?php if ($category['image']): ?>
                    <p><?php echo $lang['current_image'] ?? 'Current Image'; ?>: <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="Category Image" style="max-width: 100px;"></p>
                <?php endif; ?>

                <button type="submit"><?php echo $lang['update'] ?? 'Update'; ?></button>
            </form>
        </div>
    </main>

    <script>
        async function translateFields(sourceId, fieldPrefix) {
            const sourceText = document.getElementById(sourceId).value;
            const sourceLang = sourceId.split('_')[1]; // e.g., 'en' from 'name_en'

            const targetFields = {
                'en': ['fa', 'fr', 'ar'],
                'fa': ['en', 'fr', 'ar'],
                'fr': ['en', 'fa', 'ar'],
                'ar': ['en', 'fa', 'fr']
            };

            for (let targetLang of targetFields[sourceLang]) {
                const targetId = `${fieldPrefix}_${targetLang}`;
                if (sourceText.trim() === '') {
                    document.getElementById(targetId).value = '';
                    continue;
                }

                try {
                    const response = await fetch('translate.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `text=${encodeURIComponent(sourceText)}&source=${sourceLang}&target=${targetLang}`
                    });
                    const result = await response.json();
                    if (result.translatedText) {
                        document.getElementById(targetId).value = result.translatedText;
                    }
                } catch (error) {
                    console.error('Translation error:', error);
                }
            }
        }
    </script>
</body>
</html>