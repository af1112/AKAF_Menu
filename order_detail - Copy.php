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

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: cart.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

if (!$is_logged_in) {
    header("Location: user_login.php");
    exit();
}

// Load settings
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency'");
$stmt->execute();
$currency = $stmt->get_result()->fetch_assoc()['value'] ?? 'OMR';
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency_Decimal'");
$stmt->execute();
$currency_Decimal = $stmt->get_result()->fetch_assoc()['value'] ?? '3';

// Load order
$stmt_order = $conn->prepare("SELECT total_price, vat_amount, grand_total, status, waiter_id, estimated_time, table_number FROM orders WHERE id = ? AND user_id = ?");
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();

if (!$order) {
    header("Location: cart.php");
    exit();
}

$total_price = $order['total_price'] ?? 0;
$vat_amount = $order['vat_amount'] ?? 0;
$grand_total = $order['grand_total'] ?? 0;
$order_status = $order['status'] ?? 'Confirmed';
$waiter_id = $order['waiter_id'] ?? null;
$estimated_time = $order['estimated_time'] ?? null;
$table_number = $order['table_number'] ?? '';

// Load waiter
$waiter = null;
if ($waiter_id) {
    $stmt_waiter = $conn->prepare("SELECT name, image_url FROM waiters WHERE id = ?");
    $stmt_waiter->bind_param("i", $waiter_id);
    $stmt_waiter->execute();
    $waiter = $stmt_waiter->get_result()->fetch_assoc();
}

// Load order items
$stmt_items = $conn->prepare("SELECT oi.food_id, oi.quantity, oi.price, oi.comment, f.name_" . $_SESSION['lang'] . " AS name FROM order_items oi JOIN foods f ON oi.food_id = f.id WHERE oi.order_id = ?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$order_items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

// Send request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    $request_text = $_POST['request_text'] ?? '';
    if (!empty($request_text)) {
        file_put_contents('debug.txt', "Request sent: " . $request_text . " for order_id: " . $order_id . "\n", FILE_APPEND);
    }
}

// Call waiter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['call_waiter'])) {
    file_put_contents('debug.txt', "Waiter called for order_id: " . $order_id . "\n", FILE_APPEND);
}

?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['cart'] ?? 'Cart'; ?></title>
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
<body class="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
    <!-- Language Bar -->
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
                <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="order_detail.php?lang=en">
                    <img src="images/flags/en.png" alt="English" class="flag-icon"> EN
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="order_detail.php?lang=fa">
                    <img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="order_detail.php?lang=ar">
                    <img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="order_detail.php?lang=fr">
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
                        <a class="nav-link" href="checkout.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                            <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                            <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
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

    <div class="container mt-4">
        <h2><?php echo $lang['order_detail'] ?? 'Order Detail'; ?></h2>

        <!-- جزئیات سفارش -->
        <div class="section">
            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo $lang['item'] ?? 'Item'; ?></th>
                        <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                        <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                        <th><?php echo $lang['subtotal'] ?? 'Subtotal'; ?></th>
                        <th><?php echo $lang['Special_Request'] ?? 'Special Request'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                            <td><?php echo number_format($item['price'] * $item['quantity'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                            <td><?php echo htmlspecialchars($item['comment'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><?php echo $lang['total'] ?? 'Total'; ?>: <?php echo number_format($total_price, $currency_Decimal); ?> <?php echo $currency; ?></p>
            <p>VAT: <?php echo number_format($vat_amount, $currency_Decimal); ?> <?php echo $currency; ?></p>
            <p class="grand-total"><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>: <?php echo number_format($grand_total, $currency_Decimal); ?> <?php echo $currency; ?></p>
        </div>

        <!-- اطلاعات گارسون -->
        <?php if ($waiter): ?>
            <div class="section waiter-info">
                <h3><?php echo $lang['your_waiter'] ?? 'Your Waiter'; ?></h3>
                <img src="<?php echo htmlspecialchars($waiter['image_url']); ?>" alt="<?php echo htmlspecialchars($waiter['name']); ?>">
                <p><?php echo $lang['name'] ?? 'Name'; ?>: <?php echo htmlspecialchars($waiter['name']); ?></p>
            </div>
        <?php endif; ?>

        <!-- وضعیت آماده‌سازی -->
        <div class="section">
            <h3><?php echo $lang['order_status'] ?? 'Order Status'; ?></h3>
            <p><?php echo $order_status; ?></p>
            <?php if ($estimated_time): ?>
                <p><?php echo $lang['estimated_time'] ?? 'Estimated Delivery Time'; ?>: <?php echo $estimated_time; ?> <?php echo $lang['minutes'] ?? 'minutes'; ?></p>
            <?php endif; ?>
            <?php if ($table_number): ?>
                <p><?php echo $lang['table_number'] ?? 'Table Number'; ?>: <?php echo $table_number; ?></p>
            <?php endif; ?>
        </div>

        <!-- درخواست جدید -->
        <div class="section">
            <h3><?php echo $lang['send_request'] ?? 'Send Request'; ?></h3>
            <form method="POST">
                <textarea name="request_text" class="form-control" rows="3" placeholder="<?php echo $lang['enter_request'] ?? 'Enter your request'; ?>"></textarea>
                <button type="submit" name="send_request" class="btn btn-secondary mt-2"><i class="fas fa-paper-plane"></i> <?php echo $lang['submit'] ?? 'Submit'; ?></button>
            </form>
        </div>

        <!-- فراخوانی گارسون -->
        <div class="section">
            <h3><?php echo $lang['call_waiter'] ?? 'Call Waiter'; ?></h3>
            <form method="POST">
                <button type="submit" name="call_waiter" class="btn btn-warning"><i class="fas fa-bell"></i> <?php echo $lang['call'] ?? 'Call'; ?></button>
            </form>
        </div>

        <!-- پرداخت -->
        <div class="section">
            <h3><?php echo $lang['payment'] ?? 'Payment'; ?></h3>
            <p><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>: <?php echo number_format($grand_total, $currency_Decimal); ?> <?php echo $currency; ?></p>
            <button class="btn btn-success"><i class="fas fa-credit-card"></i> <?php echo $lang['pay_now'] ?? 'Pay Now'; ?></button>
        </div>
    </div>
</body>
</html>