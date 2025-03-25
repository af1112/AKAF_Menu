<?php
include 'db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKAF Restaurant Digital Menu</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <header>
        <h1>Welcome to Gourmet Delight 🍽️</h1>
        <p>Manage your menu items</p>
    </header>

    <div class="container">
        <h2>Our Menu</h2>
        <a href="add_food_form.php" class="add-btn">➕ Add New Food</a>
        <div class="menu-grid">
            <?php
            $result = $conn->query("SELECT * FROM foods");

            while ($row = $result->fetch_assoc()) {
                echo "
                    <div class='menu-item'>
                        <img src='images/{$row["image"]}' alt='{$row["name"]}'>
                        <h3>{$row["name"]}</h3>
                        <p>{$row["description"]}</p>
                        <p class='price'>\${$row["price"]}</p>
                        <a href='edit_food_form.php?id={$row["id"]}' class='edit-btn'>✏️ Edit</a>
                        <a href='delete_food.php?id={$row["id"]}' class='delete-btn' onclick='return confirm(\"Are you sure you want to delete this item?\")'>🗑️ Delete</a>
                    </div>
                ";
            }
            ?>
        </div>
    </div>

</body>
</html>
