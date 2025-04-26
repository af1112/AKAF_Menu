<?php
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($category_id <= 0) {
    header("Location: admin_dashboard.php?page=categories");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    header("Location: admin_dashboard.php?page=categories");
    exit();
}

// Handle image deletion
if (isset($_GET['delete_image']) && !isset($error)) {
    if (!empty($category['image'])) {
        if (file_exists($category['image'])) {
            unlink($category['image']);
        }
        $stmt = $conn->prepare("UPDATE categories SET image = '' WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_dashboard.php?page=edit_category&id=$category_id");
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($error)) {
    $name_en = $_POST['name_en'] ?? '';
    $name_fa = $_POST['name_fa'] ?? '';
    $name_fr = $_POST['name_fr'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';

    $errors = [];
    if (empty($name_en) || empty($name_fa) || empty($name_fr) || empty($name_ar)) {
        $errors[] = $lang['name_required'] ?? "All name fields are required.";
    }

    $image = $category['image'] ?? '';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = $lang['invalid_image_type'] ?? "Image must be a JPEG, PNG, or GIF.";
        } else {
            if ($image && file_exists($image)) {
                unlink($image);
            }
            $image = 'images/' . time() . '_' . basename($_FILES['image']['name']);
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image)) {
                $errors[] = $lang['upload_failed'] ?? "Failed to upload image.";
            }
        }
    }

    if (empty($errors)) {
        $image = $conn->real_escape_string($image);
        $stmt = $conn->prepare("UPDATE categories SET name_en = ?, name_fa = ?, name_fr = ?, name_ar = ?, image = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $name_en, $name_fa, $name_fr, $name_ar, $image, $category_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = $lang['category_updated'] ?? "Category updated successfully.";
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

<div class="admin-section page-edit_category">
    <?php if (isset($error)): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    <form id="edit_category-form" action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name_en"><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</label>
            <input type="text" name="name_en" id="name_en" value="<?php echo htmlspecialchars($category['name_en']); ?>" required>
        </div>
        <div class="form-group">
            <label for="name_fa"><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</label>
            <input type="text" name="name_fa" id="name_fa" value="<?php echo htmlspecialchars($category['name_fa']); ?>" required>
        </div>
        <div class="form-group">
            <label for="name_fr"><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</label>
            <input type="text" name="name_fr" id="name_fr" value="<?php echo htmlspecialchars($category['name_fr']); ?>" required>
        </div>
        <div class="form-group">
            <label for="name_ar"><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</label>
            <input type="text" name="name_ar" id="name_ar" value="<?php echo htmlspecialchars($category['name_ar']); ?>" required>
        </div>

        <div class="form-group full-width">
            <label for="image"><?php echo $lang['image'] ?? 'Image'; ?>:</label>
            <?php if (!empty($category['image'])): ?>
                <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="Category Image" class="image-frame">
                <a href="admin_dashboard.php?page=edit_category&id=<?php echo $category_id; ?>&delete_image=1" class="delete-btn" onclick="return confirm('<?php echo $lang['confirm_delete_image'] ?? 'Are you sure you want to delete this image?'; ?>')">
                    <i class="fas fa-trash"></i> <?php echo $lang['delete_image'] ?? 'Delete Image'; ?>
                </a>
            <?php endif; ?>
            <input type="file" name="image" id="image" accept="image/*">
        </div>

        <div class="button-group">
            <button type="submit"><?php echo $lang['update'] ?? 'Update'; ?></button>
            <a href="admin_dashboard.php?page=categories" class="button cancel-btn"><?php echo $lang['cancel'] ?? 'Cancel'; ?></a>
        </div>
    </form>
</div>