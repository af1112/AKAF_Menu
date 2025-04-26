<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($category_id > 0) {
        $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($category && !empty($category['image']) && file_exists($category['image'])) {
            unlink($category['image']);
        }

        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_message'] = $lang['category_deleted'] ?? "Category deleted successfully.";
    }
    header("Location: admin_dashboard.php?page=categories");
    exit();
}
?>