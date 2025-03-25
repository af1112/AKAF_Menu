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

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch user orders
$stmt = $conn->prepare("SELECT o.*, oi.food_id, oi.quantity, f.name_" . $_SESSION['lang'] . " AS food_name 
                        FROM orders o 
                        JOIN order_items oi ON o.id = oi.order_id 
                        JOIN foods f ON oi.food_id = f.id 
                        WHERE o.user_id = ? 
                        ORDER BY o.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();

// Calculate cart item count
$cart_items = $_SESSION['cart'] ?? [];
$cart_count = 0;
foreach ($cart_items as $quantity) {
    $cart_count += $quantity;
}

// Update user profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (empty($name) || empty($email) || empty($phone)) {
        $error = $lang['fill_all_fields'] ?? 'Please fill all fields.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
        if ($stmt->execute()) {
            $success = $lang['profile_updated'] ?? 'Profile updated successfully!';
            // Update session
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
        } else {
            $error = $lang['update_failed'] ?? 'Failed to update profile.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['user_dashboard'] ?? 'User Dashboard'; ?></title>
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

        /* داشبورد */
        .dashboard {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .dashboard h2 {
            color: #ff6f61;
            margin: 0 0 20px;
        }

        .profile, .orders {
            margin-bottom: 30px;
        }

        .profile h3, .orders h3 {
            color: #333;
            margin: 0 0 15px;
        }

        .profile form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 500px;
        }

        .profile label {
            font-weight: bold;
        }

        .profile input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .profile .error, .profile .success {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .profile .error {
            background-color: #f8d7da;
            color: #dc3545;
        }

        .profile .success {
            background-color: #d4edda;
            color: #28a745;
        }

        .profile button {
            padding: 10px 20px;
            background-color: #ff6f61;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .profile button:hover {
            background-color: #e65b50;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .orders-table th, .orders-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .orders-table th {
            background-color: #f9f9f9;
            color: #ff6f61;
        }

        .orders-table td {
            color: #666;
        }

        /* ریسپانسیو */
        @media (max-width: 768px) {
            .orders-table th, .orders-table td {
                font-size: 0.9rem;
                padding: 8px;
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
        <h1><?php echo $lang['user_dashboard'] ?? 'User Dashboard'; ?></h1>
        <div class="controls">
            <select onchange="window.location='user_dashboard.php?lang=' + this.value">
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
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </div>

    <div class="container">
        <!-- داشبورد -->
        <div class="dashboard" data-aos="fade-up">
            <h2><?php echo $lang['welcome'] ?? 'Welcome'; ?>, <?php echo htmlspecialchars($user['name']); ?>!</h2>

            <!-- پروفایل -->
            <div class="profile">
                <h3><?php echo $lang['profile'] ?? 'Profile'; ?></h3>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="success"><?php echo $success; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <label for="name"><?php echo $lang['name'] ?? 'Name'; ?>:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    <label for="email"><?php echo $lang['email'] ?? 'Email'; ?>:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    <label for="phone"><?php echo $lang['phone'] ?? 'Phone'; ?>:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    <button type="submit"><?php echo $lang['update'] ?? 'Update'; ?></button>
                </form>
            </div>

            <!-- سفارش‌ها -->
            <div class="orders">
                <h3><?php echo $lang['your_orders'] ?? 'Your Orders'; ?></h3>
                <?php if ($orders->num_rows > 0): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th><?php echo $lang['order_id'] ?? 'Order ID'; ?></th>
                                <th><?php echo $lang['item'] ?? 'Item'; ?></th>
                                <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                                <th><?php echo $lang['status'] ?? 'Status'; ?></th>
                                <th><?php echo $lang['date'] ?? 'Date'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['food_name']); ?></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($order['status']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php echo $lang['no_orders'] ?? 'You have no orders yet.'; ?></p>
                <?php endif; ?>
            </div>
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