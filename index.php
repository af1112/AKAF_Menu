<?php

session_start();
include 'db.php';

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

// Load language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'fa'; // زبان پیش‌فرض رو فارسی می‌کنیم
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

// Calculate cart item count
$cart_items = $_SESSION['cart'] ?? [];
$cart_count = 0;
foreach ($cart_items as $item) {
    $cart_count += $item['quantity'];
}

// Fetch footer info
$footer_info = $conn->query("SELECT * FROM footer_info LIMIT 1")->fetch_assoc();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;
$username = $is_logged_in ? $_SESSION['user']['username'] : null;

// Determine greeting based on time of day
$hour = (int)date('H'); // ساعت فعلی (0-23)
if ($hour >= 5 && $hour < 12) {
    $greeting = $lang['good_morning'] ?? 'Good Morning';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = $lang['good_afternoon'] ?? 'Good Afternoon';
} else {
    $greeting = $lang['good_evening'] ?? 'Good Evening';
}

// Fetch hero texts
$hero_texts = $conn->query("SELECT * FROM hero_texts LIMIT 1")->fetch_assoc();
$hero_title = $hero_texts['title_' . $_SESSION['lang']] ?? ($lang['welcome_message'] ?? 'Welcome to Our Restaurant!');
$hero_description = $hero_texts['description_' . $_SESSION['lang']] ?? ($lang['welcome_description'] ?? 'Discover our delicious menu and enjoy a great dining experience.');

?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <title><?php echo $lang['welcome'] ?? 'Welcome'; ?></title>
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
			<div class="language-switcher">
				<a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="index.php?lang=en">
					<img src="images/flags/en.png" alt="English" class="flag-icon"> EN
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="index.php?lang=fa">
					<img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="index.php?lang=ar">
					<img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="index.php?lang=fr">
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
    <!-- Hero Section -->
	<div class="hero-section">
		<div class="hero-content">
			<h2><?php echo htmlspecialchars($hero_title); ?></h2>
			<p><?php echo htmlspecialchars($hero_description); ?></p>
		</div>
	</div>

    <!-- Categories Section -->
    <div class="container">
        <div class="indexcategories-section">
            <h2><?php echo $lang['categories'] ?? 'Categories'; ?></h2>
            <div class="indexcategory-cards">
                <?php if ($categories->num_rows > 0): ?>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <a href="menu.php?category_id=<?php echo $category['id']; ?>" class="indexcategory-card" data-aos="fade-up">
                            <img src="<?php echo htmlspecialchars($category['image'] ?? 'images/default-category.jpg'); ?>" alt="<?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?>" class="indexcategory-image">
                            <h3><?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?></h3>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-center"><?php echo $lang['no_categories'] ?? 'No categories available at the moment.'; ?></p>
                <?php endif; ?>
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
                        <li><i class="fas fa-envelope"></i> <?php echo $lang['email'] ?? 'Email'; ?>: <a href="mailto:<?php echo $footer_info['email'] ?? 'info@myrestaurant.com'; ?>"><?php echo $footer_info['email'] ?? 'info@myrestaurant.com'; ?></a></li>
                        <li><i class="fas fa-globe"></i> <?php echo $lang['website'] ?? 'Website'; ?>: <a href="http://<?php echo $footer_info['website'] ?? 'www.myrestaurant.com'; ?>" target="_blank"><?php echo $footer_info['website'] ?? 'www.myrestaurant.com'; ?></a></li>
                    </ul>
                </div>
            </div>
            <div class="text-center mt-3">
                <p>© <?php echo date('Y'); ?> <?php echo $footer_info['company_name_' . $_SESSION['lang']] ?? 'My Restaurant'; ?>. <?php echo $lang['all_rights_reserved'] ?? 'All rights reserved.'; ?></p>
            </div>
        </div>
    </footer>
    <script>
        function changeLanguage() {
            const langSelect = document.getElementById("lang").value;
            document.documentElement.lang = langSelect;

            // تنظیم چپ‌چین و راست‌چین بر اساس زبان
            if (langSelect === "fa" || langSelect === "ar") {
                document.documentElement.dir = "rtl";
            } else {
                document.documentElement.dir = "ltr";
            }

            // ترجمه منوی پایین موبایل
            const mobileTranslations = {
                fa: ["خانه", "جستجو", "سبد خرید", "علاقه‌مندی‌ها", "منو"],
                ar: ["الرئيسية", "بحث", "سلة المشتريات", "المفضلة", "القائمة"],
                en: ["Home", "Search", "Cart", "Favorites", "Menu"],
                fr: ["Accueil", "Recherche", "Panier", "Favoris", "Menu"]
            };

            // ترجمه منوی دسکتاپ
            const desktopTranslations = {
                fa: ["خانه", "منو", "سفارش آنلاین", "تماس با ما"],
                ar: ["الرئيسية", "القائمة", "طلب عبر الإنترنت", "اتصل بنا"],
                en: ["Home", "Menu", "Order Online", "Contact Us"],
                fr: ["Accueil", "Menu", "Commander en ligne", "Contactez-nous"]
            };

            // تغییر متن منوی موبایل
            const menuTexts = document.querySelectorAll(".menu-text");
            menuTexts.forEach((item, index) => {
                item.textContent = mobileTranslations[langSelect][index];
            });

            // تغییر متن منوی دسکتاپ
            const desktopTexts = document.querySelectorAll(".desktop-menu-text");
            desktopTexts.forEach((item, index) => {
                item.textContent = desktopTranslations[langSelect][index];
            });
        }
    </script>
    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
    <!-- AOS for animations -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

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

        // Initial cart count from PHP
        const initialCartCount = <?php echo $cart_count; ?>;
        if (initialCartCount > 0) {
            const cartLink = document.querySelector('a[href="cart.php"]');
            let cartCountElement = document.querySelector('.cart-count');
            if (!cartCountElement) {
                cartLink.innerHTML += `<span class="cart-count">${initialCartCount}</span>`;
            }
        }

        fetchCartCount();
    </script>
</body>
</html>