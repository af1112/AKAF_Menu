<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
        $stmt = $conn->prepare("INSERT INTO foods (name_en, name_fa, name_fr, name_ar, description_en, description_fa, description_fr, description_ar, ingredients_en, ingredients_fa, ingredients_fr, ingredients_ar, category_id, price, prep_time, is_available, main_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssssssdiis", $name_en, $name_fa, $name_fr, $name_ar, $description_en, $description_fa, $description_fr, $description_ar, $ingredients_en, $ingredients_fa, $ingredients_fr, $ingredients_ar, $category_id, $price, $prep_time, $is_available, $main_image);
        if ($stmt->execute()) {
            $food_id = $stmt->insert_id;

            if (isset($_FILES['gallery_images'])) {
                $upload_dir = 'images/';
                foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                        if (!in_array($_FILES['gallery_images']['type'][$key], $allowed_types)) {
                            $errors[] = $lang['invalid_image_type'] ?? "Gallery images must be JPEG, PNG, or GIF.";
                            break;
                        }
                        $image_path = $upload_dir . time() . '_' . basename($_FILES['gallery_images']['name'][$key]);
                        if (move_uploaded_file($tmp_name, $image_path)) {
                            $stmt = $conn->prepare("INSERT INTO food_images (food_id, image_path) VALUES (?, ?)");
                            $stmt->bind_param("is", $food_id, $image_path);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }

            $_SESSION['success_message'] = $lang['food_added'] ?? "Food item added successfully.";
            header("Location: admin_dashboard.php?page=foods");
            exit();
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

<div class="admin-section page-add_food">
    <?php if (isset($error)): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    <form id="add-food-form" action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name_en"><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</label>
            <input type="text" name="name_en" id="name_en" required oninput="translateFields('name_en', ['name_fa', 'name_fr', 'name_ar'])">
        </div>
        <div class="form-group">
            <label for="name_fa"><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</label>
            <input type="text" name="name_fa" id="name_fa" placeholder="<?php echo $lang['auto_translated'] ?? 'Will be auto-translated'; ?>">
        </div>
        <div class="form-group">
            <label for="name_fr"><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</label>
            <input type="text" name="name_fr" id="name_fr" placeholder="<?php echo $lang['auto_translated'] ?? 'Will be auto-translated'; ?>">
        </div>
        <div class="form-group">
            <label for="name_ar"><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</label>
            <input type="text" name="name_ar" id="name_ar" placeholder="<?php echo $lang['auto_translated'] ?? 'Will be auto-translated'; ?>">
        </div>

        <div class="form-group">
            <label for="description_en"><?php echo $lang['description_en'] ?? 'Description (English)'; ?>:</label>
            <textarea name="description_en" id="description_en" oninput="translateFields('description_en', ['description_fa', 'description_fr', 'description_ar'])"></textarea>
        </div>
        <div class="form-group">
            <label for="description_fa"><?php echo $lang['description_fa'] ?? 'Description (Persian)'; ?>:</label>
            <textarea name="description_fa" id="description_fa" placeholder="<?php echo $lang['auto_translated'] ?? 'Will be auto-translated'; ?>"></textarea>
        </div>
        <div class="form-group">
            <label for="description_fr"><?php echo $lang['description_fr'] ?? 'Description (French)'; ?>:</label>
            <textarea name="description_fr" id="description_fr" placeholder="<?php echo $lang['auto_translated'] ?? 'Will be auto-translated'; ?>"></textarea>
        </div>
        <div class="form-group">
            <label for="description_ar"><?php echo $lang['description_ar'] ?? 'Description (Arabic)'; ?>:</label>
            <textarea name="description_ar" id="description_ar" placeholder="<?php echo $lang['auto_translated'] ?? 'Will be auto-translated'; ?>"></textarea>
        </div>

        <div class="form-group">
            <label for="ingredients_en"><?php echo $lang['ingredients_en'] ?? 'Ingredients (English)'; ?>:</label>
            <textarea name="ingredients_en" id="ingredients_en" oninput="translateFields('ingredients_en', ['ingredients_fa', 'ingredients_fr', 'ingredients_ar'])"></textarea>
        </div>
        <div class="form-group">
            <label for="ingredients_fa"><?php echo $lang['ingredients_fa'] ?? 'Ingredients (Persian)'; ?>:</label>
            <textarea name="ingredients_fa" id="ingredients_fa" placeholder="<?php echo $lang['auto_translated'] ?? 'Will be auto-translated'; ?>"></textarea>
        </div>
        <div class="form-group">
            <label for="ingredients_fr"><?php echo $lang['ingredients_fr'] ?? 'Ingredients (French)'; ?>:</label>
            <textarea name="ingredients_fr" id="ingredients_fr" placeholder="<?php echo $lang['auto_translated'] ?? 'Will be auto-translated'; ?>"></textarea>
        </div>
        <div class="form-group">
            <label for="ingredients_ar"><?php echo $lang['ingredients_ar'] ?? 'Ingredients (Arabic)'; ?>:</label>
            <textarea name="ingredients_ar" id="ingredients_ar" placeholder="<?php echo $lang['auto_translated'] ?? 'Will be auto-translated'; ?>"></textarea>
        </div>

        <div class="form-group">
            <label for="category_id"><?php echo $lang['category'] ?? 'Category'; ?>:</label>
            <select name="category_id" id="category_id" required>
                <option value=""><?php echo $lang['select_category'] ?? 'Select a category'; ?></option>
                <?php foreach ($categories as $id => $name): ?>
                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="price"><?php echo $lang['price'] ?? 'Price'; ?>:</label>
            <input type="number" step="0.01" name="price" id="price" required>
        </div>

        <div class="form-group">
            <label for="prep_time"><?php echo $lang['prep_time'] ?? 'Preparation Time (minutes)'; ?>:</label>
            <input type="number" name="prep_time" id="prep_time" required>
        </div>

        <div class="form-group">
            <label for="is_available"><?php echo $lang['is_available'] ?? 'Available'; ?>:</label>
            <input type="checkbox" name="is_available" id="is_available" checked>
        </div>

        <div class="form-group">
            <label for="main_image"><?php echo $lang['main_image'] ?? 'Main Image'; ?>:</label>
            <input type="file" name="main_image" id="main_image" accept="image/*">
        </div>

        <div class="form-group full-width">
            <label for="gallery_images"><?php echo $lang['gallery_images'] ?? 'Gallery Images'; ?>:</label>
            <input type="file" name="gallery_images[]" id="gallery_images" accept="image/*" multiple>
        </div>

        <div class="button-group">
            <button type="submit"><?php echo $lang['add'] ?? 'Add'; ?></button>
            <a href="admin_dashboard.php?page=foods" class="button cancel-btn"><?php echo $lang['cancel'] ?? 'Cancel'; ?></a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    async function translateFields(sourceFieldId, targetFieldIds) {
        const sourceText = document.getElementById(sourceFieldId).value;
        if (!sourceText) {
            // اگر متن انگلیسی خالی شد، فیلدهای مقصد هم خالی بشن
            for (const targetFieldId of targetFieldIds) {
                document.getElementById(targetFieldId).value = '';
            }
            return;
        }

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

        // پاک کردن محتوای فیلدهای مقصد قبل از ترجمه
        for (const targetFieldId of targetFieldIds) {
            const targetField = document.getElementById(targetFieldId);
            targetField.value = ''; // پاک کردن محتوای فعلی
        }

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
                targetField.value = translatedText; // مقدار ترجمه‌شده رو مستقیماً قرار بده
            } catch (error) {
                console.error(`Translation failed for ${targetFieldId}:`, error);
            }
        }
    }
</script>