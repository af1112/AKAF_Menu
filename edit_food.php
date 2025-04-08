<?php
session_start();
include 'db.php';

// Enable error reporting for debugging (can be removed in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Debug: Check the value of $food_id
if ($food_id <= 0) {
    $error = $lang['invalid_food_id'] ?? "Invalid food ID.";
} else {
    $stmt = $conn->prepare("SELECT * FROM foods WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $food_id);
    $stmt->execute();
    $food = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$food) {
        $error = $lang['food_not_found'] ?? "Food item with ID $food_id not found.";
    }
}

// Fetch gallery images from food_images table
$gallery_images = [];
if (!isset($error)) {
    $gallery_stmt = $conn->prepare("SELECT * FROM food_images WHERE food_id = ?");
    $gallery_stmt->bind_param("i", $food_id);
    $gallery_stmt->execute();
    $gallery_images = $gallery_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $gallery_stmt->close();
}

// Handle gallery image deletion
if (isset($_GET['delete_image']) && !isset($error)) {
    $image_id = (int)$_GET['delete_image'];
    $stmt = $conn->prepare("SELECT image_path FROM food_images WHERE id = ? AND food_id = ?");
    $stmt->bind_param("ii", $image_id, $food_id);
    $stmt->execute();
    $image = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($image) {
        // Delete the image file from the server
        if (file_exists($image['image_path'])) {
            unlink($image['image_path']);
        }
        // Delete the image record from the database
        $stmt = $conn->prepare("DELETE FROM food_images WHERE id = ? AND food_id = ?");
        $stmt->bind_param("ii", $image_id, $food_id);
        $stmt->execute();
        $stmt->close();
        // Redirect to refresh the page
        header("Location: edit_food.php?id=$food_id");
        exit();
    } else {
        $error = $lang['image_not_found'] ?? "Image not found.";
    }
}

// Fetch categories
$categories = [];
$category_result = $conn->query("SELECT id, name_" . $_SESSION['lang'] . " AS name FROM categories");
while ($cat = $category_result->fetch_assoc()) {
    $categories[$cat['id']] = $cat['name'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($error)) {
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
    $food_id = (int)$food_id; // Ensure $food_id is an integer

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

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        // Track if any changes were made
        $has_changes = false;

        // Define allowed image types for both main and gallery images
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        // Handle main image upload
        $main_image = $food['main_image'];
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            if (!in_array($_FILES['main_image']['type'], $allowed_types)) {
                $error = $lang['invalid_image_type'] ?? "Main image must be a JPEG, PNG, or GIF.";
            } else {
                $main_image = 'images/' . time() . '_' . basename($_FILES['main_image']['name']);
                if (move_uploaded_file($_FILES['main_image']['tmp_name'], $main_image)) {
                    $has_changes = true; // Main image changed
                } else {
                    $error = $lang['upload_failed'] ?? "Failed to upload main image.";
                }
            }
        }

        // Handle gallery images upload (add to food_images table)
        if (isset($_FILES['gallery_images']) && !isset($error)) {
            $upload_dir = 'images/';
            foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                    if (!in_array($_FILES['gallery_images']['type'][$key], $allowed_types)) {
                        $error = $lang['invalid_image_type'] ?? "Gallery images must be JPEG, PNG, or GIF.";
                        break;
                    }
                    $image_path = $upload_dir . time() . '_' . basename($_FILES['gallery_images']['name'][$key]);
                    if (move_uploaded_file($tmp_name, $image_path)) {
                        $stmt = $conn->prepare("INSERT INTO food_images (food_id, image_path) VALUES (?, ?)");
                        $stmt->bind_param("is", $food_id, $image_path);
                        $stmt->execute();
                        $stmt->close();
                        $has_changes = true; // Gallery image added
                    } else {
                        $error = $lang['upload_failed'] ?? "Failed to upload gallery image.";
                        break;
                    }
                }
            }
        }

        // Update food
        if (!isset($error)) {
            // Escape all string values to prevent SQL injection
            $name_en = $conn->real_escape_string($name_en);
            $name_fa = $conn->real_escape_string($name_fa);
            $name_fr = $conn->real_escape_string($name_fr);
            $name_ar = $conn->real_escape_string($name_ar);
            $description_en = $conn->real_escape_string($description_en);
            $description_fa = $conn->real_escape_string($description_fa);
            $description_fr = $conn->real_escape_string($description_fr);
            $description_ar = $conn->real_escape_string($description_ar);
            $ingredients_en = $conn->real_escape_string($ingredients_en);
            $ingredients_fa = $conn->real_escape_string($ingredients_fa);
            $ingredients_fr = $conn->real_escape_string($ingredients_fr);
            $ingredients_ar = $conn->real_escape_string($ingredients_ar);
            $main_image = $conn->real_escape_string($main_image);

            // Build the query directly
            $query = "UPDATE foods SET 
                name_en = '$name_en', 
                name_fa = '$name_fa', 
                name_fr = '$name_fr', 
                name_ar = '$name_ar', 
                description_en = '$description_en', 
                description_fa = '$description_fa', 
                description_fr = '$description_fr', 
                description_ar = '$description_ar', 
                ingredients_en = '$ingredients_en', 
                ingredients_fa = '$ingredients_fa', 
                ingredients_fr = '$ingredients_fr', 
                ingredients_ar = '$ingredients_ar', 
                category_id = $category_id, 
                price = $price, 
                prep_time = $prep_time, 
                is_available = $is_available, 
                main_image = '$main_image' 
                WHERE id = $food_id";

            // Execute the query
            if ($conn->query($query)) {
                // Check if any rows were updated in the foods table
                if ($conn->affected_rows > 0) {
                    $has_changes = true; // Foods table updated
                }

                // Redirect if any changes were made (either in foods or food_images)
                if ($has_changes) {
                    header("Location: manage_foods.php?success=" . urlencode($lang['update_success'] ?? "Food item updated successfully."));
                    exit();
                } else {
                    $error = $lang['no_changes'] ?? "No changes were made to the food item.";
                }
            } else {
                $error = $lang['db_error'] ?? "Database error: " . $conn->error;
            }
        }
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
    <style>
        .gallery-images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .gallery-image {
            position: relative;
            display: inline-block;
        }
        .gallery-image img {
            max-width: 100px;
            height: auto;
        }
        .delete-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover {
            background: darkred;
        }
    </style>
</head>
<body class="<?php echo $theme; ?>">
    <div class="header" style="background-color: #2c3e50;">
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
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </div>

    <div class="container">
        <div class="add-food">
            <h3><?php echo $lang['edit_food'] ?? 'Edit Food'; ?></h3>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <?php if (!isset($error)): ?>
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
                    <select name="category_id" id="category_id" required>
                        <option value=""><?php echo $lang['select_category'] ?? 'Select Category'; ?></option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $food['category_id'] == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="price"><?php echo $lang['price'] ?? 'Price'; ?>:</label>
                    <input type="number" name="price" id="price" step="0.01" value="<?php echo $food['price']; ?>" required>

                    <label for="prep_time"><?php echo $lang['prep_time'] ?? 'Preparation Time (minutes)'; ?>:</label>
                    <input type="number" name="prep_time" id="prep_time" value="<?php echo $food['prep_time']; ?>">

                    <label for="is_available"><?php echo $lang['is_available'] ?? 'Available'; ?>:</label>
                    <input type="checkbox" name="is_available" id="is_available" <?php echo $food['is_available'] ? 'checked' : ''; ?>>

                    <label for="main_image"><?php echo $lang['main_image'] ?? 'Main Image'; ?>:</label>
                    <input type="file" name="main_image">
                    <?php if ($food['main_image']): ?>
                        <p><?php echo $lang['current_image'] ?? 'Current Image'; ?>: <img src="<?php echo htmlspecialchars($food['main_image']); ?>" alt="Main Image" style="max-width: 200px; margin-top: 10px;"></p>
                    <?php endif; ?>

                    <label for="gallery_images"><?php echo $lang['gallery_images'] ?? 'Gallery Images'; ?>:</label>
                    <input type="file" name="gallery_images[]" multiple>
                    <div class="gallery-images">
                        <?php foreach ($gallery_images as $image): ?>
                            <div class="gallery-image">
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Gallery Image">
                                <a href="edit_food.php?id=<?php echo $food_id; ?>&delete_image=<?php echo $image['id']; ?>" class="delete-btn" onclick="return confirm('<?php echo $lang['confirm_delete'] ?? 'Are you sure you want to delete this image?'; ?>');">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit"><?php echo $lang['update'] ?? 'Update'; ?></button>
                        <a href="manage_foods.php" class="button cancel-btn">
                            <i class="fas fa-times"></i> <?php echo $lang['cancel'] ?? 'Cancel'; ?>
                        </a>
                    </div>
                </form>
            <?php endif; ?>
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