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
include "languages/" . $_SESSION['lang'] . ".php";

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
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['print_order'] ?? 'Print Order'; ?> #<?php echo $order_id; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            width: 80mm;
            margin: 0;
            padding: 10px;
            font-size: 12px;
        }
        h3 {
            text-align: center;
            margin: 0;
            font-size: 14px;
        }
        p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 5px;
            text-align: left;
            border-bottom: 1px dashed #000;
        }
        .total {
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>
<body onload="window.print()">
    <h3><?php echo $lang['order_receipt'] ?? 'Order Receipt'; ?> #<?php echo $order['id']; ?></h3>
    <p><strong><?php echo $lang['user'] ?? 'User'; ?>:</strong> <?php echo htmlspecialchars($order['username']); ?></p>
    <p><strong><?php echo $lang['created_at'] ?? 'Created At'; ?>:</strong> <?php echo $order['created_at']; ?></p>
    <p><strong><?php echo $lang['status'] ?? 'Status'; ?>:</strong> <?php echo $lang['order_status_' . $order['status']] ?? ucfirst($order['status']); ?></p>

    <table>
        <thead>
            <tr>
                <th><?php echo $lang['food_name'] ?? 'Food Name'; ?></th>
                <th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
                <th><?php echo $lang['price'] ?? 'Price'; ?></th>
            </tr>
        </thead>
        <tbody>
            <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['food_name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['price'], 2); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <p class="total"><strong><?php echo $lang['total_price'] ?? 'Total Price'; ?>:</strong> <?php echo number_format($order['total_price'], 2); ?> <?php echo $lang['currency'] ?? '$'; ?></p>
</body>
</html>