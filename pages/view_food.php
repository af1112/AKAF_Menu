<?php
// Ensure $conn and $lang are available (they are already included in admin_dashboard.php)
$food_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($food_id <= 0) {
    header("Location: admin_dashboard.php?page=foods");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM foods WHERE id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$food) {
    header("Location: admin_dashboard.php?page=foods");
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

<h3><?php echo $lang['food_details'] ?? 'Food Details'; ?> - <?php echo htmlspecialchars($food['name_' . $_SESSION['lang']]); ?></h3>
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
            <img src="<?php echo htmlspecialchars($food['main_image']); ?>" alt="Main Image" class="image-frame" onclick="openModal('<?php echo htmlspecialchars($food['main_image']); ?>')">
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
                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Gallery Image" class="image-frame" onclick="openModal('<?php echo htmlspecialchars($image['image_path']); ?>')">
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?php echo $lang['no_images'] ?? 'No Images'; ?>
    <?php endif; ?>
</p>

<!-- Action Buttons -->
<a href="admin_dashboard.php?page=edit_food&id=<?php echo $food['id']; ?>" class="button">
    <i class="fas fa-edit"></i> <?php echo $lang['edit'] ?? 'Edit'; ?>
</a>
<a href="admin_dashboard.php?page=foods" class="button">
    <i class="fas fa-arrow-left"></i> <?php echo $lang['back'] ?? 'Back'; ?>
</a>

<!-- Modal for Image Preview -->
<div id="imageModal" class="modal">
    <span class="close" onclick="closeModal()">×</span>
    <img class="modal-content" id="modalImage">
</div>