<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

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

// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];

// Fetch order details
$order_id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: manage_orders.php");
    exit();
}

// Fetch order items
$items = $conn->query("SELECT oi.*, f.name_" . $_SESSION['lang'] . " AS food_name FROM order_items oi JOIN foods f ON oi.food_id = f.id WHERE oi.order_id = $order_id");
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['view_order'] ?? 'View Order'; ?> #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['view_order'] ?? 'View Order'; ?> #<?php echo $order_id; ?></h1>
        <div class="controls">
            <select onchange="window.location='view_order.php?id=<?php echo $order_id; ?>&lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="view_order.php?id=<?php echo $order_id; ?>&theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </header>

    <aside class="admin-sidebar">
        <ul>
            <li>
                <a href="manage_foods.php">
                    <i class="fas fa-utensils"></i> <?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?>
                </a>
            </li>
            <li>
                <a href="manage_categories.php">
                    <i class="fas fa-list"></i> <?php echo $lang['manage_categories'] ?? 'Manage Categories'; ?>
                </a>
            </li>
            <li>
                <a href="manage_orders.php" class="active">
                    <i class="fas fa-shopping-cart"></i> <?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?>
                </a>
            </li>
            <li>
                <a href="manage_hero_texts.php">
                    <i class="fas fa-heading"></i> <?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?>
                </a>
            </li>
        </ul>
    </aside>

    <main class="admin-content">
        <div class="admin-section">
            <h3><?php echo $lang['order_details'] ?? 'Order Details'; ?></h3>
            <p><strong><?php echo $lang['order_id'] ?? 'Order ID'; ?>:</strong> #<?php echo $order['id']; ?></p>
            <p><strong><?php echo $lang['user'] ?? 'User'; ?>:</strong> <?php echo htmlspecialchars($order['username']); ?></p>
            <p><strong><?php echo $lang['total_price'] ?? 'Total Price'; ?>:</strong> <?php echo number_format($order['total_price'], 2); ?> <?php echo $lang['currency'] ?? '$'; ?></p>
            <p><strong><?php echo $lang['status'] ?? 'Status'; ?>:</strong> <?php echo $lang['order_status_' . $order['status']] ?? ucfirst($order['status']); ?></p>
            <p><strong><?php echo $lang['created_at'] ?? 'Created At'; ?>:</strong> <?php echo $order['created_at']; ?></p>
            <p><strong><?php echo $lang['updated_at'] ?? 'Updated At'; ?>:</strong> <?php echo $order['updated_at']; ?></p>

            <h4><?php echo $lang['order_items'] ?? 'Order Items'; ?></h4>
            <table class="foods-table">
                <thead>
                    <tr>
                        <th><?php echo $lang['food_name'] ?? 'Food Name'; ?></th>
                        <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                        <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                        <th><?php echo $lang['total'] ?? 'Total'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $items->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['food_name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['price'], 2); ?> <?php echo $lang['currency'] ?? '$'; ?></td>
                            <td><?php echo number_format($item['quantity'] * $item['price'], 2); ?> <?php echo $lang['currency'] ?? '$'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>