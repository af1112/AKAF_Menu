<?php
session_start();
include 'db.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;
$username = $is_logged_in ? $_SESSION['user']['username'] : null;

$user_id = $_SESSION['user']['id'];

// Load language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
include "languages/" . $_SESSION['lang'] . ".php";

// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];

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
$cart_items = is_array($_SESSION['cart'] ?? []) ? $_SESSION['cart']: [];
$cart_count = 0;
foreach ($cart_items as $quantity)
{
	if(is_numeric($quantity)){
    $cart_count += $quantity;
	}
}

// Update user profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (empty($name) || empty($email) || empty($phone)) {
        $error = $lang['fill_all_fields'] ?? 'Please fill all fields.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?");
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
    <title><?php echo $lang['cart'] ?? 'Cart'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS for animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- هدر -->
		<!-- Language Bar -->
	<div class="language-bar">
		<div class="container-fluid">
			<div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
				<a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="user_dashboard.php?lang=en">
					<img src="images/flags/en.png" alt="English" class="flag-icon"> EN
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="user_dashboard.php?lang=fa">
					<img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="user_dashboard.php?lang=ar">
					<img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="user_dashboard.php?lang=fr">
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
						<li class="nav-item">
							<a class="nav-link" href="index.php">
								<i class="fas fa-user"></i> <?php echo $lang['home'] ?? 'Home'; ?>
							</a>
						</li>
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
        <!-- داشبورد -->
        <div class="dashboard" data-aos="fade-up">
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
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
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
</html><?php
session_start();
include 'db.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;
$username = $is_logged_in ? $_SESSION['user']['username'] : null;

$user_id = $_SESSION['user']['id'];

// Load language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
include "languages/" . $_SESSION['lang'] . ".php";

// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];

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
$cart_items = is_array($_SESSION['cart'] ?? []) ? $_SESSION['cart']: [];
$cart_count = 0;
foreach ($cart_items as $quantity)
{
	if(is_numeric($quantity)){
    $cart_count += $quantity;
	}
}

// Update user profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (empty($name) || empty($email) || empty($phone)) {
        $error = $lang['fill_all_fields'] ?? 'Please fill all fields.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?");
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
    <title><?php echo $lang['cart'] ?? 'Cart'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS for animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- هدر -->
		<!-- Language Bar -->
	<div class="language-bar">
		<div class="container-fluid">
			<div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
				<a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="user_dashboard.php?lang=en">
					<img src="images/flags/en.png" alt="English" class="flag-icon"> EN
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="user_dashboard.php?lang=fa">
					<img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="user_dashboard.php?lang=ar">
					<img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="user_dashboard.php?lang=fr">
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
						<li class="nav-item">
							<a class="nav-link" href="index.php">
								<i class="fas fa-user"></i> <?php echo $lang['home'] ?? 'Home'; ?>
							</a>
						</li>
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
        <!-- داشبورد -->
        <div class="dashboard" data-aos="fade-up">
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
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
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