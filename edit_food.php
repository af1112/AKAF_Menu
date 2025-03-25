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

// Get food ID
$food_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch food details
$stmt = $conn->prepare("SELECT * FROM foods WHERE id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc();

if (!$food) {
    echo "Food not found.";
    exit();
}

// Fetch gallery images
$gallery_stmt = $conn->prepare("SELECT * FROM food_images WHERE food_id = ?");
$gallery_stmt->bind_param("i", $food_id);
$gallery_stmt->execute();
$gallery_images = $gallery_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch categories
$categories = $conn->query("SELECT * FROM categories");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_en = $_POST['name_en'] ?? '';
    $name_fa = $_POST['name_fa'] ?? '';
    $name_fr = $_POST['name_fr'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';
    $description_en = $_POST['description_en'] ?? '';
    $description_fa = $_POST['description_fa'] ?? '';
    $description_fr = $_POST['description_fr'] ?? '';
    $description_ar = $_POST['description_ar'] ?? '';
    $ingredients_en = $_POST['ingredients_en'] ?? '';
    $ingredients_fa = $_POST['ingredients_fa'] ?? '';
    $ingredients_fr = $_POST['ingredients_fr'] ?? '';
    $ingredients_ar = $_POST['ingredients_ar'] ?? '';
    $category_id = $_POST['category_id'] ?? 0;
    $price = $_POST['price'] ?? 0;
    $prep_time = $_POST['prep_time'] ?? 0;
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Handle main image upload
    $main_image = $food['main_image'];
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'images/';
        $main_image = $upload_dir . basename($_FILES['main_image']['name']);
        move_uploaded_file($_FILES['main_image']['tmp_name'], $main_image);
    }

    // Update food details
    $stmt = $conn->prepare("UPDATE foods SET name_en = ?, name_fa = ?, name_fr = ?, name_ar = ?, description_en = ?, description_fa = ?, description_fr = ?, description_ar = ?, ingredients_en = ?, ingredients_fa = ?, ingredients_fr = ?, ingredients_ar = ?, category_id = ?, price = ?, prep_time = ?, is_available = ?, main_image = ? WHERE id = ?");
    $stmt->bind_param("ssssssssssssidissi", $name_en, $name_fa, $name_fr, $name_ar, $description_en, $description_fa, $description_fr, $description_ar, $ingredients_en, $ingredients_fa, $ingredients_fr, $ingredients_ar, $category_id, $price, $prep_time, $is_available, $main_image, $food_id);
    if ($stmt->execute()) {
        // Handle gallery images upload
        if (isset($_FILES['gallery_images'])) {
            $upload_dir = 'images/';
            foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $image_path = $upload_dir . basename($_FILES['gallery_images']['name'][$key]);
                    move_uploaded_file($tmp_name, $image_path);
                    $stmt = $conn->prepare("INSERT INTO food_images (food_id, image_path) VALUES (?, ?)");
                    $stmt->bind_param("is", $food_id, $image_path);
                    $stmt->execute();
                }
            }
        }
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $error = $lang['update_failed'] ?? "Failed to update food.";
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['edit_food'] ?? 'Edit Food'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="<?php echo $theme; ?>">
    <div class="header">
        <h1><?php echo $lang['edit_food'] ?? 'Edit Food'; ?></h1>
        <div class="controls">
            <select onchange="window.location='edit_food.php?id=<?php echo $food_id; ?>&lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="edit_food.php?id=<?php echo $food_id; ?>&theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="admin_dashboard.php">
                <i class="fas fa-arrow-left"></i> <?php echo $lang['back'] ?? 'Back'; ?>
            </a>
        </div>
    </div>

    <div class="container">
        <div class="add-food">
            <h3><?php echo $lang['edit_food'] ?? 'Edit Food'; ?></h3>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <form action="edit_food.php?id=<?php echo $food_id; ?>" method="POST" enctype="multipart/form-data">
                <label for="name_en"><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</label>
                <input type="text" name="name_en" id="name_en" value="<?php echo htmlspecialchars($food['name_en'] ?? ''); ?>" required onblur="translateFields('name_en', ['name_fa', 'name_fr', 'name_ar'])">

                <label for="name_fa"><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</label>
                <input type="text" name="name_fa" id="name_fa" value="<?php echo htmlspecialchars($food['name_fa'] ?? ''); ?>" required>

                <label for="name_fr"><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</label>
                <input type="text" name="name_fr" id="name_fr" value="<?php echo htmlspecialchars($food['name_fr'] ?? ''); ?>" required>

                <label for="name_ar"><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</label>
                <input type="text" name="name_ar" id="name_ar" value="<?php echo htmlspecialchars($food['name_ar'] ?? ''); ?>" required>

                <label for="description_en"><?php echo $lang['description_en'] ?? 'Description (English)'; ?>:</label>
                <textarea name="description_en" id="description_en" onblur="translateFields('description_en', ['description_fa', 'description_fr', 'description_ar'])"><?php echo htmlspecialchars($food['description_en'] ?? ''); ?></textarea>

                <label for="description_fa"><?php echo $lang['description_fa'] ?? 'Description (Persian)'; ?>:</label>
                <textarea name="description_fa" id="description_fa"><?php echo htmlspecialchars($food['description_fa'] ?? ''); ?></textarea>

                <label for="description_fr"><?php echo $lang['description_fr'] ?? 'Description (French)'; ?>:</label>
                <textarea name="description_fr" id="description_fr"><?php echo htmlspecialchars($food['description_fr'] ?? ''); ?></textarea>

                <label for="description_ar"><?php echo $lang['description_ar'] ?? 'Description (Arabic)'; ?>:</label>
                <textarea name="description_ar" id="description_ar"><?php echo htmlspecialchars($food['description_ar'] ?? ''); ?></textarea>

                <label for="ingredients_en"><?php echo $lang['ingredients_en'] ?? 'Ingredients (English)'; ?>:</label>
                <textarea name="ingredients_en" id="ingredients_en" onblur="translateFields('ingredients_en', ['ingredients_fa', 'ingredients_fr', 'ingredients_ar'])"><?php echo htmlspecialchars($food['ingredients_en'] ?? ''); ?></textarea>

                <label for="ingredients_fa"><?php echo $lang['ingredients_fa'] ?? 'Ingredients (Persian)'; ?>:</label>
                <textarea name="ingredients_fa" id="ingredients_fa"><?php echo htmlspecialchars($food['ingredients_fa'] ?? ''); ?></textarea>

                <label for="ingredients_fr"><?php echo $lang['ingredients_fr'] ?? 'Ingredients (French)'; ?>:</label>
                <textarea name="ingredients_fr" id="ingredients_fr"><?php echo htmlspecialchars($food['ingredients_fr'] ?? ''); ?></textarea>

                <label for="ingredients_ar"><?php echo $lang['ingredients_ar'] ?? 'Ingredients (Arabic)'; ?>:</label>
                <textarea name="ingredients_ar" id="ingredients_ar"><?php echo htmlspecialchars($food['ingredients_ar'] ?? ''); ?></textarea>

                <label for="category_id"><?php echo $lang['category'] ?? 'Category'; ?>:</label>
                <select name="category_id" required>
                    <option value=""><?php echo $lang['select_category'] ?? 'Select Category'; ?></option>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo ($food['category_id'] ?? 0) == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label for="price"><?php echo $lang['price'] ?? 'Price'; ?>:</label>
                <input type="number" name="price" step="0.01" value="<?php echo htmlspecialchars($food['price'] ?? 0); ?>" required>

                <label for="prep_time"><?php echo $lang['prep_time'] ?? 'Preparation Time (minutes)'; ?>:</label>
                <input type="number" name="prep_time" value="<?php echo htmlspecialchars($food['prep_time'] ?? 0); ?>">

                <label for="is_available"><?php echo $lang['is_available'] ?? 'Available'; ?>:</label>
                <input type="checkbox" name="is_available" <?php echo ($food['is_available'] ?? 1) ? 'checked' : ''; ?>>

                <label for="main_image"><?php echo $lang['main_image'] ?? 'Main Image'; ?>:</label>
                <input type="file" name="main_image">
                <?php if ($food['main_image']): ?>
                    <img src="<?php echo htmlspecialchars($food['main_image']); ?>" alt="Main Image" style="max-width: 200px; margin-top: 10px;">
                <?php endif; ?>

                <label for="gallery_images"><?php echo $lang['gallery_images'] ?? 'Gallery Images'; ?>:</label>
                <input type="file" name="gallery_images[]" multiple>
                <div class="gallery-images">
                    <?php foreach ($gallery_images as $image): ?>
                        <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Gallery Image" style="max-width: 100px; margin: 5px;">
                    <?php endforeach; ?>
                </div>

                <button type="submit"><?php echo $lang['update'] ?? 'Update'; ?></button>
            </form>
        </div>
    </div>

    <script>
        async function translateFields(sourceFieldId, targetFieldIds) {
            const sourceText = document.getElementById(sourceFieldId).value;
            if (!sourceText) return;

            const sourceLang = 'en'; // Assuming the source is always English
            const targetLangs = {
                'name_fa': 'fa',
                'name_fr': 'fr',
                'name_ar': 'ar',
                'description_fa': 'fa',
                'description_fr': 'fr',
                'description_ar': 'ar',
                'ingredients_fa': 'fa',
                'ingredients_fr': 'fr',
                'ingredients_ar': 'ar'
            };

            for (const targetFieldId of targetFieldIds) {
                const targetLang = targetLangs[targetFieldId];
                if (!targetLang) continue;

                try {
                    const response = await axios.post('https://api.mymemory.translated.net/get', null, {
                        params: {
                            q: sourceText,
                            langpair: `${sourceLang}|${targetLang}`
                        }
                    });
                    const translatedText = response.data.responseData.translatedText;
                    const targetField = document.getElementById(targetFieldId);
                    if (targetField.value === '') {
                        targetField.value = translatedText;
                    }
                } catch (error) {
                    console.error(`Translation failed for ${targetFieldId}:`, error);
                }
            }
        }
    </script>
</body>
</html>