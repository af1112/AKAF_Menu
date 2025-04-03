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

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT food_id, quantity, price FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $_SESSION['cart'] = [];
    foreach ($cart_items as $item) {
        $_SESSION['cart'][] = [
            'id' => $item['food_id'],
            'quantity' => $item['quantity'],
            'price' => $item['price']
        ];
    }
}

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

// Remove item from cart
if (isset($_GET['remove'])) {
    $remove_id = $_GET['remove'];
    if (isset($_SESSION['cart'][$remove_id])) {
        unset($_SESSION['cart'][$remove_id]);
        if (empty($_SESSION['cart'])) {
            unset($_SESSION['cart']);
        }
        header("Location: cart.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['cart'] ?? 'Cart'; ?></title>
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
        }

        .header a:hover, .header select:hover {
            background-color: #f0f0f0;
        }

        /* سبد خرید */
        .cart {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .cart h2 {
            color: #ff6f61;
            margin: 0 0 20px;
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

        .cart-table .remove-btn {
            color: #dc3545;
            text-decoration: none;
            font-size: 1.1rem;
        }

        .cart-table .remove-btn:hover {
            color: #c82333;
        }

        .cart-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-top: 1px solid #eee;
        }

        .cart-summary .total {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .cart-summary .checkout-btn {
            padding: 10px 20px;
            background-color: #ff6f61;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .cart-summary .checkout-btn:hover {
            background-color: #e65b50;
        }

        .cart-summary .continue-shopping {
            padding: 10px 20px;
            background-color: #f0f0f0;
            color: #333;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .cart-summary .continue-shopping:hover {
            background-color: #e0e0e0;
        }

        /* ریسپانسیو */
        @media (max-width: 768px) {
            .cart-table th, .cart-table td {
                font-size: 0.9rem;
                padding: 8px;
            }

            .cart-summary {
                flex-direction: column;
                gap: 10px;
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
        <h1><?php echo $lang['cart'] ?? 'Cart'; ?></h1>
        <div class="controls">
            <select onchange="window.location='cart.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="menu.php"><i class="fas fa-utensils"></i> <?php echo $lang['menu'] ?? 'Menu'; ?></a>
        </div>
    </div>

    <div class="container">
        <!-- سبد خرید -->
        <div class="cart" data-aos="fade-up">
            <h2><?php echo $lang['your_cart'] ?? 'Your Cart'; ?></h2>
            <?php if (!empty($cart_details)): ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th><?php echo $lang['item'] ?? 'Item'; ?></th>
                            <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                            <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                            <th><?php echo $lang['subtotal'] ?? 'Subtotal'; ?></th>
                            <th><?php echo $lang['action'] ?? 'Action'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_details as $item): ?>
                            <tr>
                                <td class="item-name"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo number_format($item['subtotal'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                <td>
                                    <a href="cart.php?remove=<?php echo $item['id']; ?>" class="remove-btn">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="cart-summary">
                    <div class="total">
                        <?php echo $lang['total'] ?? 'Total'; ?>: <?php echo number_format($total_price, $currency_Decimal); ?> <?php echo $currency; ?>
                    </div>
                    <div>
                        <a href="menu.php" class="continue-shopping">
                            <i class="fas fa-arrow-left"></i> <?php echo $lang['continue_shopping'] ?? 'Continue Shopping'; ?>
                        </a>
						<a href="checkout.php" class="checkout-btn">
							<i class="fas fa-credit-card"></i> <?php echo $lang['checkout'] ?? 'Checkout'; ?>
						</a>
                    </div>
                </div>
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
        AOS.init()
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