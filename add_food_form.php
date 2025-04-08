<?php
session_start();
include 'db.php';

// Restrict access to Admins, Managers, and Staff
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['admin', 'manager', 'staff'])) {
    header("Location: user_login.php");
    exit();
}

// Set default language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

// Load translations
include "languages/" . $_SESSION['lang'] . ".php";
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['add_food']; ?></title>
    <link rel="stylesheet" href="styles.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; text-align: center; background: #f8f8f8; padding: 20px; }
        .form-container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1); text-align: left; }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; margin-top: 5px; }
        .submit-btn { background: #27ae60; color: white; padding: 10px; border: none; cursor: pointer; width: 100%; border-radius: 5px; }
        .submit-btn:hover { opacity: 0.8; }
        .back-btn { display: inline-block; padding: 8px 12px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        .back-btn:hover { opacity: 0.8; }
    </style>
</head>
<body>

    <h1>➕ <?php echo $lang['add_food']; ?></h1>

    <div class="form-container">
        <form action="add_food.php" method="POST" enctype="multipart/form-data">

            <!-- Food Name in Multiple Languages -->
            <div class="form-group">
                <label><?php echo $lang['food_name']; ?> (English):</label>
                <input type="text" name="name_en" required>
            </div>

            <div class="form-group">
                <label><?php echo $lang['food_name']; ?> (Français):</label>
                <input type="text" name="name_fr" required>
            </div>

            <div class="form-group">
                <label><?php echo $lang['food_name']; ?> (العربية):</label>
                <input type="text" name="name_ar" required>
            </div>

            <!-- Food Description in Multiple Languages -->
            <div class="form-group">
                <label><?php echo $lang['description']; ?> (English):</label>
                <textarea name="description_en" required></textarea>
            </div>

            <div class="form-group">
                <label><?php echo $lang['description']; ?> (Français):</label>
                <textarea name="description_fr" required></textarea>
            </div>

            <div class="form-group">
                <label><?php echo $lang['description']; ?> (العربية):</label>
                <textarea name="description_ar" required></textarea>
            </div>

            <!-- Price -->
            <div class="form-group">
                <label><?php echo $lang['price']; ?> ($):</label>
                <input type="number" name="price" step="0.01" required>
            </div>

            <!-- Image Upload (Multiple Images) -->
            <div class="form-group">
                <label><?php echo $lang['upload_images']; ?>:</label>
                <input type="file" name="images[]" multiple accept="image/*" required>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn"><?php echo $lang['add_food']; ?></button>
        </form>

        <a href="menu.php" class="back-btn">⬅️ <?php echo $lang['back_to_menu']; ?></a>
    </div>

</body>
</html>
