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
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: user_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <style>
        body { font-family: 'Poppins', sans-serif; text-align: center; background: #f8f8f8; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .add-btn { background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
        .delete-btn { background: #e74c3c; color: white; padding: 5px 10px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>

    <h1>?? Admin Panel</h1>
    <a href="add_food_form.php" class="add-btn">? Add Food</a>
    <a href="add_user_form.php" class="add-btn">? Add User</a>
    <a href="logout.php" class="add-btn" style="background:#c0392b;">?? Logout</a>

    <div class="container">
        <h2>?? User Management</h2>
        <table>
            <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>

            <?php
            $result = $conn->query("SELECT * FROM users");
            while ($row = $result->fetch_assoc()) {
                echo "
                    <tr>
                        <td>{$row["username"]}</td>
                        <td>{$row["role"]}</td>
                        <td>
                            <a href='delete_user.php?id={$row["id"]}' class='delete-btn' onclick='return confirm(\"Are you sure?\")'>??? Delete</a>
                        </td>
                    </tr>
                ";
            }
            ?>
        </table>
    </div>

</body>
</html>
