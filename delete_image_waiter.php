<?php
session_start();
include 'db.php';

// Restrict access
if (!isset($_SESSION['user']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['image_id'])) {
    $image_id = (int)$_POST['image_id'];

    // Get the image filename to delete it from the server
    $result = $conn->query("SELECT image FROM waiter_images WHERE id=$image_id");
    if ($result->num_rows > 0) {
        $image = $result->fetch_assoc()['image'];
        $file_path = "images/" . $image;

        // Delete the image file from the server
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Delete the record from the database
        $conn->query("DELETE FROM waiter_images WHERE id=$image_id");

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Image not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
exit();
?>