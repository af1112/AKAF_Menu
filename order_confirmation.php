<?php
session_start();
include 'db.php';

// Restrict access to logged-in users (optional)
if (!isset($_SESSION['user'])) {
    header("Location: user_login.php");
    exit();
}

if (!isset($_GET['order_id'])) {
    echo "❌ No order specified!";
    exit();
}

$order_id = $_GET['order_id'];
$order_result = $conn->query("
    SELECT o.*, GROUP_CONCAT(f.id SEPARATOR ',') as food_ids 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    LEFT JOIN foods f ON oi.food_id = f.id 
    WHERE o.id = $order_id AND o.user_id = {$_SESSION['user']['id']}
    GROUP BY o.id
");
if ($order_result->num_rows == 0) {
    echo "❌ Order not found!";
    exit();
}
$order = $order_result->fetch_assoc();
// Determine greeting based on time of day
$hour = (int)date('H'); // ساعت فعلی (0-23)
if ($hour >= 5 && $hour < 12) {
    $greeting = $lang['good_morning'] ?? 'Good Morning';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = $lang['good_afternoon'] ?? 'Good Afternoon';
} else {
    $greeting = $lang['good_evening'] ?? 'Good Evening';
}
// Fetch order items with quantities
$items_result = $conn->query("
    SELECT oi.quantity, f.id as food_id, f.name_en 
    FROM order_items oi 
    JOIN foods f ON oi.food_id = f.id 
    WHERE oi.order_id = $order_id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
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
    <h1>Order Details #<?php echo $order_id; ?></h1>
    <div class="order-details">
        <p><strong>Order Date:</strong> <?php echo $order['created_at']; ?></p>
        <p><strong>Order Type:</strong> <?php echo ucfirst($order['type']); ?></p>
        <?php if ($order['type'] === 'dine-in'): ?>
            <p><strong>Table Number:</strong> <?php echo $order['table_number']; ?></p>
        <?php endif; ?>
        
        <p><strong>Items:</strong></p>
        <div class="order-items">
            <?php
            while ($item = $items_result->fetch_assoc()) {
                // Fetch the first image for this food item
                $food_id = $item['food_id'];
                $image_result = $conn->query("SELECT image FROM food_images WHERE food_id = $food_id LIMIT 1");
                $image = $image_result->fetch_assoc();
                $first_image = $image ? $image['image'] : 'default.jpg';

                echo "
                    <div class='order-item'>
                        <a href='food_details.php?id=$food_id'>
                            <img src='images/$first_image' alt='{$item['name_en']}' style='width: 100px; height: 100px; object-fit: cover; border-radius: 5px;'>
                        </a>
                        <p>{$item['name_en']} (x{$item['quantity']})</p>
                        <a href='food_details.php?id=$food_id' class='view-details-btn'>View Details</a>
                    </div>";
            }
            ?>
        </div>
        <p><strong>Total:</strong> $<?php echo number_format($order['total_price'], 2); ?></p>
        <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>
        <p><strong>Payment Status:</strong> <?php echo ucfirst($order['payment_status']); ?></p>

        <div class="button-group">
            <a href="menu.php" class="btn back-btn">Back to Menu</a>
            <a href="order_history.php" class="btn order-history-btn">View Order History</a>
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