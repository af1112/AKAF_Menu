<!DOCTYPE html>
<html lang="en">
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
    </style>
</head>
<body>

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
