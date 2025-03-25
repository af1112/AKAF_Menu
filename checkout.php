<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header("Location: user_login.php");
    exit();
}

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
    <title><?php echo $lang['checkout'] ?? 'Checkout'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* هدر */
        .header {
            background: linear-gradient(to right, #ff6f61, #ff9f43);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header h1 {
            margin: 0;
            font-size: 1.8rem;
        }

        .header .controls {
            display: flex;
            gap: 15px;
        }

        .header select, .header a {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .header select {
            background-color: #fff;
            color: #333;
        }

        .header a {
            background-color: #fff;
            color: #ff6f61;
            text-decoration: none;
            position: relative;
        }

        .header a:hover, .header select:hover {
            background-color: #f0f0f0;
        }

        .header .cart-count {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8rem;
        }

        /* بخش پرداخت */
        .checkout {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .checkout h2 {
            color: #ff6f61;
            margin: 0 0 20px;
        }

        .order-summary {
            margin-bottom: 30px;
        }

        .order-summary h3 {
            color: #333;
            margin: 0 0 15px;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .cart-table th, .cart-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .cart-table th {
            background-color: #f9f9f9;
            color: #ff6f61;
        }

        .cart-table td {
            color: #666;
        }

        .cart-table .item-name {
            font-weight: bold;
            color: #333;
        }

        .total {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            text-align: right;
        }

        .checkout-form {
            margin-top: 30px;
        }

        .checkout-form h3 {
            color: #333;
            margin: 0 0 15px;
        }

        .checkout-form form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .checkout-form label {
            font-weight: bold;
        }

        .checkout-form input, .checkout-form textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .checkout-form textarea {
            height: 100px;
            resize: vertical;
        }

        .checkout-form .error, .checkout-form .success {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .checkout-form .error {
            background-color: #f8d7da;
            color: #dc3545;
        }

        .checkout-form .success {
            background-color: #d4edda;
            color: #28a745;
        }

        .checkout-form button {
            padding: 10px 20px;
            background-color: #ff6f61;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .checkout-form button:hover {
            background-color: #e65b50;
        }

        /* ریسپانسیو */
        @media (max-width: 768px) {
            .cart-table th, .cart-table td {
                font-size: 0.9rem;
                padding: 8px;
            }

            .total {
                text-align: left;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .header .controls {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- هدر -->
    <div class="header">
        <h1><?php echo $lang['checkout'] ?? 'Checkout'; ?></h1>
        <div class="controls">
            <select onchange="window.location='checkout.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="cart.php">
                <i class="fas fa-shopping-cart"></i> <?php echo $lang['cart'] ?? 'Cart'; ?>
                <?php if ($cart_count > 0): ?>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            <?php if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id'])): ?>
                <a href="user_dashboard.php">
                    <i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?>
                </a>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
                </a>
            <?php else: ?>
                <a href="user_login.php">
                    <i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

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
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="total">
                        <?php echo $lang['total'] ?? 'Total'; ?>: $<?php echo number_format($total_price, 2); ?>
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