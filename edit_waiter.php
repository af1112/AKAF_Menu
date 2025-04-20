<?php
session_start();
include 'db.php';

// Enable error reporting for debugging (can be removed in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Fetch waiter details
$waiter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Debug: Check the value of $waiter_id
if ($waiter_id <= 0) {
    $error = $lang['invalid_waiter_id'] ?? "Invalid waiter ID.";
} else {
    $stmt = $conn->prepare("SELECT * FROM waiters WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $waiter_id);
    $stmt->execute();
    $waiter = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$waiter) {
        $error = $lang['waiter_not_found'] ?? "Waiter with ID $waiter_id not found.";
    }
}

// Fetch gallery images from waiter_images table
$gallery_images = [];
if (!isset($error)) {
    $gallery_stmt = $conn->prepare("SELECT * FROM waiter_images WHERE waiters_id = ?");
    $gallery_stmt->bind_param("i", $waiters_id);
    $gallery_stmt->execute();
    $gallery_images = $gallery_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $gallery_stmt->close();
}

// Handle gallery image deletion
if (isset($_GET['delete_image_waiter']) && !isset($error)) {
    $image_id = (int)$_GET['delete_image_waiter'];
    $stmt = $conn->prepare("SELECT image_path FROM waiter_images WHERE id = ? AND waiters_id = ?");
    $stmt->bind_param("ii", $image_id, $waiters_id);
    $stmt->execute();
    $image = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($image) {
        // Delete the image file from the server
        if (file_exists($image['image_path'])) {
            unlink($image['image_path']);
        }
        // Delete the image record from the database
        $stmt = $conn->prepare("DELETE FROM waiter_images WHERE id = ? AND waiters_id = ?");
        $stmt->bind_param("ii", $image_id, $waiters_id);
        $stmt->execute();
        $stmt->close();
        // Redirect to refresh the page
        header("Location: edit_waiter.php?id=$waiters_id");
        exit();
    } else {
        $error = $lang['image_not_found'] ?? "Image not found.";
    }
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($error)) {
    // Retrieve form data
    $FullName = $_POST['FullName'] ?? '';
    $ID_num = isset($_POST['ID_num']) ? (float)$_POST['ID_num'] : 0.0;
    $phone_number = isset($_POST['phone_number']) ? (int)$_POST['phone_number'] : 0;
    $waiter_id = (int)$waiter_id; // Ensure $waiter_id is an integer

    // Input validation
    $errors = [];
    if (empty($FullName)) {
        $errors[] = $lang['name_required'] ?? " name field are required.";
    }
     else {
        // Track if any changes were made
        $has_changes = false;

        // Define allowed image types for both main and gallery images
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        // Handle waiter image upload
        $main_image = $waiter['image_url'];
        if (isset($_FILES['image_url']) && $_FILES['image_url']['error'] == 0) {
            if (!in_array($_FILES['image_url']['type'], $allowed_types)) {
                $error = $lang['invalid_image_type'] ?? "Main image must be a JPEG, PNG, or GIF.";
            } else {
                $image_url = 'images/' . time() . '_' . basename($_FILES['image_url']['FullName']);
                if (move_uploaded_file($_FILES['image_url']['tmp_name'], $image_url)) {
                    $has_changes = true; // image changed
                } else {
                    $error = $lang['upload_failed'] ?? "Failed to upload the image.";
                }
            }
        }

   

        // Update waiter
        if (!isset($error)) {
            // Escape all string values to prevent SQL injection
            $FullName = $conn->real_escape_string($FullName);
            $image_url = $conn->real_escape_string($image_url);

            // Build the query directly
            $query = "UPDATE waiters SET 
                FullName = '$FullName', 
                ID_num = $ID_num, 
                phone_number = $phone_number, 
                image_url = '$image_url' 
                WHERE id = $waiter_id";

            // Execute the query
            if ($conn->query($query)) {
                // Check if any rows were updated in the waiters table
                if ($conn->affected_rows > 0) {
                    $has_changes = true; // waiters table updated
                }

                // Redirect if any changes were made (either in waiters or waiter_images)
                if ($has_changes) {
                    header("Location: manage_waiters.php?success=" . urlencode($lang['update_success'] ?? "waiter info updated successfully."));
                    exit();
                } else {
                    $error = $lang['no_changes'] ?? "No changes were made to the waiter";
                }
            } else {
                $error = $lang['db_error'] ?? "Database error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['edit_food'] ?? 'Edit Food'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        .gallery-images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .gallery-image {
            position: relative;
            display: inline-block;
        }
        .gallery-image img {
            max-width: 100px;
            height: auto;
        }
        .delete-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover {
            background: darkred;
        }
    </style>
</head>
<body class="<?php echo $theme; ?>">
    <div class="header" style="background-color: #2c3e50;">
        <h1><?php echo $lang['edit_food'] ?? 'Edit Food'; ?></h1>
        <div class="controls">
            <select onchange="window.location='edit_food.php?id=<?php echo $food_id; ?>&lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="edit_food.php?id=<?php echo $food_id; ?>&theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </div>

    <div class="container">
        <div class="add-food">
            <h3><?php echo $lang['edit_waiter'] ?? 'Edit Waiter'; ?></h3>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <?php if (!isset($error)): ?>
                <form action="edit_waiter.php?id=<?php echo $waiter_id; ?>" method="POST" enctype="multipart/form-data">
                    <label for="FullName"><?php echo $lang['FullName'] ?? 'FullName'; ?>:</label>
                    <input type="text" name="FullName" id="FullName" value="<?php echo htmlspecialchars($waiter['FullName'] ?? ''); ?>" required >

                    <label for="ID_num"><?php echo $lang['ID_num'] ?? 'ID_num'; ?>:</label>
                    <input type="number" name="ID_num" id="ID_num" step="0.01" value="<?php echo $waiter['ID_num']; ?>" required>

                    <label for="phone_number"><?php echo $lang['phone_number'] ?? 'Phone number'; ?>:</label>
                    <input type="number" name="phone_number" id="phone_number" value="<?php echo $waiter['phone_number']; ?>">
                    
                    <label for="image_url"><?php echo $lang['image_url'] ?? ' Waiter Image'; ?>:</label>
                    <input type="file" name="image_url">
                    <?php if ($waiter['image_url']): ?>
                        <p><?php echo $lang['current_image'] ?? 'Current Image'; ?>: <img src="<?php echo htmlspecialchars($waiter['image_url']); ?>" alt="Image waiter" style="max-width: 200px; margin-top: 10px;"></p>
                    <?php endif; ?>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit"><?php echo $lang['update'] ?? 'Update'; ?></button>
                        <a href="manage_waiters.php" class="button cancel-btn">
                            <i class="fas fa-times"></i> <?php echo $lang['cancel'] ?? 'Cancel'; ?>
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        async function translateFields(sourceFieldId, targetFieldIds) {
            const sourceText = document.getElementById(sourceFieldId).value;
            if (!sourceText) return;

            const sourceLang = 'en'; // Assuming the source is always English
            const targetLangs = {
                'name_fa': 'fa',
                'name_fr': 'fr',
                'name_ar': 'ar',
                'description_fa': 'fa',
                'description_fr': 'fr',
                'description_ar': 'ar',
                'ingredients_fa': 'fa',
                'ingredients_fr': 'fr',
                'ingredients_ar': 'ar'
            };

            for (const targetFieldId of targetFieldIds) {
                const targetLang = targetLangs[targetFieldId];
                if (!targetLang) continue;

                try {
                    const response = await axios.post('https://api.mymemory.translated.net/get', null, {
                        params: {
                            q: sourceText,
                            langpair: `${sourceLang}|${targetLang}`
                        }
                    });
                    const translatedText = response.data.responseData.translatedText;
                    const targetField = document.getElementById(targetFieldId);
                    if (targetField.value === '') {
                        targetField.value = translatedText;
                    }
                } catch (error) {
                    console.error(`Translation failed for ${targetFieldId}:`, error);
                }
            }
        }
    </script>
</body>
</html>