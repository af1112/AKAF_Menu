<?php

session_start();
include 'db.php';

$is_mobile = preg_match('/(android|iphone|ipad|ipod)/i', $_SERVER['HTTP_USER_AGENT']);
// بررسی order_id
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    file_put_contents('debug.txt', "No order_id found in GET at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    header("Location: cart.php");
    exit();
}

$order_id = intval($_GET['order_id']);

// حذف آیتم
if (isset($_GET['remove_item']) && isset($_GET['food_id']) && isset($_GET['order_id'])) {
    $food_id_to_remove = intval($_GET['food_id']);
    $order_id_to_remove = intval($_GET['order_id']);
    $stmt_remove = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND food_id = ?");
    $stmt_remove->bind_param("ii", $order_id_to_remove, $food_id_to_remove);
    $stmt_remove->execute();
    header("Location: checkout.php?order_id=" . $order_id_to_remove);
    exit();
}

// تغییر تعداد آیتم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $food_id = intval($_POST['food_id']);
    $quantity = intval($_POST['quantity']);
    if ($quantity > 0) {
        $stmt_update = $conn->prepare("UPDATE order_items SET quantity = ? WHERE order_id = ? AND food_id = ?");
        $stmt_update->bind_param("iii", $quantity, $order_id, $food_id);
        $stmt_update->execute();
    } else {
        $stmt_remove = $conn->prepare("DELETE FROM order_items WHERE order_id = ? AND food_id = ?");
        $stmt_remove->bind_param("ii", $order_id, $food_id);
        $stmt_remove->execute();
    }
    header("Location: checkout.php?order_id=" . $order_id);
    exit();
}

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

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

if (!$is_logged_in) {
    header("Location: user_login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

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

// دریافت اطلاعات سفارش
$stmt_order = $conn->prepare("SELECT total_price, vat_amount, grand_total, status, waiter_id, estimated_time FROM orders WHERE id = ? AND user_id = ?");
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
$order_status = $order['status'] ?? 'Pending';
$waiter_id = $order['waiter_id'] ?? null;
$estimated_time = $order['estimated_time'] ?? null;

// اعمال کد تخفیف
$discount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_discount'])) {
    $discount_code = $_POST['discount_code'] ?? '';
    if ($discount_code === 'DISCOUNT10') {
        $discount = $grand_total * 0.10;
        $grand_total -= $discount;
        $stmt_update = $conn->prepare("UPDATE orders SET grand_total = ? WHERE id = ?");
        $stmt_update->bind_param("di", $grand_total, $order_id);
        $stmt_update->execute();
        file_put_contents('debug.txt', "Discount applied: " . $discount . " for order_id: " . $order_id . "\n", FILE_APPEND);
    } else {
        $discount_error = $lang['invalid_discount_code'] ?? "Invalid discount code.";
    }
}

// پردازش Place Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $delivery_type = $_POST['delivery_type'] ?? '';
    if ($delivery_type === 'dine-in') {
        $table_number = $_POST['table_number'] ?? '';
        if (empty($table_number)) {
            $error = $lang['table_number_required'] ?? "Table number is required for Dine-In.";
        } else {
            $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed', table_number = ? WHERE id = ?");
            $stmt_update->bind_param("si", $table_number, $order_id);
            $stmt_update->execute();
            file_put_contents('debug.txt', "Order confirmed for Dine-In with table: " . $table_number . "\n", FILE_APPEND);
            header("Location: order_detail.php?order_id=" . $order_id);
            exit();
        }
    } elseif ($delivery_type === 'delivery') {
        $address = $_POST['address'] ?? '';
        $latitude = $_POST['latitude-delivery'] ?? '';
        $longitude = $_POST['longitude-delivery'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';
        $recipient_name = $_POST['recipient_name'] ?? '';
        $preferred_time = $_POST['preferred_time'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';

        if (empty($address) || empty($contact_number) || empty($recipient_name) || empty($payment_method)) {
            $error = $lang['delivery_details_required'] ?? "All delivery and payment details are required.";
        } else {
            $location = "$latitude,$longitude";
            $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed', delivery_address = ?, delivery_location = ?, delivery_contact = ?, delivery_recipient = ?, delivery_preferred_time = ?, payment_method = ? WHERE id = ?");
            $stmt_update->bind_param("ssssssi", $address, $location, $contact_number, $recipient_name, $preferred_time, $payment_method, $order_id);
            $stmt_update->execute();
            file_put_contents('debug.txt', "Order confirmed for Delivery with payment: " . $payment_method . "\n", FILE_APPEND);
            header("Location: order_detail.php?order_id=" . $order_id);
            exit();
        }
    } elseif ($delivery_type === 'takeaway') {
        $contact_number = $_POST['contact_number'] ?? '';
        $recipient_name = $_POST['recipient_name'] ?? '';
        $preferred_time = $_POST['preferred_time'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        if (empty($contact_number)) {
            $error = $lang['contact_number_required'] ?? "A contact number is required to call you in case the food is ready.";
        } else {
            $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed', delivery_contact = ?, delivery_recipient = ?, delivery_preferred_time = ?, payment_method = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $contact_number, $recipient_name, $preferred_time, $payment_method, $order_id);
            $stmt_update->execute();
            file_put_contents('debug.txt', "Order confirmed for Takeaway with payment: " . $payment_method . "\n", FILE_APPEND);
            header("Location: order_detail.php?order_id=" . $order_id);
            exit();
        }
    } elseif ($delivery_type === 'drive-thru') {
        $car_brand_name = $_POST['car_brand_name'] ?? '';
        $car_color = $_POST['car_color'] ?? '';
        $car_tag_number = $_POST['car_tag_number'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        if (empty($car_brand_name) || empty($car_color) || empty($car_tag_number)) {
            $error = $lang['car_details_required'] ?? "Car Details are required to deliver your order.";
        } else {
            $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed', car_brand_name = ?, car_color = ?, car_tag_number = ?, payment_method = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $car_brand_name, $car_color, $car_tag_number, $payment_method, $order_id);
            $stmt_update->execute();
            file_put_contents('debug.txt', "Order confirmed for Drive-Thru with payment: " . $payment_method . "\n", FILE_APPEND);
            header("Location: order_detail.php?order_id=" . $order_id);
            exit();
        }
    } elseif ($delivery_type === 'contactless') {
        $address = $_POST['address'] ?? '';
        $latitude = $_POST['latitude-contactless'] ?? '';
        $longitude = $_POST['longitude-contactless'] ?? '';
        $preferred_time = $_POST['preferred_time'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';

        if (empty($address) || empty($payment_method)) {
            $error = $lang['delivery_details_required'] ?? "All delivery and payment details are required.";
        } else {
            $location = "$latitude,$longitude";
            $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed', delivery_address = ?, delivery_location = ?, delivery_preferred_time = ?, payment_method = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $address, $location, $preferred_time, $payment_method, $order_id);
            $stmt_update->execute();
            file_put_contents('debug.txt', "Order confirmed for Contactless with payment: " . $payment_method . "\n", FILE_APPEND);
            header("Location: order_detail.php?order_id=" . $order_id);
            exit();
        }
    } elseif ($delivery_type === 'curbside') {
        $car_brand_name = $_POST['car_brand_name'] ?? '';
        $car_color = $_POST['car_color'] ?? '';
        $car_tag_number = $_POST['car_tag_number'] ?? '';
        $preferred_time = $_POST['preferred_time'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $contact_number = $_POST['contact_number'] ?? '';

        if (empty($car_brand_name) || empty($car_color) || empty($car_tag_number) || empty($contact_number) || empty($payment_method)) {
            $error = $lang['car_details_required'] ?? "Car Details are required to deliver your order.";
        } else {
            $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed', car_brand_name = ?, car_color = ?, car_tag_number = ?, delivery_contact = ?, delivery_preferred_time = ?, payment_method = ? WHERE id = ?");
            $stmt_update->bind_param("ssssssi", $car_brand_name, $car_color, $car_tag_number, $contact_number, $preferred_time, $payment_method, $order_id);
            $stmt_update->execute();
            file_put_contents('debug.txt', "Order confirmed for Curbside with payment: " . $payment_method . "\n", FILE_APPEND);
            header("Location: order_detail.php?order_id=" . $order_id);
            exit();
        }
    } else {
        $stmt_update = $conn->prepare("UPDATE orders SET status = 'Confirmed' WHERE id = ?");
        $stmt_update->bind_param("i", $order_id);
        $stmt_update->execute();
        file_put_contents('debug.txt', "Order confirmed for " . $delivery_type . "\n", FILE_APPEND);
        header("Location: order_detail.php?order_id=" . $order_id);
        exit();
    }
}

// دریافت آیتم‌های سفارش
$stmt_items = $conn->prepare("SELECT oi.id AS order_item_id, oi.food_id, oi.quantity, oi.price, oi.comment, f.name_" . $_SESSION['lang'] . " AS name, f.main_image FROM order_items oi JOIN foods f ON oi.food_id = f.id WHERE oi.order_id = ?");$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$order_items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['checkout'] ?? 'Checkout'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://unpkg.com/swiper@8/swiper-bundle.min.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
/* تعریف فونت - اطمینان از وجود فایل‌ها */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    direction: <?php echo $direction; ?>;
    padding-bottom: 70px; /* برای منوی پایین */
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 10px;
}

.checkout {
    padding: 15px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.cart-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.cart-table th, .cart-table td {
    padding: 12px;
    text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>;
    border-bottom: 1px solid #ddd;
}
.cart-table td {
    height: 110px;
    vertical-align: top;
}
.cart-table th {
    background: #f8f8f8;
    font-weight: bold;
}

.cart-table th.special-request, .cart-table td.special-request {
    width: 250px;
}

.item-name {
    display: flex;
    align-items: center;
    gap: 10px;
	flex-wrap: nowrap; /* جلوگیری از شکستن خط */
}

.item-name img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 5px;
	flex-shrink: 0;
    position: <?php echo $is_rtl ? 'flex-end' : 'flex-start'; ?>;
}
.item-name div {
    display: flex;
    align-items: center;
    gap: 10px;
}
.item-name a {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
}

.item-name a:hover {
    text-decoration: underline;
}

.item-name .btn-primary {
    background: #007bff;
    color: #fff;
    border: none;
    padding: 5px 10px;
    font-size: 14px;
}

.item-name .btn-primary:hover {
    background: #0056b3;
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 5px;
}

.quantity-control input[type="number"] {
    width: 60px;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-align: center;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.quantity-control input[type="number"]::-webkit-inner-spin-button,
.quantity-control input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.quantity-control button {
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 16px;
    color: #333;
}

.quantity-control button:hover {
    background: #e0e0e0;
}

.comment-column input[type="text"],
.comment-column textarea {
    width: 100%;
    min-height: 80px;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 8px;
    resize: vertical;
    font-size: 14px;
}

.total {
    font-size: 18px;
    margin-bottom: 20px;
}

.total .summary-row {
    display: flex;
    width: 100%;
    max-width: 300px;
    margin-bottom: 5px;

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

.total .summary-row .label {
    flex: 1;
    text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>;
    margin-right: 10px;
    margin-left: 10px;
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

.btn {
    padding: 10px 20px;
    border-radius: 5px;
    font-size: 16px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background: #28a745;
    border: none;
    color: #fff;
}

.btn-primary:hover {
    background: #218838;
}

.btn-secondary {
    background: #6c757d;
    border: none;
    color: #fff;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-danger {
    color: #e74c3c;
    text-decoration: none;
    font-size: 16px;
    background: none !important;
    padding: 5px;
}

.btn-danger:hover {
    color: #c0392b;
}

.delivery-carousel {
    width: 100%;
    padding: 10px 0;
    margin-bottom: 20px;
    position: relative;
    z-index: 10;
    overflow: visible;
}

.delivery-carousel .swiper {
    width: 100%;
    display: flex;
    flex-direction: row;
    position: relative;
}

.delivery-carousel .swiper-wrapper {
    display: flex;
    flex-direction: row;
    width: auto;
}

.delivery-carousel .swiper-slide {
    flex: 0 0 120px;
    height: 120px;
    background: #f8f8f8;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    transition: background 0.3s ease;
}

.delivery-carousel .swiper-slide.active {
    background: #2c3e50;
    color: #fff;
}

.delivery-carousel .swiper-slide i {
    font-size: 24px;
    margin-bottom: 5px;
}

.delivery-carousel .swiper-slide span {
    font-size: 14px;
}

.swiper-button-prev, .swiper-button-next {
    color: #fff;
    background: #2c3e50;
    width: 40px;
    height: 40px;
    border-radius: 50%;
	display: felx;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 20;
	align-items: center;
    justify-content: center;
}

.swiper-button-prev {
    left: -10px;
}

.swiper-button-next {
    right: -10px;
}

.swiper-button-prev:after, .swiper-button-next:after {
    font-size: 18px;
}
.swiper-button-prev i, .swiper-button-next i {
    font-size: 18px;
}
.map-container {
    display: none;
    margin-top: 10px;
    z-index: 5;
}

#map-delivery, #map-contactless {
    height: 300px;
    width: 100%;
    margin-bottom: 20px;
}

.extra-fields.show-map .map-container {
    display: block;
}

.menu-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: #ffffff;
    display: none;
    justify-content: space-around;
    align-items: center;
    padding: 8px 0;
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
    margin-bottom: 2px;
}

.menu-bar a.active {
    color: #2c3e50;
    font-weight: bold;
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
    line-height: 16px;
    text-align: center;
    border-radius: 50%;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
}

.cart-table-mobile {
    display: none;
}

@media (max-width: 1000px) {
    .desktop-menu {
        display: none;
    }
    .menu-bar {
        display: flex;
    }
}

@media (max-width: 768px) {
    .cart-table {
        display: none;
    }

    .cart-table-mobile {
        display: block;
    }

    .cart-item-mobile {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .cart-item-mobile .item-name {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 10px;
        display: flex;
        flex-direction: column;
        align-items: <?php echo $is_rtl ? 'flex-end' : 'flex-start'; ?>;
    }

    .cart-item-mobile .item-name a {
        color: #007bff;
        text-decoration: none;
    }

    .cart-item-mobile .item-name a:hover {
        text-decoration: underline;
    }

    .cart-item-mobile .item-name img {
        width: 70px;
        height: 70px;
        margin-bottom: 10px;
        border-radius: 8px;
    }

    .cart-item-mobile .details-btn {
        display: inline-block;
        margin-top: 5px;
        padding: 5px 10px;
        background: #007bff;
        color: white !important;
        text-decoration: none;
        border-radius: 4px;
        font-size: 14px;
    }

    .cart-item-mobile .details-btn:hover {
        background: #0056b3;
    }

    .cart-item-mobile .item-detail {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 14px;
    }

    .cart-item-mobile input[type="text"],
    .cart-item-mobile textarea {
        width: 100%;
        padding: 8px;
        font-size: 14px;
        margin-top: 5px;
    }

    .cart-item-mobile .remove-btn {
        color: #e74c3c;
        font-size: 18px;
        text-align: center;
        display: block;
        margin-top: 10px;
        text-decoration: none;
    }

    .cart-item-mobile .remove-btn:hover {
        color: #c0392b;
    }

    .total {
        font-size: 16px;
        text-align: center;
        align-items: center;
    }

    .total .summary-row {
        justify-content: center;
    }

    .total .grand-total .summary-row {
        font-size: 18px;
    }

    .button-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        margin-top: 20px;
    }

    .btn {
        width: 100%;
        max-width: 300px;
        justify-content: center;
        padding: 12px;
        font-size: 15px;
    }

    .delivery-carousel .swiper-slide {
        flex: 0 0 100px;
        height: 100px;
        border-radius: 8px;
    }

    .delivery-carousel .swiper-slide i {
        font-size: 20px;
    }

    .delivery-carousel .swiper-slide span {
        font-size: 12px;
    }

    .swiper-button-prev, .swiper-button-next {
        width: 35px;
        height: 35px;
    }

    .swiper-button-prev {
        left: -15px;
    }

    .swiper-button-next {
        right: -15px;
    }

    .extra-fields .form-group {
        margin-bottom: 15px;
    }

    .extra-fields label {
        font-size: 14px;
        margin-bottom: 5px;
        display: block;
    }

    .form-control {
        font-size: 14px;
        padding: 8px;
    }
}
@media (max-width: 500px) {
    .navbar {
        display: none;
    }

    .container {
        padding: 3px;
    }

    .checkout {
        padding: 8px;
    }

    .checkout h2 {
        font-size: 1.3rem;
    }

    .cart-item-mobile {
        padding: 10px;
        margin-bottom: 12px;
    }

    .cart-item-mobile .item-name {
        font-size: 14px;
    }

    .cart-item-mobile .item-detail {
        font-size: 13px;
    }

    .cart-item-mobile input[type="text"],
    .cart-item-mobile textarea {
        font-size: 13px;
    }

    .cart-item-mobile .item-name img {
        width: 60px;
        height: 60px;
    }

    .quantity-control input[type="number"] {
        width: 50px;
        font-size: 13px;
    }

    .quantity-control button {
        width: 25px;
        height: 25px;
        font-size: 14px;
    }

    .total {
        font-size: 15px;
    }

    .total .grand-total .summary-row {
        font-size: 16px;
    }

    .delivery-carousel .swiper-slide {
        flex: 0 0 90px;
        height: 90px;
    }

    .delivery-carousel .swiper-slide i {
        font-size: 18px;
    }

    .delivery-carousel .swiper-slide span {
        font-size: 11px;
    }

    .swiper-button-prev, .swiper-button-next {
        width: 30px;
        height: 30px;
    }

    .swiper-button-prev {
        left: -10px;
    }

    .swiper-button-next {
        right: -10px;
    }

    .form-control {
        font-size: 13px;
        padding: 6px;
    }

    .btn {
        font-size: 14px;
        padding: 10px;
    }

    .menu-bar a i {
        font-size: 20px;
    }

    .menu-bar a {
        font-size: 9px;
    }

    .cart-badge {
        width: 14px;
        height: 14px;
        line-height: 14px;
        font-size: 9px;
        right: 10px;
    }
}
@media only screen and (max-width: 430px) {
    .button { width: 100%; }
    .form-field { font-size: 16px; }
}

    </style>
</head>

<body class="<?php echo $theme; ?>">
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
                <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="checkout.php?lang=en&order_id=<?php echo $order_id; ?>"><img src="images/flags/en.png" alt="English" class="flag-icon"> EN</a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="checkout.php?lang=fa&order_id=<?php echo $order_id; ?>"><img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA</a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="checkout.php?lang=ar&order_id=<?php echo $order_id; ?>"><img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR</a>
                <a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="checkout.php?lang=fr&order_id=<?php echo $order_id; ?>"><img src="images/flags/fr.png" alt="French" class="flag-icon"> FR</a>
            </div>
        </div>
    </div>

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
                                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?></a>
                            <?php else: ?>
                                <a class="nav-link" href="user_login.php"><i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
					<li class="nav-item">
						<a class="nav-link" href="index.php">
							<i class="fas fa-bars"></i> <?php echo $lang['home'] ?? 'Home'; ?>
						</a>
					</li>
                    <li class="nav-item">
                        <a class="nav-link" href="checkout.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>&order_id=<?php echo $order_id; ?>">
                            <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                            <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php"><i class="fas fa-shopping-cart"></i> <?php echo $lang['cart'] ?? 'Cart'; ?></a>
                    </li>
                    <?php if ($is_logged_in && !$is_rtl): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if ($is_rtl): ?>
                        <?php if ($is_logged_in): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="user_dashboard.php"><i class="fas fa-user"></i> <?php echo $lang['profile'] ?? 'Profile'; ?></a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <?php if ($is_logged_in): ?>
                                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?></a>
                            <?php else: ?>
                                <a class="nav-link" href="user_login.php"><i class="fas fa-sign-in-alt"></i> <?php echo $lang['login'] ?? 'Login'; ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

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
            <span class="cart-badge" id="cart-count"><?php echo count($order_items); ?></span>
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

    <div class="container">
		<div class="checkout" data-aos="fade-up">
			<h2><?php echo $lang['order_details'] ?? 'Order Details'; ?></h2>
			<?php if (empty($order_items)): ?>
				<p><?php echo $lang['cart_empty'] ?? 'Your cart is empty.'; ?></p>
				<a href="menu.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <?php echo $lang['continue_shopping'] ?? 'Continue Shopping'; ?></a>
			<?php else: ?>
				<?php if (isset($error)): ?>
					<div class="alert alert-danger"><?php echo $error; ?></div>
				<?php endif; ?>
				<?php if (isset($discount_error)): ?>
					<div class="alert alert-warning"><?php echo $discount_error; ?></div>
				<?php endif; ?>

				<section class="cart-summary">
					<form method="POST" action="checkout.php?order_id=<?php echo $order_id; ?>">
						<?php if (!$is_mobile): ?>
							<!-- نمایش جدول برای دسکتاپ -->
							<table class="cart-table">
								<thead>
									<tr>
										<th><?php echo $lang['item'] ?? 'Item'; ?></th>
										<th><?php echo $lang['price'] ?? 'Price'; ?></th>
										<th><?php echo $lang['quantity'] ?? 'Quantity'; ?></th>
										<th><?php echo $lang['subtotal'] ?? 'Subtotal'; ?></th>
										<th class="special-request"><?php echo $lang['Special_Request'] ?? 'Special Request'; ?></th>
										<th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($order_items as $item): ?>
										<tr>
											<td class="item-name">
												<?php if (!empty($item['main_image'])): ?>
													<img src="<?php echo htmlspecialchars($item['main_image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
												<?php endif; ?>
												<div>
													<a href="food_detail.php?id=<?php echo $item['food_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a>
													<a href="food_detail.php?id=<?php echo $item['food_id']; ?>" class="btn btn-primary btn-sm mt-1"><?php echo $lang['details'] ?? 'Details'; ?></a>
												</div>
											</td>
											<td><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></td>
											<td>
												<?php if ($order_status !== 'Preparing'): ?>
													<div class="quantity-control">
														<button type="submit" name="update_quantity" onclick="this.form.quantity.value--;"><i class="fas fa-minus"></i></button>
														<input type="number" name="quantity" id="quantity-<?php echo $item['order_item_id']; ?>" value="<?php echo $item['quantity']; ?>" min="0" readonly>
														<input type="hidden" name="food_id" value="<?php echo $item['food_id']; ?>">
														<button type="submit" name="update_quantity" onclick="this.form.quantity.value++;"><i class="fas fa-plus"></i></button>
													</div>
												<?php else: ?>
													<?php echo $item['quantity']; ?>
												<?php endif; ?>
											</td>
											<td><?php echo number_format($item['price'] * $item['quantity'], $currency_Decimal); ?> <?php echo $currency; ?></td>
											<td class="comment-column special-request">
												<textarea name="comments[<?php echo $item['food_id']; ?>]" <?php echo $order_status === 'Preparing' ? 'disabled' : ''; ?>><?php echo htmlspecialchars($item['comment'] ?? ''); ?></textarea>
											</td>
											<td>
												<?php if ($order_status !== 'Preparing'): ?>
													<a href="checkout.php?remove_item=1&food_id=<?php echo $item['food_id']; ?>&order_id=<?php echo $order_id; ?>" class="btn btn-danger"><i class="fas fa-trash"></i></a>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php else: ?>
							<!-- نمایش کارت‌ها برای موبایل -->
							<div class="cart-table-mobile">
								<?php foreach ($order_items as $item): ?>
									<div class="cart-item-mobile">
										<div class="item-name">
											<?php if (!empty($item['main_image'])): ?>
												<img src="<?php echo htmlspecialchars($item['main_image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
											<?php endif; ?>
											<div>
												<a href="food_detail.php?id=<?php echo $item['food_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a>
												<a href="food_detail.php?id=<?php echo $item['food_id']; ?>" class="details-btn"><?php echo $lang['details'] ?? 'Details'; ?></a>
											</div>
										</div>
										<div class="item-detail">
											<span><?php echo $lang['price'] ?? 'Price'; ?>:</span>
											<span><?php echo number_format($item['price'], $currency_Decimal); ?> <?php echo $currency; ?></span>
										</div>
										<div class="item-detail">
											<span><?php echo $lang['quantity'] ?? 'Quantity'; ?>:</span>
											<?php if ($order_status !== 'Preparing'): ?>
												<div class="quantity-control">
													<button type="submit" name="update_quantity" onclick="this.form.querySelector('#quantity-<?php echo $item['order_item_id']; ?>').value--;"><i class="fas fa-minus"></i></button>
													<input type="number" name="quantity" id="quantity-<?php echo $item['order_item_id']; ?>" value="<?php echo $item['quantity']; ?>" min="0" readonly>
													<input type="hidden" name="food_id" value="<?php echo $item['food_id']; ?>">
													<button type="submit" name="update_quantity" onclick="this.form.querySelector('#quantity-<?php echo $item['order_item_id']; ?>').value++;"><i class="fas fa-plus"></i></button>
												</div>
											<?php else: ?>
												<span><?php echo $item['quantity']; ?></span>
											<?php endif; ?>
										</div>
										<div class="item-detail">
											<span><?php echo $lang['subtotal'] ?? 'Subtotal'; ?>:</span>
											<span><?php echo number_format($item['price'] * $item['quantity'], $currency_Decimal); ?> <?php echo $currency; ?></span>
										</div>
										<div class="item-detail">
											<span><?php echo $lang['Special_Request'] ?? 'Special Request'; ?>:</span>
										</div>
										<textarea name="comments[<?php echo $item['food_id']; ?>]" <?php echo $order_status === 'Preparing' ? 'disabled' : ''; ?>><?php echo htmlspecialchars($item['comment'] ?? ''); ?></textarea>
										<?php if ($order_status !== 'Preparing'): ?>
											<a href="checkout.php?remove_item=1&food_id=<?php echo $item['food_id']; ?>&order_id=<?php echo $order_id; ?>" class="remove-btn">
												<i class="fas fa-trash"></i> <?php echo $lang['remove'] ?? 'Remove'; ?>
											</a>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

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

						<form method="POST" class="mt-3">
							<label for ="discount-code"><?php echo $lang['discount_code'] ?? 'Discount Code'; ?>:</label>
							<div class="input-group">
								<input type="text" id="discount-code" name="discount_code" class="form-control" placeholder="<?php echo $lang['enter_code'] ?? 'Enter code'; ?>">
								<button type="submit" name="apply_discount" class="btn btn-secondary"><?php echo $lang['apply'] ?? 'Apply'; ?></button>
							</div>
						</form>

						<?php if ($order_status === 'Pending'): ?>
							<form method="POST" class="mt-3">
								<h3><?php echo $lang['delivery_options'] ?? 'Delivery Options'; ?></h3>
								<div class="delivery-carousel swiper" data-aos="fade-up" data-aos-delay="100" data-aos-duration="800">
									<div class="swiper-wrapper">
										<div class="swiper-slide delivery-option" data-value="dine-in">
											<i class="fas fa-utensils"></i>
											<span><?php echo $lang['dine_in'] ?? 'Dine-In'; ?></span>
										</div>
										<div class="swiper-slide delivery-option" data-value="delivery">
											<i class="fas fa-truck"></i>
											<span><?php echo $lang['delivery'] ?? 'Delivery'; ?></span>
										</div>
										<div class="swiper-slide delivery-option" data-value="takeaway">
											<i class="fas fa-shopping-bag"></i>
											<span><?php echo $lang['takeaway'] ?? 'Takeaway / Pick-up'; ?></span>
										</div>
										<div class="swiper-slide delivery-option" data-value="drive-thru">
											<i class="fas fa-car"></i>
											<span><?php echo $lang['drive_thru'] ?? 'Drive-Thru'; ?></span>
										</div>
										<div class="swiper-slide delivery-option" data-value="contactless">
											<i class="fas fa-box"></i>
											<span><?php echo $lang['contactless_delivery'] ?? 'Contactless Delivery'; ?></span>
										</div>
										<div class="swiper-slide delivery-option" data-value="curbside">
											<i class="fas fa-parking"></i>
											<span><?php echo $lang['curbside_pickup'] ?? 'Curbside Pickup'; ?></span>
										</div>
									</div>
									<div class="swiper-button-prev"><i class="fas fa-arrow-left"></i></div>
									<div class="swiper-button-next"><i class="fas fa-arrow-right"></i></div>
								</div>
								<input type="hidden" name="delivery_type" id="delivery_type" required>

								<div id="dine-in-fields" class="extra-fields">
									<label for="table-number"><?php echo $lang['table_number'] ?? 'Table Number'; ?>:</label>
									<input type="text" id="table-number" name="table_number" class="form-control" required>
								</div>
								<div id="delivery-fields" class="extra-fields">
									<div class="form-group">
										<label><?php echo $lang['address'] ?? 'Address'; ?>:</label>
										<input type="text" name="address" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['location'] ?? 'Location'; ?>:</label>
										<div id="map-delivery" class="map-container"></div>
										<input type="hidden" name="latitude" id="latitude-delivery">
										<input type="hidden" name="longitude" id="longitude-delivery">
									</div>
									<div class="form-group">
										<label><?php echo $lang['contact_number'] ?? 'Contact Number'; ?>:</label>
										<input type="tel" name="contact_number" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['recipient_name'] ?? 'Recipient Name'; ?>:</label>
										<input type="text" name="recipient_name" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['preferred_time'] ?? 'Preferred Delivery Time'; ?>:</label>
										<input type="datetime-local" name="preferred_time" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['payment_method'] ?? 'Payment Method'; ?>:</label>
										<select name="payment_method" class="form-control" required>
											<option value="cash"><?php echo $lang['cash'] ?? 'Cash'; ?></option>
											<option value="card"><?php echo $lang['card'] ?? 'Card'; ?></option>
											<option value="bank_transfer"><?php echo $lang['bank_transfer'] ?? 'Bank Transfer'; ?></option>
										</select>
									</div>
								</div>
								<div id="takeaway-fields" class="extra-fields">
									<div class="form-group">
										<label><?php echo $lang['contact_number'] ?? 'Contact Number'; ?>:</label>
										<input type="tel" name="contact_number" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['recipient_name'] ?? 'Recipient Name'; ?>:</label>
										<input type="text" name="recipient_name" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['preferred_time'] ?? 'Preferred Delivery Time'; ?>:</label>
										<input type="datetime-local" name="preferred_time" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['payment_method'] ?? 'Payment Method'; ?>:</label>
										<select name="payment_method" class="form-control" required>
											<option value="cash"><?php echo $lang['cash'] ?? 'Cash'; ?></option>
											<option value="card"><?php echo $lang['card'] ?? 'Card'; ?></option>
											<option value="bank_transfer"><?php echo $lang['bank_transfer'] ?? 'Bank Transfer'; ?></option>
										</select>
									</div>
								</div>
								<div id="drive-thru-fields" class="extra-fields">
									<label><?php echo $lang['car_brand_name'] ?? 'Car Brand Name'; ?>:</label>
									<input type="text" name="car_brand_name" class="form-control" required>
									<label><?php echo $lang['car_color'] ?? 'Car Color'; ?>:</label>
									<input type="text" name="car_color" class="form-control" required>
									<label><?php echo $lang['car_tag_number'] ?? 'Car Tag Number'; ?>:</label>
									<input type="text" name="car_tag_number" class="form-control" required>
								</div>
								<div id="contactless-fields" class="extra-fields">
									<div class="form-group">
										<label><?php echo $lang['address'] ?? 'Address'; ?>:</label>
										<input type="text" name="address" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['location'] ?? 'Location'; ?>:</label>
										<div id="map-contactless" class="map-container"></div>
										<input type="hidden" name="latitude" id="latitude-contactless">
										<input type="hidden" name="longitude" id="longitude-contactless">
									</div>
									<div class="form-group">
										<label><?php echo $lang['preferred_time'] ?? 'Preferred Delivery Time'; ?>:</label>
										<input type="datetime-local" name="preferred_time" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['payment_method'] ?? 'Payment Method'; ?>:</label>
										<select name="payment_method" class="form-control" required>
											<option value="cash"><?php echo $lang['cash'] ?? 'Cash'; ?></option>
											<option value="card"><?php echo $lang['card'] ?? 'Card'; ?></option>
											<option value="bank_transfer"><?php echo $lang['bank_transfer'] ?? 'Bank Transfer'; ?></option>
										</select>
									</div>
								</div>
								<div id="curbside-fields" class="extra-fields">
									<label><?php echo $lang['car_brand_name'] ?? 'Car Brand Name'; ?>:</label>
									<input type="text" name="car_brand_name" class="form-control" required>
									<label><?php echo $lang['car_color'] ?? 'Car Color'; ?>:</label>
									<input type="text" name="car_color" class="form-control" required>
									<label><?php echo $lang['car_tag_number'] ?? 'Car Tag Number'; ?>:</label>
									<input type="text" name="car_tag_number" class="form-control" required>
									<div class="form-group">
										<label><?php echo $lang['contact_number'] ?? 'Contact Number'; ?>:</label>
										<input type="tel" name="contact_number" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['preferred_time'] ?? 'Preferred Delivery Time'; ?>:</label>
										<input type="datetime-local" name="preferred_time" class="form-control" required>
									</div>
									<div class="form-group">
										<label><?php echo $lang['payment_method'] ?? 'Payment Method'; ?>:</label>
										<select name="payment_method" class="form-control" required>
											<option value="cash"><?php echo $lang['cash'] ?? 'Cash'; ?></option>
											<option value="card"><?php echo $lang['card'] ?? 'Card'; ?></option>
											<option value="bank_transfer"><?php echo $lang['bank_transfer'] ?? 'Bank Transfer'; ?></option>
										</select>
									</div>
								</div>
								<div class="button-container">
									<button type="submit" name="place_order" class="btn btn-primary">
										<i class="fas fa-check"></i> <?php echo $lang['place_order'] ?? 'Place Order'; ?>
									</button>
								</div>
							</form>
						<?php endif; ?>

						<?php if ($estimated_time): ?>
							<div class="order-status mt-3">
								<?php echo $lang['estimated_time'] ?? 'Estimated Delivery Time'; ?>: <?php echo $estimated_time; ?> <?php echo $lang['minutes'] ?? 'minutes'; ?>
							</div>
						<?php endif; ?>
					</form>
				</section>
			<?php endif; ?>
		</div>
    </div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init();

    document.addEventListener('DOMContentLoaded', function() {
        const dineInFields = document.getElementById('dine-in-fields');
        const deliveryFields = document.getElementById('delivery-fields');
        const takeawayFields = document.getElementById('takeaway-fields');
        const drivethruFields = document.getElementById('drive-thru-fields');
        const contactlessFields = document.getElementById('contactless-fields');
        const curbsideFields = document.getElementById('curbside-fields');
        const deliveryTypeInput = document.getElementById('delivery_type');

        let map = null;
        let isMapInitializing = false;

        if (!deliveryTypeInput || !dineInFields || !deliveryFields || !takeawayFields || !drivethruFields || !contactlessFields || !curbsideFields) {
            console.error('One or more elements not found!');
            return;
        }

        const swiper = new Swiper('.delivery-carousel', {
            slidesPerView: 'auto',
            spaceBetween: 10,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            direction: 'horizontal',
            freeMode: false,
            grabCursor: true,
            centeredSlides: false,
            loop: false,
            slidesOffsetBefore: 0,
            slidesOffsetAfter: 0,
            breakpoints: {
                320: {
                    slidesPerView: 2.5,
                    spaceBetween: 8
                },
                768: {
                    slidesPerView: 4,
                    spaceBetween: 10
                },
                1024: {
                    slidesPerView: 6,
                    spaceBetween: 10
                }
            }
        });

        const deliveryOptions = document.querySelectorAll('.delivery-option');
        deliveryOptions.forEach(option => {
            option.addEventListener('click', function() {
                deliveryOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                const value = this.getAttribute('data-value');
                deliveryTypeInput.value = value;
                updateFields(value);
                const slideIndex = Array.from(deliveryOptions).indexOf(this);
                swiper.slideTo(slideIndex, 300);
            });
        });

        function updateFields(value) {
            dineInFields.style.display = 'none';
            deliveryFields.style.display = 'none';
            takeawayFields.style.display = 'none';
            drivethruFields.style.display = 'none';
            contactlessFields.style.display = 'none';
            curbsideFields.style.display = 'none';

            const allInputs = dineInFields.querySelectorAll('input, select');
            const allDeliveryInputs = deliveryFields.querySelectorAll('input, select');
            const allTakeawayInputs = takeawayFields.querySelectorAll('input, select');
            const allDrivethruInputs = drivethruFields.querySelectorAll('input, select');
            const allContactlessInputs = contactlessFields.querySelectorAll('input, select');
            const allCurbsideInputs = curbsideFields.querySelectorAll('input, select');

            allInputs.forEach(input => input.disabled = true);
            allDeliveryInputs.forEach(input => input.disabled = true);
            allTakeawayInputs.forEach(input => input.disabled = true);
            allDrivethruInputs.forEach(input => input.disabled = true);
            allContactlessInputs.forEach(input => input.disabled = true);
            allCurbsideInputs.forEach(input => input.disabled = true);

            if (value === 'dine-in') {
                dineInFields.style.display = 'block';
                allInputs.forEach(input => input.disabled = false);
                clearMap();
            } else if (value === 'delivery') {
                deliveryFields.style.display = 'block';
                allDeliveryInputs.forEach(input => input.disabled = false);
                if (!map && !isMapInitializing) {
                    setTimeout(() => {
                        isMapInitializing = true;
                        map = initMap('map-delivery');
                        isMapInitializing = false;
                    }, 100);
                }
            } else if (value === 'takeaway') {
                takeawayFields.style.display = 'block';
                allTakeawayInputs.forEach(input => input.disabled = false);
                clearMap();
            } else if (value === 'drive-thru') {
                drivethruFields.style.display = 'block';
                allDrivethruInputs.forEach(input => input.disabled = false);
                clearMap();
            } else if (value === 'contactless') {
                contactlessFields.style.display = 'block';
                allContactlessInputs.forEach(input => input.disabled = false);
                if (!map && !isMapInitializing) {
                    setTimeout(() => {
                        isMapInitializing = true;
                        map = initMap('map-contactless');
                        isMapInitializing = false;
                    }, 100);
                }
            } else if (value === 'curbside') {
                curbsideFields.style.display = 'block';
                allCurbsideInputs.forEach(input => input.disabled = false);
                clearMap();
            }
        }

        function clearMap() {
            if (map && typeof map.remove === 'function') {
                try {
                    map.remove();
                } catch (e) {
                    console.warn('Error removing map:', e);
                }
                map = null;
            }
        }

        function initMap(containerId) {
            const mapContainer = document.getElementById(containerId);
            if (!mapContainer) {
                console.error(`Map container with id ${containerId} not found!`);
                return null;
            }

            mapContainer.style.display = 'block';
            mapContainer.style.height = '300px';
            mapContainer.style.width = '100%';

            if (mapContainer.offsetHeight === 0 || mapContainer.offsetWidth === 0) {
                console.warn(`Map container ${containerId} has zero dimensions, retrying...`);
                setTimeout(() => initMap(containerId), 100);
                return null;
            }

            if (mapContainer._leaflet_id) {
                console.log(`Removing existing map on ${containerId}`);
                try {
                    const leafletMaps = Object.values(L.Map._maps || {});
                    for (let existingMap of leafletMaps) {
                        if (existingMap._container === mapContainer && typeof existingMap.remove === 'function') {
                            existingMap.remove();
                        }
                    }
                    delete mapContainer._leaflet_id;
                } catch (e) {
                    console.warn(`Error while trying to remove existing map on ${containerId}:`, e);
                }
            }

            let newMap = L.map(containerId);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(newMap);

            const newMarker = L.marker([35.6892, 51.3890], { draggable: true }).addTo(newMap);
            newMarker.on('dragend', function(event) {
                const latlng = newMarker.getLatLng();
                if (containerId === 'map-delivery') {
                    const latInput = document.getElementById('latitude-delivery');
                    const lngInput = document.getElementById('longitude-delivery');
                    if (latInput && lngInput) {
                        latInput.value = latlng.lat;
                        lngInput.value = latlng.lng;
                    }
                } else if (containerId === 'map-contactless') {
                    const latInput = document.getElementById('latitude-contactless');
                    const lngInput = document.getElementById('longitude-contactless');
                    if (latInput && lngInput) {
                        latInput.value = latlng.lat;
                        lngInput.value = latlng.lng;
                    }
                }
            });

            newMap.on('click', function(event) {
                newMarker.setLatLng(event.latlng);
                if (containerId === 'map-delivery') {
                    const latInput = document.getElementById('latitude-delivery');
                    const lngInput = document.getElementById('longitude-delivery');
                    if (latInput && lngInput) {
                        latInput.value = event.latlng.lat;
                        lngInput.value = event.latlng.lng;
                    }
                } else if (containerId === 'map-contactless') {
                    const latInput = document.getElementById('latitude-contactless');
                    const lngInput = document.getElementById('longitude-contactless');
                    if (latInput && lngInput) {
                        latInput.value = event.latlng.lat;
                        lngInput.value = event.latlng.lng;
                    }
                }
            });

            setTimeout(() => {
                try {
                    newMap.setView([35.6892, 51.3890], 12);
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                const userLocation = [position.coords.latitude, position.coords.longitude];
                                newMap.setView(userLocation, 15);
                                newMarker.setLatLng(userLocation);
                            },
                            (error) => {
                                console.log("Geolocation error:", error);
                            }
                        );
                    }
                } catch (e) {
                    console.error(`Error setting map view for ${containerId}:`, e);
                }
            }, 100);

            return newMap;
        }

        const initialOption = document.querySelector('.delivery-option[data-value="dine-in"]');
        if (initialOption) {
            initialOption.classList.add('active');
            deliveryTypeInput.value = 'dine-in';
            updateFields('dine-in');
        }

        // مدیریت دکمه‌های افزایش و کاهش تعداد در موبایل
        document.querySelectorAll('.quantity-control .increase').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.inputId);
                let value = parseInt(input.value) || 0;
                input.value = value + 1;
            });
        });

        document.querySelectorAll('.quantity-control .decrease').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.inputId);
                let value = parseInt(input.value) || 0;
                if (value > 0) {
                    input.value = value - 1;
                }
            });
        });
    });
</script>
</body>
</html>