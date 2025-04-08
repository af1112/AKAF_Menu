<?php
session_start();
include 'db.php';

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: cart.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

if (!$is_logged_in) {
    header("Location: user_login.php");
    exit();
}

// Load settings
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency'");
$stmt->execute();
$currency = $stmt->get_result()->fetch_assoc()['value'] ?? 'OMR';
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency_Decimal'");
$stmt->execute();
$currency_Decimal = $stmt->get_result()->fetch_assoc()['value'] ?? '3';

// Load order
$stmt_order = $conn->prepare("SELECT total_price, vat_amount, grand_total, status, waiter_id, estimated_time, table_number FROM orders WHERE id = ? AND user_id = ?");
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();

if (!$order) {
    header("Location: cart.php");
    exit();
}

$total_price = $order['total_price'] ?? 0;
$vat_amount = $order['vat_amount'] ?? 0;
$grand_total = $order['grand_total'] ?? 0;
$order_status = $order['status'] ?? 'Confirmed';
$waiter_id = $order['waiter_id'] ?? null;
$estimated_time = $order['estimated_time'] ?? null;
$table_number = $order['table_number'] ?? '';

// Load waiter
$waiter = null;
if ($waiter_id) {
    $stmt_waiter = $conn->prepare("SELECT name, image_url FROM waiters WHERE id = ?");
    $stmt_waiter->bind_param("i", $waiter_id);
    $stmt_waiter->execute();
    $waiter = $stmt_waiter->get_result()->fetch_assoc();
}

// Load order items
$stmt_items = $conn->prepare("SELECT oi.food_id, oi.quantity, oi.price, oi.comment, f.name_" . $_SESSION['lang'] . " AS name FROM order_items oi JOIN foods f ON oi.food_id = f.id WHERE oi.order_id = ?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$order_items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

// Send request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    $request_text = $_POST['request_text'] ?? '';
    if (!empty($request_text)) {
        file_put_contents('debug.txt', "Request sent: " . $request_text . " for order_id: " . $order_id . "\n", FILE_APPEND);
    }
}

// Call waiter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['call_waiter'])) {
    file_put_contents('debug.txt', "Waiter called for order_id: " . $order_id . "\n", FILE_APPEND);
}

?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['order_detail'] ?? 'Order Detail'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .waiter-info img {
            max-width: 100px;
            border-radius: 50%;
        }
        .section {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body class="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
    <div class="container mt-4">
        <h2><?php echo $lang['order_detail'] ?? 'Order Detail'; ?></h2>

        <!-- جزئیات سفارش -->
        <div class="section">
            <h3><?php echo $lang['order_items'] ?? 'Order Items'; ?></h3>
            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo $lang['item'] ?? 'Item'; ?></th>
                        <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                        <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                        <th><?php echo $lang['subtotal'] ?? 'Subtotal'; ?></th>
                        <th><?php echo $lang['Special_Request'] ?? 'Special Request'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                            <td><?php echo number_format($item['price'] * $item['quantity'], $currency_Decimal); ?> <?php echo $currency; ?></td>
                            <td><?php echo htmlspecialchars($item['comment'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><?php echo $lang['total'] ?? 'Total'; ?>: <?php echo number_format($total_price, $currency_Decimal); ?> <?php echo $currency; ?></p>
            <p>VAT: <?php echo number_format($vat_amount, $currency_Decimal); ?> <?php echo $currency; ?></p>
            <p class="grand-total"><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>: <?php echo number_format($grand_total, $currency_Decimal); ?> <?php echo $currency; ?></p>
        </div>

        <!-- اطلاعات گارسون -->
        <?php if ($waiter): ?>
            <div class="section waiter-info">
                <h3><?php echo $lang['your_waiter'] ?? 'Your Waiter'; ?></h3>
                <img src="<?php echo htmlspecialchars($waiter['image_url']); ?>" alt="<?php echo htmlspecialchars($waiter['name']); ?>">
                <p><?php echo $lang['name'] ?? 'Name'; ?>: <?php echo htmlspecialchars($waiter['name']); ?></p>
            </div>
        <?php endif; ?>

        <!-- وضعیت آماده‌سازی -->
        <div class="section">
            <h3><?php echo $lang['order_status'] ?? 'Order Status'; ?></h3>
            <p><?php echo $order_status; ?></p>
            <?php if ($estimated_time): ?>
                <p><?php echo $lang['estimated_time'] ?? 'Estimated Delivery Time'; ?>: <?php echo $estimated_time; ?> <?php echo $lang['minutes'] ?? 'minutes'; ?></p>
            <?php endif; ?>
            <?php if ($table_number): ?>
                <p><?php echo $lang['table_number'] ?? 'Table Number'; ?>: <?php echo $table_number; ?></p>
            <?php endif; ?>
        </div>

        <!-- درخواست جدید -->
        <div class="section">
            <h3><?php echo $lang['send_request'] ?? 'Send Request'; ?></h3>
            <form method="POST">
                <textarea name="request_text" class="form-control" rows="3" placeholder="<?php echo $lang['enter_request'] ?? 'Enter your request'; ?>"></textarea>
                <button type="submit" name="send_request" class="btn btn-secondary mt-2"><i class="fas fa-paper-plane"></i> <?php echo $lang['submit'] ?? 'Submit'; ?></button>
            </form>
        </div>

        <!-- فراخوانی گارسون -->
        <div class="section">
            <h3><?php echo $lang['call_waiter'] ?? 'Call Waiter'; ?></h3>
            <form method="POST">
                <button type="submit" name="call_waiter" class="btn btn-warning"><i class="fas fa-bell"></i> <?php echo $lang['call'] ?? 'Call'; ?></button>
            </form>
        </div>

        <!-- پرداخت -->
        <div class="section">
            <h3><?php echo $lang['payment'] ?? 'Payment'; ?></h3>
            <p><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>: <?php echo number_format($grand_total, $currency_Decimal); ?> <?php echo $currency; ?></p>
            <button class="btn btn-success"><i class="fas fa-credit-card"></i> <?php echo $lang['pay_now'] ?? 'Pay Now'; ?></button>
        </div>
    </div>
</body>
</html>