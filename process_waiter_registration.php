<?php
include "db.php"; // Database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST["fullname"];
    $image_url = $_POST["image_url"];
    $phone_number = $_POST["phone_number"];
	$ID_num = $_POST["ID_num"];
	$dob = $_POST["dob"];

    // Insert into database
    $sql = "INSERT INTO waiters (fullname, image_url, phone_number, ID_num, dob) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $fullname, $image_url, $phone_number, $ID_num, $dob);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful!');</script>";
    }
 else {
        echo "<script>alert('Error:Phone Number may already exist.');</script>";
    }	
}
?>
