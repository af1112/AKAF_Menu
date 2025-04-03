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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS for animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>"> <!-- اضافه کردن نسخه برای جلوگیری از کش -->
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
                            <a class="nav-link" href="profile.php">
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