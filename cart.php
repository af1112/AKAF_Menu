<?php
session_start();
include 'db.php';
file_put_contents('debug.txt', "Script started at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

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

if ($is_logged_in) {
    $stmt = $conn->prepare("SELECT c.food_id, c.quantity, c.price, c.comment, f.name_" . $_SESSION['lang'] . " AS name FROM cart c JOIN foods f ON c.food_id = f.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $_SESSION['user']['id']);
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
$hour = (int)date('H'); // ساعت فعلی (0-23)
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
		// بررسی و به‌روزرسانی آیتم‌های سبد خرید
		if (!empty($_POST['quantities'])) {
			foreach ($_POST['quantities'] as $food_id => $quantity) {
				$comment = isset($_POST['comments'][$food_id]) ? trim($_POST['comments'][$food_id]) : '';

				foreach ($_SESSION['cart'] as $key => &$item) {
					if ($item['id'] == $food_id) {
						if ($quantity <= 0) {
							$stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND food_id = ?");
							if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
							$stmt->bind_param("ii", $_SESSION['user']['id'], $food_id);
							if (!$stmt->execute()) throw new Exception("Delete failed: " . $conn->error);

							unset($_SESSION['cart'][$key]);
						} else {
							$stmt = $conn->prepare("UPDATE cart SET quantity = ?, comment = ? WHERE user_id = ? AND food_id = ?");
							if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
							$stmt->bind_param("issi", $quantity, $comment, $_SESSION['user']['id'], $food_id);
							if (!$stmt->execute()) throw new Exception("Update failed: " . $conn->error);

							$item['quantity'] = $quantity;
							$item['comment'] = $comment;
						}
						break;
					}
				}
			}
			$_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index
		}
		$conn->commit();
	} catch (Exception $e) {
		$conn->rollback();
		file_put_contents('debug.txt', "Error during checkout: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
		echo "An error occurred. Please try again.";
		exit();
	}
 
	if (isset($_POST['checkout'])) {
		$conn->begin_transaction();
		try {

            // ایجاد سفارش جدید
            $stmt_create_order = $conn->prepare("INSERT INTO orders (user_id, total_price, vat_amount, grand_total) VALUES (?, ?, ?, ?)");
            if (!$stmt_create_order) throw new Exception("Prepare failed for creating order: " . $conn->error);
            $stmt_create_order->bind_param("iddd", $_SESSION['user']['id'], $total_price, $vat_amount, $grand_total);
            if (!$stmt_create_order->execute()) throw new Exception("Failed to create order: " . $conn->error);
            $order_id = $conn->insert_id;

            // ذخیره آیتم‌های سفارش
            foreach ($_SESSION['cart'] as $item) {
                $comment = $item['comment'] ?? ''; // ذخیره کامنت در متغیر جداگانه
                $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, food_id, quantity, price, comment) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt_items) throw new Exception("Prepare failed for order items: " . $conn->error);
                $stmt_items->bind_param("iiids", $order_id, $item['id'], $item['quantity'], $item['price'], $comment);
                if (!$stmt_items->execute()) throw new Exception("Failed to add order item for food ID " . $item['id'] . ": " . $conn->error);
                $stmt_items->close(); // بستن statement
            }

            $conn->commit();
            file_put_contents('debug.txt', "Transaction committed successfully at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

            // ریدایرکت به checkout.php با order_id
            file_put_contents('debug.txt', "Redirecting to checkout.php with order_id: " . $order_id . "\n", FILE_APPEND);
            header("Location: checkout.php?order_id=" . $order_id);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            file_put_contents('debug.txt', "Error during checkout: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            echo "An error occurred. Please try again.";
            exit();
        }
    } elseif (isset($_POST['continue'])) { // برای دکمه Continue Shopping
        header("Location: menu.php");
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
<body class="<?php echo $theme; ?>">
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
        <div class="cart" data-aos="fade-up">
            <h2><?php echo $lang['shopping_cart'] ?? 'Shopping Cart'; ?></h2>
            <?php if (!empty($_SESSION['cart'])): ?>
                <section class="cart-summary">
                    <form method="POST" action="cart.php">
                        <table class="cart-table">
                            <!-- table headers -->
                            <thead>
                                <tr>
                                    <th><?php echo $lang['food'] ?? 'Food'; ?></th>
                                    <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                                    <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                                    <th><?php echo $lang['subtotal'] ?? 'SubTotal'; ?></th>
                                    <th><?php echo $lang['Special_Request'] ?? 'Special Request'; ?></th>
                                    <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['cart'] as $item): ?>
                                    <tr>
                                        <td class="item-name"><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td>
                                            <input type="number" name="quantities[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="0" style="width: 50px;">
                                        </td>
                                        <td><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                        <td><?php echo number_format($item['price'] * $item['quantity'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                                        <td>
                                            <input type="text" name="comments[<?php echo $item['id']; ?>]" value="<?php echo htmlspecialchars($item['comment'] ?? ''); ?>">
                                        </td>
                                        <td>
                                            <a href="cart.php?remove_item=1&food_id=<?php echo $item['id']; ?>" class="button">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="cart-summary">
                            <div class="total">
                                <?php echo $lang['total'] ?? 'Total'; ?>: <?php echo number_format($total_price, $currency_Decimal); ?> <?php echo $currency; ?>
                                <p><?php echo $lang['VAT'] ?? 'VAT'; ?> (<?php echo $vat_rate * 100; ?>%): <?php echo number_format($vat_amount, $currency_Decimal); ?> <?php echo $currency; ?></p>
                                <p class="grand-total"><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>: <?php echo number_format($grand_total, $currency_Decimal); ?> <?php echo $currency; ?></p>
                            </div>

                            <!-- Buttons in same form -->
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
                            if (cartLink) {
                                cartLink.innerHTML += `<span class="cart-count">${data.count}</span>`;
                            }
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