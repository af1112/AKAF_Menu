<?php
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
unset($_SESSION['success_message']);
?>

<div class="admin-section">
    <h3><?php echo $lang['manage_categories'] ?? 'Manage Categories'; ?></h3>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <a href="admin_dashboard.php?page=add_category" class="button" style="margin-bottom: 20px;">
        <i class="fas fa-plus"></i> <?php echo $lang['add_category'] ?? 'Add Category'; ?>
    </a>
    <?php if ($categories_data->num_rows > 0): ?>
        <table class="foods-table">
            <thead>
                <tr>
                    <th><?php echo $lang['name'] ?? 'Name'; ?></th>
                    <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
                </tr>
            </thead>
            <tbody class="admin-dashboard">
                <?php while ($category = $categories_data->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php 
                            $lang_key = 'name_' . $_SESSION['lang'];
                            echo htmlspecialchars($category[$lang_key] ?? $category['name_en'] ?? 'Unnamed');
                            ?>
                        </td>
                        <td>
                            <a href="admin_dashboard.php?page=edit_category&id=<?php echo $category['id']; ?>" class="button">
                                <i class="fas fa-edit"></i> <?php echo $lang['edit'] ?? 'Edit'; ?>
                            </a>
                            <form action="admin_dashboard.php?page=delete_category" method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                <button type="submit" class="delete-btn" onclick="return confirm('<?php echo $lang['confirm_delete_category'] ?? 'Are you sure you want to delete this category?'; ?>')">
                                    <i class="fas fa-trash"></i> <?php echo $lang['delete'] ?? 'Delete'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><?php echo $lang['no_categories'] ?? 'No categories found.'; ?></p>
    <?php endif; ?>
</div>