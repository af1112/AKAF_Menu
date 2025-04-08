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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name_en = $_POST['name_en'] ?? '';
    $name_fa = $_POST['name_fa'] ?? '';
    $name_fr = $_POST['name_fr'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';

    // Input validation
    $errors = [];
    if (empty($name_en) || empty($name_fa) || empty($name_fr) || empty($name_ar)) {
        $errors[] = $lang['name_required'] ?? "All name fields are required.";
    }

    // Handle image upload
    $image = '';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = $lang['invalid_image_type'] ?? "Image must be a JPEG, PNG, or GIF.";
        } else {
            $image = 'images/' . time() . '_' . basename($_FILES['image']['name']);
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image)) {
                $errors[] = $lang['upload_failed'] ?? "Failed to upload image.";
            }
        }
    }

    if (empty($errors)) {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO categories (name_en, name_fa, name_fr, name_ar, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name_en, $name_fa, $name_fr, $name_ar, $image);
        if ($stmt->execute()) {
            header("Location: manage_categories.php?success=" . urlencode($lang['category_added'] ?? "Category added successfully."));
            exit();
        } else {
            $errors[] = $lang['db_error'] ?? "Database error: " . $stmt->error;
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['add_category'] ?? 'Add Category'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: <?php echo $theme === 'light' ? '#f4f4f4' : '#333'; ?>;
            color: <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 500;
        }
        .controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .controls select {
            padding: 8px;
            font-size: 16px;
            border-radius: 5px;
            background-color: #34495e;
            color: white;
            border: none;
        }
        .controls a {
            color: white;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .controls a:hover {
            color: #ddd;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background-color: <?php echo $theme === 'light' ? '#fff' : '#444'; ?>;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .add-category h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: <?php echo $theme === 'light' ? '#2c3e50' : '#ddd'; ?>;
            font-weight: 500;
        }
        .add-category form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .add-category label {
            font-size: 16px;
            font-weight: 500;
            color: <?php echo $theme === 'light' ? '#555' : '#bbb'; ?>;
        }
        .add-category input[type="text"],
        .add-category input[type="file"] {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid <?php echo $theme === 'light' ? '#ddd' : '#555'; ?>;
            border-radius: 5px;
            background-color: <?php echo $theme === 'light' ? '#fff' : '#555'; ?>;
            color: <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            box-sizing: border-box;
        }
        .add-category input[type="text"]:focus,
        .add-category input[type="file"]:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 5px rgba(44, 62, 80, 0.3);
        }
        .add-category .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .add-category button,
        .add-category .button {
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s ease;
        }
        .add-category button {
            background-color: #2c3e50;
            color: white;
        }
        .add-category button:hover {
            background-color: #34495e;
        }
        .add-category .button.cancel-btn {
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
        }
        .add-category .button.cancel-btn:hover {
            background-color: #c0392b;
        }
        .error {
            color: #e74c3c;
            font-size: 16px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="<?php echo $theme; ?>">
    <div class="header">
        <h1><?php echo $lang['add_category'] ?? 'Add Category'; ?></h1>
        <div class="controls">
            <select onchange="window.location='add_category.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="add_category.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </div>

    <div class="container">
        <div class="add-category">
            <h3><?php echo $lang['add_category'] ?? 'Add Category'; ?></h3>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <form action="add_category.php" method="POST" enctype="multipart/form-data">
                <label for="name_en"><?php echo $lang['name_en'] ?? 'Name (English)'; ?>:</label>
                <input type="text" name="name_en" id="name_en" required onblur="translateFields('name_en', ['name_fa', 'name_fr', 'name_ar'])">

                <label for="name_fa"><?php echo $lang['name_fa'] ?? 'Name (Persian)'; ?>:</label>
                <input type="text" name="name_fa" id="name_fa" required>

                <label for="name_fr"><?php echo $lang['name_fr'] ?? 'Name (French)'; ?>:</label>
                <input type="text" name="name_fr" id="name_fr" required>

                <label for="name_ar"><?php echo $lang['name_ar'] ?? 'Name (Arabic)'; ?>:</label>
                <input type="text" name="name_ar" id="name_ar" required>

                <label for="image"><?php echo $lang['image'] ?? 'Image'; ?>:</label>
                <input type="file" name="image" id="image">

                <div class="button-group">
                    <button type="submit"><?php echo $lang['add'] ?? 'Add'; ?></button>
                    <a href="manage_categories.php" class="button cancel-btn">
                        <i class="fas fa-times"></i> <?php echo $lang['cancel'] ?? 'Cancel'; ?>
                    </a>
                </div>
            </form>
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
                'name_ar': 'ar'
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