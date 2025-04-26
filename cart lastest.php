<?php
session_start();
include 'db.php';

// Load currency from settings
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency'");
$stmt->execute();
$currency = $stmt->get_result()->fetch_assoc()['value'] ?? 'OMR';

$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency_Decimal'");
$stmt->execute();
$currency_Decimal = $stmt->get_result()->fetch_assoc()['value'] ?? '3';

$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'vat_rate'");
$stmt->execute();
$vat_rate = floatval($stmt->get_result()->fetch_assoc()['value'] ?? 0.0);

$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'apply_vat'");
$stmt->execute();
$apply_vat = intval($stmt->get_result()->fetch_assoc()['value'] ?? 0);

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

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

// Detect language direction
$rtl_languages = ['fa', 'ar'];
$is_rtl = in_array($_SESSION['lang'], $rtl_languages);
$direction = $is_rtl ? 'rtl' : 'ltr';

// Debug: Log the language and RTL status
file_put_contents('debug.txt', "Language: " . $_SESSION['lang'] . ", Is RTL: " . ($is_rtl ? 'Yes' : 'No') . "\n", FILE_APPEND);

// Handle item removal
if (isset($_GET['remove_item']) && $is_logged_in) {
    $food_id = intval($_GET['food_id']);
    try {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND food_id = ?");
        $stmt->bind_param("ii", $user_id, $food_id);
        $stmt->execute();
        
        // Update session cart
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['id'] == $food_id) {
                unset($_SESSION['cart'][$key]);
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        header("Location: cart.php");
        exit();
    } catch (Exception $e) {
        file_put_contents('debug.txt', "Error removing item: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Load cart items
if ($is_logged_in) {
    $stmt = $conn->prepare("SELECT c.food_id, c.quantity, c.price, c.comment, f.name_" . $_SESSION['lang'] . " AS name FROM cart c JOIN foods f ON c.food_id = f.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $_SESSION['cart'] = [];
    foreach ($cart_items as $item) {
        $_SESSION['cart'][] = [
            'id' => $item['food_id'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'name' => $item['name'],
            'comment' => $item['comment'],
        ];
    }
    $total_price = 0;
    $vat_amount = 0;
    $grand_total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total_price += $item['price'] * $item['quantity'];
    }
    $vat_amount = $apply_vat ? $total_price * $vat_rate : 0;
    $grand_total = $total_price + $vat_amount;
} else {
    $_SESSION['cart'] = [];
    file_put_contents('debug.txt', "User ID not set\n", FILE_APPEND);
}

// Determine greeting based on time of day
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = $lang['good_morning'] ?? 'Good Morning';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = $lang['good_afternoon'] ?? 'Good Afternoon';
} else {
    $greeting = $lang['good_evening'] ?? 'Good Evening';
}

// Calculate cart item count
$cart_items = $_SESSION['cart'] ?? [];
$cart_count = 0;
foreach ($cart_items as $item) {
    $cart_count += $item['quantity'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents('debug.txt', "POST REQUEST detected\n", FILE_APPEND);
    file_put_contents('debug.txt', "POST Data: " . print_r($_POST, true) . "\n", FILE_APPEND);

    if (!$is_logged_in) {
        file_put_contents('debug.txt', "User is not logged in\n", FILE_APPEND);
        echo $lang['please_login'] ?? 'Please login to checkout.';
        exit();
    }

    $conn->begin_transaction();
    try {
        // Update cart items
        if (!empty($_POST['quantities'])) {
            foreach ($_POST['quantities'] as $food_id => $quantity) {
                $quantity = intval($quantity);
                $comment = isset($_POST['comments'][$food_id]) ? trim($_POST['comments'][$food_id]) : '';
                $food_id = intval($food_id);

                foreach ($_SESSION['cart'] as $key => &$item) {
                    if ($item['id'] == $food_id) {
                        if ($quantity <= 0) {
                            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND food_id = ?");
                            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                            $stmt->bind_param("ii", $user_id, $food_id);
                            if (!$stmt->execute()) throw new Exception("Delete failed: " . $conn->error);
                            unset($_SESSION['cart'][$key]);
                        } else {
                            $stmt = $conn->prepare("UPDATE cart SET quantity = ?, comment = ? WHERE user_id = ? AND food_id = ?");
                            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                            $stmt->bind_param("issi", $quantity, $comment, $user_id, $food_id);
                            if (!$stmt->execute()) throw new Exception("Update failed: " . $conn->error);
                            $item['quantity'] = $quantity;
                            $item['comment'] = $comment;
                        }
                        break;
                    }
                }
				unset($item); // 🛠️ رفع باگ تکرار در order_items
            }
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
        $conn->commit();

        if (isset($_POST['checkout'])) {
            $stmt_create_order = $conn->prepare("INSERT INTO orders (user_id, total_price, vat_amount, grand_total) VALUES (?, ?, ?, ?)");
            if (!$stmt_create_order) throw new Exception("Prepare failed for creating order: " . $conn->error);
            $stmt_create_order->bind_param("iddd", $user_id, $total_price, $vat_amount, $grand_total);
            if (!$stmt_create_order->execute()) throw new Exception("Failed to create order: " . $conn->error);
            $order_id = $conn->insert_id;
			file_put_contents('debug.txt', "Cart items before checkout: " . print_r($_SESSION['cart'], true) . "\n", FILE_APPEND);
            foreach ($_SESSION['cart'] as $item) {
                $comment = $item['comment'] ?? '';
                $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price, comment) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt_items) throw new Exception("Prepare failed for order items: " . $conn->error);
                $stmt_items->bind_param("iiids", $order_id, $item['id'], $item['quantity'], $item['price'], $comment);
                if (!$stmt_items->execute()) throw new Exception("Failed to add order item for food ID " . $item['id'] . ": " . $conn->error);
                $stmt_items->close();
            }

            $stmt_clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt_clear_cart->bind_param("i", $user_id);
            $stmt_clear_cart->execute();

            $_SESSION['cart'] = [];
            $conn->commit();
            file_put_contents('debug.txt', "Order created with ID: $order_id at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            header("Location: checkout.php?order_id=" . $order_id);
            exit();
        } elseif (isset($_POST['continue'])) {
            header("Location: menu.php");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        file_put_contents('debug.txt', "Error during checkout: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        echo "An error occurred. Please try again.";
        exit();
    }
}

// Detect if mobile device for rendering
$is_mobile = isset($_SERVER['HTTP_USER_AGENT']) && (stripos($_SERVER['HTTP_USER_AGENT'], 'mobile') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'android') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'iphone') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'ipad') !== false);
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['cart'] ?? 'Cart'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            padding-bottom: 70px;
        }

        .desktop-menu a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            font-size: 16px;
        }

        .menu-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #ffffff;
            display: none;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
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
            margin-bottom: 2px;
        }

        .menu-bar a.active {
            color: #2c3e50;
            font-weight: bold;
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
            line-height: 16px;
            text-align: center;
            border-radius: 50%;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .cart-table th, .cart-table td {
            padding: 12px;
            text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>;
            border-bottom: 1px solid #ddd;
        }

        .cart-table th {
            background: #f8f8f8;
            font-weight: bold;
        }

        .cart-table th.special-request, .cart-table td.special-request {
            width: 250px;
        }

        .cart-table input[type="number"] {
            width: 60px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .cart-table input[type="text"] {
            width: 100%;
            min-width: 200px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .cart-table .item-name a {
            color: #007bff;
            text-decoration: none;
        }

        .cart-table .item-name a:hover {
            text-decoration: underline;
        }

        .cart-table .remove-btn {
            color: #e74c3c;
            text-decoration: none;
            font-size: 16px;
            background: none !important;
            padding: 5px;
        }

        .cart-table .remove-btn:hover {
            color: #c0392b;
        }

        .cart-summary {
            margin-top: 20px;
        }

        /* چیدمان جمع‌ها */
        .cart-summary .cart-total {
            font-size: 18px;
            margin-bottom: 10px;
            display: flex;
            flex-direction: column;
        }

        .cart-summary .cart-total .summary-row {
            display: flex;
            width: 100%;
            max-width: 300px;
            margin-bottom: 5px;
        }

        /* تنظیمات پیش‌فرض برای LTR */
        .cart-summary .cart-total.ltr {
            align-items: flex-end;
        }

        .cart-summary .cart-total.ltr .summary-row {
            justify-content: flex-end;
        }

        /* تنظیمات برای RTL */
        .cart-summary .cart-total.rtl {
            align-items: flex-start !important;
        }

        .cart-summary .cart-total.rtl .summary-row {
            justify-content: flex-start !important;
        }

        .cart-summary .cart-total .summary-row .label {
            flex: 1;
            text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>;
            margin-right: 10px;
            margin-left: 10px;
        }

        .cart-summary .cart-total .summary-row .value {
            flex: 1;
            text-align: right;
        }

        .cart-summary .cart-total .summary-row .currency {
            width: 50px;
            text-align: left;
            margin-left: 5px;
        }

        .cart-summary .grand-total .summary-row {
            font-weight: bold;
            font-size: 20px;
        }

        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: <?php echo $is_rtl ? 'flex-start' : 'flex-end'; ?>;
        }

        .continue-shopping, .checkout-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .continue-shopping {
            background: #6c757d;
            color: white;
        }

        .continue-shopping:hover {
            background: #5a6268;
        }

        .checkout-btn {
            background: #28a745;
            color: white;
        }

        .checkout-btn:hover {
            background: #218838;
        }

        /* استایل‌های موبایل */
        .cart-table-mobile {
            display: none;
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

        .cart-item-mobile .item-name a {
            color: #007bff;
            text-decoration: none;
        }

        .cart-item-mobile .item-name a:hover {
            text-decoration: underline;
        }

        .cart-item-mobile .details-btn {
            display: inline-block;
            margin-top: 5px;
            padding: 5px 10px;
            background: #007bff;
            color: white !important;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }

        .cart-item-mobile .details-btn:hover {
            background: #0056b3;
        }

        @media (max-width: 1000px) {
            .desktop-menu {
                display: none;
            }
            .menu-bar {
                display: flex;
            }
        }

        @media (max-width: 768px) {
            .cart-table {
                display: none;
            }
            .cart-table-mobile {
                display: block;
            }
            .cart-item-mobile {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .cart-item-mobile .item-name {
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .cart-item-mobile .item-detail {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                font-size: 14px;
            }
            .cart-item-mobile input[type="text"] {
                width: 100%;
                padding: 8px;
                font-size: 14px;
                margin-top: 5px;
            }
            .cart-item-mobile .remove-btn {
                color: #e74c3c;
                font-size: 18px;
                text-align: center;
                display: block;
                margin-top: 10px;
                text-decoration: none;
            }
            .cart-item-mobile .remove-btn:hover {
                color: #c0392b;
            }
            .cart-summary .cart-total {
                font-size: 16px;
                text-align: center;
                align-items: center;
            }
            .cart-summary .cart-total .summary-row {
                justify-content: center;
            }
            .cart-summary .grand-total .summary-row {
                font-size: 18px;
            }
            .button-container {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            .continue-shopping, .checkout-btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
                padding: 12px;
                font-size: 15px;
            }
        }

        @media (max-width: 500px) {
            .navbar {
                display: none;
            }
            .cart-item-mobile .item-name {
                font-size: 14px;
            }
            .cart-item-mobile .item-detail {
                font-size: 13px;
            }
            .cart-item-mobile input[type="text"] {
                font-size: 13px;
            }
            .menu-bar a i {
                font-size: 20px;
            }
            .menu-bar a {
                font-size: 9px;
            }
            .cart-badge {
                width: 14px;
                height: 14px;
                line-height: 14px;
                font-size: 9px;
                right: 10px;
            }
        }
    </style>
</head>
<body class="<?php echo $theme; ?>">
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
                <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="cart.php?lang=en">
                    <img src="images/flags/en.png" alt="English" class="flag-icon"> EN
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="cart.php?lang=fa">
                    <img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="cart.php?lang=ar">
                    <img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="cart.php?lang=fr">
                    <img src="images/flags/fr.png" alt="French" class="flag-icon"> FR
                </a>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg custom-navbar">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse <?php echo $is_rtl ? '' : 'justify-content-end'; ?>" id="navbarNav">
                <ul class="navbar-nav <?php echo $is_rtl ? 'nav-rtl' : ''; ?>">
                    <?php if ($is_rtl): ?>
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
					<li class="nav-item">
						<a class="nav-link" href="index.php">
							<i class="fas fa-bars"></i> <?php echo $lang['home'] ?? 'Home'; ?>
						</a>
					</li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
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
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user_dashboard.php">
                                <i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (!$is_rtl): ?>
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
        <!-- Debug: Display RTL status -->
        <div class="cart" data-aos="fade-up">
            <h2><?php echo $lang['shopping_cart'] ?? 'Shopping Cart'; ?></h2>
            <?php 
            file_put_contents('debug.txt', "Cart items count: " . count($_SESSION['cart']) . "\n", FILE_APPEND);
            file_put_contents('debug.txt', "Cart items: " . print_r($_SESSION['cart'], true) . "\n", FILE_APPEND);
            ?>
            <?php if (!empty($_SESSION['cart'])): ?>
                <section class="cart-summary">
                    <form method="POST" action="cart.php">
                        <?php if (!$is_mobile): ?>
                            <!-- نمایش جدول برای دسکتاپ -->
                            <table class="cart-table">
                                <thead>
                                    <tr>
                                        <th><?php echo $lang['food'] ?? 'Food'; ?></th>
                                        <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                                        <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                                        <th><?php echo $lang['subtotal'] ?? 'SubTotal'; ?></th>
                                        <th class="special-request"><?php echo $lang['Special_Request'] ?? 'Special Request'; ?></th>
                                        <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['cart'] as $item): ?>
                                        <tr>
                                            <td class="item-name">
                                                <a href="food_details.php?id=<?php echo $item['id']; ?>">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <input type="number" name="quantities[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="0" style="width: 60px;">
                                            </td>
                                            <td><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                            <td><?php echo number_format($item['price'] * $item['quantity'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                            <td class="special-request">
                                                <input type="text" name="comments[<?php echo $item['id']; ?>]" value="<?php echo htmlspecialchars($item['comment'] ?? ''); ?>">
                                            </td>
                                            <td>
                                                <a href="cart.php?remove_item=1&food_id=<?php echo $item['id']; ?>" class="remove-btn">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <!-- نمایش کارت‌ها برای موبایل -->
                            <div class="cart-table-mobile">
                                <?php foreach ($_SESSION['cart'] as $item): ?>
                                    <div class="cart-item-mobile">
                                        <div class="item-name">
                                            <a href="food_details.php?id=<?php echo $item['id']; ?>">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </a>
                                            <a href="food_details.php?id=<?php echo $item['id']; ?>" class="details-btn">
                                                <?php echo $lang['details'] ?? 'Details'; ?>
                                            </a>
                                        </div>
                                        <div class="item-detail">
                                            <span><?php echo $lang['quantity'] ?? 'Quantity'; ?>:</span>
                                            <div class="quantity-control">
                                                <button type="button" class="decrease" data-input-id="quantity-<?php echo $item['id']; ?>">-</button>
                                                <input type="number" id="quantity-<?php echo $item['id']; ?>" name="quantities[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="0">
                                                <button type="button" class="increase" data-input-id="quantity-<?php echo $item['id']; ?>">+</button>
                                            </div>
                                        </div>
                                        <div class="item-detail">
                                            <span><?php echo $lang['price'] ?? 'Price'; ?>:</span>
                                            <span><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></span>
                                        </div>
                                        <div class="item-detail">
                                            <span><?php echo $lang['subtotal'] ?? 'SubTotal'; ?>:</span>
                                            <span><?php echo number_format($item['price'] * $item['quantity'], $currency_Decimal); ?> <?php echo $currency; ?></span>
                                        </div>
                                        <div class="item-detail">
                                            <span><?php echo $lang['Special_Request'] ?? 'Special Request'; ?>:</span>
                                        </div>
                                        <input type="text" name="comments[<?php echo $item['id']; ?>]" value="<?php echo htmlspecialchars($item['comment'] ?? ''); ?>">
                                        <a href="cart.php?remove_item=1&food_id=<?php echo $item['id']; ?>" class="remove-btn">
                                            <i class="fas fa-trash"></i> <?php echo $lang['remove'] ?? 'Remove'; ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="cart-summary">
                            <div class="cart-total <?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
                                <div class="summary-row">
                                    <span class="label"><?php echo $lang['total'] ?? 'Total'; ?>:</span>
                                    <span class="value"><?php echo number_format($total_price, $currency_Decimal); ?></span>
                                    <span class="currency"><?php echo $currency; ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="label"><?php echo $lang['VAT'] ?? 'VAT'; ?> (<?php echo $vat_rate * 100; ?>%):</span>
                                    <span class="value"><?php echo number_format($vat_amount, $currency_Decimal); ?></span>
                                    <span class="currency"><?php echo $currency; ?></span>
                                </div>
                                <div class="summary-row grand-total">
                                    <span class="label"><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>:</span>
                                    <span class="value"><?php echo number_format($grand_total, $currency_Decimal); ?></span>
                                    <span class="currency"><?php echo $currency; ?></span>
                                </div>
                            </div>

                            <div class="button-container">
                                <button type="submit" name="continue" value="1" class="continue-shopping">
                                    <i class="fas fa-arrow-left"></i> <?php echo $lang['continue_shopping'] ?? 'Continue Shopping'; ?>
                                </button>
                                <button type="submit" name="checkout" value="1" class="checkout-btn">
                                    <i class="fas fa-credit-card"></i> <?php echo $lang['checkout'] ?? 'Checkout'; ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </section>
            <?php else: ?>
                <p><?php echo $lang['cart_empty'] ?? 'Your cart is empty.'; ?></p>
                <a href="menu.php" class="continue-shopping">
                    <i class="fas fa-arrow-left"></i> <?php echo $lang['continue_shopping'] ?? 'Continue Shopping'; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

        // مدیریت دکمه‌های افزایش و کاهش تعداد توی موبایل
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

        function fetchCartCount() {
            fetch('get_cart_count.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response from get_cart_count.php:', text);
                    const data = JSON.parse(text);
                    const cartCountElements = document.querySelectorAll('.cart-count, .cart-badge');
                    cartCountElements.forEach(element => {
                        if (data.count > 0) {
                            element.textContent = data.count;
                            element.style.display = 'inline-block';
                        } else {
                            element.style.display = 'none';
                        }
                    });
                })
                .catch(error => console.error('Error in fetchCartCount:', error));
        }

        fetchCartCount();
    </script>
</body>
</html>