<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

// Load currency from settings
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency'");
$stmt->execute();
$currency = $stmt->get_result()->fetch_assoc()['value'] ?? 'OMR'; // پیش‌فرض OMR اگه چیزی پیدا نشد
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency_Decimal'");
$stmt->execute();
$currency_Decimal = $stmt->get_result()->fetch_assoc()['value'] ?? '3'; // پیش‌فرض 3 اگه چیزی پیدا نشد
// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;
$username = $is_logged_in ? $_SESSION['user']['username'] : null;
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

// Fetch categories
$categories = $conn->query("SELECT * FROM categories");

// Fetch foods (filter by category if selected)
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM foods WHERE category_id = ? AND is_available = 1");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $foods = $stmt->get_result();
} else {
    $foods = $conn->query("SELECT * FROM foods WHERE is_available = 1");
}

// Calculate cart item count
$cart_items = $_SESSION['cart'] ?? [];
$cart_count = 0;
foreach ($cart_items as $item) {
    $cart_count += $item['quantity'];
}
// Determine greeting based on time of day
$hour = (int)date('H'); // ساعت فعلی (0-23)
if ($hour >= 5 && $hour < 12) {
    $greeting = $lang['good_morning'] ?? 'Good Morning';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = $lang['good_afternoon'] ?? 'Good Afternoon';
} else {
    $greeting = $lang['good_evening'] ?? 'Good Evening';
}
// Fetch footer info
$footer_info = $conn->query("SELECT * FROM footer_info LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
<body class="<?php echo $theme; ?>">
    <!-- Language Bar -->
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
                <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="menu.php?lang=en<?php  ?>">
                    <img src="images/flags/en.png" alt="English" class="flag-icon"> EN
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="menu.php?lang=fa<?php echo $category_id ? '&category_id=' . $category_id : ''; ?>">
                    <img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="menu.php?lang=ar<?php echo $category_id ? '&category_id=' . $category_id : ''; ?>">
                    <img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="menu.php?lang=fr<?php echo $category_id ? '&category_id=' . $category_id : ''; ?>">
                    <img src="images/flags/fr.png" alt="French" class="flag-icon"> FR
                </a>
            </div>
        </div>
    </div>

    <!-- Navbar -->
   <nav class="navbar navbar-expand-lg custom-navbar">
        <div class="container-fluid">
            <span class="navbar-brand">
				<?php if ($is_logged_in): ?>
                <?php echo "$greeting, $username!"; ?>
				<?php else: ?>
                <?php echo $lang['welcome'] ?? 'Welcome'; ?>
				<?php endif; ?>
			</span>
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
                        <a class="nav-link" href="index.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                            <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                            <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
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
                                <a class="nav-link" href="profile.php">
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
            <span class="cart-badge" id="cart-count">2</span>
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
    <!-- Main Content -->
    <div class="container my-5">
        <!-- Category Carousel -->
        <div class="mb-4">
            <h2 class="text-center mb-3"><?php echo $lang['menu'] ?? 'Menu'; ?></h2>
            <div class="category-carousel">
                <div class="category-items">
                    <a href="menu.php?category_id=0" class="category-item <?php echo $category_id == 0 ? 'active' : ''; ?>">
                        <i class="fas fa-th"></i>
                        <span><?php echo $lang['all_categories'] ?? 'All Categories'; ?></span>
                    </a>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <a href="menu.php?category_id=<?php echo $category['id']; ?>" class="category-item <?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                            <img src="<?php echo htmlspecialchars($category['image'] ?? 'images/default-category.jpg'); ?>" alt="<?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?>" class="category-image">
                            <span><?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?></span>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Food Items -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if ($foods->num_rows > 0): ?>
                <?php while ($food = $foods->fetch_assoc()): ?>
                    <div class="col" data-aos="fade-up">
                        <div class="card h-100">
							<a href="food_details.php?id=<?php echo htmlspecialchars($food['id'], ENT_QUOTES, 'UTF-8'); ?>">
								<img src="<?php echo htmlspecialchars($food['main_image'] ?? 'images/default.jpg'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($food['name_' . $_SESSION['lang']]); ?>">
							</a>						
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($food['name_' . $_SESSION['lang']]); ?></h5>
                                <p class="card-text text-muted"><?php echo number_format($food['price'], $currency_Decimal); ?> <?php echo $currency; ?></p>
                                <p>
                                    <span class="badge <?php echo $food['is_available'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $food['is_available'] ? ($lang['available'] ?? 'Available') : ($lang['unavailable'] ?? 'Unavailable'); ?>
                                    </span>
                                </p>
                                <div class="d-flex justify-content-between">
                                    <a href="food_details.php?id=<?php echo $food['id']; ?>" class="btn btn-primary btn-sm"><?php echo $lang['view_details'] ?? 'View Details'; ?></a>
                                    <?php if ($food['is_available']): ?>
                                        <?php if ($is_logged_in): ?>
											<button onclick="addToCart(<?php echo isset($food['id']) ? htmlspecialchars($food['id'], ENT_QUOTES, 'UTF-8') : '0'; ?>)"><?php echo $lang['add_to_cart'] ?? 'Add to Cart'; ?></button>
                                        <?php else: ?>
                                            <button class="btn btn-success btn-sm disabled" title="<?php echo $lang['login_to_add_to_cart'] ?? 'Please log in to add to cart'; ?>">
                                                <i class="fas fa-cart-plus"></i> <?php echo $lang['add_to_cart'] ?? 'Add to Cart'; ?>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-center"><?php echo $lang['no_foods'] ?? 'No foods available.'; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Image Zoom Modal -->
    <div class="modal fade" id="imageZoomModal" tabindex="-1" aria-labelledby="imageZoomModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageZoomModalLabel"><?php echo $lang['image_preview'] ?? 'Image Preview'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <img src="" class="img-fluid" id="zoomedImage" alt="Zoomed Image">
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><?php echo $lang['about_company'] ?? 'About Our Company'; ?></h5>
                    <p><?php echo $footer_info['about_company_' . $_SESSION['lang']] ?? 'We are a leading restaurant providing the best dining experience with a variety of delicious foods.'; ?></p>
                </div>
                <div class="col-md-4">
                    <h5><?php echo $lang['address'] ?? 'Address'; ?></h5>
                    <p><?php echo $footer_info['address_' . $_SESSION['lang']] ?? '123 Food Street, Flavor Town, Country'; ?></p>
                </div>
                <div class="col-md-4">
                    <h5><?php echo $lang['contact_us'] ?? 'Contact Us'; ?></h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-phone"></i> <?php echo $lang['phone'] ?? 'Phone'; ?>: <?php echo $footer_info['phone'] ?? '+123-456-7890'; ?></li>
                        <li><i class="fas fa-envelope"></i> <?php echo $lang['email'] ?? 'Email'; ?>: <?php echo $footer_info['email'] ?? 'info@myrestaurant.com'; ?></li>
                        <li><i class="fas fa-globe"></i> <?php echo $lang['website'] ?? 'Website'; ?>: <?php echo $footer_info['website'] ?? 'www.myrestaurant.com'; ?></li>
                    </ul>
                </div>
            </div>
            <div class="text-center mt-3">
                <p>© <?php echo date('Y'); ?> <?php echo $footer_info['company_name_' . $_SESSION['lang']] ?? 'My Restaurant'; ?>. <?php echo $lang['all_rights_reserved'] ?? 'All rights reserved.'; ?></p>
            </div>
        </div>
    </footer>
    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
    <!-- AOS for animations -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
	<script>
		const langMessages = {
			addedToCart: '<?php echo addslashes($lang['added_to_cart'] ?? 'Added to cart!'); ?>',
			failedToAddToCart: '<?php echo addslashes($lang['failed_to_add_to_cart'] ?? 'Failed to add to cart.'); ?>'
		};
	</script>
	<script src="scripts.js"></script>
</body>
</html>