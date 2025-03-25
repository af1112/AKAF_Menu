<?php
session_start();
include 'db.php';

// Restrict access to logged-in users
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Load language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
include "languages/" . $_SESSION['lang'] . ".php";

// Fetch cart items
$stmt = $conn->prepare("SELECT c.food_id, c.quantity, f.name_en, f.price FROM cart c JOIN foods f ON c.food_id = f.id WHERE c.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

if ($cart_result->num_rows == 0) {
    header("Location: menu.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['complete_order']; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>🍽️ <?php echo $lang['complete_order']; ?></h1>

    <div class="form-container">
        <form id="orderForm" action="process_order.php" method="POST">
            <!-- Cart Summary -->
            <div class="form-group">
                <label><?php echo $lang['your_order']; ?>:</label>
                <ul class="cart-items">
                    <?php
                    $total = 0;
                    while ($cart = $cart_result->fetch_assoc()) {
                        $subtotal = $cart['price'] * $cart['quantity'];
                        $total += $subtotal;
                        echo "<li>{$cart['name_en']} (x{$cart['quantity']}) - \$$subtotal</li>";
                    }
                    ?>
                </ul>
                <p><strong><?php echo $lang['total']; ?>: $<?php echo number_format($total, 2); ?></strong></p>
            </div>

            <!-- Order Type Selection -->
            <div class="form-group">
                <label><?php echo $lang['order_type']; ?>:</label>
                <select name="order_type" id="orderType" onchange="toggleOrderFields()" required>
                    <option value=""><?php echo $lang['select_type']; ?></option>
                    <option value="delivery"><?php echo $lang['delivery']; ?></option>
                    <option value="dine-in"><?php echo $lang['dine_in']; ?></option>
                </select>
            </div>

            <!-- Delivery Fields -->
            <div id="deliveryFields" style="display: none;">
                <div class="form-group">
                    <label><?php echo $lang['delivery_address']; ?>:</label>
                    <textarea name="address" placeholder="<?php echo $lang['enter_address']; ?>" required></textarea>
                </div>
                <div class="form-group">
                    <label><?php echo $lang['contact_number']; ?>:</label>
                    <input type="text" name="contact_info" placeholder="e.g., +1234567890" required>
                </div>
            </div>

            <!-- Dine-in Fields -->
            <div id="dineInFields" style="display: none;">
                <div class="form-group">
                    <label><?php echo $lang['table_number']; ?>:</label>
                    <select name="table_number" required>
                        <option value=""><?php echo $lang['select_table']; ?></option>
                        <?php for ($i = 1; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $lang['table']; ?> <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="button" class="btn call-waiter-btn" onclick="callWaiter()">📞 <?php echo $lang['call_waiter']; ?></button>
            </div>

            <!-- Payment Option -->
            <div class="form-group">
                <label><?php echo $lang['payment_method']; ?>:</label>
                <select name="payment_method" id="paymentMethod" onchange="toggleEditability()">
                    <option value="online"><?php echo $lang['pay_online']; ?></option>
                    <option value="cash"><?php echo $lang['cash']; ?></option>
                </select>
            </div>

            <div class="button-group">
                <button type="submit" class="btn submit-btn" id="submitBtn"><?php echo $lang['place_order']; ?></button>
                <button type="button" class="btn back-btn" onclick="window.location='menu.php'">🔙 <?php echo $lang['back']; ?></button>
            </div>
        </form>

        <!-- Order Status -->
        <div id="orderStatus" style="display: none;">
            <h3><?php echo $lang['order_status']; ?></h3>
            <p id="statusText"></p>
        </div>
    </div>

    <script>
        function toggleOrderFields() {
            const orderType = document.getElementById('orderType').value;
            document.getElementById('deliveryFields').style.display = orderType === 'delivery' ? 'block' : 'none';
            document.getElementById('dineInFields').style.display = orderType === 'dine-in' ? 'block' : 'none';
            document.querySelectorAll('#deliveryFields input, #deliveryFields textarea').forEach(el => el.required = orderType === 'delivery');
            document.querySelectorAll('#dineInFields select').forEach(el => el.required = orderType === 'dine-in');
        }

        function toggleEditability() {
            document.getElementById('submitBtn').textContent = document.getElementById('paymentMethod').value === 'online' ? '<?php echo $lang['pay_finalize']; ?>' : '<?php echo $lang['place_order']; ?>';
        }

        function callWaiter() {
            alert('<?php echo $lang['waiter_called']; ?>');
        }

        document.getElementById('orderForm').onsubmit = function(e) {
            e.preventDefault();
            const orderType = document.getElementById('orderType').value;
            document.getElementById('orderStatus').style.display = 'block';
            let statusText = document.getElementById('statusText');

            if (orderType === 'delivery') {
                statusText.textContent = '<?php echo $lang['preparing']; ?>';
                setTimeout(() => statusText.textContent = '<?php echo $lang['on_the_way']; ?>', 2000);
                setTimeout(() => statusText.textContent = '<?php echo $lang['delivered']; ?>', 4000);
            } else {
                statusText.textContent = '<?php echo $lang['preparing']; ?>';
                setTimeout(() => statusText.textContent = '<?php echo $lang['served']; ?>', 2000);
            }
            this.submit();
        };
    </script>
</body>
</html>