<?php
session_start();
session_start();
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
include "languages/" . $_SESSION['lang'] . ".php";
if (!isset($_SESSION['user']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header("Location: user_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard</title>
</head>
<body>

    <h1>?? Manager Dashboard</h1>
    <p>Welcome, <?php echo $_SESSION['user']; ?>!</p>
    <p>You have access to manage food items.</p>

    <a href="add_food_form.php">? Add Food</a>
    <a href="logout.php">?? Logout</a>

</body>
</html>
