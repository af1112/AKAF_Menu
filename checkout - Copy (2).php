<?php
session_start();
include 'db.php';

// بررسی order_id
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    file_put_contents('debug.txt', "No order_id found in GET at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    header("Location: cart.php");
    exit();
}

$order_id = intval($_GET['order_id']);

// حذف آیتم
if (isset($_GET['remove_item']) && isset($_GET['food_id']) && isset($_GET['order_id'])) {
    $food_id_to_remove = intval($_GET['food_id']);
    $order_id_to_remove = intval($_GET['order_id']);
    $stmt_remove = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND food_id = ?");
    $stmt_remove->bind_param("ii", $order_id_to_remove, $food_id_to_remove);
    $stmt_remove->execute();
    header("Location: checkout.php?order_id=" . $order_id_to_remove);
    exit();
}

// Load settings
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

// دریافت اطلاعات سفارش
$stmt_order = $conn->prepare("SELECT total_price, vat_amount, grand_total, status, waiter_id, estimated_time FROM orders WHERE id = ? AND user_id = ?");
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
$order_status = $order['status'] ?? 'Pending';
$waiter_id = $order['waiter_id'] ?? null;
$estimated_time = $order['estimated_time'] ?? null;

// اعمال کد تخفیف
$discount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_discount'])) {
    $discount_code = $_POST['discount_code'] ?? '';
    if ($discount_code === 'DISCOUNT10') { // تست ساده
        $discount = $grand_total * 0.10;
        $grand_total -= $discount;
        $stmt_update = $conn->prepare("UPDATE orders SET grand_total = ? WHERE id = ?");
        $stmt_update->bind_param("di", $grand_total, $order_id);
        $stmt_update->execute();
        file_put_contents('debug.txt', "Discount applied: " . $discount . " for order_id: " . $order_id . "\n", FILE_APPEND);
    } else {
        $discount_error = "Invalid discount code.";
    }
}

// پردازش Place Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $delivery_type = $_POST['delivery_type'] ?? '';
    if ($delivery_type === 'dine-in') {
        $table_number = $_POST['table_number'] ?? '';
        if (empty($table_number)) {
            $error = "Table number is required for Dine-In.";
        } else {
            $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed', table_number = ? WHERE id = ?");
            $stmt_update->bind_param("si", $table_number, $order_id);
            $stmt_update->execute();
            file_put_contents('debug.txt', "Order confirmed for Dine-In with table: " . $table_number . "\n", FILE_APPEND);
            header("Location: order_detail.php?order_id=" . $order_id);
            exit();
        }
    } elseif ($delivery_type === 'delivery') {
        $address = $_POST['address'] ?? '';
        $location = $_POST['location'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';
        $recipient_name = $_POST['recipient_name'] ?? '';
        $preferred_time = $_POST['preferred_time'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';

        if (empty($address) || empty($contact_number) || empty($recipient_name) || empty($payment_method)) {
            $error = "All delivery and payment details are required.";
        } else {
            $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed', delivery_address = ?, delivery_location = ?, delivery_contact = ?, delivery_recipient = ?, delivery_preferred_time = ?, payment_method = ? WHERE id = ?");
            $stmt_update->bind_param("ssssssi", $address, $location, $contact_number, $recipient_name, $preferred_time, $payment_method, $order_id);
            $stmt_update->execute();
            file_put_contents('debug.txt', "Order confirmed for Delivery with payment: " . $payment_method . "\n", FILE_APPEND);
            header("Location: order_detail.php?order_id=" . $order_id);
            exit();
        }
    } else {
        $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed' WHERE id = ?");
        $stmt_update->bind_param("i", $order_id);
        $stmt_update->execute();
        file_put_contents('debug.txt', "Order confirmed for " . $delivery_type . "\n", FILE_APPEND);
        header("Location: order_detail.php?order_id=" . $order_id);
        exit();
    }
}

// دریافت آیتم‌های سفارش
$stmt_items = $conn->prepare("SELECT oi.food_id, oi.quantity, oi.price, oi.comment, f.name_" . $_SESSION['lang'] . " AS name FROM order_items oi JOIN foods f ON oi.food_id = f.id WHERE oi.order_id = ?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$order_items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['checkout'] ?? 'Checkout'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- AOS for animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
	<link rel="stylesheet" href="style.css?v=<?php echo time(); ?>"> <!-- اضافه کردن نسخه برای جلوگیری از کش -->
	<!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
		#map {
			height: 400px !important;
			width: 100% !important;
			margin-bottom: 20px;
			position: relative; /* اطمینان از رندر صحیح */
		}
		.map-container {
			display: none;
			margin-top: 10px;
		}
		.show-map {
			display: block !important;
		}
		/* سایر استایل‌ها */
		/* بررسی تداخل احتمالی */
		.extra-fields.show-map #map {
			display: block !important;
			height: 400px !important;
			width: 100% !important;
		}
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
@media (max-width: 1000px) {
            .desktop-menu { display: none; }
            .menu-bar { display: flex; }
        }
        @media (max-width: 500px) {
            .navbar { display: none; }
        }
        .delivery-select { width: 100%; padding: 10px; font-size: 16px; }
        .extra-fields { display: none; margin-top: 10px; }
    </style>
</head>
<body class="<?php echo $theme; ?>">
    <!-- Language Bar -->
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
                <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="cart.php?lang=en"><img src="images/flags/en.png" alt="English" class="flag-icon"> EN</a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="cart.php?lang=fa"><img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA</a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="cart.php?lang=ar"><img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR</a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="cart.php?lang=fr"><img src="images/flags/fr.png" alt="French" class="flag-icon"> FR</a>
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
                                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?></a>
                            <?php else: ?>
                                <a class="nav-link" href="user_login.php"><i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="checkout.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>&order_id=<?php echo $order_id; ?>">
                            <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                            <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php"><i class="fas fa-shopping-cart"></i> <?php echo $lang['cart'] ?? 'Cart'; ?></a>
                    </li>
                    <?php if ($is_logged_in && !$is_rtl): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if ($is_rtl): ?>
                        <?php if ($is_logged_in): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="user_dashboard.php"><i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?></a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <?php if ($is_logged_in): ?>
                                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?></a>
                            <?php else: ?>
                                <a class="nav-link" href="user_login.php"><i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?></a>
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
    <div class="container">
        <div class="checkout" data-aos="fade-up">
            <h2><?php echo $lang['order_details'] ?? 'Order Details'; ?></h2>
            <?php if (empty($order_items)): ?>
                <p><?php echo $lang['cart_empty'] ?? 'Your cart is empty.'; ?></p>
                <a href="menu.php" class="continue-shopping"><i class="fas fa-arrow-left"></i> <?php echo $lang['continue_shopping'] ?? 'Continue Shopping'; ?></a>
            <?php else: ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (isset($discount_error)): ?>
                    <div class="alert alert-warning"><?php echo $discount_error; ?></div>
                <?php endif; ?>

                <!-- جدول آیتم‌ها -->
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th><?php echo $lang['item'] ?? 'Item'; ?></th>
                            <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                            <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                            <th><?php echo $lang['Special_Request'] ?? 'Special Request'; ?></th>
                            <th><?php echo $lang['subtotal'] ?? 'Subtotal'; ?></th>
                            <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td class="item-name"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                <td><input type="number" name="quantities[<?php echo $item['food_id']; ?>]" value="<?php echo $item['quantity']; ?>" min="0" style="width: 50px;" <?php echo $order_status === 'Preparing' ? 'disabled' : ''; ?>></td>
                                <td class="comment-column"><input type="text" name="comments[<?php echo $item['food_id']; ?>]" value="<?php echo htmlspecialchars($item['comment'] ?? ''); ?>" <?php echo $order_status === 'Preparing' ? 'disabled' : ''; ?>></td>
                                <td><?php echo number_format($item['price'] * $item['quantity'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                <td>
                                    <?php if ($order_status !== 'Preparing'): ?>
                                        <a href="checkout.php?remove_item=1&food_id=<?php echo $item['food_id']; ?>&order_id=<?php echo $order_id; ?>" class="button"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total mt-3">
                    <?php echo $lang['total'] ?? 'Total'; ?>: <?php echo number_format($total_price, $currency_Decimal); ?> <?php echo $currency; ?>
                    <p>VAT (<?php echo $vat_rate * 100; ?>%): <?php echo number_format($vat_amount, $currency_Decimal); ?> <?php echo $currency; ?></p>
                    <?php if ($discount > 0): ?>
                        <p><?php echo $lang['discount'] ?? 'Discount'; ?>: <?php echo number_format($discount, $currency_Decimal); ?> <?php echo $currency; ?></p>
                    <?php endif; ?>
                    <p class="grand-total"><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>: <?php echo number_format($grand_total, $currency_Decimal); ?> <?php echo $currency; ?></p>
                </div>

                <!-- فرم کد تخفیف -->
                <form method="POST" class="mt-3">
                    <label><?php echo $lang['discount_code'] ?? 'Discount Code'; ?>:</label>
                    <input type="text" name="discount_code" class="form-control d-inline w-50" placeholder="Enter code">
                    <button type="submit" name="apply_discount" class="btn btn-secondary"><?php echo $lang['apply'] ?? 'Apply'; ?></button>
                </form>

                <!-- فرم Place Order -->
                <?php if ($order_status === 'Pending'): ?>
                    <form method="POST" class="mt-3">
                        <h3><?php echo $lang['delivery_options'] ?? 'Delivery Options'; ?></h3>
						<select name="delivery_type" class="delivery-select" required onchange="toggleMap(this.value)">
							<option value="dine-in"><?php echo $lang['dine_in'] ?? 'Dine-In'; ?></option>
                            <option value="delivery"><?php echo $lang['delivery'] ?? 'Delivery'; ?></option>
                            <option value="takeaway"><?php echo $lang['takeaway'] ?? 'Takeaway / Pick-up'; ?></option>
                            <option value="drive-thru"><?php echo $lang['drive_thru'] ?? 'Drive-Thru'; ?></option>
                            <option value="contactless"><?php echo $lang['contactless_delivery'] ?? 'Contactless Delivery'; ?></option>
                            <option value="self-service"><?php echo $lang['self_service'] ?? 'Self Service'; ?></option>
                            <option value="curbside"><?php echo $lang['curbside_pickup'] ?? 'Curbside Pickup'; ?></option>
                        </select>

                        <!-- فیلدهای اضافی -->
                        <div id="dine-in-fields" class="extra-fields">
                            <label><?php echo $lang['table_number'] ?? 'Table Number'; ?>:</label>
                            <input type="text" name="table_number" class="form-control" required>
                        </div>
						<div id="delivery-fields" class="extra-fields">
							<div class="form-group">
								<label><?php echo $lang['address'] ?? 'Address'; ?>:</label>
								<input type="text" name="address" class="form-control" required>
							</div>
							<div class="form-group">
								<label><?php echo $lang['location'] ?? 'Location'; ?>:</label>
								<div id="map" class="map-container"></div>
								<input type="hidden" name="latitude" id="latitude">
								<input type="hidden" name="longitude" id="longitude">
							</div>
							<div class="form-group">
								<label><?php echo $lang['contact_number'] ?? 'Contact Number'; ?>:</label>
								<input type="tel" name="contact_number" class="form-control" required>
							</div>
							<div class="form-group">
								<label><?php echo $lang['recipient_name'] ?? 'Recipient Name'; ?>:</label>
								<input type="text" name="recipient_name" class="form-control" required>
							</div>
							<div class="form-group">
								<label><?php echo $lang['preferred_time'] ?? 'Preferred Delivery Time'; ?>:</label>
								<input type="datetime-local" name="preferred_time" class="form-control" required>
							</div>
							<div class="form-group">
								<label><?php echo $lang['payment_method'] ?? 'Payment Method'; ?>:</label>
								<select name="payment_method" class="form-control" required>
									<option value="cash"><?php echo $lang['cash'] ?? 'Cash'; ?></option>
									<option value="card"><?php echo $lang['card'] ?? 'Card'; ?></option>
									<option value="bank_transfer"><?php echo $lang['bank_transfer'] ?? 'Bank Transfer'; ?></option>
								</select>
							</div>
						</div>
                        <button type="submit" name="place_order" class="btn btn-primary mt-3">
                            <i class="fas fa-check"></i> <?php echo $lang['place_order'] ?? 'Place Order'; ?>
                        </button>
                    </form>
                <?php endif; ?>

                <!-- نمایش زمان تخمینی -->
                <?php if ($estimated_time): ?>
                    <div class="order-status mt-3">
                        <?php echo $lang['estimated_time'] ?? 'Estimated Delivery Time'; ?>: <?php echo $estimated_time; ?> <?php echo $lang['minutes'] ?? 'minutes'; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init();

    document.addEventListener('DOMContentLoaded', function() {
        const select = document.querySelector('select[name="delivery_type"]');
        const dineInFields = document.getElementById('dine-in-fields');
        const deliveryFields = document.getElementById('delivery-fields');
        let map = null;
        let marker = null;

        function updateFields() {
            dineInFields.style.display = 'none';
            deliveryFields.style.display = 'none';
            const allInputs = dineInFields.querySelectorAll('input, select');
            const allDeliveryInputs = deliveryFields.querySelectorAll('input, select');
            allInputs.forEach(input => input.disabled = true);
            allDeliveryInputs.forEach(input => input.disabled = true);

            if (select.value === 'dine-in') {
                dineInFields.style.display = 'block';
                allInputs.forEach(input => input.disabled = false);
                if (map) {
                    map.remove();
                    map = null;
                }
            } else if (select.value === 'delivery') {
                deliveryFields.style.display = 'block';
                allDeliveryInputs.forEach(input => input.disabled = false);
                if (!map) {
                    // اطمینان از رندر DOM قبل از فراخوانی نقشه
                    requestAnimationFrame(() => {
                        console.log("Requesting animation frame for map initialization...");
                        initializeMapWhenReady();
                    });
                }
            }
        }

        function initializeMapWhenReady() {
            const mapContainer = document.getElementById('map');
            console.log("Checking map container readiness...");
            if (!mapContainer) {
                console.error("Map element not found!");
                return;
            }

            // اعمال استایل مستقیم برای اطمینان
            mapContainer.style.height = '400px';
            mapContainer.style.width = '100%';
            mapContainer.style.display = 'block';

            // چک کردن ابعاد پس از رندر
            const checkMapReady = setInterval(() => {
                console.log("Map container dimensions:", mapContainer.offsetHeight, mapContainer.offsetWidth);
                if (mapContainer.offsetHeight > 0 && mapContainer.offsetWidth > 0) {
                    clearInterval(checkMapReady);
                    console.log("Map container is ready, initializing map...");
                    initMap();
                }
            }, 100); // هر 100 میلی‌ثانیه چک شود

            // توقف بعد از 5 ثانیه
            setTimeout(() => {
                if (!map) {
                    console.error("Map container failed to get dimensions after 5 seconds!");
                    clearInterval(checkMapReady);
                }
            }, 5000);
        }

        select.addEventListener('change', updateFields);
        updateFields(); // اولیه
    });

    function initMap() {
        console.log("initMap called");
        const mapContainer = document.getElementById('map');
        if (!mapContainer || mapContainer.offsetHeight === 0 || mapContainer.offsetWidth === 0) {
            console.error("Map container not ready or has zero dimensions!");
            return;
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const userLocation = [position.coords.latitude, position.coords.longitude];
                    console.log("User location:", userLocation);

                    window.map = L.map('map').setView(userLocation, 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(window.map);

                    window.marker = L.marker(userLocation, { draggable: true }).addTo(window.map);
                    window.marker.on('dragend', function(event) {
                        const latlng = window.marker.getLatLng();
                        document.getElementById('latitude').value = latlng.lat;
                        document.getElementById('longitude').value = latlng.lng;
                        console.log("Marker moved to:", latlng);
                    });

                    window.map.on('click', function(event) {
                        window.marker.setLatLng(event.latlng);
                        document.getElementById('latitude').value = event.latlng.lat;
                        document.getElementById('longitude').value = event.latlng.lng;
                        console.log("Map clicked at:", event.latlng);
                    });
                },
                (error) => {
                    console.log("Geolocation error:", error);
                    const defaultLocation = [35.6892, 51.3890];
                    window.map = L.map('map').setView(defaultLocation, 12);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(window.map);

                    window.marker = L.marker(defaultLocation, { draggable: true }).addTo(window.map);
                    window.marker.on('dragend', function(event) {
                        const latlng = window.marker.getLatLng();
                        document.getElementById('latitude').value = latlng.lat;
                        document.getElementById('longitude').value = latlng.lng;
                        console.log("Marker moved to:", latlng);
                    });

                    window.map.on('click', function(event) {
                        window.marker.setLatLng(event.latlng);
                        document.getElementById('latitude').value = event.latlng.lat;
                        document.getElementById('longitude').value = event.latlng.lng;
                        console.log("Map clicked at:", event.latlng);
                    });
                }
            );
        } else {
            console.log("Geolocation not supported");
            const defaultLocation = [35.6892, 51.3890];
            window.map = L.map('map').setView(defaultLocation, 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(window.map);

            window.marker = L.marker(defaultLocation, { draggable: true }).addTo(window.map);
            window.marker.on('dragend', function(event) {
                const latlng = window.marker.getLatLng();
                document.getElementById('latitude').value = latlng.lat;
                document.getElementById('longitude').value = latlng.lng;
                console.log("Marker moved to:", latlng);
            });

            window.map.on('click', function(event) {
                window.marker.setLatLng(event.latlng);
                document.getElementById('latitude').value = event.latlng.lat;
                document.getElementById('longitude').value = event.latlng.lng;
                console.log("Map clicked at:", event.latlng);
            });
        }
    }

    function toggleMap(value) {
        const deliveryFields = document.getElementById('delivery-fields');
        const mapContainer = document.getElementById('map');
        if (value === 'delivery') {
            deliveryFields.classList.add('show-map');
            if (mapContainer && !window.map) {
                console.log("Initializing map...");
                initializeMapWhenReady();
            }
        } else {
            deliveryFields.classList.remove('show-map');
            if (window.map) {
                window.map.remove();
                window.map = null;
            }
        }
    }
</script>
</body>
</html>
<?php