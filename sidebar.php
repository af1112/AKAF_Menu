<?php
// Fetch total unread messages for sidebar
$stmt_total_unread = $conn->prepare("
    SELECT COUNT(*) AS total_unread 
    FROM order_messages om 
    JOIN orders o ON om.order_id = o.id 
    WHERE om.sender_type = 'customer' AND om.is_read = 0
");
$stmt_total_unread->execute();
$total_unread = $stmt_total_unread->get_result()->fetch_assoc()['total_unread'];

// Determine the active page
$current_page = $_GET['page'] ?? 'overview';
?>

<aside class="admin-sidebar">
    <ul>
        <li>
            <a href="admin_dashboard.php?page=overview" class="<?php echo $current_page == 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> <?php echo $lang['dashboard'] ?? 'Dashboard'; ?>
            </a>
        </li>
        <li>
            <a href="admin_dashboard.php?page=foods" class="<?php echo $current_page == 'foods' ? 'active' : ''; ?>">
                <i class="fas fa-utensils"></i> <?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?>
            </a>
        </li>
        <li>
            <a href="admin_dashboard.php?page=categories" class="<?php echo $current_page == 'categories' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> <?php echo $lang['manage_categories'] ?? 'Manage Categories'; ?>
            </a>
        </li>
        <li>
            <a href="admin_dashboard.php?page=orders" class="<?php echo $current_page == 'orders' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> <?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?>
            </a>
        </li>
        <li>
            <a href="admin_dashboard.php?page=hero_texts" class="<?php echo $current_page == 'hero_texts' ? 'active' : ''; ?>">
                <i class="fas fa-heading"></i> <?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?>
            </a>
        </li>
        <li>
            <a href="admin_dashboard.php?page=messages" class="<?php echo $current_page == 'messages' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> <?php echo $lang['manage_messages'] ?? 'Manage Messages'; ?>
                <?php if ($total_unread > 0): ?>
                    <span class="sidebar-unread-count"><?php echo $total_unread; ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>
</aside>