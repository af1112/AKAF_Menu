<?php
session_start();
include 'db.php';

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
include "languages/" . $_SESSION['lang'] . ".php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // ذخیره اطلاعات کاربر به‌عنوان آرایه
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];

        // Debugging output (اختیاری، می‌تونی حذفش کنی)
        echo "? Login successful! User: " . $_SESSION['user']['username'] . " | Role: " . $_SESSION['user']['role'];

        header("Location: menu.php");
        exit();
    } else {
        $error = $lang['invalid_login'] ?? "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['login']; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>👤 <?php echo $lang['login']; ?></h1>

    <div class="form-container">
        <form action="" method="POST">
            <div class="form-group">
                <label><?php echo $lang['username']; ?>:</label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label><?php echo $lang['password']; ?>:</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="submit-btn"><?php echo $lang['login']; ?></button>

            <?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
        </form>
    </div>
</body>
</html>