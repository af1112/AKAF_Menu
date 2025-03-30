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

// Fetch categories
$categories = [];
$category_result = $conn->query("SELECT id, name_" . $_SESSION['lang'] . " AS name FROM categories");
while ($cat = $category_result->fetch_assoc()) {
    $categories[$cat['id']] = $cat['name'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
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
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
    $prep_time = isset($_POST['prep_time']) ? (int)$_POST['prep_time'] : 0;
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Input validation
    $errors = [];
    if (empty($name_en) || empty($name_fa) || empty($name_fr) || empty($name_ar)) {
        $errors[] = $lang['name_required'] ?? "All name fields are required.";
    }
    if ($category_id <= 0 || !isset($categories[$category_id])) {
        $errors[] = $lang['invalid_category'] ?? "Please select a valid category.";
    }
    if ($price <= 0) {
        $errors[] = $lang['invalid_price'] ?? "Price must be a positive number.";
    }
    if ($prep_time < 0) {
        $errors[] = $lang['invalid_prep_time'] ?? "Preparation time cannot be negative.";
    }

    // Handle image upload
    $main_image = '';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
        if (!in_array($_FILES['main_image']['type'], $allowed_types)) {
            $errors[] = $lang['invalid_image_type'] ?? "Main image must be a JPEG, PNG, or GIF.";
        } else {
            $main_image = 'images/' . time() . '_' . basename($_FILES['main_image']['name']);
            if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $main_image)) {
                $errors[] = $lang['upload_failed'] ?? "Failed to upload main image.";
            }
        }
    }

    if (empty($errors)) {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO foods (name_en, name_fa, name_fr, name_ar, description_en, description_fa, description_fr, description_ar, ingredients_en, ingredients_fa, ingredients_fr, ingredients_ar, category_id, price, prep_time, is_available, main_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssssidiii", $name_en, $name_fa, $name_fr, $name_ar, $description_en, $description_fa, $description_fr, $description_ar, $ingredients_en, $ingredients_fa, $ingredients_fr, $ingredients_ar, $category_id, $price, $prep_time, $is_available, $main_image);
        
        if ($stmt->execute()) {
            // Handle gallery images
            if (isset($_FILES['gallery_images'])) {
                $upload_dir = 'images/';
                $food_id = $stmt->insert_id; // Get the ID of the newly inserted food
                foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                        if (!in_array($_FILES['gallery_images']['type'][$key], $allowed_types)) {
                            $errors[] = $lang['invalid_image_type'] ?? "Gallery images must be JPEG, PNG, or GIF.";
                            break;
                        }
                        $image_path = $upload_dir . time() . '_' . basename($_FILES['gallery_images']['name'][$key]);
                        if (move_uploaded_file($tmp_name, $image_path)) {
                            $gallery_stmt = $conn->prepare("INSERT INTO food_images (food_id, image_path) VALUES (?, ?)");
                            $gallery_stmt->bind_param("is", $food_id, $image_path);
                            $gallery_stmt->execute();
                            $gallery_stmt->close();
                        } else {
                            $errors[] = $lang['upload_failed'] ?? "Failed to upload gallery image.";
                            break;
                        }
                    }
                }
            }

            if (empty($errors)) {
                header("Location: manage_foods.php?success=" . urlencode($lang['food_added'] ?? "Food added successfully."));
                exit();
            }
        } else {
            $errors[] = $lang['db_error'] ?? "Database error: " . $stmt->error;
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['add_food'] ?? 'Add Food'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="<?php echo $theme; ?>">
    <div class="header" style="background-color: #2c3e50;">
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
    </div>

    <div class="container">
        <div class="add-food">
            <h3><?php echo $lang['add_food'] ?? 'Add Food'; ?></h3>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <form action="add_food.php" method="POST" enctype="multipart/form-data">
                <label for="name_en"><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</label>
                <input type="text" name="name_en" id="name_en" required onblur="translateFields('name_en', ['name_fa', 'name_fr', 'name_ar'])">

                <label for="name_fa"><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</label>
                <input type="text" name="name_fa" id="name_fa" required>

                <label for="name_fr"><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</label>
                <input type="text" name="name_fr" id="name_fr" required>

                <label for="name_ar"><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</label>
                <input type="text" name="name_ar" id="name_ar" required>

                <label for="description_en"><?php echo $lang['description_en'] ?? 'Description (English)'; ?>:</label>
                <textarea name="description_en" id="description_en" onblur="translateFields('description_en', ['description_fa', 'description_fr', 'description_ar'])"></textarea>

                <label for="description_fa"><?php echo $lang['description_fa'] ?? 'Description (Persian)'; ?>:</label>
                <textarea name="description_fa" id="description_fa"></textarea>

                <label for="description_fr"><?php echo $lang['description_fr'] ?? 'Description (French)'; ?>:</label>
                <textarea name="description_fr" id="description_fr"></textarea>

                <label for="description_ar"><?php echo $lang['description_ar'] ?? 'Description (Arabic)'; ?>:</label>
                <textarea name="description_ar" id="description_ar"></textarea>

                <label for="ingredients_en"><?php echo $lang['ingredients_en'] ?? 'Ingredients (English)'; ?>:</label>
                <textarea name="ingredients_en" id="ingredients_en" onblur="translateFields('ingredients_en', ['ingredients_fa', 'ingredients_fr', 'ingredients_ar'])"></textarea>

                <label for="ingredients_fa"><?php echo $lang['ingredients_fa'] ?? 'Ingredients (Persian)'; ?>:</label>
                <textarea name="ingredients_fa" id="ingredients_fa"></textarea>

                <label for="ingredients_fr"><?php echo $lang['ingredients_fr'] ?? 'Ingredients (French)'; ?>:</label>
                <textarea name="ingredients_fr" id="ingredients_fr"></textarea>

                <label for="ingredients_ar"><?php echo $lang['ingredients_ar'] ?? 'Ingredients (Arabic)'; ?>:</label>
                <textarea name="ingredients_ar" id="ingredients_ar"></textarea>

                <label for="category_id"><?php echo $lang['category'] ?? 'Category'; ?>:</label>
                <select name="category_id" id="category_id" required>
                    <option value=""><?php echo $lang['select_category'] ?? 'Select Category'; ?></option>
                    <?php foreach ($categories as $id => $name): ?>
                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="price"><?php echo $lang['price'] ?? 'Price'; ?>:</label>
                <input type="number" name="price" id="price" step="0.01" required>

                <label for="prep_time"><?php echo $lang['prep_time'] ?? 'Preparation Time (minutes)'; ?>:</label>
                <input type="number" name="prep_time" id="prep_time">

                <label for="is_available"><?php echo $lang['is_available'] ?? 'Available'; ?>:</label>
                <input type="checkbox" name="is_available" id="is_available" checked>

                <label for="main_image"><?php echo $lang['main_image'] ?? 'Main Image'; ?>:</label>
                <input type="file" name="main_image" id="main_image">

                <label for="gallery_images"><?php echo $lang['gallery_images'] ?? 'Gallery Images'; ?>:</label>
                <input type="file" name="gallery_images[]" id="gallery_images" multiple>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit"><?php echo $lang['add'] ?? 'Add'; ?></button>
                    <a href="manage_foods.php" class="button cancel-btn">
                        <i class="fas fa-times"></i> <?php echo $lang['cancel'] ?? 'Cancel'; ?>
                    </a>
                </div>
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