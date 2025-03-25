<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['Name'];


    // Handle Image Upload
    $targetDir = "images/";
    $imageFileName = basename($_FILES["image"]["name"]);
    $targetFilePath = $targetDir . $imageFileName;
    $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

    // Allowed file types
    $allowedTypes = ["jpg", "jpeg", "png", "gif", "webp"];

    if (in_array($imageFileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            // Insert into database
            $sql = "INSERT INTO category (Name, image) 
                    VALUES ('$name', '$imageFileName')";

            if ($conn->query($sql) === TRUE) {
                echo "New Category item added successfully!";
                echo "<br><a href='index.php'>Go to Menu</a>";
            } else {
                echo "Error: " . $conn->error;
            }
        } else {
            echo "Error uploading the image.";
        }
    } else {
        echo "Invalid file format. Only JPG, JPEG, PNG & GIF are allowed.";
    }
}

$conn->close();
?>
