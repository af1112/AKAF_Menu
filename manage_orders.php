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

// Handle sorting and searching
$sort_column = $_GET['sort_column'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';
$search = $_GET['search'] ?? '';
$show_all = isset($_GET['show_all']) ? true : false;

// Validate sort column
$valid_columns = ['id', 'username', 'total_price', 'created_at', 'updated_at', 'table_number'];
if (!in_array($sort_column, $valid_columns)) {
    $sort_column = 'created_at';
}

// Validate sort order
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}

// Fetch orders by status
$statuses = $show_all ? ['pending', 'confirmed', 'preparing', 'canceled', 'serving', 'completed'] : ['pending', 'confirmed', 'preparing', 'serving'];
$orders_by_status = [];
foreach ($statuses as $status) {
    $query = "SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = ?";

    // Add search condition
    if ($search) {
        $query .= " AND (o.id LIKE ? OR u.username LIKE ? OR o.total_price LIKE ? OR o.table_number LIKE ?)";
    }

    // Add sorting
    $query .= " ORDER BY $sort_column $sort_order";

    $stmt = $conn->prepare($query);
    if ($search) {
        $search_param = "%$search%";
        $stmt->bind_param("sssss", $status, $search_param, $search_param, $search_param, $search_param);
    } else {
        $stmt->bind_param("s", $status);
    }
    $stmt->execute();
    $orders_by_status[$status] = $stmt->get_result();
    $stmt->close();
}

// Function to get sort link
function getSortLink($column, $current_sort_column, $current_sort_order) {
    $new_sort_order = ($current_sort_column == $column && $current_sort_order == 'ASC') ? 'DESC' : 'ASC';
    return "?sort_column=$column&sort_order=$new_sort_order" . (isset($_GET['search']) ? "&search=" . $_GET['search'] : "") . (isset($_GET['show_all']) ? "&show_all=1" : "");
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?></h1>
        <div class="controls">
            <select onchange="window.location='manage_orders.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="manage_orders.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
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
            <h3><?php echo $lang['filters'] ?? 'Filters'; ?></h3>
            <form method="GET" action="manage_orders.php">
                <label for="search"><?php echo $lang['search'] ?? 'Search'; ?>:</label>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?php echo $lang['search_placeholder'] ?? 'Search by ID, user, price, or table number'; ?>">
                <label for="show_all"><?php echo $lang['show_all_orders'] ?? 'Show All Orders'; ?>:</label>
                <input type="checkbox" name="show_all" id="show_all" <?php echo $show_all ? 'checked' : ''; ?> onchange="this.form.submit()">
                <button type="submit"><?php echo $lang['apply'] ?? 'Apply'; ?></button>
            </form>
        </div>

        <?php foreach ($statuses as $status): ?>
            <div class="admin-section">
                <h3><?php echo $lang['order_status_' . $status] ?? ucfirst($status) . ' Orders'; ?></h3>
                <table class="foods-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?php echo getSortLink('id', $sort_column, $sort_order); ?>">
                                    <?php echo $lang['order_id'] ?? 'Order ID'; ?>
                                    <?php if ($sort_column == 'id') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortLink('username', $sort_column, $sort_order); ?>">
                                    <?php echo $lang['user'] ?? 'User'; ?>
                                    <?php if ($sort_column == 'username') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortLink('table_number', $sort_column, $sort_order); ?>">
                                    <?php echo $lang['table_number'] ?? 'Table Number'; ?>
                                    <?php if ($sort_column == 'table_number') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortLink('total_price', $sort_column, $sort_order); ?>">
                                    <?php echo $lang['total_price'] ?? 'Total Price'; ?>
                                    <?php if ($sort_column == 'total_price') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortLink('created_at', $sort_column, $sort_order); ?>">
                                    <?php echo $lang['created_at'] ?? 'Created At'; ?>
                                    <?php if ($sort_column == 'created_at') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo getSortLink('updated_at', $sort_column, $sort_order); ?>">
                                    <?php echo $lang['updated_at'] ?? 'Updated At'; ?>
                                    <?php if ($sort_column == 'updated_at') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                                </a>
                            </th>
                            <th><?php echo $lang['comment'] ?? 'Comment'; ?></th>
                            <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = $orders_by_status[$status]->fetch_assoc()): ?>
                            <tr class="<?php echo $order['status']; ?>">
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['username']); ?></td>
                                <td><?php echo htmlspecialchars($order['table_number'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($order['total_price'], 2); ?> <?php echo $lang['currency'] ?? '$'; ?></td>
                                <td><?php echo $order['created_at']; ?></td>
                                <td><?php echo $order['updated_at']; ?></td>
                                <td>
                                    <form action="update_order_comment.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="text" name="comment" value="<?php echo htmlspecialchars($order['comment'] ?? ''); ?>" placeholder="<?php echo $lang['add_comment'] ?? 'Add comment'; ?>">
                                        <button type="submit"><?php echo $lang['save'] ?? 'Save'; ?></button>
                                    </form>
                                </td>
                                <td>
                                    <a href="view_order.php?id=<?php echo $order['id']; ?>" class="button">
                                        <i class="fas fa-eye"></i> <?php echo $lang['view'] ?? 'View'; ?>
                                    </a>
                                    <?php if ($status != 'canceled' && $status != 'completed'): ?>
                                        <form action="update_order_status.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>><?php echo $lang['order_status_pending'] ?? 'Pending'; ?></option>
                                                <option value="confirmed" <?php echo $status == 'confirmed' ? 'selected' : ''; ?>><?php echo $lang['order_status_confirmed'] ?? 'Confirmed'; ?></option>
                                                <option value="preparing" <?php echo $status == 'preparing' ? 'selected' : ''; ?>><?php echo $lang['order_status_preparing'] ?? 'Preparing'; ?></option>
                                                <option value="serving" <?php echo $status == 'serving' ? 'selected' : ''; ?>><?php echo $lang['order_status_serving'] ?? 'Serving'; ?></option>
                                                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>><?php echo $lang['order_status_completed'] ?? 'Completed'; ?></option>
                                                <option value="canceled" <?php echo $status == 'canceled' ? 'selected' : ''; ?>><?php echo $lang['order_status_canceled'] ?? 'Canceled'; ?></option>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                    <a href="print_order.php?id=<?php echo $order['id']; ?>" class="button" target="_blank">
                                        <i class="fas fa-print"></i> <?php echo $lang['print'] ?? 'Print'; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </main>
</body>
</html>