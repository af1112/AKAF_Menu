<?php
// Ensure $conn and $lang are available (they are already included in admin_dashboard.php)
$food_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// لود دسته‌بندی‌ها از جدول categories
$categories = [];
$category_query = $conn->query("SELECT * FROM categories");
if ($category_query) {
    while ($row = $category_query->fetch_assoc()) {
        $categories[$row['id']] = $row['name_fa']; // استفاده از name_fa برای فارسی
    }
} else {
    die($lang['error_loading_categories'] ?? "Error loading categories: " . $conn->error);
}

if ($food_id <= 0) {
    $error = $lang['invalid_food_id'] ?? "Invalid food ID.";
} else {
    $stmt = $conn->prepare("SELECT * FROM foods WHERE id = ?");
    if (!$stmt) {
        die($lang['prepare_failed'] ?? "Prepare failed: " . $conn->error);
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

// Handle main image deletion
if (isset($_GET['delete_main_image']) && !isset($error)) {
    if (!empty($food['main_image'])) {
        // Delete the image file from the server
        if (file_exists($food['main_image'])) {
            unlink($food['main_image']);
        }
        // Update the database to remove the main image
        $stmt = $conn->prepare("UPDATE foods SET main_image = '' WHERE id = ?");
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $stmt->close();
        // Refresh food data
        $stmt = $conn->prepare("SELECT * FROM foods WHERE id = ?");
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $food = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        // Set session flag to indicate an image was deleted
        $_SESSION['image_deleted'] = true;
        // Redirect to refresh the page
        header("Location: /myrestaurant/admin_dashboard.php?page=edit_food&id=$food_id");
        exit();
    }
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
        // Set session flag to indicate an image was deleted
        $_SESSION['image_deleted'] = true;
        // Redirect to refresh the page
        header("Location: /myrestaurant/admin_dashboard.php?page=edit_food&id=$food_id");
        exit();
    } else {
        $error = $lang['image_not_found'] ?? "Image not found.";
    }
}
?>

<?php if (isset($error)): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<?php if (!isset($error)): ?>
<div class="admin-section page-edit_food">
    <h3><?php echo $lang['edit_food'] ?? 'ویرایش غذا'; ?></h3>
    <form method="POST" action="/myrestaurant/admin_dashboard.php?page=edit_food&id=<?= $food_id ?>" enctype="multipart/form-data" id="edit-food-form">
        <div class="form-group">
            <label for="name_en"><?php echo $lang['name_en'] ?? 'نام (انگلیسی)'; ?>:</label>
            <input type="text" id="name_en" name="name_en" value="<?= htmlspecialchars($food['name_en'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="name_fa"><?php echo $lang['name_fa'] ?? 'نام (فارسی)'; ?>:</label>
            <input type="text" id="name_fa" name="name_fa" value="<?= htmlspecialchars($food['name_fa'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="name_fr"><?php echo $lang['name_fr'] ?? 'نام (فرانسوی)'; ?>:</label>
            <input type="text" id="name_fr" name="name_fr" value="<?= htmlspecialchars($food['name_fr'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="name_ar"><?php echo $lang['name_ar'] ?? 'الاسم (عربي)'; ?>:</label>
            <input type="text" id="name_ar" name="name_ar" value="<?= htmlspecialchars($food['name_ar'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="description_en"><?php echo $lang['description_en'] ?? 'توضیحات (انگلیسی)'; ?>:</label>
            <textarea id="description_en" name="description_en"><?= htmlspecialchars($food['description_en'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="description_fa"><?php echo $lang['description_fa'] ?? 'توضیحات (فارسی)'; ?>:</label>
            <textarea id="description_fa" name="description_fa"><?= htmlspecialchars($food['description_fa'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="description_fr"><?php echo $lang['description_fr'] ?? 'توضیحات (فرانسوی)'; ?>:</label>
            <textarea id="description_fr" name="description_fr"><?= htmlspecialchars($food['description_fr'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="description_ar"><?php echo $lang['description_ar'] ?? 'الوصف (عربي)'; ?>:</label>
            <textarea id="description_ar" name="description_ar"><?= htmlspecialchars($food['description_ar'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="ingredients_en"><?php echo $lang['ingredients_en'] ?? 'مواد اولیه (انگلیسی)'; ?>:</label>
            <input type="text" id="ingredients_en" name="ingredients_en" value="<?= htmlspecialchars($food['ingredients_en'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="ingredients_fa"><?php echo $lang['ingredients_fa'] ?? 'مواد اولیه (فارسی)'; ?>:</label>
            <input type="text" id="ingredients_fa" name="ingredients_fa" value="<?= htmlspecialchars($food['ingredients_fa'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="ingredients_fr"><?php echo $lang['ingredients_fr'] ?? 'مواد اولیه (فرانسوی)'; ?>:</label>
            <input type="text" id="ingredients_fr" name="ingredients_fr" value="<?= htmlspecialchars($food['ingredients_fr'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="ingredients_ar"><?php echo $lang['ingredients_ar'] ?? 'المكونات (عربي)'; ?>:</label>
            <input type="text" id="ingredients_ar" name="ingredients_ar" value="<?= htmlspecialchars($food['ingredients_ar'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="price"><?php echo $lang['price'] ?? 'قیمت'; ?>:</label>
            <input type="number" id="price" name="price" step="0.01" value="<?= htmlspecialchars($food['price'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="prep_time"><?php echo $lang['prep_time'] ?? 'زمان آماده‌سازی (دقیقه)'; ?>:</label>
            <input type="number" id="prep_time" name="prep_time" value="<?= htmlspecialchars($food['prep_time'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="category_id"><?php echo $lang['category'] ?? 'دسته‌بندی'; ?>:</label>
            <select id="category_id" name="category_id">
                <option value=""><?php echo $lang['select_category'] ?? 'انتخاب دسته‌بندی'; ?></option>
                <?php foreach ($categories as $id => $name): ?>
                    <option value="<?= $id ?>" <?= ($food['category_id'] ?? 0) == $id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="is_available">
                <input type="checkbox" id="is_available" name="is_available" value="1" <?= isset($food['is_available']) && $food['is_available'] ? 'checked' : '' ?>>
                <?php echo $lang['is_available'] ?? 'موجود'; ?>
            </label>
        </div>

        <div class="form-group full-width">
            <label for="main_image"><?php echo $lang['main_image'] ?? 'تصویر اصلی'; ?>:</label>
            <?php if (!empty($food['main_image'])): ?>
                <div class="image-preview">
                    <img src="<?= htmlspecialchars($food['main_image']) ?>" alt="Main Image" style="max-width: 100px; max-height: 100px;">
                    <a href="/myrestaurant/admin_dashboard.php?page=edit_food&id=<?= $food_id ?>&delete_main_image=1" class="delete-image">×</a>
                </div>
            <?php endif; ?>
            <input type="file" id="main_image" name="main_image">
        </div>

        <div class="form-group full-width">
            <label for="gallery_images"><?php echo $lang['gallery_images'] ?? 'تصاویر گالری'; ?>:</label>
            <?php if (!empty($gallery_images)): ?>
                <div class="gallery-preview">
                    <?php foreach ($gallery_images as $image): ?>
                        <div class="image-preview">
                            <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="Gallery Image" style="max-width: 100px; max-height: 100px;">
                            <a href="/myrestaurant/admin_dashboard.php?page=edit_food&id=<?= $food_id ?>&delete_image=<?= $image['id'] ?>" class="delete-image">×</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <input type="file" id="gallery_images" name="gallery_images[]" multiple>
        </div>

        <style>
            .gallery-preview {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 10px;
            }
            .image-preview {
                position: relative;
                display: inline-block;
            }
            .delete-image {
                position: absolute;
                top: 5px;
                right: 5px;
                background: red;
                color: white;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                text-align: center;
                line-height: 20px;
                text-decoration: none;
                font-size: 14px;
            }
        </style>
    </form>
</div>
<?php endif; ?>