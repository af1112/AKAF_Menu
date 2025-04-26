<?php
session_start();
include 'db.php';

// Load settings
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency'");
$stmt->execute();
$currency = $stmt->get_result()->fetch_assoc()['value'] ?? 'OMR';

$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency_Decimal'");
$stmt->execute();
$currency_Decimal = $stmt->get_result()->fetch_assoc()['value'] ?? '3';

$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'vat_rate'");
$stmt->execute();
$vat_rate = floatval($stmt->get_result()->fetch_assoc()['value'] ?? 0.0);

$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'apply_vat'");
$stmt->execute();
$apply_vat = intval($stmt->get_result()->fetch_assoc()['value'] ?? 0);

// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];

// Load language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
include "languages/" . $_SESSION['lang'] . ".php";
file_put_contents('debug.txt', "Loaded lang: " . print_r($lang, true) . "\n", FILE_APPEND);

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

// Detect language direction
$rtl_languages = ['fa', 'ar'];
$is_rtl = in_array($_SESSION['lang'], $rtl_languages);
$direction = $is_rtl ? 'rtl' : 'ltr';

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: cart.php");
    exit();
}

$order_id = intval($_GET['order_id']);
if (!$is_logged_in) {
    header("Location: user_login.php");
    exit();
}

// Load order
$stmt_order = $conn->prepare("SELECT total_price, status, waiter_id, estimated_time, table_number, discount, order_type FROM orders WHERE id = ? AND user_id = ?");
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();

if (!$order) {
    header("Location: cart.php");
    exit();
}

$total_price = $order['total_price'] ?? 0;
$vat_amount = $apply_vat ? ($total_price * $vat_rate) : 0;
$discount = $order['discount'] ?? 0;
$grand_total = $total_price + $vat_amount - $discount;
$order_status = $order['status'] ?? 'Confirmed';
$waiter_id = $order['waiter_id'] ?? null;
$estimated_time = $order['estimated_time'] ?? null;
$table_number = $order['table_number'] ?? '';
$order_type = $order['order_type'] ?? 'dine_in';

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

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_text = trim($_POST['message_text'] ?? '');
    if (!empty($message_text)) {
        $stmt_message = $conn->prepare("INSERT INTO order_messages (order_id, user_id, message_text, sender_type) VALUES (?, ?, ?, 'customer')");
        $stmt_message->bind_param("iis", $order_id, $user_id, $message_text);
        $stmt_message->execute();
        file_put_contents('debug.txt', "Message saved: " . $message_text . " for order_id: " . $order_id . "\n", FILE_APPEND);
        
        $redirect_url = "order_detail.php?order_id=" . $order_id;
        if (isset($_GET['lang'])) {
            $redirect_url .= "&lang=" . $_GET['lang'];
        }
        if (isset($_GET['theme'])) {
            $redirect_url .= "&theme=" . $_GET['theme'];
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

// Call waiter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['call_waiter'])) {
    $stmt_call = $conn->prepare("INSERT INTO waiter_calls (order_id, user_id) VALUES (?, ?)");
    $stmt_call->bind_param("ii", $order_id, $user_id);
    $stmt_call->execute();
    file_put_contents('debug.txt', "Waiter called for order_id: " . $order_id . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

// Define status steps based on order type
$status_steps = [
    'dine_in' => ['Confirmed', 'Preparing', 'Serving', 'Completed'],
    'delivery' => ['Confirmed', 'Preparing', 'On the way', 'Delivered'],
    'takeaway' => ['Confirmed', 'Preparing', 'Ready', 'Delivered'],
    'drive_thru' => ['Confirmed', 'Preparing', 'Ready', 'Delivered'],
    'contactless_delivery' => ['Confirmed', 'Preparing', 'On the way', 'Delivered'],
    'curbside_pickup' => ['Confirmed', 'Preparing', 'Ready', 'Delivered']
];
$order_steps = $status_steps[$order_type] ?? $status_steps['dine_in'];

// محاسبه تعداد آیتم‌های سبد خرید
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = count($_SESSION['cart']);
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['cart'] ?? 'Cart'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .desktop-menu a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            font-size: 16px;
        }

        .order-number {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .order-number.rtl {
            text-align: right;
        }

        .order-number.ltr {
            text-align: left;
        }

        .total {
            font-size: 18px;
            margin-bottom: 20px;
            max-width: 300px;
        }

        .total.rtl {
            align-items: flex-start !important;
        }

        .total.rtl .summary-row {
            justify-content: flex-start !important;
        }

        .total.ltr {
            align-items: flex-end;
        }

        .total.ltr .summary-row {
            justify-content: flex-end;
        }

        .total .summary-row {
            display: flex;
            width: 100%;
            margin-bottom: 5px;
        }

        .total .summary-row .label {
            flex: 1;
            text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>;
            margin-right: 10px;
            margin-left: 10px;
            font-weight: 700;
        }

        .total .summary-row .value {
            flex: 1;
            text-align: right;
        }

        .total .summary-row .currency {
            width: 50px;
            text-align: left;
            margin-left: 5px;
        }

        .total .grand-total .summary-row {
            font-weight: bold;
            font-size: 20px;
        }

        .menu-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #ffffff;
            display: none;
            justify-content: space-around;
            padding: 5px 0;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .menu-bar a {
            text-decoration: none;
            color: #666;
            font-size: 10px;
            text-align: center;
            flex: 1;
            position: relative;
            transition: all 0.3s ease;
        }

        .menu-bar a i {
            font-size: 22px;
            display: block;
            margin-bottom: 0px;
        }

        .cart-badge {
            position: absolute;
            top: 0;
            right: 15px;
            background: red;
            color: white;
            font-size: 10px;
            width: 16px;
            height: 16px;
            line-height: 18px;
            text-align: center;
            border-radius: 50%;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 1000px) {
            .desktop-menu {
                display: none;
            }

            .menu-bar {
                display: flex;
            }
        }

        @media (max-width: 500px) {
            .navbar {
                display: none;
            }
        }

        .timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 20px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 4px;
            background: #ddd;
            z-index: 1;
            transition: background 0.5s ease;
        }

        .timeline-item {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 2;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            background: #ddd;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 20px;
        }

        .timeline-item.active .timeline-icon {
            background: #28a745;
            animation: pulse 1.5s infinite;
        }

        .timeline-item.completed .timeline-icon {
            background: #007bff;
        }

        .timeline-content {
            font-size: 14px;
            color: #333;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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
            justify-content: flex-end;
            text-align: right;
        }

        .chat-message.restaurant {
            justify-content: flex-start;
            text-align: left;
        }

        .chat-message .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            position: relative;
        }

        .chat-message.customer .message-bubble {
            background-color: #007bff;
            color: white;
            border-bottom-right-radius: 0;
        }

        .chat-message.restaurant .message-bubble {
            background-color: #e9ecef;
            color: #333;
            border-bottom-left-radius: 0;
        }

        .chat-message .message-time {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .chat-message.customer .message-time {
            text-align: right;
        }

        .chat-message.restaurant .message-time {
            text-align: left;
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
<body class="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
    <!-- Language Bar -->
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
                <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="order_detail.php?order_id=<?php echo $order_id; ?>&lang=en&v=<?php echo time(); ?>">
                    <img src="images/flags/en.png" alt="English" class="flag-icon"> EN
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="order_detail.php?order_id=<?php echo $order_id; ?>&lang=fa&v=<?php echo time(); ?>">
                    <img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="order_detail.php?order_id=<?php echo $order_id; ?>&lang=ar&v=<?php echo time(); ?>">
                    <img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
                </a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="order_detail.php?order_id=<?php echo $order_id; ?>&lang=fr&v=<?php echo time(); ?>">
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
                    <li class="nav-item">
                        <a class="nav-link" href="menu.php">
                            <i class="fas fa-bars"></i> <?php echo $lang['home'] ?? 'Home'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="checkout.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                            <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                            <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
                        </a>
                    </li>
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (!$is_rtl): ?>
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
    <!-- منوی پایین مخصوص موبایل -->
    <div class="menu-bar" id="menu">
        <a href="index.php" class="active">
            <i class="fa-solid fa-house"></i>
            <span class="menu-text"><?php echo $lang['home'] ?? 'Home'; ?></span>
        </a>
        <a href="search.php">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span class="menu-text"><?php echo $lang['search'] ?? 'Search'; ?></span>
        </a>
        <a href="cart.php" class="shopping-cart">
            <i class="fa-solid fa-shopping-cart"></i>
            <span class="menu-text"><?php echo $lang['shopping_cart'] ?? 'Shopping Cart'; ?></span>
            <?php if ($cart_count > 0): ?>
                <span class="cart-badge" id="cart-count"><?php echo $cart_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="favourite.php">
            <i class="fa-solid fa-heart"></i>
            <span class="menu-text"><?php echo $lang['favourite'] ?? 'Favourite'; ?></span>
        </a>
        <a href="menu.php">
            <i class="fa-solid fa-ellipsis-vertical"></i>
            <span class="menu-text"><?php echo $lang['menu'] ?? 'Menu'; ?></span>
        </a>
    </div>

    <div class="container mt-4">
        <h2 class="text-center"><?php echo $lang['order_detail'] ?? 'Order Detail'; ?></h2>

        <!-- جزئیات سفارش -->
        <div class="section">
            <div class="order-number <?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
                <?php echo $lang['order_number'] ?? 'Order #'; ?><?php echo $order_id; ?>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo $lang['items'] ?? 'Items'; ?></th>
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
            <div class="total <?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
                <div class="summary-row">
                    <span class="label"><?php echo $lang['total'] ?? 'Total'; ?>:</span>
                    <span class="value"><?php echo number_format($total_price, $currency_Decimal); ?></span>
                    <span class="currency"><?php echo $currency; ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">VAT (<?php echo $vat_rate * 100; ?>%):</span>
                    <span class="value"><?php echo number_format($vat_amount, $currency_Decimal); ?></span>
                    <span class="currency"><?php echo $currency; ?></span>
                </div>
                <?php if ($discount > 0): ?>
                    <div class="summary-row">
                        <span class="label"><?php echo $lang['discount'] ?? 'Discount'; ?>:</span>
                        <span class="value"><?php echo number_format($discount, $currency_Decimal); ?></span>
                        <span class="currency"><?php echo $currency; ?></span>
                    </div>
                <?php endif; ?>
                <div class="summary-row grand-total">
                    <span class="label"><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>:</span>
                    <span class="value"><?php echo number_format($grand_total, $currency_Decimal); ?></span>
                    <span class="currency"><?php echo $currency; ?></span>
                </div>
            </div>
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
        <div class="order-timeline">
            <h4><?php echo $lang['order_progress'] ?? 'Order Progress'; ?></h4>
            <div class="timeline">
                <?php foreach ($order_steps as $index => $step): ?>
                    <?php
                    $is_active = $order_status === $step;
                    $is_completed = array_search($order_status, $order_steps) > $index;
                    ?>
                    <div class="timeline-item <?php echo $is_active ? 'active' : ($is_completed ? 'completed' : ''); ?>">
                        <div class="timeline-icon">
                            <i class="fas fa-<?php
                                if ($step === 'Confirmed') echo 'check-circle';
                                elseif ($step === 'Preparing') echo 'utensils';
                                elseif ($step === 'Serving') echo 'concierge-bell';
                                elseif ($step === 'Completed') echo 'flag-checkered';
                                elseif ($step === 'On the way') echo 'truck';
                                elseif ($step === 'Ready') echo 'box-open';
                                elseif ($step === 'Delivered') echo 'hand-holding';
                                else echo 'circle';
                            ?>"></i>
                        </div>
                        <div class="timeline-content"><?php echo $lang['order_status_' . strtolower(str_replace(' ', '_', $step))] ?? $step; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- چت با رستوران -->
        <div class="section">
            <h3><?php echo $lang['chat_with_restaurant'] ?? 'Chat with Restaurant'; ?></h3>
            <div class="chat-container" id="chat-container">
                <?php
                $stmt_messages = $conn->prepare("SELECT message_text, sender_type, created_at FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
                $stmt_messages->bind_param("i", $order_id);
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
                    <div class="d-flex align-items-center gap-3">
                        <textarea name="message_text" class="form-control" rows="2" placeholder="<?php echo $lang['enter_message'] ?? 'Enter your message'; ?>"></textarea>
                        <button type="submit" name="send_message" class="btn btn-secondary"><i class="fas fa-paper-plane"></i> <?php echo $lang['send'] ?? 'Send'; ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- فراخوانی گارسون -->
        <div class="section">
            <h3><?php echo $lang['call_waiter'] ?? 'Call Waiter'; ?></h3>
            <form method="POST">
                <button type="submit" name="call_waiter" class="btn btn-warning"><i class="fas fa-bell"></i> <?php echo $lang['call'] ?? 'Call'; ?></button>
            </form>
        </div>
        <?php
        $stmt_calls = $conn->prepare("SELECT call_time, attended_time, status FROM waiter_calls WHERE order_id = ? ORDER BY call_time DESC");
        $stmt_calls->bind_param("i", $order_id);
        $stmt_calls->execute();
        $calls = $stmt_calls->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>
        <div class="call-history">
            <h4><?php echo $lang['call_history'] ?? 'Call History'; ?></h4>
            <?php if (empty($calls)): ?>
                <p><?php echo $lang['no_calls'] ?? 'No calls yet'; ?></p>
            <?php else: ?>
                <ul>
                    <?php foreach ($calls as $call): ?>
                        <li>
                            <p><strong><?php echo $lang['call_time'] ?? 'Call Time'; ?>:</strong> <?php echo $call['call_time']; ?></p>
                            <?php if ($call['attended_time']): ?>
                                <p><strong><?php echo $lang['attended_time'] ?? 'Attended Time'; ?>:</strong> <?php echo $call['attended_time']; ?></p>
                            <?php elseif ($call['status'] === 'called'): ?>
                                <p><em><?php echo $lang['waiting'] ?? 'Waiting'; ?></em></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <!-- پرداخت -->
        <div class="section">
            <h3><?php echo $lang['payment'] ?? 'Payment'; ?></h3>
            <p><?php echo $lang['grand_total'] ?? 'Grand Total'; ?>: <?php echo number_format($grand_total, $currency_Decimal); ?> <?php echo $currency; ?></p>
            <button class="btn btn-success"><i class="fas fa-credit-card"></i> <?php echo $lang['pay_now'] ?? 'Pay Now'; ?></button>
        </div>
    </div>

    <!-- تگ audio برای پخش صدای نوتیفیکیشن -->
    <audio id="notification-sound" src="sounds/notification.mp3" preload="auto"></audio>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let isCompleted = false;
        let initialLoad = true;
        // Load last message count from localStorage or set to 0 if not exists
        let lastMessageCount = parseInt(localStorage.getItem('lastMessageCount_<?php echo $order_id; ?>')) || 0;

        function setInitialTimelineStatus() {
            const initialStatus = '<?php echo $order_status; ?>';
            const steps = <?php echo json_encode($order_steps); ?>;
            $('.timeline-item').removeClass('active completed');
            
            console.log("Initial Status: " + initialStatus);
            console.log("Steps: " + JSON.stringify(steps));

            steps.forEach((step, index) => {
                if (initialStatus.toLowerCase() === step.toLowerCase()) {
                    $('.timeline-item').eq(index).addClass('active');
                    console.log("Set active for step: " + step);
                }
                if (steps.indexOf(initialStatus) > index) {
                    $('.timeline-item').eq(index).addClass('completed');
                    console.log("Set completed for step: " + step);
                }
                if (initialStatus.toLowerCase() === steps[steps.length - 1].toLowerCase()) {
                    $('.timeline-item').eq(steps.length - 1).addClass('active completed');
                    isCompleted = true;
                    console.log("Order is completed");
                }
            });
        }

        function updateOrderStatus() {
            if (isCompleted) {
                console.log("Order is already completed, skipping update");
                return;
            }
            $.get('get_order_status.php?order_id=<?php echo $order_id; ?>', function(data) {
                console.log("Fetched Data: " + JSON.stringify(data));
                
                if (!data || !data.status) {
                    console.log("Invalid response or status not found, keeping current timeline state");
                    return;
                }

                const status = data.status;
                const steps = <?php echo json_encode($order_steps); ?>;
                
                console.log("Fetched Status: " + status);
                
                $('.timeline-item').removeClass('active completed');
                steps.forEach((step, index) => {
                    if (status.toLowerCase() === step.toLowerCase()) {
                        $('.timeline-item').eq(index).addClass('active');
                        console.log("Set active for step: " + step);
                    }
                    if (steps.indexOf(status) > index) {
                        $('.timeline-item').eq(index).addClass('completed');
                        console.log("Set completed for step: " + step);
                    }
                    if (status.toLowerCase() === steps[steps.length - 1].toLowerCase()) {
                        $('.timeline-item').eq(steps.length - 1).addClass('active completed');
                        isCompleted = true;
                        console.log("Order is completed");
                    }
                });
            }).fail(function() {
                console.log("Error fetching order status, keeping current timeline state");
            });
        }

        // تابع برای نمایش نوتیفیکیشن
        function showNotification(message) {
            if (Notification.permission === "granted") {
                new Notification("<?php echo $lang['new_message'] ?? 'New Message'; ?>", {
                    body: message,
                    icon: 'images/restaurant-icon.png'
                });
            } else if (Notification.permission !== "denied") {
                Notification.requestPermission().then(permission => {
                    if (permission === "granted") {
                        new Notification("<?php echo $lang['new_message'] ?? 'New Message'; ?>", {
                            body: message,
                            icon: 'images/restaurant-icon.png'
                        });
                    }
                });
            }
        }

        // تابع برای پخش صدای آلارم
        function playNotificationSound() {
            const audio = document.getElementById('notification-sound');
            audio.play().catch(error => {
                console.log("Error playing sound: ", error);
            });
        }

        // تابع برای لود پیام‌ها
        function loadMessages() {
            $.get('get_order_messages.php?order_id=<?php echo $order_id; ?>', function(data) {
                if (!data || !data.messages) {
                    console.log("No messages found or invalid response");
                    return;
                }

                const chatContainer = $('#chat-container');
                const currentMessageCount = data.messages.length;

                // چک کردن پیام جدید از رستوران
                if (currentMessageCount > lastMessageCount) {
                    const newMessages = data.messages.slice(lastMessageCount);
                    newMessages.forEach(message => {
                        if (message.sender_type === 'restaurant') {
                            showNotification(message.message_text);
                            playNotificationSound();
                        }
                    });
                    // به‌روزرسانی lastMessageCount و ذخیره در localStorage
                    lastMessageCount = currentMessageCount;
                    localStorage.setItem('lastMessageCount_<?php echo $order_id; ?>', lastMessageCount);
                }

                chatContainer.empty(); // پاک کردن پیام‌های قبلی
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

                // اسکرول به پایین
                chatContainer.scrollTop(chatContainer[0].scrollHeight);
            }).fail(function() {
                console.log("Error fetching messages");
            });
        }

        $(document).ready(function() {
            setInitialTimelineStatus();
            if (!isCompleted) {
                updateOrderStatus();
                setInterval(updateOrderStatus, 10000);
            }
            initialLoad = false;

            // درخواست اجازه برای نوتیفیکیشن
            if (Notification.permission !== "granted" && Notification.permission !== "denied") {
                Notification.requestPermission();
            }

            // لود اولیه پیام‌ها و به‌روزرسانی هر 5 ثانیه
            loadMessages();
            setInterval(loadMessages, 5000);
        });
    </script>
</body>
</html>