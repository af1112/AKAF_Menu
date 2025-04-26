<?php
session_start();
include 'db.php';

// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];

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

// Fetch orders with messages and check for unread messages
$stmt_orders = $conn->prepare("
    SELECT DISTINCT o.id AS order_id, o.user_id, u.username AS customer_name, o.table_number,
    (SELECT COUNT(*) FROM order_messages om2 WHERE om2.order_id = o.id AND om2.sender_type = 'customer' AND om2.is_read = 0) AS unread_count
    FROM orders o 
    JOIN order_messages om ON o.id = om.order_id 
    JOIN users u ON o.user_id = u.id 
    WHERE om.sender_type = 'customer' 
    ORDER BY om.created_at DESC
");
$stmt_orders->execute();
$orders_with_messages = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total unread messages for sidebar
$stmt_total_unread = $conn->prepare("
    SELECT COUNT(*) AS total_unread 
    FROM order_messages om 
    JOIN orders o ON om.order_id = o.id 
    WHERE om.sender_type = 'customer' AND om.is_read = 0
");
$stmt_total_unread->execute();
$total_unread = $stmt_total_unread->get_result()->fetch_assoc()['total_unread'];

// Mark messages as read when the admin views them
$selected_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
if ($selected_order_id) {
    $stmt_mark_read = $conn->prepare("UPDATE order_messages SET is_read = 1 WHERE order_id = ? AND sender_type = 'customer' AND is_read = 0");
    $stmt_mark_read->bind_param("i", $selected_order_id);
    $stmt_mark_read->execute();
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $order_id = intval($_POST['order_id']);
    $message_text = trim($_POST['message_text'] ?? '');
    
    if (!empty($message_text)) {
        $stmt_message = $conn->prepare("INSERT INTO order_messages (order_id, user_id, message_text, sender_type, is_read) VALUES (?, ?, ?, 'restaurant', 1)");
        $user_id = $orders_with_messages[array_search($order_id, array_column($orders_with_messages, 'order_id'))]['user_id'];
        $stmt_message->bind_param("iis", $order_id, $user_id, $message_text);
        $stmt_message->execute();
        
        $redirect_url = "admin_dashboard.php?page=messages";
        if (isset($_GET['lang'])) {
            $redirect_url .= "&lang=" . $_GET['lang'];
        }
        if (isset($_GET['theme'])) {
            $redirect_url .= "&theme=" . $_GET['theme'];
        }
        header("Location: $redirect_url");
        exit();
    }
}

// Determine which page to show
$page = $_GET['page'] ?? 'overview';
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['admin_dashboard'] ?? 'Admin Dashboard'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>" media="only screen and (max-width: 768px)">
    <style>
        .admin-content {
            padding: 20px;
        }

        .order-list {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
        }

        .order-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.3s;
            position: relative;
        }

        .order-item:hover {
            background-color: #f1f1f1;
        }

        .order-item.active {
            background-color: #e9ecef;
            font-weight: bold;
        }

        .unread-count {
            display: inline-block;
            min-width: 20px;
            height: 20px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            font-size: 12px;
            position: absolute;
            top: 10px;
            <?php echo $is_rtl ? 'left: 10px;' : 'right: 10px;'; ?>
        }

        .sidebar-unread-count {
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

        .chat-container {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
            background-color: #f9f9f9;
        }

        .chat-message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }

        .chat-message.customer {
            justify-content: flex-start;
            text-align: left;
        }

        .chat-message.restaurant {
            justify-content: flex-end;
            text-align: right;
        }

        .chat-message .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            position: relative;
        }

        .chat-message.customer .message-bubble {
            background-color: #e9ecef;
            color: #333;
            border-bottom-left-radius: 0;
        }

        .chat-message.restaurant .message-bubble {
            background-color: #007bff;
            color: white;
            border-bottom-right-radius: 0;
        }

        .chat-message .message-time {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .chat-message.customer .message-time {
            text-align: left;
        }

        .chat-message.restaurant .message-time {
            text-align: right;
        }

        .chat-input {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-input textarea {
            resize: none;
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .chat-input button {
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .chat-input button:hover {
            background-color: #0056b3;
        }

        .admin-section {
            margin-bottom: 20px;
        }

        .notification {
            position: fixed;
            top: 20px;
            <?php echo $is_rtl ? 'left: 20px;' : 'right: 20px;'; ?>
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: none;
        }

        @media (max-width: 768px) {
            .admin-content {
                padding: 10px;
            }

            .chat-input {
                flex-direction: column;
            }

            .chat-input textarea {
                margin-bottom: 10px;
            }

            .notification {
                top: 10px;
                <?php echo $is_rtl ? 'left: 10px;' : 'right: 10px;'; ?>
                width: 200px;
            }
        }
    </style>
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['admin_dashboard'] ?? 'Admin Dashboard'; ?></h1>
        <div class="controls">
            <select onchange="window.location='admin_dashboard.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="admin_dashboard.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
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
                <a href="manage_orders.php">
                    <i class="fas fa-shopping-cart"></i> <?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?>
                </a>
            </li>
            <li>
                <a href="manage_hero_texts.php">
                    <i class="fas fa-heading"></i> <?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?>
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?page=messages">
                    <i class="fas fa-envelope"></i> <?php echo $lang['manage_messages'] ?? 'Manage Messages'; ?>
                    <?php if ($total_unread > 0): ?>
                        <span class="sidebar-unread-count"><?php echo $total_unread; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </aside>

    <main class="admin-content">
        <?php if ($page === 'overview'): ?>
            <div class="admin-section">
                <h3><?php echo $lang['welcome_admin'] ?? 'Welcome to Admin Dashboard'; ?></h3>
                <p><?php echo $lang['select_option'] ?? 'Please select an option from the sidebar to manage the restaurant.'; ?></p>
            </div>
        <?php elseif ($page === 'messages'): ?>
            <div class="admin-section">
                <h3><?php echo $lang['messages_from_customers'] ?? 'Messages from Customers'; ?></h3>
                <div class="row">
                    <!-- List of Orders with Messages -->
                    <div class="col-md-4">
                        <div class="order-list" id="order-list">
                            <?php if (empty($orders_with_messages)): ?>
                                <p><?php echo $lang['no_messages'] ?? 'No messages yet.'; ?></p>
                            <?php else: ?>
                                <?php foreach ($orders_with_messages as $order): ?>
                                    <a href="admin_dashboard.php?page=messages&order_id=<?php echo $order['order_id']; ?>&lang=<?php echo $_SESSION['lang']; ?>&theme=<?php echo $theme; ?>" class="text-decoration-none">
                                        <div class="order-item <?php echo $selected_order_id === $order['order_id'] ? 'active' : ''; ?>">
                                            <strong><?php echo $lang['order'] ?? 'Order'; ?> #<?php echo $order['order_id']; ?></strong>
                                            <?php if ($order['unread_count'] > 0): ?>
                                                <span class="unread-count"><?php echo $order['unread_count']; ?></span>
                                            <?php endif; ?>
                                            <br>
                                            <span><?php echo $lang['customer'] ?? 'Customer'; ?>: <?php echo htmlspecialchars($order['customer_name']); ?></span><br>
                                            <?php if ($order['table_number']): ?>
                                                <span><?php echo $lang['table_number'] ?? 'Table'; ?>: <?php echo $order['table_number']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Section -->
                    <div class="col-md-8">
                        <?php if ($selected_order_id): ?>
                            <h4><?php echo $lang['chat_with_customer'] ?? 'Chat with Customer'; ?> (<?php echo $lang['order'] ?? 'Order'; ?> #<?php echo $selected_order_id; ?>)</h4>
                            <div class="chat-container" id="chat-container">
                                <?php
                                $stmt_messages = $conn->prepare("SELECT message_text, sender_type, created_at FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
                                $stmt_messages->bind_param("i", $selected_order_id);
                                $stmt_messages->execute();
                                $messages = $stmt_messages->get_result()->fetch_all(MYSQLI_ASSOC);

                                foreach ($messages as $message):
                                ?>
                                    <div class="chat-message <?php echo $message['sender_type']; ?>">
                                        <div class="message-bubble">
                                            <?php echo htmlspecialchars($message['message_text']); ?>
                                        </div>
                                    </div>
                                    <div class="chat-message <?php echo $message['sender_type']; ?>">
                                        <div class="message-time">
                                            <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="chat-input">
                                <form method="POST" style="width: 100%;">
                                    <input type="hidden" name="order_id" value="<?php echo $selected_order_id; ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <textarea name="message_text" rows="2" placeholder="<?php echo $lang['enter_message'] ?? 'Enter your message'; ?>"></textarea>
                                        <button type="submit" name="send_message"><i class="fas fa-paper-plane"></i> <?php echo $lang['send'] ?? 'Send'; ?></button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <p><?php echo $lang['select_order_to_chat'] ?? 'Please select an order to start chatting.'; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <div class="notification" id="notification"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let lastMessageCount = 0;
        let lastTotalUnread = <?php echo $total_unread; ?>;

        // Load messages for the selected order
        function loadMessages() {
            const orderId = <?php echo json_encode($selected_order_id); ?>;
            if (!orderId) return;

            $.get('get_order_messages.php?order_id=' + orderId, function(data) {
                if (!data || !data.messages) {
                    console.log("No messages found or invalid response");
                    return;
                }

                const chatContainer = $('#chat-container');
                const currentMessageCount = data.messages.length;

                if (currentMessageCount > lastMessageCount) {
                    lastMessageCount = currentMessageCount;
                }

                chatContainer.empty();
                data.messages.forEach(message => {
                    const messageClass = message.sender_type === 'customer' ? 'customer' : 'restaurant';
                    const messageHtml = `
                        <div class="chat-message ${messageClass}">
                            <div class="message-bubble">
                                ${message.message_text}
                            </div>
                        </div>
                        <div class="chat-message ${messageClass}">
                            <div class="message-time">
                                ${message.created_at}
                            </div>
                        </div>
                    `;
                    chatContainer.append(messageHtml);
                });

                chatContainer.scrollTop(chatContainer[0].scrollHeight);
            }).fail(function() {
                console.log("Error fetching messages");
            });
        }

        // Load orders and update unread counts
        function loadOrders() {
            $.get('get_orders_with_messages.php', function(data) {
                if (!data || !data.orders) {
                    console.log("No orders found or invalid response");
                    return;
                }

                const orderList = $('#order-list');
                orderList.empty();

                if (data.orders.length === 0) {
                    orderList.append('<p><?php echo $lang['no_messages'] ?? 'No messages yet.'; ?></p>');
                } else {
                    data.orders.forEach(order => {
                        const isActive = order.order_id == <?php echo json_encode($selected_order_id); ?>;
                        const unreadCountHtml = order.unread_count > 0 ? `<span class="unread-count">${order.unread_count}</span>` : '';
                        const orderHtml = `
                            <a href="admin_dashboard.php?page=messages&order_id=${order.order_id}&lang=<?php echo $_SESSION['lang']; ?>&theme=<?php echo $theme; ?>" class="text-decoration-none">
                                <div class="order-item ${isActive ? 'active' : ''}">
                                    <strong><?php echo $lang['order'] ?? 'Order'; ?> #${order.order_id}</strong>
                                    ${unreadCountHtml}
                                    <br>
                                    <span><?php echo $lang['customer'] ?? 'Customer'; ?>: ${order.customer_name}</span><br>
                                    ${order.table_number ? `<span><?php echo $lang['table_number'] ?? 'Table'; ?>: ${order.table_number}</span>` : ''}
                                </div>
                            </a>
                        `;
                        orderList.append(orderHtml);
                    });
                }
            }).fail(function() {
                console.log("Error fetching orders");
            });
        }

        // Check for new messages and show notification
        function checkNewMessages() {
            $.get('get_total_unread_messages.php', function(data) {
                if (!data || !data.total_unread) {
                    console.log("No unread messages data");
                    return;
                }

                const totalUnread = parseInt(data.total_unread);
                if (totalUnread > lastTotalUnread) {
                    const notification = $('#notification');
                    notification.text(`<?php echo $lang['new_message'] ?? 'New Message'; ?>: ${totalUnread}`);
                    notification.fadeIn(500).delay(3000).fadeOut(500);

                    // Play sound
                    const audio = new Audio('notification.mp3');
                    audio.play().catch(error => console.log("Error playing sound:", error));

                    lastTotalUnread = totalUnread;
                }

                // Update sidebar unread count
                const sidebarUnread = $('.sidebar-unread-count');
                if (totalUnread > 0) {
                    if (sidebarUnread.length) {
                        sidebarUnread.text(totalUnread);
                    } else {
                        $('a[href="admin_dashboard.php?page=messages"]').append(`<span class="sidebar-unread-count">${totalUnread}</span>`);
                    }
                } else {
                    sidebarUnread.remove();
                }

                // Update orders list if on messages page
                if (window.location.href.includes('page=messages')) {
                    loadOrders();
                }
            }).fail(function() {
                console.log("Error fetching unread messages");
            });
        }

        $(document).ready(function() {
            // Load messages for selected order
            if (<?php echo json_encode($selected_order_id); ?>) {
                loadMessages();
                setInterval(loadMessages, 5000);
            }

            // Check for new messages every 5 seconds
            setInterval(checkNewMessages, 5000);
        });
    </script>
</body>
</html>