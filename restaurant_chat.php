<?php
include 'db.php';

$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    echo "Invalid order ID";
    exit();
}

// Send message from restaurant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_text = trim($_POST['message_text'] ?? '');
    if (!empty($message_text)) {
        $stmt_message = $conn->prepare("INSERT INTO order_messages (order_id, user_id, message_text, sender_type) VALUES (?, NULL, ?, 'restaurant')");
        $stmt_message->bind_param("is", $order_id, $message_text);
        $stmt_message->execute();
        header("Location: restaurant_chat.php?order_id=" . $order_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Restaurant Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Chat for Order #<?php echo $order_id; ?></h2>
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
        <form method="POST">
            <div class="d-flex align-items-center gap-3">
                <textarea name="message_text" class="form-control" rows="2" placeholder="Enter your response"></textarea>
                <button type="submit" name="send_message" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
            </div>
        </form>
    </div>
</body>
</html>