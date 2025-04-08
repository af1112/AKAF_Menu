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
$food_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($food_id <= 0) {
    header("Location: manage_foods.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM foods WHERE id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$food) {
    header("Location: manage_foods.php");
    exit();
}

// Fetch gallery images from food_images table
$gallery_images = [];
$gallery_stmt = $conn->prepare("SELECT * FROM food_images WHERE food_id = ?");
$gallery_stmt->bind_param("i", $food_id);
$gallery_stmt->execute();
$gallery_images = $gallery_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$gallery_stmt->close();

// Fetch category name
$category_id = $food['category_id'] ?? 0;
$stmt = $conn->prepare("SELECT name_" . $_SESSION['lang'] . " AS name FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['view_food'] ?? 'View Food'; ?> - <?php echo htmlspecialchars($food['name_' . $_SESSION['lang']]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-section {
            padding: 20px;
        }
        .admin-section h3 {
            margin-bottom: 20px;
            font-size: 24px;
            color: <?php echo $theme === 'light' ? '#2c3e50' : '#ddd'; ?>;
        }
        .admin-section p {
            margin: 10px 0;
            font-size: 16px;
            line-height: 1.6;
        }
        .admin-section p strong {
            display: inline-block;
            width: 200px;
            font-weight: bold;
            color: <?php echo $theme === 'light' ? '#555' : '#bbb'; ?>;
        }
        .gallery-images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .gallery-image img, .main-image img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 5px;
            transition: transform 0.3s ease;
        }
        .gallery-image img:hover, .main-image img:hover {
            transform: scale(1.05);
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
        }
        .close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #bbb;
        }
    </style>
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
            <!-- Name in all languages -->
            <p><strong><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</strong> <?php echo htmlspecialchars($food['name_en'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>
            <p><strong><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</strong> <?php echo htmlspecialchars($food['name_fa'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>
            <p><strong><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</strong> <?php echo htmlspecialchars($food['name_fr'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>
            <p><strong><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</strong> <?php echo htmlspecialchars($food['name_ar'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>

            <!-- Description in all languages -->
            <p><strong><?php echo $lang['description_en'] ?? 'Description (English)'; ?>:</strong> <?php echo htmlspecialchars($food['description_en'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>
            <p><strong><?php echo $lang['description_fa'] ?? 'Description (Persian)'; ?>:</strong> <?php echo htmlspecialchars($food['description_fa'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>
            <p><strong><?php echo $lang['description_fr'] ?? 'Description (French)'; ?>:</strong> <?php echo htmlspecialchars($food['description_fr'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>
            <p><strong><?php echo $lang['description_ar'] ?? 'Description (Arabic)'; ?>:</strong> <?php echo htmlspecialchars($food['description_ar'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>

            <!-- Ingredients in all languages -->
            <p><strong><?php echo $lang['ingredients_en'] ?? 'Ingredients (English)'; ?>:</strong> <?php echo htmlspecialchars($food['ingredients_en'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>
            <p><strong><?php echo $lang['ingredients_fa'] ?? 'Ingredients (Persian)'; ?>:</strong> <?php echo htmlspecialchars($food['ingredients_fa'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>
            <p><strong><?php echo $lang['ingredients_fr'] ?? 'Ingredients (French)'; ?>:</strong> <?php echo htmlspecialchars($food['ingredients_fr'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>
            <p><strong><?php echo $lang['ingredients_ar'] ?? 'Ingredients (Arabic)'; ?>:</strong> <?php echo htmlspecialchars($food['ingredients_ar'] ?: ($lang['not_available'] ?? 'N/A')); ?></p>

            <!-- Other details -->
            <p><strong><?php echo $lang['category'] ?? 'Category'; ?>:</strong> <?php echo htmlspecialchars($category['name'] ?? ($lang['no_category'] ?? 'No Category')); ?></p>
            <p><strong><?php echo $lang['price'] ?? 'Price'; ?>:</strong> <?php echo number_format($food['price'], 2); ?> <?php echo $lang['currency'] ?? '$'; ?></p>
            <p><strong><?php echo $lang['prep_time'] ?? 'Preparation Time'; ?>:</strong> <?php echo $food['prep_time'] ? ($food['prep_time'] . ' ' . ($lang['minutes'] ?? 'minutes')) : ($lang['not_available'] ?? 'N/A'); ?></p>
            <p><strong><?php echo $lang['is_available'] ?? 'Available'; ?>:</strong> <?php echo $food['is_available'] ? ($lang['yes'] ?? 'Yes') : ($lang['no'] ?? 'No'); ?></p>

            <!-- Main Image -->
            <p><strong><?php echo $lang['main_image'] ?? 'Main Image'; ?>:</strong>
                <?php if ($food['main_image']): ?>
                    <span class="main-image">
                        <img src="<?php echo htmlspecialchars($food['main_image']); ?>" alt="Main Image" onclick="openModal('<?php echo htmlspecialchars($food['main_image']); ?>')">
                    </span>
                <?php else: ?>
                    <?php echo $lang['no_image'] ?? 'No Image'; ?>
                <?php endif; ?>
            </p>

            <!-- Gallery Images -->
            <p><strong><?php echo $lang['gallery_images'] ?? 'Gallery Images'; ?>:</strong>
                <?php if (!empty($gallery_images)): ?>
                    <div class="gallery-images">
                        <?php foreach ($gallery_images as $image): ?>
                            <div class="gallery-image">
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Gallery Image" onclick="openModal('<?php echo htmlspecialchars($image['image_path']); ?>')">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php echo $lang['no_images'] ?? 'No Images'; ?>
                <?php endif; ?>
            </p>

            <!-- Action Buttons -->
            <a href="edit_food.php?id=<?php echo $food['id']; ?>" class="button">
                <i class="fas fa-edit"></i> <?php echo $lang['edit'] ?? 'Edit'; ?>
            </a>
            <a href="manage_foods.php" class="button">
                <i class="fas fa-arrow-left"></i> <?php echo $lang['back'] ?? 'Back'; ?>
            </a>
        </div>
    </main>

    <!-- Modal for Image Preview -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <script>
        function openModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modal.style.display = 'flex';
            modalImage.src = imageSrc;
        }

        function closeModal() {
            const modal = document.getElementById('imageModal');
            modal.style.display = 'none';
        }

        // Close modal when clicking outside the image
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>