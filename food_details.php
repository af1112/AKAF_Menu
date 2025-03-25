<?php
session_start();
include 'db.php';

// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light'; // Default theme
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

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

// Get food ID from URL
$food_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch food details
$stmt = $conn->prepare("SELECT * FROM foods WHERE id = ? AND is_available = 1");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$result = $stmt->get_result();
$food = $result->fetch_assoc();

// Check if food exists
if (!$food) {
    // If food not found, display message and exit
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $lang['food_not_found'] ?? 'Food Not Found'; ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="<?php echo $theme; ?>">
        <!-- هدر -->
        <div class="header">
            <h1><?php echo $lang['food_details'] ?? 'Food Details'; ?></h1>
            <div class="controls">
                <select onchange="window.location='food_details.php?id=<?php echo $food_id; ?>&lang=' + this.value">
                    <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                    <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                    <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
                </select>
                <a href="food_details.php?id=<?php echo $food_id; ?>&theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                    <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                    <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
                </a>
                <a href="cart.php">
                    <i class="fas fa-shopping-cart"></i> <?php echo $lang['cart'] ?? 'Cart'; ?>
                </a>
                <?php if ($is_logged_in): ?>
                    <a href="user_dashboard.php">
                        <i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?>
                    </a>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
                    </a>
                <?php else: ?>
                    <a href="user_login.php">
                        <i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="container">
            <p style="color: #dc3545;"><?php echo $lang['food_not_found'] ?? 'Food not found.'; ?></p>
            <a href="menu.php" class="back"><?php echo $lang['back_to_menu'] ?? 'Back to Menu'; ?></a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Fetch language-specific columns
$lang_name_col = 'name_' . $_SESSION['lang'];
$lang_desc_col = 'description_' . $_SESSION['lang'];

// Fetch gallery images
$gallery_stmt = $conn->prepare("SELECT * FROM food_images WHERE food_id = ?");
$gallery_stmt->bind_param("i", $food_id);
$gallery_stmt->execute();
$gallery_images = $gallery_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch reviews from food_reviews table
$reviews_stmt = $conn->prepare("SELECT fr.*, u.username FROM food_reviews fr JOIN users u ON fr.user_id = u.id WHERE fr.food_id = ? ORDER BY fr.created_at DESC");
$reviews_stmt->bind_param("i", $food_id);
$reviews_stmt->execute();
$reviews = $reviews_stmt->get_result();

// Handle review submission
$review_error = $review_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in) {
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    if ($rating < 1 || $rating > 5) {
        $review_error = $lang['invalid_rating'] ?? 'Please select a valid rating (1-5).';
    } elseif (empty($comment)) {
        $review_error = $lang['comment_required'] ?? 'Comment is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO food_reviews (food_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $food_id, $user_id, $rating, $comment);
        if ($stmt->execute()) {
            $review_success = $lang['review_submitted'] ?? 'Review submitted successfully!';
            // Refresh the page to show the new review
            header("Location: food_details.php?id=$food_id");
            exit();
        } else {
            $review_error = $lang['review_failed'] ?? 'Failed to submit review. Please try again.';
        }
    }
}

// Calculate cart item count
$cart_items = $_SESSION['cart'] ?? [];
$cart_count = 0;
foreach ($cart_items as $quantity) {
    $cart_count += $quantity;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($food[$lang_name_col]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?php echo $theme; ?>">
    <!-- هدر -->
    <div class="header">
        <h1><?php echo $lang['food_details'] ?? 'Food Details'; ?></h1>
        <div class="controls">
            <select onchange="window.location='food_details.php?id=<?php echo $food_id; ?>&lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="food_details.php?id=<?php echo $food_id; ?>&theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="cart.php">
                <i class="fas fa-shopping-cart"></i> <?php echo $lang['cart'] ?? 'Cart'; ?>
                <?php if ($cart_count > 0): ?>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            <?php if ($is_logged_in): ?>
                <a href="user_dashboard.php">
                    <i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?>
                </a>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
                </a>
            <?php else: ?>
                <a href="user_login.php">
                    <i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <!-- جزئیات غذا -->
        <div class="food-details" data-aos="fade-up">
            <div class="main-info">
                <img src="<?php echo htmlspecialchars($food['main_image'] ?? 'images/default.jpg'); ?>" alt="<?php echo htmlspecialchars($food[$lang_name_col]); ?>" class="main-image">
                <div class="info-text">
                    <h2><?php echo htmlspecialchars($food[$lang_name_col]); ?></h2>
                    <p class="price">$<?php echo number_format($food['price'], 2); ?></p>
                    <p class="availability <?php echo $food['is_available'] ? 'available' : 'unavailable'; ?>">
                        <?php echo $food['is_available'] ? ($lang['available'] ?? 'Available') : ($lang['unavailable'] ?? 'Unavailable'); ?>
                    </p>
                    <p class="prep-time"><?php echo $lang['prep_time'] ?? 'Preparation Time'; ?>: 20 <?php echo $lang['minutes'] ?? 'minutes'; ?></p>
                    <p class="description">
                        <?php echo isset($food[$lang_desc_col]) ? htmlspecialchars($food[$lang_desc_col]) : 'توضیحات موجود نیست'; ?>
                    </p>
                    <p class="ingredients">
                        <?php echo $lang['ingredients'] ?? 'Ingredients'; ?>: 
                        <?php echo isset($food['ingredients_' . $_SESSION['lang']]) ? htmlspecialchars($food['ingredients_' . $_SESSION['lang']]) : 'مواد تشکیل‌دهنده مشخص نشده'; ?>
                    </p>
                    <div class="actions">
                        <input type="number" class="quantity-input" value="1" min="1" id="quantity-<?php echo $food['id']; ?>">
                        <button class="add-to-cart" onclick="addToCart(<?php echo $food['id']; ?>)">
                            <i class="fas fa-cart-plus"></i> <?php echo $lang['add_to_cart'] ?? 'Add to Cart'; ?>
                        </button>
                        <button class="share" onclick="shareFood('<?php echo htmlspecialchars($food[$lang_name_col]); ?>')">
                            <i class="fas fa-share-alt"></i> <?php echo $lang['share'] ?? 'Share'; ?>
                        </button>
                        <a href="menu.php" class="back"><?php echo $lang['back_to_menu'] ?? 'Back to Menu'; ?></a>
                    </div>
                </div>
            </div>

            <!-- گالری تصاویر -->
            <?php if (!empty($gallery_images)): ?>
                <div class="gallery-images" data-aos="fade-up">
                    <?php foreach ($gallery_images as $image): ?>
                        <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Gallery Image" onclick="changeMainImage(this.src)">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- نظرات -->
        <div class="reviews" data-aos="fade-up">
            <h3><?php echo $lang['reviews'] ?? 'Reviews'; ?></h3>
            <?php if ($reviews->num_rows > 0): ?>
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <div class="review-item">
                        <p><strong><?php echo htmlspecialchars($review['username']); ?>:</strong> 
                            <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                        </p>
                        <p><?php echo htmlspecialchars($review['comment']); ?></p>
                        <p><small><?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?></small></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p><?php echo $lang['no_reviews'] ?? 'No reviews yet.'; ?></p>
            <?php endif; ?>
        </div>

        <!-- فرم افزودن نظر -->
        <?php if ($is_logged_in): ?>
            <div class="add-review" data-aos="fade-up">
                <h3><?php echo $lang['add_review'] ?? 'Add a Review'; ?></h3>
                <?php if ($review_error): ?>
                    <p class="error"><?php echo $review_error; ?></p>
                <?php endif; ?>
                <?php if ($review_success): ?>
                    <p class="success"><?php echo $review_success; ?></p>
                <?php endif; ?>
                <form method="POST">
                    <label for="rating"><?php echo $lang['rating'] ?? 'Rating'; ?>:</label>
                    <select name="rating" id="rating" required>
                        <option value=""><?php echo $lang['select_rating'] ?? 'Select rating'; ?></option>
                        <option value="1">1 ★</option>
                        <option value="2">2 ★★</option>
                        <option value="3">3 ★★★</option>
                        <option value="4">4 ★★★★</option>
                        <option value="5">5 ★★★★★</option>
                    </select>

                    <label for="comment"><?php echo $lang['comment'] ?? 'Comment'; ?>:</label>
                    <textarea name="comment" id="comment" required></textarea>

                    <button type="submit"><?php echo $lang['submit_review'] ?? 'Submit Review'; ?></button>
                </form>
            </div>
        <?php else: ?>
            <p><?php echo $lang['login_to_review'] ?? 'Please log in to add a review.'; ?> <a href="user_login.php"><?php echo $lang['login'] ?? 'Login'; ?></a></p>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

        function addToCart(foodId) {
            const quantity = document.getElementById('quantity-' + foodId).value;
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'food_id=' + foodId + '&quantity=' + quantity
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fetchCartCount();
                    alert('<?php echo $lang['added_to_cart'] ?? 'Added to cart!'; ?>');
                } else {
                    alert(data.message || '<?php echo $lang['failed_to_add_to_cart'] ?? 'Failed to add to cart.'; ?>');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('<?php echo $lang['failed_to_add_to_cart'] ?? 'Failed to add to cart.'; ?>');
            });
        }

        function fetchCartCount() {
            fetch('get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                const cartCountElement = document.querySelector('.cart-count');
                if (data.count > 0) {
                    if (cartCountElement) {
                        cartCountElement.textContent = data.count;
                    } else {
                        const cartLink = document.querySelector('a[href="cart.php"]');
                        cartLink.innerHTML += `<span class="cart-count">${data.count}</span>`;
                    }
                } else if (cartCountElement) {
                    cartCountElement.remove();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function shareFood(foodName) {
            if (navigator.share) {
                navigator.share({
                    title: foodName,
                    text: `Check out this delicious ${foodName}!`,
                    url: window.location.href
                })
                .then(() => console.log('Shared successfully'))
                .catch(error => console.error('Error sharing:', error));
            } else {
                alert('Sharing is not supported on this browser.');
            }
        }

        function changeMainImage(src) {
            document.querySelector('.main-image').src = src;
        }

        fetchCartCount();
    </script>
</body>
</html>