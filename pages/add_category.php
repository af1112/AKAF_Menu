<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name_en = $_POST['name_en'] ?? '';
    $name_fa = $_POST['name_fa'] ?? '';
    $name_fr = $_POST['name_fr'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';

    $errors = [];
    if (empty($name_en) || empty($name_fa) || empty($name_fr) || empty($name_ar)) {
        $errors[] = $lang['name_required'] ?? "All name fields are required.";
    }

    $image = '';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = $lang['invalid_image_type'] ?? "Image must be a JPEG, PNG, or GIF.";
        } else {
            $image = 'images/' . time() . '_' . basename($_FILES['image']['name']);
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image)) {
                $errors[] = $lang['upload_failed'] ?? "Failed to upload image.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO categories (name_en, name_fa, name_fr, name_ar, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name_en, $name_fa, $name_fr, $name_ar, $image);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = $lang['category_added'] ?? "Category added successfully.";
            header("Location: admin_dashboard.php?page=categories");
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

<div class="admin-section page-add_category">
    <?php if (isset($error)): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    <form id="add_category-form" action="" method="POST" enctype="multipart/form-data">
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

        <div class="form-group full-width">
            <label for="image"><?php echo $lang['image'] ?? 'Image'; ?>:</label>
            <input type="file" name="image" id="image" accept="image/*">
        </div>

        <div class="button-group">
            <button type="submit"><?php echo $lang['add'] ?? 'Add'; ?></button>
            <a href="admin_dashboard.php?page=categories" class="button cancel-btn"><?php echo $lang['cancel'] ?? 'Cancel'; ?></a>
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
            'name_ar': 'ar'
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