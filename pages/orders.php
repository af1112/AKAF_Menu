<?php
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency'");
$stmt->execute();
$currency = $stmt->get_result()->fetch_assoc()['value'] ?? 'OMR';

$sort_column = $_GET['sort_column'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';
$search = $_GET['search'] ?? '';
$search_column = $_GET['search_column'] ?? 'id';
$filter_by_status = $_GET['filter_by_status'] ?? '';
$filter_by_type = $_GET['filter_by_type'] ?? '';
$show_all = isset($_GET['show_all']) ? true : false;

$valid_columns = ['id', 'user_id', 'total_price', 'created_at', 'updated_at', 'table_number'];
if (!in_array($sort_column, $valid_columns)) {
    $sort_column = 'created_at';
}

if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}

$order_types = [
    'dine_in' => ['Pending', 'Confirmed', 'Preparing', 'Serving', 'Completed'],
    'delivery' => ['Pending', 'Confirmed', 'Preparing', 'On the way', 'Delivered'],
    'takeaway' => ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivered'],
    'drive_thru' => ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivered'],
    'contactless_delivery' => ['Pending', 'Confirmed', 'Preparing', 'On the way', 'Delivered'],
    'curbside_pickup' => ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivered']
];

$all_statuses = array_unique(array_merge(...array_values($order_types)));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $order_type = $_POST['order_type'];

    if (in_array($new_status, $order_types[$order_type])) {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        $stmt->execute();
    }
    header("Location: admin_dashboard.php?page=orders&" . http_build_query($_GET));
    exit;
}

$today = date('Y-m-d');
$total_completed_today = 0;
$stmt = $conn->prepare("SELECT SUM(total_price) as total FROM orders WHERE DATE(created_at) = ? AND (status = 'Completed' OR status = 'Delivered')");
$stmt->bind_param("s", $today);
$stmt->execute();
$total_completed_today = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$statuses = $show_all ? $all_statuses : array_diff($all_statuses, ['Completed', 'Delivered']);
if ($filter_by_status) {
    $statuses = in_array($filter_by_status, $all_statuses) ? [$filter_by_status] : [];
}
$order_types_filter = array_keys($order_types);
if ($filter_by_type) {
    $order_types_filter = in_array($filter_by_type, array_keys($order_types)) ? [$filter_by_type] : [];
}

$orders_by_status = [];
foreach ($statuses as $status) {
    $query = "
        SELECT o.*, u.username
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.status = ?
    ";
    $params = [$status];

    if ($filter_by_type) {
        $query .= " AND o.order_type = ?";
        $params[] = $filter_by_type;
    } else {
        $query .= " AND o.order_type IN (" . implode(',', array_fill(0, count($order_types_filter), '?')) . ")";
        $params = array_merge($params, $order_types_filter);
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
        }
    }
    $query .= " ORDER BY o.$sort_column $sort_order";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat("s", count($params)), ...$params);
    $stmt->execute();
    $orders_by_status[$status] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$request_history = [];
foreach ($orders_by_status as $status => $orders) {
    foreach ($orders as $order) {
        $order_id = $order['id'];
        if ($conn->query("SHOW TABLES LIKE 'order_requests'")->num_rows > 0) {
            $stmt_requests = $conn->prepare("SELECT request_text, response_text, created_at, status FROM order_requests WHERE order_id = ? ORDER BY created_at DESC");
            $stmt_requests->bind_param("i", $order_id);
            $stmt_requests->execute();
            $request_history[$order_id] = $stmt_requests->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $request_history[$order_id] = [];
        }
    }
}

if (!function_exists('getSortLink')) {
    function getSortLink($column, $direction, $current_sort_column, $current_sort_order) {
        return "admin_dashboard.php?page=orders&sort_column=$column&sort_order=$direction" . (isset($_GET['search']) ? "&search=" . $_GET['search'] : "") . (isset($_GET['search_column']) ? "&search_column=" . $_GET['search_column'] : "") . (isset($_GET['filter_by_status']) ? "&filter_by_status=" . $_GET['filter_by_status'] : "") . (isset($_GET['filter_by_type']) ? "&filter_by_type=" . $_GET['filter_by_type'] : "") . (isset($_GET['show_all']) ? "&show_all=1" : "");
    }
}
?>

<div class="admin-section">
    <h3><?php echo $lang['total_completed_today'] ?? 'Total Completed Today'; ?>: <?php echo number_format($total_completed_today, 2); ?> <?php echo $currency; ?></h3>
    <h3><?php echo $lang['filters'] ?? 'Filters'; ?></h3>
    <form method="GET" action="admin_dashboard.php">
        <input type="hidden" name="page" value="orders">
        <label for="search"><?php echo $lang['search'] ?? 'Search'; ?>:</label>
        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?php echo $lang['search_placeholder'] ?? 'Search by selected field'; ?>">
        <label for="search_column"><?php echo $lang['search_in'] ?? 'Search In'; ?>:</label>
        <select name="search_column" id="search_column">
            <option value="id" <?php echo $search_column == 'id' ? 'selected' : ''; ?>><?php echo $lang['order_id'] ?? 'Order ID'; ?></option>
            <option value="username" <?php echo $search_column == 'username' ? 'selected' : ''; ?>><?php echo $lang['user'] ?? 'User'; ?></option>
            <option value="total_price" <?php echo $search_column == 'total_price' ? 'selected' : ''; ?>><?php echo $lang['total_price'] ?? 'Total Price'; ?></option>
            <option value="table_number" <?php echo $search_column == 'table_number' ? 'selected' : ''; ?>><?php echo $lang['table_number'] ?? 'Table Number'; ?></option>
        </select>
        <label for="filter_by_status"><?php echo $lang['filter_by_status'] ?? 'Filter By Status'; ?>:</label>
        <select name="filter_by_status" id="filter_by_status" onchange="this.form.submit()">
            <option value="" <?php echo !$filter_by_status ? 'selected' : ''; ?>><?php echo $lang['all'] ?? 'All'; ?></option>
            <?php foreach ($all_statuses as $status): ?>
                <option value="<?php echo $status; ?>" <?php echo $filter_by_status == $status ? 'selected' : ''; ?>><?php echo $lang['order_status_' . strtolower(str_replace(' ', '_', $status))] ?? $status; ?></option>
            <?php endforeach; ?>
        </select>
        <label for="filter_by_type"><?php echo $lang['filter_by_type'] ?? 'Filter By Type'; ?>:</label>
        <select name="filter_by_type" id="filter_by_type" onchange="this.form.submit()">
            <option value="" <?php echo !$filter_by_type ? 'selected' : ''; ?>><?php echo $lang['all'] ?? 'All'; ?></option>
            <?php foreach (array_keys($order_types) as $type): ?>
                <option value="<?php echo $type; ?>" <?php echo $filter_by_type == $type ? 'selected' : ''; ?>><?php echo $lang['order_type_' . $type] ?? ucwords(str_replace('_', ' ', $type)); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="show_all"><?php echo $lang['show_all_orders'] ?? 'Show All Orders'; ?>:</label>
        <input type="checkbox" name="show_all" id="show_all" <?php echo $show_all ? 'checked' : ''; ?> onchange="this.form.submit()">
        <button type="submit"><?php echo $lang['apply'] ?? 'Apply'; ?></button>
    </form>
</div>

<?php foreach ($statuses as $status): ?>
    <div class="admin-section">
        <h3><?php echo $lang['order_status_' . strtolower(str_replace(' ', '_', $status))] ?? ucfirst($status) . ' Orders'; ?></h3>
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
                        <?php echo $lang['order_type'] ?? 'Order Type'; ?>
                    </th>
                    <th>
                        <?php echo $lang['table_number'] ?? 'Table Number'; ?>
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
                    <th><?php echo $lang['request_history'] ?? 'Request History'; ?></th>
                    <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders_by_status[$status] as $order): ?>
                    <tr class="<?php echo strtolower(str_replace(' ', '_', $order['status'])); ?>">
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                        <td><?php echo $lang['order_type_' . $order['order_type']] ?? ucwords(str_replace('_', ' ', $order['order_type'])); ?></td>
                        <td><?php echo $order['table_number'] ?? 'N/A'; ?></td>
                        <td><?php echo number_format($order['total_price'], 2); ?> <?php echo $currency; ?></td>
                        <td><?php echo $order['created_at']; ?></td>
                        <td><?php echo $order['updated_at']; ?></td>
                        <td class="request-history">
                            <?php if (empty($request_history[$order['id']])): ?>
                                <p><?php echo $lang['no_requests'] ?? 'No requests'; ?></p>
                            <?php else: ?>
                                <?php foreach ($request_history[$order['id']] as $request): ?>
                                    <p><strong><?php echo $request['created_at']; ?>:</strong> <?php echo htmlspecialchars($request['request_text']); ?></p>
                                    <?php if ($request['response_text']): ?>
                                        <p class="responded"><strong><?php echo $lang['response'] ?? 'Response'; ?>:</strong> <?php echo htmlspecialchars($request['response_text']); ?></p>
                                    <?php elseif ($request['status'] === 'pending'): ?>
                                        <p class="pending"><em><?php echo $lang['pending'] ?? 'Pending'; ?></em></p>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="view_order.php?id=<?php echo $order['id']; ?>" class="button"><i class="fas fa-eye"></i> <?php echo $lang['view'] ?? 'View'; ?></a>
                            <?php if ($order['status'] != 'Completed' && $order['status'] != 'Delivered'): ?>
                                <form action="" method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="order_type" value="<?php echo $order['order_type']; ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <?php foreach ($order_types[$order['order_type']] as $possible_status): ?>
                                            <option value="<?php echo $possible_status; ?>" <?php echo $order['status'] == $possible_status ? 'selected' : ''; ?>>
                                                <?php echo $lang['order_status_' . strtolower(str_replace(' ', '_', $possible_status))] ?? $possible_status; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            <?php endif; ?>
                            <a href="print_order.php?id=<?php echo $order['id']; ?>" class="button"><i class="fas fa-print"></i> <?php echo $lang['print'] ?? 'Print'; ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>