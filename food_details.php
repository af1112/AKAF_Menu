<?php
session_start();
include 'db.php';

// Load currency from settings
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency'");
$stmt->execute();
$currency = $stmt->get_result()->fetch_assoc()['value'] ?? 'OMR'; // پیش‌فرض OMR اگه چیزی پیدا نشد
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency_Decimal'");
$stmt->execute();
$currency_Decimal = $stmt->get_result()->fetch_assoc()['value'] ?? '3'; // پیش‌فرض 3 اگه چیزی پیدا نشد

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

// بررسی ورود کاربر و دریافت تم و زبان پیش‌فرض
$user_theme = 'light';
$user_language = 'fa'; // مقدار پیش‌فرض
if (isset($_SESSION['user']['id'])) {
    $user_id = $_SESSION['user']['id'];
    $stmt = $pdo->prepare("SELECT theme, language FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_theme = $user['theme'] ?? 'light';
    $user_language = $user['language'] ?? 'fa';
}

// Load language
// ابتدا زبان پیش‌فرض کاربر از دیتابیس تنظیم می‌شود
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = $user_language;
}
// اگر کاربر از نوار زبان تغییر داد، زبان سشن به‌روزرسانی می‌شود
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$language_file = "languages/" . $_SESSION['lang'] . ".php";
if (!file_exists($language_file)) {
    $language_file = "languages/en.php";
}
include $language_file;

// Detect language direction
$rtl_languages = ['fa', 'ar'];
$is_rtl = in_array($_SESSION['lang'], $rtl_languages);
$direction = $is_rtl ? 'rtl' : 'ltr';

// بررسی وضعیت ورود کاربر
$is_logged_in = isset($_SESSION['user']['id']);

// تعیین کلاس تم برای body
$theme_class = $user_theme === 'dark' ? 'dark-theme' : 'light-theme';

// Get food ID from URL
$food_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch food details
$stmt = $conn->prepare("SELECT * FROM foods WHERE id = ? AND is_available = 1");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$result = $stmt->get_result();
$food = $result->fetch_assoc();


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
foreach ($cart_items as $item) {
    $cart_count += $item['quantity'];
}

?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- AOS for animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>"> <!-- اضافه کردن نسخه برای جلوگیری از کش -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .desktop-menu a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            font-size: 16px;
        }
        body {
            font-family: <?php echo $is_rtl ? "'IRANSans', sans-serif" : "'Roboto', sans-serif"; ?>;
            transition: all 0.3s ease;
        }
        /* تم روشن (پیش‌فرض) */
        body.light-theme {
            background-color: #f8f9fa;
        }
        body.light-theme .menu-container {
            background: #fff;
        }
        body.light-theme .menu-list .list-group-item {
            background-color: #f8f9fa;
        }
        body.light-theme .menu-header h2 {
            color: #ff5722;
        }
        /* تم تیره */
        body.dark-theme {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }
        body.dark-theme .menu-container {
            background: #2a2a2a;
            color: #e0e0e0;
        }
        body.dark-theme .menu-list .list-group-item {
            background-color: #333;
            color: #e0e0e0;
        }
        body.dark-theme .menu-list .list-group-item:hover {
            background-color: #ff7043;
            color: #000;
        }
        body.dark-theme .menu-header h2 {
            color: #ff7043;
        }
		.quantity-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .quantity-control input[type="number"] {
            width: 50px;
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        .quantity-control input[type="number"]::-webkit-inner-spin-button,
        .quantity-control input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .quantity-control button {
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            color: #333;
        }

        .quantity-control button:hover {
            background: #e0e0e0;
        }

        /* ✅ استایل منوی پایین برای موبایل */
        .menu-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #ffffff;
            display: none;
            justify-content: space-around;
            padding: 5px 0;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
			z-index: 1000; /* ⬅ مقدار زیاد که منو همیشه روی همه چیز باشد */
        }

        .menu-bar a {
            text-decoration: none;
            color: #666;
            font-size: 10px;
            text-align: center;
            flex: 1;
            position: relative;
            transition: all 0.3s ease;
        }

        .menu-bar a i {
            font-size: 22px;
            display: block;
            margin-bottom: 0px;
        }

        .cart-badge {
            position: absolute;
            top: 0;
            right: 15px;
            background: red;
            color: white;
            font-size: 10px;
            width: 16px;
            height: 16px;
            line-height: 18px;
            text-align: center;
            border-radius: 50%;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        /* ✅ نمایش منوی پایین در موبایل و تبلت */
        @media (max-width: 1000px) {
            .desktop-menu {
                display: none;
            }

            .menu-bar {
                display: flex;
            }
        }
        @media (max-width: 500px) {
		.navbar {
				display: none;
			}
		}
    </style>
</head>
<body class="<?php echo $theme_class; ?>">
<!-- Language Bar -->
	<div class="language-bar">
		<div class="container-fluid">
			<div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
				<a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="food_details.php?lang=en<?php echo $food_id ? '&id=' . $food_id : ''; ?>">
					<img src="images/flags/en.png" alt="English" class="flag-icon"> EN
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="food_details.php?lang=fa<?php echo $food_id ? '&id=' . $food_id : ''; ?>">
					<img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="food_details.php?lang=ar<?php echo $food_id ? '&id=' . $food_id : ''; ?>">
					<img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="food_details.php?lang=fr<?php echo $food_id ? '&id=' . $food_id : ''; ?>">
					<img src="images/flags/fr.png" alt="French" class="flag-icon"> FR
				</a>
			</div>
		</div>
	</div>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg custom-navbar">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse <?php echo $is_rtl ? '' : 'justify-content-end'; ?>" id="navbarNav">
                <ul class="navbar-nav <?php echo $is_rtl ? 'nav-rtl' : ''; ?>">
                    <?php if ($is_rtl): ?>
                        <!-- RTL: Login/Logout on the far left -->
                        <li class="nav-item login-item">
                            <?php if ($is_logged_in): ?>
                                <a class="nav-link" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
                                </a>
                            <?php else: ?>
                                <a class="nav-link" href="user_login.php">
                                    <i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                    <!-- Middle items -->
					<li class="nav-item">
						<a class="nav-link" href="index.php">
							<i class="fas fa-bars"></i> <?php echo $lang['home'] ?? 'Home'; ?>
						</a>
					</li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> <?php echo $lang['cart'] ?? 'Cart'; ?>
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-count"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if ($is_logged_in && !$is_rtl): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user_dashboard.php">
                                <i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($is_rtl): ?>
                        <!-- RTL: Profile in the middle -->
                        <?php if ($is_logged_in): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="user_dashboard.php">
                                    <i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- LTR: Login/Logout on the far right -->
                        <li class="nav-item">
                            <?php if ($is_logged_in): ?>
                                <a class="nav-link" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
                                </a>
                            <?php else: ?>
                                <a class="nav-link" href="user_login.php">
                                    <i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
	<!-- ✅ منوی پایین مخصوص موبایل -->
    <div class="menu-bar" id="menu">
        <a href="index.php" class="active">
            <i class="fa-solid fa-house"></i>
            <span class="menu-text"><?php echo $lang['home'] ?? 'Home'; ?></span>
        </a>
        <a href="search.php">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span class="menu-text"><?php echo $lang['search'] ?? 'Search'; ?></span>
        </a>
        <a href="cart.php" class="shopping-cart">
            <i class="fa-solid fa-shopping-cart"></i>
            <span class="menu-text"><?php echo $lang['shopping_cart'] ?? 'Shopping Cart'; ?></span>
             <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?php echo $cart_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="favourite.php">
            <i class="fa-solid fa-heart"></i>
            <span class="menu-text"><?php echo $lang['favourite'] ?? 'Favourite'; ?></span>
        </a>
        <a href="menu.php">
            <i class="fa-solid fa-ellipsis-vertical"></i>
            <span class="menu-text"><?php echo $lang['menu'] ?? 'Menu'; ?></span>
        </a>
    </div>

    <div class="container">
        <!-- جزئیات غذا -->
		
        <div class="food-details" data-aos="fade-up">
            <div class="main-info">
                <img src="<?php echo htmlspecialchars($food['main_image'] ?? 'images/default.jpg'); ?>" alt="<?php echo htmlspecialchars($food[$lang_name_col]); ?>" class="main-image">
                <div class="info-text">
                    <h2><?php echo htmlspecialchars($food[$lang_name_col]); ?></h2>
                    <p class="price"><?php echo number_format($food['price'], $currency_Decimal); ?> <?php echo $currency; ?></p>
                    <p class="availability <?php echo $food['is_available'] ? 'available' : 'unavailable'; ?>">
                        <?php echo $food['is_available'] ? ($lang['available'] ?? 'Available') : ($lang['unavailable'] ?? 'Unavailable'); ?>
                    </p>
                    <p class="prep-time"><?php echo $lang['prep_time'] ?? 'Preparation Time'; ?>:<?php echo $food['prep_time'] ? ($food['prep_time'] . ' ' . ($lang['minutes'] ?? 'minutes')) : ($lang['not_available'] ?? 'N/A'); ?> </p>
                    <p class="description">
                        <?php echo isset($food[$lang_desc_col]) ? htmlspecialchars($food[$lang_desc_col]) : 'توضیحات موجود نیست'; ?>
                    </p>
                    <p class="ingredients">
                        <?php echo $lang['ingredients'] ?? 'Ingredients'; ?>: 
                        <?php echo isset($food['ingredients_' . $_SESSION['lang']]) ? htmlspecialchars($food['ingredients_' . $_SESSION['lang']]) : 'مواد تشکیل‌دهنده مشخص نشده'; ?>
                    </p>
                    <div class="actions">
					     <div class="quantity-control">
						 <button type="button" class="decrease" data-input-id="quantity-<?php echo $food['id']; ?>">-</button>
                         <input type="number" id="quantity-<?php echo $food['id']; ?>" name="quantities[<?php echo $food['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="0"  > 
                         <button type="button" class="increase" data-input-id="quantity-<?php echo $food['id']; ?>">+</button>
                        </div>
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
		
          document.querySelectorAll('.quantity-control .increase').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.inputId);
                let value = parseInt(input.value) || 0;
                input.value = value + 1;
            });
        });

        document.querySelectorAll('.quantity-control .decrease').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.inputId);
                let value = parseInt(input.value) || 0;
                if (value > 0) {
                    input.value = value - 1;
                }
            });
        });		

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
                    alert("<?php echo $lang['added_to_cart'] ?? 'Added to cart!'; ?>");
                } else {
                    alert(data.message || "<?php echo $lang['failed_to_add_to_cart'] ?? 'Failed to add to cart.'; ?>");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("<?php echo $lang['failed_to_add_to_cart'] ?? 'Failed to add to cart.'; ?>");
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
