<?php
session_start();
include 'db.php';

// Restrict access to logged-in users
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

$lang_name_col = "name_" . $_SESSION['lang'];

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    $rating = $_POST['rating'];
    $rating_comment = $_POST['rating_comment'] ?? '';
    $stmt = $conn->prepare("UPDATE orders SET rating = ?, rating_comment = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("isii", $rating, $rating_comment, $order_id, $user_id);
    $stmt->execute();
    header("Location: order_history.php");
    exit();
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
// Fetch order history
$stmt = $conn->prepare("
    SELECT o.*, GROUP_CONCAT(f.$lang_name_col SEPARATOR ', ') as items 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    LEFT JOIN foods f ON oi.food_id = f.id 
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['order_history']; ?></title>
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
<body>
    <h1>📜 <?php echo $lang['order_history']; ?></h1>

    <div class="container">
        <?php if ($orders_result->num_rows == 0): ?>
            <p><?php echo $lang['no_orders']; ?></p>
        <?php else: ?>
            <?php while ($order = $orders_result->fetch_assoc()): ?>
                <div class="order-item">
                    <p><strong><?php echo $lang['order']; ?> #<?php echo $order['id']; ?></strong> - <?php echo $order['created_at']; ?></p>
                    <p><strong><?php echo $lang['order_type']; ?>:</strong> <?php echo ucfirst($order['type']); ?></p>
                    <p><strong><?php echo $lang['items']; ?>:</strong> <?php echo $order['items']; ?></p>
                    <p><strong><?php echo $lang['total']; ?>:</strong> $<?php echo number_format($order['total_price'], 2); ?></p>
                    <p><strong><?php echo $lang['status']; ?>:</strong> <?php echo ucfirst($order['status']); ?></p>
                    <p><strong><?php echo $lang['payment_status']; ?>:</strong> <?php echo ucfirst($order['payment_status']); ?></p>
                    <?php if ($order['rating']): ?>
                        <p><strong><?php echo $lang['rating']; ?>:</strong> <?php echo $order['rating']; ?>/5 <?php echo $order['rating_comment'] ? " - " . htmlspecialchars($order['rating_comment']) : ''; ?></p>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <label><?php echo $lang['rate_order']; ?> (1-5):</label>
                            <select name="rating" required>
                                <option value=""><?php echo $lang['select_rating']; ?></option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <input type="text" name="rating_comment" placeholder="<?php echo $lang['optional_comment']; ?>">
                            <button type="submit" class="btn submit-btn"><?php echo $lang['submit_rating']; ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
        <div class="button-group">
            <button class="btn back-btn" onclick="window.location='menu.php'">🔙 <?php echo $lang['back_to_menu']; ?></button>
        </div>
    </div>
	
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
</body>
</html>