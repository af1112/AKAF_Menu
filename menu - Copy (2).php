<?php
session_start();
include 'db.php';

// Restrict access to logged-in users
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

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
$showAdminControls = in_array($_SESSION['user']['role'], ['admin', 'manager', 'staff']);

// Add to cart
if (isset($_GET['add_to_cart'])) {
    $food_id = $_GET['add_to_cart'];
    $quantity = $_GET['quantity'] ?? 1;
    $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND food_id = ?");
    $stmt->bind_param("ii", $user_id, $food_id);
    $stmt->execute();
    $check = $stmt->get_result();
    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND food_id = ?");
        $stmt->bind_param("iii", $quantity, $user_id, $food_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, food_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $user_id, $food_id, $quantity);
    }
    $stmt->execute();
    header("Location: menu.php");
    exit();
}

// Remove from cart
if (isset($_GET['remove_from_cart'])) {
    $food_id = $_GET['remove_from_cart'];
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND food_id = ?");
    $stmt->bind_param("ii", $user_id, $food_id);
    $stmt->execute();
    header("Location: menu.php");
    exit();
}

// Fetch cart items
$stmt = $conn->prepare("SELECT c.food_id, c.quantity, f.$lang_name_col, f.price FROM cart c JOIN foods f ON c.food_id = f.id WHERE c.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['menu']; ?></title>
    <link rel="stylesheet" href="styles.css">
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
        <span style="color:white;">👤 <?php echo $_SESSION['user']['username']; ?> (<?php echo ucfirst($_SESSION['user']['role']); ?>)</span>
        <a href="order_history.php" class="order-history-btn"><?php echo $lang['order_history']; ?></a>
        <a href="logout.php" class="logout-btn"><?php echo $lang['logout']; ?></a>
        <?php if ($_SESSION['user']['role'] == 'admin'): ?>
            <a href="admin_panel.php" class="admin-btn"><?php echo $lang['admin_panel']; ?></a>
        <?php elseif ($_SESSION['user']['role'] == 'manager'): ?>
            <a href="manager_dashboard.php" class="admin-btn"><?php echo $lang['manager_panel']; ?></a>
        <?php endif; ?>
    </div>

    <!-- Add Food Button (Only for Admins, Managers, Staff) -->
    <?php if ($showAdminControls): ?>
        <a href="add_food_form.php" class="add-btn">➕ <?php echo $lang['add_food']; ?></a>
    <?php endif; ?>

    <!-- Cart Display -->
    <div class="cart-container">
        <h3>🛒 <?php echo $lang['cart']; ?></h3>
        <?php if ($cart_result->num_rows == 0): ?>
            <p><?php echo $lang['cart_empty']; ?></p>
        <?php else: ?>
            <ul class="cart-items">
                <?php
                $total = 0;
                while ($cart = $cart_result->fetch_assoc()) {
                    $subtotal = $cart['price'] * $cart['quantity'];
                    $total += $subtotal;
                    echo "
                        <li>
                            {$cart[$lang_name_col]} (x{$cart['quantity']}) - \$$subtotal
                            <a href='menu.php?remove_from_cart={$cart['food_id']}' class='delete-btn'>🗑️ {$lang['remove']}</a>
                        </li>";
                }
                ?>
            </ul>
            <p><strong><?php echo $lang['total']; ?>: $<?php echo number_format($total, 2); ?></strong></p>
            <a href="order_food.php" class="btn submit-btn"><?php echo $lang['proceed_to_order']; ?></a>
        <?php endif; ?>
    </div>

    <!-- Suggested Foods -->
    <h2><?php echo $lang['suggested_for_you']; ?></h2>
    <div class="menu-grid">
        <?php
        $stmt = $conn->prepare("
            SELECT f.*, AVG(o.rating) as avg_rating 
            FROM foods f 
            LEFT JOIN order_items oi ON f.id = oi.food_id 
            LEFT JOIN orders o ON oi.order_id = o.id 
            WHERE o.user_id = ? AND o.rating >= 4 
            GROUP BY f.id 
            ORDER BY avg_rating DESC LIMIT 3");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $suggested_result = $stmt->get_result();
        
        while ($row = $suggested_result->fetch_assoc()) {
            $food_id = $row["id"];
            $stmt = $conn->prepare("SELECT image FROM food_images WHERE food_id = ?");
            $stmt->bind_param("i", $food_id);
            $stmt->execute();
            $images = $stmt->get_result();
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
                    <p>{$lang['rating']}: " . number_format($row['avg_rating'], 1) . "/5</p>
                    <div class='add-to-cart'>
                        <input type='number' min='1' value='1' id='quantity-$food_id' class='quantity-input'>
                        <a href='menu.php?add_to_cart=$food_id&quantity=' class='order-btn' onclick='this.href += document.getElementById(\"quantity-$food_id\").value'>🛒 {$lang['add_to_cart']}</a>
                    </div>
                </div>";
        }
        ?>
    </div>

    <!-- Menu Display -->
    <h2><?php echo $lang['menu']; ?></h2>
    <div class="menu-grid">
        <?php
        $result = $conn->query("SELECT * FROM foods");

        while ($row = $result->fetch_assoc()) {
            $food_id = $row["id"];
            $stmt = $conn->prepare("SELECT image FROM food_images WHERE food_id = ?");
            $stmt->bind_param("i", $food_id);
            $stmt->execute();
            $images = $stmt->get_result();
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

            if ($showAdminControls) {
                echo "
                <a href='edit_food_form.php?id={$row["id"]}' class='edit-btn'>✏️ {$lang['edit']}</a>
                <a href='delete_food.php?id={$row["id"]}' class='delete-btn' onclick='return confirm(\"Are you sure?\")'>🗑️ {$lang['delete']}</a>";
            }

            echo "
                <div class='add-to-cart'>
                    <input type='number' min='1' value='1' id='quantity-$food_id' class='quantity-input'>
                    <a href='menu.php?add_to_cart=$food_id&quantity=' class='order-btn' onclick='this.href += document.getElementById(\"quantity-$food_id\").value'>🛒 {$lang['add_to_cart']}</a>
                </div>
                </div>";
        }
        ?>
    </div>

    <script>
        document.querySelectorAll(".gallery-thumb").forEach(img => {
            img.addEventListener("click", function() {
                let foodId = this.dataset.food;
                let newImage = this.dataset.image;
                document.getElementById("main-img-" + foodId).src = "images/" + newImage;
            });
        });

        document.querySelectorAll(".main-image").forEach(img => {
            img.addEventListener("mouseover", function() {
                this.style.cursor = "zoom-in";
            });
        });
    </script>
</body>
</html>