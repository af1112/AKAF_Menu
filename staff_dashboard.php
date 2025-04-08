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
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'staff') {
    header("Location: user_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
</head>
<body>

    <h1>??ž?? Staff Dashboard</h1>
    <p>Welcome, <?php echo $_SESSION['user']; ?>!</p>
    <p>You can only view the menu.</p>

    <a href="index.php">?? View Menu</a>
    <a href="logout.php">?? Logout</a>

</body>
</html>
