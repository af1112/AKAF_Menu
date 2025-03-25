<?php
session_start();
include 'db.php';

// Restrict access to logged-in users
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Load language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
include "languages/" . $_SESSION['lang'] . ".php";

$food_id = $_GET['id'] ?? null;
if (!$food_id) {
    die("❌ " . ($lang['food_not_found'] ?? 'Food not found'));
}

// Fetch Food Details
$stmt = $conn->prepare("SELECT * FROM foods WHERE id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$food = $stmt->get_result()->fetch_assoc();
if (!$food) {
    die("❌ " . ($lang['food_not_found'] ?? 'Food not found'));
}

// Fetch Images
$stmt = $conn->prepare("SELECT image FROM food_images WHERE food_id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$images = $stmt->get_result();

// Fetch Reviews
$stmt = $conn->prepare("SELECT * FROM food_reviews WHERE food_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$reviews = $stmt->get_result();

$stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM food_reviews WHERE food_id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$average_rating = $stmt->get_result()->fetch_assoc()["avg_rating"] ?? ($lang['no_ratings'] ?? 'No ratings yet');

// Define language columns
$lang_name_col = "name_" . $_SESSION['lang'];
$lang_desc_col = "description_" . $_SESSION['lang'];

// Detect language direction
$rtl_languages = ['fa', 'ar'];
$is_rtl = in_array($_SESSION['lang'], $rtl_languages);
$direction = $is_rtl ? 'rtl' : 'ltr';
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($food[$lang_name_col]); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($food[$lang_name_col]); ?></h1>

        <!-- Food Info -->
        <div class="food-info">
            <?php 
            $first_image = $images->fetch_assoc();
            echo "<!-- Debug: first_image = " . var_export($first_image, true) . " -->";
            $main_image = $first_image && isset($first_image['image']) ? $first_image['image'] : 'default.jpg';
            echo "<!-- Debug: main_image = " . var_export($main_image, true) . " -->";
            ?>
            <img src="images/<?php echo htmlspecialchars($main_image); ?>" id="food-main-image" class="food-main-image">
            <div class="food-details">
                <p><strong><?php echo $lang['price'] ?? 'Price'; ?>:</strong> $<?php echo $food["price"]; ?></p>
                <p><?php echo htmlspecialchars($food[$lang_desc_col]); ?></p>
                <p><strong>⭐ <?php echo $lang['rating'] ?? 'Rating'; ?>:</strong> <?php echo is_numeric($average_rating) ? number_format($average_rating, 1) : $average_rating; ?>/5</p>
            </div>
        </div>

        <!-- Image Gallery -->
        <div class="gallery-images">
            <?php 
            // از $images باقی‌مونده استفاده می‌کنیم، نیازی به اجرای دوباره $stmt نیست
            while ($img = $images->fetch_assoc()): ?>
                <?php 
                // چک می‌کنیم که $img مقدار داره و کلید 'image' وجود داره
                $gallery_image = isset($img['image']) ? $img['image'] : 'default.jpg';
                ?>
                <img src="images/<?php echo htmlspecialchars($gallery_image); ?>" class="gallery-thumb" data-image="<?php echo htmlspecialchars($gallery_image); ?>">
            <?php endwhile; ?>
        </div>

        <!-- Buttons -->
        <div class="buttons">
            <div class="add-to-cart">
                <input type="number" min="1" value="1" id="quantity-<?php echo $food_id; ?>" class="quantity-input">
                <button onclick="window.location='menu.php?add_to_cart=<?php echo $food_id; ?>&quantity=' + document.getElementById('quantity-<?php echo $food_id; ?>').value">🛒 <?php echo $lang['add_to_cart'] ?? 'Add to Cart'; ?></button>
            </div>
            <button class="back-btn" onclick="history.back()">🔙 <?php echo $lang['back'] ?? 'Back'; ?></button>
        </div>

        <!-- Reviews -->
        <h2>📝 <?php echo $lang['reviews'] ?? 'Reviews'; ?></h2>
        <div class="reviews">
            <?php if ($reviews->num_rows > 0): ?>
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <div class="review-item">
                        <p><strong><?php echo htmlspecialchars($review["username"]); ?>:</strong> ⭐ <?php echo $review["rating"]; ?>/5</p>
                        <p><?php echo htmlspecialchars($review["comment"]); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p><?php echo $lang['no_reviews'] ?? 'No reviews yet'; ?></p>
            <?php endif; ?>
        </div>

        <!-- Add Review Form -->
        <h3>➕ <?php echo $lang['add_review'] ?? 'Add a Review'; ?></h3>
        <div class="add-review">
            <form action="submit_review.php" method="POST">
                <input type="hidden" name="food_id" value="<?php echo $food_id; ?>">
                <label>⭐ <?php echo $lang['rating'] ?? 'Rating'; ?> (1-5):</label>
                <select name="rating" required>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?php echo $i; ?>">⭐<?php echo str_repeat('⭐', $i-1); ?> (<?php echo $i; ?>)</option>
                    <?php endfor; ?>
                </select>
                <label>💬 <?php echo $lang['comment'] ?? 'Comment'; ?>:</label>
                <textarea name="comment" required></textarea>
                <button type="submit"><?php echo $lang['submit_review'] ?? 'Submit Review'; ?></button>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll(".gallery-thumb").forEach(img => {
            img.addEventListener("click", function() {
                document.getElementById("food-main-image").src = this.src;
            });
        });
    </script>
</body>
</html>