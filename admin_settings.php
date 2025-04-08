<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

// Load language
include "languages/" . $_SESSION['lang'] . ".php";

// Update currency
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_currency'])) {
    $new_currency = $_POST['currency'];
    $stmt = $conn->prepare("INSERT INTO settings (`key`, value) VALUES ('currency', ?) ON DUPLICATE KEY UPDATE value = ?");
    $stmt->bind_param("ss", $new_currency, $new_currency);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_settings.php");
    exit();
}

// Fetch current currency
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'currency'");
$stmt->execute();
$current_currency = $stmt->get_result()->fetch_assoc()['value'] ?? 'OMR';
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['admin_settings'] ?? 'Admin Settings'; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
    <header>
        <h1><?php echo $lang['admin_settings'] ?? 'Admin Settings'; ?></h1>
        <a href="logout.php"><?php echo $lang['logout'] ?? 'Logout'; ?></a>
    </header>
    <main>
        <form method="POST">
            <label><?php echo $lang['currency'] ?? 'Currency'; ?>:</label>
            <select name="currency">
                <option value="OMR" <?php echo $current_currency == 'OMR' ? 'selected' : ''; ?>>OMR (Omani Rial)</option>
                <option value="USD" <?php echo $current_currency == 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                <option value="EUR" <?php echo $current_currency == 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                <option value="IRR" <?php echo $current_currency == 'IRR' ? 'selected' : ''; ?>>IRR (Iranian Rial)</option>
            </select>
            <button type="submit" name="update_currency"><?php echo $lang['save'] ?? 'Save'; ?></button>
        </form>
    </main>
</body>
</html>