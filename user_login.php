<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

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

?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login | AKAF Menu</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        body {
            background: linear-gradient(to right, #141E30, #243B55);
            color: white;
            font-family: 'Arial', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-box {
            background: rgba(0, 0, 0, 0.8);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
            width: 350px;
            text-align: center;
        }
        .login-box h2 {
            margin-bottom: 20px;
            font-weight: bold;
        }
        .login-box .form-control {
            background: transparent;
            border: none;
            border-bottom: 2px solid white;
            color: white;
            text-align: center;
        }
        .login-box .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .login-box .btn {
            background: #17a2b8;
            border: none;
            transition: 0.3s;
        }
        .login-box .btn:hover {
            background: #138496;
        }
		.language-bar {
            position: fixed; /* موقعیت ثابت */
            top: 0;
            left: 0;
            width: 100%; /* عرض کامل */
            background-color: #f8f9fa;
            padding: 5px 0;
            z-index: 2000; /* بالاتر از بقیه المان‌ها */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* اضافه کردن سایه */
        }
		.language-switcher {
            display: flex;
            gap: 10px;
            margin-left: 20px; /* فاصله از سمت چپ */
            direction: ltr; /* همیشه از چپ به راست */
        }

        .language-switcher .lang-link {
            font-size: 14px;
            padding: 3px 8px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            color: #212529;
            text-decoration: none;
        }
        .flag-icon {
            width: 16px;
            height: 16px;
            border-radius: 50%;
        }
		.language-switcher .lang-link.active {
            background-color: #ff5733;
            color: white !important;
        }
    </style>
</head>
<body>
    <div class="language-bar">
        <div class="container-fluid">
            <div class="language-switcher <?php echo $is_rtl ? 'text-start' : 'text-end'; ?>">
               <a class="lang-link <?php echo $_SESSION['lang'] == 'en' ? 'active' : ''; ?>" href="user_login.php?lang=en">
					<img src="images/flags/en.png" alt="English" class="flag-icon"> EN
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fa' ? 'active' : ''; ?>" href="user_login.php?lang=fa">
					<img src="images/flags/fa.png" alt="Persian" class="flag-icon"> FA
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'ar' ? 'active' : ''; ?>" href="user_login.php?lang=ar">
					<img src="images/flags/ar.png" alt="Arabic" class="flag-icon"> AR
				</a>
				<a class="lang-link <?php echo $_SESSION['lang'] == 'fr' ? 'active' : ''; ?>" href="user_login.php?lang=fr">
					<img src="images/flags/fr.png" alt="French" class="flag-icon"> FR
				</a>
            </div>
        </div>
    </div>

    <div class="login-box">
        <h2><i class="fas fa-user-circle"></i> User Login</h2>
        <form action="process_login.php" method="POST">
            <div class="mb-3">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
        <p class="mt-3">Don't have an account? <a href="register.php" class="text-info">Sign Up</a></p>
    </div>

</body>
</html>
