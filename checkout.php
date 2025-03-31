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


$user_id = $_SESSION['user']['id'];

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

// Fetch cart items
$cart_items = $_SESSION['cart'] ?? [];
$cart_details = [];
$total_price = 0;

if (!empty($cart_items)) {
    $food_ids = array_keys($cart_items);
    $placeholders = implode(',', array_fill(0, count($food_ids), '?'));
    $stmt = $conn->prepare("SELECT id, name_" . $_SESSION['lang'] . " AS name, price FROM foods WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($food_ids)), ...$food_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($food = $result->fetch_assoc()) {
        $food_id = $food['id'];
        $quantity = $cart_items[$food_id];
        $subtotal = $food['price'] * $quantity;
        $total_price += $subtotal;
        $cart_details[] = [
            'id' => $food_id,
            'name' => $food['name'],
            'price' => $food['price'],
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
}
// Calculate cart item count
$cart_items = $_SESSION['cart'] ?? [];
$cart_count = 0;
foreach ($cart_items as $quantity) {
    $cart_count += $quantity;
}
// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (empty($name) || empty($address) || empty($phone)) {
        $error = $lang['fill_all_fields'] ?? 'Please fill all fields.';
    } elseif (empty($cart_items)) {
        $error = $lang['cart_empty'] ?? 'Your cart is empty.';
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Insert into orders table
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, status, shipping_name, shipping_address, shipping_phone) 
                                    VALUES (?, ?, 'Pending', ?, ?, ?)");
            $stmt->bind_param("idsss", $user_id, $total_price, $name, $address, $phone);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // Insert into order_items table
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity) VALUES (?, ?, ?)");
            foreach ($cart_items as $food_id => $quantity) {
                $stmt->bind_param("iii", $order_id, $food_id, $quantity);
                $stmt->execute();
            }

            // Commit transaction
            $conn->commit();

            // Clear cart
            unset($_SESSION['cart']);
            $success = $lang['order_placed'] ?? 'Your order has been placed successfully!';
        } catch (Exception $e) {
            $conn->rollback();
            $error = $lang['order_failed'] ?? 'Failed to place order. Please try again.';
        }
    }
}
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
    <!-- هدر -->
		<!-- Language Bar -->
	<div class="language-bar">
		<div class="container-fluid">
			<div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
				<a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="checkout.php?lang=en">
					<img src="https://flagcdn.com/20x15/gb.png" alt="English" class="flag-icon"> EN
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="checkout.php?lang=fa">
					<img src="https://flagcdn.com/20x15/ir.png" alt="Persian" class="flag-icon"> FA
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="checkout.php?lang=ar">
					<img src="https://flagcdn.com/20x15/sa.png" alt="Arabic" class="flag-icon"> AR
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="checkout.php?lang=fr">
					<img src="https://flagcdn.com/20x15/fr.png" alt="French" class="flag-icon"> FR
				</a>
			</div>
		</div>
	</div>			

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg custom-navbar">
        <div class="container-fluid">
            <span class="navbar-brand"><?php echo $lang['chekout'] ?? 'Chekout'; ?></span>
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
            <h2><?php echo $lang['checkout'] ?? 'Checkout'; ?></h2>
            <?php if (empty($cart_details)): ?>
                <p><?php echo $lang['cart_empty'] ?? 'Your cart is empty.'; ?></p>
                <a href="menu.php" class="continue-shopping">
                    <i class="fas fa-arrow-left"></i> <?php echo $lang['continue_shopping'] ?? 'Continue Shopping'; ?>
                </a>
            <?php else: ?>
                <!-- خلاصه سفارش -->
                <div class="order-summary">
                    <h3><?php echo $lang['order_summary'] ?? 'Order Summary'; ?></h3>
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th><?php echo $lang['item'] ?? 'Item'; ?></th>
                                <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                                <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                                <th><?php echo $lang['subtotal'] ?? 'Subtotal'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_details as $item): ?>
                                <tr>
                                    <td class="item-name"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo number_format($item['subtotal'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="total">
                        <?php echo $lang['total'] ?? 'Total'; ?>: <?php echo number_format($total_price, $currency_Decimal); ?> <?php echo $currency; ?>
                    </div>
                </div>

                <!-- فرم پرداخت -->
                <div class="checkout-form">
                    <h3><?php echo $lang['shipping_info'] ?? 'Shipping Information'; ?></h3>
                    <?php if (isset($error)): ?>
                        <div class="error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if (isset($success)): ?>
                        <div class="success"><?php echo $success; ?></div>
                        <a href="menu.php" class="continue-shopping">
                            <i class="fas fa-arrow-left"></i> <?php echo $lang['continue_shopping'] ?? 'Continue Shopping'; ?>
                        </a>
                    <?php else: ?>
                        <form method="POST">
                            <label for="name"><?php echo $lang['name'] ?? 'Name'; ?>:</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['user']['name']); ?>" required>
                            <label for="address"><?php echo $lang['address'] ?? 'Address'; ?>:</label>
                            <textarea id="address" name="address" required></textarea>
                            <label for="phone"><?php echo $lang['phone'] ?? 'Phone'; ?>:</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_SESSION['user']['phone']); ?>" required>
                            <button type="submit">
                                <i class="fas fa-check"></i> <?php echo $lang['place_order'] ?? 'Place Order'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

 <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init();

    // تابع برای گرفتن تعداد آیتم‌های سبد خرید
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

    // بارگذاری اولیه تعداد آیتم‌ها
    fetchCartCount();
</script>
</body>
</html>