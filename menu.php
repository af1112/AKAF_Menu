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
foreach ($cart_items as $quantity) {
    $cart_count += $quantity;
}

// Fetch footer info
$footer_info = $conn->query("SELECT * FROM footer_info LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['menu'] ?? 'Menu'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS for animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?php echo $theme; ?>">
    <!-- Language Bar -->
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
                <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="menu.php?lang=en<?php echo $category_id ? '&category_id=' . $category_id : ''; ?>">
                    <img src="https://flagcdn.com/20x15/gb.png" alt="English" class="flag-icon"> EN
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="menu.php?lang=fa<?php echo $category_id ? '&category_id=' . $category_id : ''; ?>">
                    <img src="https://flagcdn.com/20x15/ir.png" alt="Persian" class="flag-icon"> FA
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="menu.php?lang=ar<?php echo $category_id ? '&category_id=' . $category_id : ''; ?>">
                    <img src="https://flagcdn.com/20x15/sa.png" alt="Arabic" class="flag-icon"> AR
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="menu.php?lang=fr<?php echo $category_id ? '&category_id=' . $category_id : ''; ?>">
                    <img src="https://flagcdn.com/20x15/fr.png" alt="French" class="flag-icon"> FR
                </a>
            </div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg custom-navbar">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo $lang['menu'] ?? 'Menu'; ?></span>
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
                        <a class="nav-link" href="menu.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?><?php echo $category_id ? '&category_id=' . $category_id : ''; ?>">
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
                            <img src="<?php echo htmlspecialchars($food['main_image'] ?? 'images/default.jpg'); ?>" class="card-img-top zoomable-image" alt="<?php echo htmlspecialchars($food['name_' . $_SESSION['lang']]); ?>" data-full-image="<?php echo htmlspecialchars($food['main_image'] ?? 'images/default.jpg'); ?>">
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
                                            <button class="btn btn-success btn-sm" onclick="addToCart(<?php echo $food['id']; ?>)">
                                                <i class="fas fa-cart-plus"></i> <?php echo $lang['add_to_cart'] ?? 'Add to Cart'; ?>
                                            </button>
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

        function addToCart(foodId) {
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'food_id=' + foodId + '&quantity=1'
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

        // Image Zoom Functionality
        document.querySelectorAll('.zoomable-image').forEach(image => {
            image.addEventListener('click', function() {
                const fullImage = this.getAttribute('data-full-image');
                const modalImage = document.getElementById('zoomedImage');
                modalImage.src = fullImage;
                const modal = new bootstrap.Modal(document.getElementById('imageZoomModal'));
                modal.show();
            });
        });

        // Category Carousel Drag Functionality
        const categoryCarousel = document.querySelector('.category-items');
        let isDown = false;
        let startX;
        let scrollLeft;

        categoryCarousel.addEventListener('mousedown', (e) => {
            isDown = true;
            categoryCarousel.classList.add('active');
            startX = e.pageX - categoryCarousel.offsetLeft;
            scrollLeft = categoryCarousel.scrollLeft;
        });

        categoryCarousel.addEventListener('mouseleave', () => {
            isDown = false;
            categoryCarousel.classList.remove('active');
        });

        categoryCarousel.addEventListener('mouseup', () => {
            isDown = false;
            categoryCarousel.classList.remove('active');
        });

        categoryCarousel.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - categoryCarousel.offsetLeft;
            const walk = (x - startX) * 2; // سرعت اسکرول
            categoryCarousel.scrollLeft = scrollLeft - walk;
        });

        fetchCartCount();
    </script>
</body>
</html>