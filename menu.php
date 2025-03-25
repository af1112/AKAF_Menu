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

// Fetch foods based on filter
$filter = $_GET['filter'] ?? 'all';
$category_id = $_GET['category_id'] ?? 0;

$query = "SELECT * FROM foods WHERE is_available = 1";
if ($category_id) {
    $query .= " AND category_id = " . intval($category_id);
}
if ($filter === 'less_than_10') {
    $query .= " AND price < 10";
} elseif ($filter === 'less_than_20') {
    $query .= " AND price < 20";
}

$foods = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['menu'] ?? 'Menu'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?php echo $theme; ?>">
    <div class="header">
        <h1><?php echo $lang['menu'] ?? 'Menu'; ?></h1>
        <div class="controls">
            <select onchange="window.location='menu.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="menu.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i> <?php echo $lang['cart'] ?? 'Cart'; ?></a>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="profile.php"><i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?></a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?></a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <!-- Categories -->
        <div class="category-tabs">
            <a href="menu.php?filter=all" class="tab <?php echo $filter === 'all' && !$category_id ? 'active' : ''; ?>">
                <?php echo $lang['all'] ?? 'All'; ?>
            </a>
            <?php while ($category = $categories->fetch_assoc()): ?>
                <a href="menu.php?category_id=<?php echo $category['id']; ?>" class="tab <?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                    <?php if ($category['image']): ?>
                        <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?>" style="max-width: 30px; margin-right: 10px;">
                    <?php endif; ?>
                    <?php echo htmlspecialchars($category['name_' . $_SESSION['lang']]); ?>
                </a>
            <?php endwhile; ?>
            <?php $categories->data_seek(0); // Reset pointer for reuse ?>
        </div>

        <!-- Filters -->
        <div class="filters">
            <button class="<?php echo $filter === 'less_than_10' ? 'active' : ''; ?>" onclick="window.location='menu.php?filter=less_than_10'">
                <?php echo $lang['less_than_10'] ?? 'Less than $10'; ?>
            </button>
            <button class="<?php echo $filter === 'less_than_20' ? 'active' : ''; ?>" onclick="window.location='menu.php?filter=less_than_20'">
                <?php echo $lang['less_than_20'] ?? 'Less than $20'; ?>
            </button>
        </div>

        <!-- Foods -->
        <div class="menu-items">
            <?php if ($foods->num_rows > 0): ?>
                <?php while ($food = $foods->fetch_assoc()): ?>
                    <div class="food-card">
                        <img src="<?php echo htmlspecialchars($food['main_image'] ?? 'images/default_food.jpg'); ?>" alt="<?php echo htmlspecialchars($food['name_' . $_SESSION['lang']] ?? 'Unnamed Food'); ?>">
                        <div class="info">
                            <h3><?php echo htmlspecialchars($food['name_' . $_SESSION['lang']] ?? 'Unnamed Food'); ?></h3>
                            <p class="price">$<?php echo number_format($food['price'] ?? 0, 2); ?></p>
                            <div class="actions">
                                <a href="food_details.php?id=<?php echo $food['id']; ?>"><?php echo $lang['details'] ?? 'Details'; ?></a>
                                <button class="add-to-cart" data-id="<?php echo $food['id']; ?>">
                                    <i class="fas fa-cart-plus"></i> <?php echo $lang['add_to_cart'] ?? 'Add to Cart'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p><?php echo $lang['no_foods'] ?? 'No foods available.'; ?></p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', () => {
                const foodId = button.getAttribute('data-id');
                fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `food_id=${foodId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Added to cart!');
                    } else {
                        alert('Failed to add to cart.');
                    }
                });
            });
        });
    </script>
</body>
</html>