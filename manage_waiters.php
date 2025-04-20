<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

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

// Manage theme
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}
$theme = $_SESSION['theme'];

// Fetch all waiters
$waiters = $conn->query("SELECT * FROM waiters");
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>" media="only screen and (max-width: 768px)">
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['manage_waiters'] ?? 'Manage Waiters'; ?></h1>
        <div class="controls">
            <select onchange="window.location='manage_waiters.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="manage_waiters.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </header>

    <aside class="admin-sidebar">
        <ul>
            <li>
                <a href="manage_foods.php" >
                    <i class="fas fa-utensils"></i> <?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?>
                </a>
            </li>
            <li>
                <a href="manage_categories.php">
                    <i class="fas fa-list"></i> <?php echo $lang['manage_categories'] ?? 'Manage Categories'; ?>
                </a>
            </li>
            <li>
                <a href="manage_orders.php">
                    <i class="fas fa-shopping-cart"></i> <?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?>
                </a>
            </li>
            <li>
                <a href="manage_hero_texts.php">
                    <i class="fas fa-heading"></i> <?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?>
                </a>
            </li>
			<li>
			    <a href="manage_waiters.php" class="active">
				    <i class="fas fa-id-card"></i> <?php echo $lang['manage_waiters'] ?? 'Manage Waiters'; ?>
				</a>
			</li>
        </ul>
    </aside>

    <main class="admin-content">
        <div class="admin-section">
            <h3><?php echo $lang['manage_waiters'] ?? 'Manage Waiters'; ?></h3>
            <a href="add_food.php" class="button" style="margin-bottom: 20px;">
                <i class="fas fa-plus"></i> <?php echo $lang['add_waiter'] ?? 'Add Waiter'; ?>
            </a>
            <table class="foods-table">
                <thead>
                    <tr>
                        <th><?php echo $lang['fullname'] ?? 'Full name'; ?></th>
                        <th><?php echo $lang['image_url'] ?? 'Image waiter'; ?></th>
                        <th><?php echo $lang['Phone_number'] ?? 'Phone number'; ?></th>
                        <th><?php echo $lang['ID_number'] ?? 'ID Number'; ?></th>
                        <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($waiter = $waiters->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($waiter['FullName']); ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($waiter['image_url']); ?>" alt="waiter Image" style="width: 50 px; height: auto;">
                            </td>
                            <td><?php echo htmlspecialchars($waiter['phone_number']); ?> </td>
                            <td><?php echo htmlspecialchars($waiter['ID_num']); ?> </td>
                            <td>
                                <a href="view_waiter.php?id=<?php echo $waiter['id']; ?>" class="button">
                                    <i class="fas fa-eye"></i> <?php echo $lang['view'] ?? 'View'; ?>
                                </a>
                                <a href="edit_waiter.php?id=<?php echo $waiter['id']; ?>" class="button">
                                    <i class="fas fa-edit"></i> <?php echo $lang['edit'] ?? 'Edit'; ?>
                                </a>
                                <form action="delete_waiter.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $waiter['id']; ?>">
                                    <button type="submit" class="delete-btn" onclick="return confirm('<?php echo $lang['confirm_delete'] ?? 'Are you sure you want to delete this Waiter?'; ?>')">
                                        <i class="fas fa-trash"></i> <?php echo $lang['delete'] ?? 'Delete'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>