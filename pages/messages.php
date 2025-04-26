<?php
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Fetch order details
if ($order_id > 0) {
    $stmt = $conn->prepare("
        SELECT o.*, u.username 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $error = $lang['order_not_found'] ?? "Order not found.";
    }
}

// Fetch messages for the order
$messages = [];
if ($order_id > 0 && !isset($error)) {
    $stmt = $conn->prepare("
        SELECT om.*, u.username as sender_name 
        FROM order_messages om 
        LEFT JOIN users u ON om.sender_id = u.id AND om.sender_type = 'customer' 
        WHERE om.order_id = ? 
        ORDER BY om.created_at ASC
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Mark messages as read
    $stmt = $conn->prepare("UPDATE order_messages SET is_read = 1 WHERE order_id = ? AND sender_type = 'customer'");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message']) && $order_id > 0 && !isset($error)) {
    $message_text = $_POST['message_text'] ?? '';
    if (!empty($message_text)) {
        $stmt = $conn->prepare("
            INSERT INTO order_messages (order_id, sender_type, sender_id, message_text, created_at) 
            VALUES (?, 'admin', ?, ?, NOW())
        ");
        $admin_id = $_SESSION['admin']['id'] ?? 0; // فرض می‌کنم ID ادمین در سشن ذخیره شده
        $stmt->bind_param("iis", $order_id, $admin_id, $message_text);
        if ($stmt->execute()) {
            // به جای ریدایرکت به داشبورد، صفحه رو رفرش می‌کنیم تا در همون صفحه بمونه
            header("Location: admin_dashboard.php?page=messages&order_id=$order_id");
            exit();
        } else {
            $error = $lang['message_send_failed'] ?? "Failed to send message.";
        }
        $stmt->close();
    } else {
        $error = $lang['message_empty'] ?? "Message cannot be empty.";
    }
}

// Fetch all orders with messages for the sidebar
$orders_with_messages = [];
$stmt = $conn->prepare("
    SELECT o.id, o.created_at, u.username, COUNT(om.id) as message_count, SUM(CASE WHEN om.is_read = 0 AND om.sender_type = 'customer' THEN 1 ELSE 0 END) as unread_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_messages om ON o.id = om.order_id
    GROUP BY o.id
    HAVING message_count > 0
    ORDER BY o.created_at DESC
");
$stmt->execute();
$orders_with_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="admin-section messages-page">
    <div class="messages-container">
        <div class="messages-sidebar">
            <h3><?php echo $lang['orders_with_messages'] ?? 'Orders with Messages'; ?></h3>
            <ul>
                <?php foreach ($orders_with_messages as $order_msg): ?>
                    <li class="<?php echo $order_id == $order_msg['id'] ? 'active' : ''; ?>">
                        <a href="admin_dashboard.php?page=messages&order_id=<?php echo $order_msg['id']; ?>">
                            <?php echo htmlspecialchars($order_msg['username']); ?> (#<?php echo $order_msg['id']; ?>)
                            <?php if ($order_msg['unread_count'] > 0): ?>
                                <span class="unread-count"><?php echo $order_msg['unread_count']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="messages-content">
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php elseif ($order_id == 0): ?>
                <p><?php echo $lang['select_order'] ?? 'Please select an order to view messages.'; ?></p>
            <?php else: ?>
                <h3><?php echo $lang['messages_for_order'] ?? 'Messages for Order'; ?> #<?php echo $order_id; ?> (<?php echo htmlspecialchars($order['username']); ?>)</h3>
                <div class="messages-list">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['sender_type'] == 'admin' ? 'sent' : 'received'; ?>">
                            <div class="message-header">
                                <strong><?php echo $message['sender_type'] == 'admin' ? ($lang['you'] ?? 'You') : htmlspecialchars($message['sender_name']); ?></strong>
                                <span><?php echo $message['created_at']; ?></span>
                            </div>
                            <p><?php echo htmlspecialchars($message['message_text']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form action="" method="POST" class="message-form">
                    <textarea name="message_text" placeholder="<?php echo $lang['type_message'] ?? 'Type your message...'; ?>" required></textarea>
                    <button type="submit" name="send_message"><?php echo $lang['send'] ?? 'Send'; ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.messages-page {
    display: flex;
    flex-direction: row;
    height: 100%;
}

.messages-container {
    display: flex;
    width: 100%;
    height: 80vh;
}

.messages-sidebar {
    width: 30%;
    border-right: 1px solid #ddd;
    padding: 20px;
    overflow-y: auto;
}

.messages-sidebar ul {
    list-style: none;
    padding: 0;
}

.messages-sidebar li {
    margin-bottom: 10px;
}

.messages-sidebar li a {
    display: block;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 5px;
    text-decoration: none;
    color: #333;
}

.messages-sidebar li.active a {
    background: #007bff;
    color: white;
}

.messages-sidebar .unread-count {
    display: inline-block;
    min-width: 20px;
    height: 20px;
    background-color: #dc3545;
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 20px;
    font-size: 12px;
    margin-left: 5px;
}

.messages-content {
    width: 70%;
    padding: 20px;
    overflow-y: auto;
}

.messages-list {
    margin-bottom: 20px;
}

.message {
    margin-bottom: 15px;
    padding: 10px;
    border-radius: 5px;
}

.message.sent {
    background: #007bff;
    color: white;
    margin-left: 20%;
    margin-right: 5%;
}

.message.received {
    background: #f5f5f5;
    margin-right: 20%;
    margin-left: 5%;
}

.message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.message-form textarea {
    width: 100%;
    height: 100px;
    margin-bottom: 10px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.message-form button {
    padding: 10px 20px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.message-form button:hover {
    background: #0056b3;
}
</style>