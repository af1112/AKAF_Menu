<?php
session_start();
include 'db.php';

// Check if restaurant user is logged in
if (!isset($_SESSION['restaurant_user'])) {
    header("Location: restaurant_login.php");
    exit();
}

$restaurant_user_id = $_SESSION['restaurant_user']['id'];

// Load language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
include "languages/" . $_SESSION['lang'] . ".php";

$rtl_languages = ['fa', 'ar'];
$is_rtl = in_array($_SESSION['lang'], $rtl_languages);
$direction = $is_rtl ? 'rtl' : 'ltr';

// Fetch orders with messages
$stmt_orders = $conn->prepare("
    SELECT DISTINCT o.id AS order_id, o.user_id, u.name AS customer_name, o.table_number 
    FROM orders o 
    JOIN order_messages om ON o.id = om.order_id 
    JOIN users u ON o.user_id = u.id 
    WHERE om.sender_type = 'customer' 
    ORDER BY om.created_at DESC
");
$stmt_orders->execute();
$orders_with_messages = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $order_id = intval($_POST['order_id']);
    $message_text = trim($_POST['message_text'] ?? '');
    
    if (!empty($message_text)) {
        $stmt_message = $conn->prepare("INSERT INTO order_messages (order_id, user_id, message_text, sender_type) VALUES (?, ?, ?, 'restaurant')");
        $user_id = $orders_with_messages[array_search($order_id, array_column($orders_with_messages, 'order_id'))]['user_id'];
        $stmt_message->bind_param("iis", $order_id, $user_id, $message_text);
        $stmt_message->execute();
        
        $redirect_url = "restaurant_dashboard.php?tab=messages";
        if (isset($_GET['lang'])) {
            $redirect_url .= "&lang=" . $_GET['lang'];
        }
        header("Location: $redirect_url");
        exit();
    }
}

// Determine which tab to show
$tab = $_GET['tab'] ?? 'overview';
$selected_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['restaurant_dashboard'] ?? 'Restaurant Dashboard'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .nav-tabs {
            margin-bottom: 20px;
        }

        .tab-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
        }

        .order-item:hover {
            background-color: #f1f1f1;
        }

        .order-item.active {
            background-color: #e9ecef;
            font-weight: bold;
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
        }
    </style>
</head>
<body>
    <!-- Language Bar -->
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
                <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="restaurant_dashboard.php?lang=en&v=<?php echo time(); ?>">
                    <img src="images/flags/en.png" alt="English" class="flag-icon"> EN
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="restaurant_dashboard.php?lang=fa&v=<?php echo time(); ?>">
                    <img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="restaurant_dashboard.php?lang=ar&v=<?php echo time(); ?>">
                    <img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="restaurant_dashboard.php?lang=fr&v=<?php echo time(); ?>">
                    <img src="images/flags/fr.png" alt="French" class="flag-icon"> FR
                </a>
            </div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg custom-navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="restaurant_dashboard.php"><?php echo $lang['restaurant_dashboard'] ?? 'Restaurant Dashboard'; ?></a>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="restaurant_logout.php">
                            <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <h2 class="text-center mb-4"><?php echo $lang['restaurant_dashboard'] ?? 'Restaurant Dashboard'; ?></h2>

        <!-- Tabs -->
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $tab === 'overview' ? 'active' : ''; ?>" href="restaurant_dashboard.php?tab=overview"><?php echo $lang['overview'] ?? 'Overview'; ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab === 'messages' ? 'active' : ''; ?>" href="restaurant_dashboard.php?tab=messages"><?php echo $lang['messages'] ?? 'Messages'; ?></a>
            </li>
        </ul>

        <div class="tab-content">
            <?php if ($tab === 'overview'): ?>
                <!-- Overview Tab -->
                <h3><?php echo $lang['overview'] ?? 'Overview'; ?></h3>
                <p><?php echo $lang['welcome_dashboard'] ?? 'Welcome to your dashboard! Here you can manage orders, messages, and more.'; ?></p>
                <!-- Add more overview content here if needed -->
            <?php elseif ($tab === 'messages'): ?>
                <!-- Messages Tab -->
                <h3><?php echo $lang['messages_from_customers'] ?? 'Messages from Customers'; ?></h3>
                <div class="row">
                    <!-- List of Orders with Messages -->
                    <div class="col-md-4">
                        <div class="order-list">
                            <?php if (empty($orders_with_messages)): ?>
                                <p><?php echo $lang['no_messages'] ?? 'No messages yet.'; ?></p>
                            <?php else: ?>
                                <?php foreach ($orders_with_messages as $order): ?>
                                    <a href="restaurant_dashboard.php?tab=messages&order_id=<?php echo $order['order_id']; ?>&lang=<?php echo $_SESSION['lang']; ?>" class="text-decoration-none">
                                        <div class="order-item <?php echo $selected_order_id === $order['order_id'] ? 'active' : ''; ?>">
                                            <strong><?php echo $lang['order'] ?? 'Order'; ?> #<?php echo $order['order_id']; ?></strong><br>
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
                                        <textarea name="message_text" class="form-control" rows="2" placeholder="<?php echo $lang['enter_message'] ?? 'Enter your message'; ?>"></textarea>
                                        <button type="submit" name="send_message" class="btn btn-secondary"><i class="fas fa-paper-plane"></i> <?php echo $lang['send'] ?? 'Send'; ?></button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <p><?php echo $lang['select_order_to_chat'] ?? 'Please select an order to start chatting.'; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let lastMessageCount = 0;

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

        $(document).ready(function() {
            if (<?php echo json_encode($selected_order_id); ?>) {
                loadMessages();
                setInterval(loadMessages, 5000);
            }
        });
    </script>
</body>
</html>