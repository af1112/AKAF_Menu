<?php
// Fetch all foods
$foods = $conn->query("SELECT * FROM foods");
?>

<div class="admin-section">
    <h3><?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?></h3>
    <a href="admin_dashboard.php?page=add_food" class="button" style="margin-bottom: 20px;">
        <i class="fas fa-plus"></i> <?php echo $lang['add_food'] ?? 'Add Food'; ?>
    </a>
    <table class="foods-table">
        <thead>
            <tr>
                <th><?php echo $lang['name'] ?? 'Name'; ?></th>
                <th><?php echo $lang['category'] ?? 'Category'; ?></th>
                <th><?php echo $lang['price'] ?? 'Price'; ?></th>
                <th><?php echo $lang['is_available'] ?? 'Available'; ?></th>
                <th><?php echo $lang['actions'] ?? 'Actions'; ?></th>
            </tr>
        </thead>
        <tbody>
            <?php while ($food = $foods->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php 
                        $lang_key = 'name_' . $_SESSION['lang'];
                        echo htmlspecialchars($food[$lang_key] ?? $food['name_en'] ?? 'Unnamed');
                        ?>
                    </td>
                    <td>
                        <?php
                        $category_id = $food['category_id'] ?? 0;
                        echo htmlspecialchars($categories[$category_id] ?? ($lang['no_category'] ?? 'No Category'));
                        ?>
                    </td>
                    <td><?php echo number_format($food['price'], 2); ?> <?php echo $lang['currency'] ?? '$'; ?></td>
                    <td><?php echo $food['is_available'] ? ($lang['yes'] ?? 'Yes') : ($lang['no'] ?? 'No'); ?></td>
                    <td>
                        <a href="admin_dashboard.php?page=view_food&id=<?php echo $food['id']; ?>" class="button">
                            <i class="fas fa-eye"></i> <?php echo $lang['view'] ?? 'View'; ?>
                        </a>
                        <a href="admin_dashboard.php?page=edit_food&id=<?php echo $food['id']; ?>" class="button">
                            <i class="fas fa-edit"></i> <?php echo $lang['edit'] ?? 'Edit'; ?>
                        </a>
                        <form action="admin_dashboard.php?page=delete_food" method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $food['id']; ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('<?php echo $lang['confirm_delete'] ?? 'Are you sure you want to delete this food?'; ?>')">
                                <i class="fas fa-trash"></i> <?php echo $lang['delete'] ?? 'Delete'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>