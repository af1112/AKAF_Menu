<?php
session_start();
include 'db.php';

// Set default language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

// Change language if selected
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Load translations
include "languages/" . $_SESSION['lang'] . ".php";

// Define language column names dynamically
$lang_name_col = "name_" . $_SESSION['lang'];
$lang_desc_col = "description_" . $_SESSION['lang'];

// Check if user is logged in and role is staff, admin, or manager
$showAdminControls = isset($_SESSION['user']) && in_array($_SESSION['role'], ['admin', 'manager', 'staff']);
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['menu']; ?></title>
    <link rel="stylesheet" href="styles.css">
        <style>
        body { font-family: 'Poppins', sans-serif; text-align: center; background: #f8f8f8; padding: 20px; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; padding: 20px; max-width: 1200px; margin: auto; }
        .menu-item { background: white; padding: 15px; border-radius: 10px; box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1); text-align: center; transition: transform 0.2s ease-in-out; }
        .menu-item:hover { transform: scale(1.05); }
        .menu-item img { width: 100%; height: 200px; object-fit: cover; border-radius: 10px; cursor: pointer; }
        .gallery-images { display: flex; justify-content: center; gap: 10px; margin-top: 10px; }
        .gallery-images img { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; cursor: pointer; }
        .edit-btn, .delete-btn, .order-btn, .login-btn, .logout-btn, .add-btn { display: block; padding: 8px 12px; margin-top: 10px; text-decoration: none; border-radius: 5px; text-align: center; }
        .edit-btn { background: #f1c40f; color: black; }
        .delete-btn { background: #e74c3c; color: white; }
        .order-btn { background: #27ae60; color: white; }
        .login-btn { background: #3498db; color: white; }
        .logout-btn { background: #e74c3c; color: white; }
        .add-btn { background: #2ecc71; color: white; }
        .edit-btn:hover, .delete-btn:hover, .order-btn:hover, .login-btn:hover, .logout-btn:hover, .add-btn:hover { opacity: 0.8; }
        .language-selector { text-align: right; margin: 10px; }
        .language-selector a { margin: 5px; text-decoration: none; padding: 5px; background: #c0392b; color: white; border-radius: 5px; }
        .language-selector a:hover { background: #e74c3c; }
        .nav-bar { background: #c0392b; padding: 10px; display: flex; justify-content: space-between; align-items: center; }
        .nav-bar a { color: white; text-decoration: none; padding: 8px 12px; background: #e74c3c; border-radius: 5px; }
        .nav-bar a:hover { background: #c0392b; }
    </style>
</head>

<body>

    <!-- Language Selector -->
    <div class="language-selector">
        <span><?php echo $lang['select_language']; ?>:</span>
        <a href="?lang=en">🇬🇧 English</a>
        <a href="?lang=fr">🇫🇷 Français</a>
        <a href="?lang=ar">🇸🇦 عربي</a>
    </div>

    <!-- Navbar with Login/Logout -->
    <div class="nav-bar">
        <h2 style="color:white;"><?php echo $lang['welcome']; ?></h2>
        
        <?php if (isset($_SESSION['user'])): ?>
            <span style="color:white;">👤 <?php echo $_SESSION['user']; ?> (<?php echo ucfirst($_SESSION['role']); ?>)</span>
            <a href="logout.php" class="logout-btn"><?php echo $lang['logout']; ?></a>
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <a href="admin_panel.php" class="admin-btn"><?php echo $lang['admin_panel']; ?></a>
            <?php elseif ($_SESSION['role'] == 'manager'): ?>
                <a href="manager_dashboard.php" class="admin-btn"><?php echo $lang['manager_panel']; ?></a>
            <?php endif; ?>
        <?php else: ?>
            <a href="user_login.php" class="login-btn"><?php echo $lang['login']; ?></a>
        <?php endif; ?>
    </div>

    <!-- Add Food Button (Only for Admins, Managers, Staff) -->
    <?php if ($showAdminControls): ?>
        <a href="add_food_form.php" class="add-btn">➕ <?php echo $lang['add_food']; ?></a>
    <?php endif; ?>

    <!-- Menu Display -->
    <h2><?php echo $lang['menu']; ?></h2>
    <div class="menu-grid">
        <?php
        $result = $conn->query("SELECT * FROM foods");

        while ($row = $result->fetch_assoc()) {
            $food_id = $row["id"];

            // Get images for food item
            $images = $conn->query("SELECT image FROM food_images WHERE food_id = $food_id");
            $firstImageRow = $images->fetch_assoc();
            $firstImage = ($firstImageRow) ? $firstImageRow["image"] : "default.jpg";

            echo "
                <div class='menu-item'>
                    <a href='food_details.php?id={$row["id"]}'>
                    <img src='images/$firstImage' id='main-img-$food_id' class='main-image' data-food='$food_id' title='Click to see details'>
					</a>
                    <h3>{$row[$lang_name_col]}</h3>
                    <p>{$row[$lang_desc_col]}</p>
                    <p class='price'>{$lang['price']}: \${$row["price"]}</p>
                    <div class='gallery-images' id='gallery-$food_id'>";
            
            while ($img = $images->fetch_assoc()) {
				echo "<img src='images/{$img["image"]}' class='gallery-thumb' data-food='$food_id' data-image='{$img["image"]}'>";
            }

            echo "</div>";

            // Show Edit/Delete buttons only for Staff, Admins, and Managers
            if ($showAdminControls) {
                echo "
                <a href='edit_food_form.php?id={$row["id"]}' class='edit-btn'>✏️ {$lang['edit']}</a>
                <a href='delete_food.php?id={$row["id"]}' class='delete-btn' onclick='return confirm(\"Are you sure?\")'>🗑️ {$lang['delete']}</a>";
            }

            // Order Now button
            echo "<a href='order_food.php?food_id={$row["id"]}' class='order-btn'>🛒 {$lang['order_now']}</a>
                </div>";
        }
        ?>
    </div>

    <script>
        function changeImage(foodId, imageUrl) {
            document.querySelector(`.main-image[src="images/${imageUrl}"]`).src = 'images/' + imageUrl;
        }
    </script>
<script>
    // Change Main Image on Gallery Click
    document.querySelectorAll(".gallery-thumb").forEach(img => {
        img.addEventListener("click", function() {
            let foodId = this.dataset.food;
            let newImage = this.dataset.image;
            document.getElementById("main-img-" + foodId).src = "images/" + newImage;
        });
    });

    // Hover Effect to Show Zoom Option
    document.querySelectorAll(".main-image").forEach(img => {
        img.addEventListener("mouseover", function() {
            this.style.cursor = "zoom-in";
        });
    });
</script>

</body>
</html>
