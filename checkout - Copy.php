<?php
session_start();
include 'db.php';
file_put_contents('debug.txt', "Checkout script started at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// بررسی اینکه order_id وجود دارد
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    file_put_contents('debug.txt', "No order_id found in GET at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    header("Location: cart.php");
    exit();
}

$order_id = intval($_GET['order_id']);
file_put_contents('debug.txt', "Order ID received: " . $order_id . "\n", FILE_APPEND);

if (isset($_GET['remove_item']) && isset($_GET['food_id']) && isset($_GET['order_id'])) {
    $food_id_to_remove = intval($_GET['food_id']);
    $order_id_to_remove = intval($_GET['order_id']);
    $stmt_remove = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND food_id = ?");
    $stmt_remove->bind_param("ii", $order_id_to_remove, $food_id_to_remove);
    $stmt_remove->execute();
    header("Location: checkout.php?order_id=" . $order_id_to_remove);
    exit();
}

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

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

if (!$is_logged_in) {
    header("Location: user_login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

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

// دریافت اطلاعات سفارش از جدول orders
$stmt_order = $conn->prepare("SELECT total_price, vat_amount, grand_total FROM orders WHERE id = ? AND user_id = ?");
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();

if (!$order) {
    header("Location: cart.php");
    exit();
}

// استخراج مقادیر از سفارش
$total_price = $order['total_price'] ?? 0;
$vat_amount = $order['vat_amount'] ?? 0;
$grand_total = $order['grand_total'] ?? 0;

// دریافت آیتم‌های سفارش (اضافه کردن food_id به کوئری)
$stmt_items = $conn->prepare("SELECT oi.food_id, oi.quantity, oi.price, oi.comment, f.name_" . $_SESSION['lang'] . " AS name FROM order_items oi JOIN foods f ON oi.food_id = f.id WHERE oi.order_id = ?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$order_items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

// حذف این دو خط چون نیازی به مقدار ثابت نداریم
// $quantity = $order_items['quantity'] ?? 0;
// $comment = $order_items['comment'] ?? 0;

// Calculate cart item count (optional, since cart is empty after checkout)
$cart_count = 0; // No need to calculate this here as cart should be empty

?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['checkout'] ?? 'Checkout'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS for animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const radios = document.querySelectorAll('input[name="delivery_type"]');
            const fields = {
                'dine-in': document.getElementById('dine-in-fields'),
                'delivery': document.getElementById('delivery-fields'),
                'takeaway': document.getElementById('takeaway-fields'),
                'drive-thru': document.getElementById('drive-thru-fields'),
                'contactless': document.getElementById('contactless-fields'),
                'self-service': document.getElementById('self-service-fields'),
                'curbside': document.getElementById('curbside-fields')
            };

            radios.forEach(radio => {
                radio.addEventListener('change', function() {
                    Object.values(fields).forEach(field => field.style.display = 'none');
                    if (fields[this.value]) fields[this.value].style.display = 'block';
                });
            });
        });
    </script>
</head>
<body class="<?php echo $theme; ?>">
    <!-- هدر -->
    <!-- Language Bar -->
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
                        <a class="nav-link" href="checkout.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
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

    <div class="container">
        <!-- پرداخت -->
        <div class="checkout" data-aos="fade-up">
            <h2><?php echo $lang['order_details'] ?? 'Order Details'; ?></h2>
            <?php if (empty($order_items)): ?>
                <p><?php echo $lang['cart_empty'] ?? 'Your cart is empty.'; ?></p>
                <a href="menu.php" class="continue-shopping">
                    <i class="fas fa-arrow-left"></i> <?php echo $lang['continue_shopping'] ?? 'Continue Shopping'; ?>
                </a>
            <?php else: ?>
                <!-- خلاصه سفارش -->
                <div class="cart-summary">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th><?php echo $lang['item'] ?? 'Item'; ?></th>
                                <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                                <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                                <th><?php echo $lang['subtotal'] ?? 'Subtotal'; ?></th>
                                <th><?php echo $lang['Special_Request'] ?? 'Special Request'; ?></th>
                                <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td class="item-name"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                    <td><input type="number" name="quantities[<?php echo $item['food_id']; ?>]" value="<?php echo $item['quantity']; ?>" min="0" style="width: 50px;"></td>
                                    <td><?php echo number_format($item['price'] * $item['quantity'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                    <td class="comment-column"><input type="text" name="comments[<?php echo $item['food_id']; ?>]" value="<?php echo htmlspecialchars($item['comment'] ?? ''); ?>"></td>
                                    <td><a href="checkout.php?remove_item=1&food_id=<?php echo $item['food_id']; ?>&order_id=<?php echo $order_id; ?>" class="button"><i class="fas fa-trash"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="total">
                        <?php echo $lang['total'] ?? 'Total'; ?>: <?php echo number_format($total_price, $currency_Decimal); ?> <?php echo $currency; ?>
                        <p>VAT (<?php echo $vat_rate * 100; ?>%): <?php echo number_format($vat_amount, $currency_Decimal); ?> <?php echo $currency; ?></p>
                        <p class="grand-total"><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>: <?php echo number_format($grand_total, $currency_Decimal); ?> <?php echo $currency; ?></p>
                    </div>
                    <div class="delivery-options">
                        <h3><?php echo $lang['delivery_options'] ?? 'Delivery Options'; ?></h3>
                        <label><input type="radio" name="delivery_type" value="dine-in" required> <?php echo $lang['dine_in'] ?? 'Dine-In'; ?></label><br>
                        <label><input type="radio" name="delivery_type" value="takeaway"> <?php echo $lang['takeaway'] ?? 'Takeaway / Pick-up'; ?></label><br>
                        <label><input type="radio" name="delivery_type" value="delivery"> <?php echo $lang['delivery'] ?? 'Delivery'; ?></label><br>
                        <label><input type="radio" name="delivery_type" value="drive-thru"> <?php echo $lang['drive_thru'] ?? 'Drive-Thru'; ?></label><br>
                        <label><input type="radio" name="delivery_type" value="contactless"> <?php echo $lang['contactless_delivery'] ?? 'Contactless Delivery'; ?></label><br>
                        <label><input type="radio" name="delivery_type" value="self-service"> <?php echo $lang['self_service'] ?? 'Self Service'; ?></label><br>
                        <label><input type="radio" name="delivery_type" value="curbside"> <?php echo $lang['curbside_pickup'] ?? 'Curbside Pickup'; ?></label><br>
                        <div class="total">
                            <h3><?php echo $lang['Required_information_for_food_Delivery'] ?? 'Required information for food Delivery'; ?></h3>
                        </div>
                        <div id="dine-in-fields" style="display:none;">
                            <label><?php echo $lang['table_number'] ?? 'Table Number'; ?>:</label>
                            <input type="text" name="table_number">
                        </div>
                        <div id="delivery-fields" style="display:none;">
                            <label><?php echo $lang['address'] ?? 'Address'; ?>:</label>
                            <input type="text" name="address">
                            <label><?php echo $lang['contact_info'] ?? 'Contact Info'; ?>:</label>
                            <input type="text" name="contact_info">
                        </div>
                        <!-- می‌تونید برای گزینه‌های دیگر فیلد اضافه کنید -->
                    </div>
                </div>

                <!-- فرم پرداخت -->
                <div class="checkout-form">
                    <h3><?php echo $lang['shipping_info'] ?? 'Shipping Information'; ?></h3>
                    <form method="POST">
                        <label for="name"><?php echo $lang['name'] ?? 'Name'; ?>:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        <label for="phone"><?php echo $lang['phone'] ?? 'Phone'; ?>:</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        <button type="submit">
                            <i class="fas fa-check"></i> <?php echo $lang['place_order'] ?? 'Place Order'; ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>