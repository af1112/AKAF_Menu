<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $food_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($food_id > 0) {
        $stmt = $conn->prepare("SELECT main_image FROM foods WHERE id = ?");
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $food = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($food && !empty($food['main_image']) && file_exists($food['main_image'])) {
            unlink($food['main_image']);
        }

        $stmt = $conn->prepare("SELECT image_path FROM food_images WHERE food_id = ?");
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $gallery_images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($gallery_images as $image) {
            if (file_exists($image['image_path'])) {
                unlink($image['image_path']);
            }
        }

        $stmt = $conn->prepare("DELETE FROM food_images WHERE food_id = ?");
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM foods WHERE id = ?");
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_message'] = $lang['food_deleted'] ?? "Food item deleted successfully.";
    }
    header("Location: admin_dashboard.php?page=foods");
    exit();
}
?>