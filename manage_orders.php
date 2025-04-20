<?php
session_start();
include 'db.php'; // Assuming this sets up $conn with mysqli

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

// Handle sorting, searching, and filtering
$sort_column = $_GET['sort_column'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';
$search = $_GET['search'] ?? '';
$search_column = $_GET['search_column'] ?? 'id';
$filter_by = $_GET['filter_by'] ?? '';
$show_all = isset($_GET['show_all']) ? true : false;

// Validate sort column
$valid_columns = ['id', 'user_id', 'total_price', 'created_at', 'updated_at', 'table_number'];
if (!in_array($sort_column, $valid_columns)) {
    $sort_column = 'created_at';
}

// Validate sort order
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}


// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    header("Location: manage_orders.php?" . http_build_query($_GET));
    exit;
}

// Calculate total price of completed orders today
$today = date('Y-m-d');
$total_completed_today = 0;
$stmt = $pdo->prepare("SELECT SUM(total_price) as total FROM orders WHERE DATE(created_at) = ? AND status IN ('delivered', 'served')");
$stmt->execute([$today]);
$total_completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Fetch orders with filters
$statuses = $show_all ? ['pending', 'preparing', 'on_the_way', 'delivered', 'served'] : ['pending', 'preparing', 'on_the_way', 'served'];
if ($filter_by) {
    $statuses = in_array($filter_by, $statuses) ? [$filter_by] : ($filter_by === 'paid' ? $statuses : []);
}
$orders_by_status = [];
foreach ($statuses as $status) {
    $query = "
        SELECT o.*, u.username, oi.food_id, oi.quantity, oi.price
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status = ?
    ";
    $params = [$status];
    if ($filter_by === 'paid') {
        $query .= " AND o.payment_status = 'paid'";
    }
    if ($search) {
        $search_param = "%$search%";
        switch ($search_column) {
            case 'id':
                $query .= " AND o.id LIKE ?";
                $params[] = $search_param;
                break;
            case 'username':
                $query .= " AND u.username LIKE ?";
                $params[] = $search_param;
                break;
            case 'total_price':
                $query .= " AND o.total_price LIKE ?";
                $params[] = $search_param;
                break;
            case 'table_number':
                $query .= " AND o.table_number LIKE ?";
                $params[] = $search_param;
                break;
            case 'address':
                $query .= " AND o.address LIKE ?";
                $params[] = $search_param;
                break;
        }
    }
    $query .= " ORDER BY o.$sort_column $sort_order";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders_by_status[$status] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Group orders for display
$grouped_orders = [];
foreach ($statuses as $status) {
    $grouped_orders[$status] = [];
    foreach ($orders_by_status[$status] as $row) {
        $order_id = $row['id'];
        if (!isset($grouped_orders[$status][$order_id])) {
            $grouped_orders[$status][$order_id] = [
                'details' => [
                    'id' => $row['id'],
                    'user_id' => $row['user_id'],
                    'username' => $row['username'],
                    'type' => $row['type'],
                    'table_number' => $row['table_number'],
                    'address' => $row['address'],
                    'total_price' => $row['total_price'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'status' => $row['status'],
                    'comment' => $row['comment']
                ],
                'items' => []
            ];
        }
        if ($row['food_id']) {
            $grouped_orders[$status][$order_id]['items'][] = [
                'food_id' => $row['food_id'],
                'quantity' => $row['quantity'],
                'price' => $row['price']
            ];
        }
    }
}

// Function to get sort link
if (!function_exists('getSortLink')) {
    function getSortLink($column, $direction, $current_sort_column, $current_sort_order) {
        return "?sort_column=$column&sort_order=$direction" . (isset($_GET['search']) ? "&search=" . $_GET['search'] : "") . (isset($_GET['search_column']) ? "&search_column=" . $_GET['search_column'] : "") . (isset($_GET['filter_by']) ? "&filter_by=" . $_GET['filter_by'] : "") . (isset($_GET['show_all']) ? "&show_all=1" : "");
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="10">
    <title><?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>" media="only screen and (max-width: 768px)">
    <style>
        .foods-table thead th { color: #fff; background-color: #333; }
        .sort-icon { margin-left: 5px; color: #fff; text-decoration: none; font-size: 12px; }
        .sort-icon:hover { color: #ddd; }
		@media (max-width: 768px) {
			.admin-sidebar{
				top : 940px;
			}
		}
    </style>
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
            <li><a href="manage_foods.php"><i class="fas fa-utensils"></i> <?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?></a></li>
            <li><a href="manage_categories.php"><i class="fas fa-list"></i> <?php echo $lang['manage_categories'] ?? 'Manage Categories'; ?></a></li>
            <li><a href="manage_orders.php" class="active"><i class="fas fa-shopping-cart"></i> <?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?></a></li>
            <li><a href="manage_hero_texts.php"><i class="fas fa-heading"></i> <?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></a></li>
        </ul>
    </aside>

    <main class="admin-content">
        <div class="admin-section">
            <h3><?php echo $lang['total_completed_today'] ?? 'Total Completed Today'; ?>: <?php echo number_format($total_completed_today, 2); ?> <?php echo $lang['currency'] ?? '$'; ?></h3>
            <h3><?php echo $lang['filters'] ?? 'Filters'; ?></h3>
            <form method="GET" action="manage_orders.php">
                <label for="search"><?php echo $lang['search'] ?? 'Search'; ?>:</label>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?php echo $lang['search_placeholder'] ?? 'Search by selected field'; ?>">
                <label for="search_column"><?php echo $lang['search_in'] ?? 'Search In'; ?>:</label>
                <select name="search_column" id="search_column">
                    <option value="id" <?php echo $search_column == 'id' ? 'selected' : ''; ?>><?php echo $lang['order_id'] ?? 'Order ID'; ?></option>
                    <option value="username" <?php echo $search_column == 'username' ? 'selected' : ''; ?>><?php echo $lang['user'] ?? 'User'; ?></option>
                    <option value="total_price" <?php echo $search_column == 'total_price' ? 'selected' : ''; ?>><?php echo $lang['total_price'] ?? 'Total Price'; ?></option>
                    <option value="table_number" <?php echo $search_column == 'table_number' ? 'selected' : ''; ?>><?php echo $lang['table_number'] ?? 'Table Number'; ?></option>
                    <option value="address" <?php echo $search_column == 'address' ? 'selected' : ''; ?>><?php echo $lang['address'] ?? 'Address'; ?></option>
                </select>
                <label for="filter_by"><?php echo $lang['filter_by'] ?? 'Filter By'; ?>:</label>
                <select name="filter_by" id="filter_by" onchange="this.form.submit()">
                    <option value="" <?php echo !$filter_by ? 'selected' : ''; ?>><?php echo $lang['all'] ?? 'All'; ?></option>
                    <option value="pending" <?php echo $filter_by == 'pending' ? 'selected' : ''; ?>><?php echo $lang['order_status_pending'] ?? 'Pending'; ?></option>
                    <option value="preparing" <?php echo $filter_by == 'preparing' ? 'selected' : ''; ?>><?php echo $lang['order_status_preparing'] ?? 'Preparing'; ?></option>
                    <option value="on_the_way" <?php echo $filter_by == 'on_the_way' ? 'selected' : ''; ?>><?php echo $lang['order_status_on_the_way'] ?? 'On the Way'; ?></option>
                    <option value="delivered" <?php echo $filter_by == 'delivered' ? 'selected' : ''; ?>><?php echo $lang['order_status_delivered'] ?? 'Delivered'; ?></option>
                    <option value="served" <?php echo $filter_by == 'served' ? 'selected' : ''; ?>><?php echo $lang['order_status_served'] ?? 'Served'; ?></option>
                    <option value="paid" <?php echo $filter_by == 'paid' ? 'selected' : ''; ?>><?php echo $lang['payment_status_paid'] ?? 'Paid'; ?></option>
                </select>
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
                                <?php echo $lang['order_id'] ?? 'Order ID'; ?>
                                <a href="<?php echo getSortLink('id', 'ASC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-up"></i></a>
                                <a href="<?php echo getSortLink('id', 'DESC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-down"></i></a>
                                <?php if ($sort_column == 'id') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                            </th>
                            <th>
                                <?php echo $lang['user'] ?? 'User'; ?>
                                <a href="<?php echo getSortLink('user_id', 'ASC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-up"></i></a>
                                <a href="<?php echo getSortLink('user_id', 'DESC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-down"></i></a>
                                <?php if ($sort_column == 'user_id') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                            </th>
                            <th>
                                <?php echo $lang['table_address'] ?? 'Table/Address'; ?>
                                <a href="<?php echo getSortLink('table_number', 'ASC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-up"></i></a>
                                <a href="<?php echo getSortLink('table_number', 'DESC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-down"></i></a>
                                <?php if ($sort_column == 'table_number') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                            </th>
                            <th>
                                <?php echo $lang['total_price'] ?? 'Total Price'; ?>
                                <a href="<?php echo getSortLink('total_price', 'ASC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-up"></i></a>
                                <a href="<?php echo getSortLink('total_price', 'DESC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-down"></i></a>
                                <?php if ($sort_column == 'total_price') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                            </th>
                            <th>
                                <?php echo $lang['created_at'] ?? 'Created At'; ?>
                                <a href="<?php echo getSortLink('created_at', 'ASC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-up"></i></a>
                                <a href="<?php echo getSortLink('created_at', 'DESC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-down"></i></a>
                                <?php if ($sort_column == 'created_at') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                            </th>
                            <th>
                                <?php echo $lang['updated_at'] ?? 'Updated At'; ?>
                                <a href="<?php echo getSortLink('updated_at', 'ASC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-up"></i></a>
                                <a href="<?php echo getSortLink('updated_at', 'DESC', $sort_column, $sort_order); ?>" class="sort-icon"><i class="fas fa-arrow-down"></i></a>
                                <?php if ($sort_column == 'updated_at') echo $sort_order == 'ASC' ? '↑' : '↓'; ?>
                            </th>
                            <th><?php echo $lang['items'] ?? 'Items'; ?></th>
                            <th><?php echo $lang['comment'] ?? 'Comment'; ?></th>
                            <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped_orders[$status] as $order): ?>
                            <tr class="<?php echo $order['details']['status']; ?>">
                                <td>#<?php echo $order['details']['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['details']['username']); ?></td>
                                <td><?php echo $order['details']['type'] === 'dine-in' ? ($order['details']['table_number'] ?? 'N/A') : ($order['details']['address'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($order['details']['total_price'], 2); ?> <?php echo $lang['currency'] ?? '$'; ?></td>
                                <td><?php echo $order['details']['created_at']; ?></td>
                                <td><?php echo $order['details']['updated_at']; ?></td>
                                <td>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <p><?php echo $lang['food_id'] ?? 'Food ID'; ?>: <?php echo $item['food_id']; ?>, <?php echo $lang['quantity'] ?? 'Qty'; ?>: <?php echo $item['quantity']; ?>, <?php echo $lang['price'] ?? 'Price'; ?>: <?php echo number_format($item['price'], 2); ?></p>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <form action="update_order_comment.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['details']['id']; ?>">
                                        <input type="text" name="comment" value="<?php echo htmlspecialchars($order['details']['comment'] ?? ''); ?>" placeholder="<?php echo $lang['add_comment'] ?? 'Add comment'; ?>">
                                        <button type="submit"><?php echo $lang['save'] ?? 'Save'; ?></button>
                                    </form>
                                </td>
                                <td>
                                    <a href="view_order.php?id=<?php echo $order['details']['id']; ?>" class="button"><i class="fas fa-eye"></i> <?php echo $lang['view'] ?? 'View'; ?></a>
                                    <?php if ($status != 'delivered' && $status != 'served'): ?>
                                        <form action="" method="POST" style="display:inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['details']['id']; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>><?php echo $lang['order_status_pending'] ?? 'Pending'; ?></option>
                                                <option value="preparing" <?php echo $status == 'preparing' ? 'selected' : ''; ?>><?php echo $lang['order_status_preparing'] ?? 'Preparing'; ?></option>
                                                <option value="on_the_way" <?php echo $status == 'on_the_way' ? 'selected' : ''; ?>><?php echo $lang['order_status_on_the_way'] ?? 'On the Way'; ?></option>
                                                <option value="delivered" <?php echo $status == 'delivered' ? 'selected' : ''; ?>><?php echo $lang['order_status_delivered'] ?? 'Delivered'; ?></option>
                                                <option value="served" <?php echo $status == 'served' ? 'selected' : ''; ?>><?php echo $lang['order_status_served'] ?? 'Served'; ?></option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    <?php endif; ?>
                                    <a href="print_order.php?id=<?php echo $order['details']['id']; ?>" class="button" target="_blank"><i class="fas fa-print"></i> <?php echo $lang['print'] ?? 'Print'; ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </main>
</body>
</html>