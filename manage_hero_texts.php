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
    $title_en = $_POST['title_en'] ?? '';
    $title_fa = $_POST['title_fa'] ?? '';
    $title_fr = $_POST['title_fr'] ?? '';
    $title_ar = $_POST['title_ar'] ?? '';
    $description_en = $_POST['description_en'] ?? '';
    $description_fa = $_POST['description_fa'] ?? '';
    $description_fr = $_POST['description_fr'] ?? '';
    $description_ar = $_POST['description_ar'] ?? '';

    // Input validation
    $errors = [];
    if (empty($title_en) || empty($title_fa) || empty($title_fr) || empty($title_ar)) {
        $errors[] = $lang['title_required'] ?? "All title fields are required.";
    }
    if (empty($description_en) || empty($description_fa) || empty($description_fr) || empty($description_ar)) {
        $errors[] = $lang['description_required'] ?? "All description fields are required.";
    }

    if (empty($errors)) {
        // Check if record exists
        $stmt = $conn->prepare("SELECT id FROM hero_texts WHERE id = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE hero_texts SET title_en = ?, title_fa = ?, title_fr = ?, title_ar = ?, description_en = ?, description_fa = ?, description_fr = ?, description_ar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
            $stmt->bind_param("ssssssss", $title_en, $title_fa, $title_fr, $title_ar, $description_en, $description_fa, $description_fr, $description_ar);
        } else {
            // Insert new record with id=1
            $stmt = $conn->prepare("INSERT INTO hero_texts (id, title_en, title_fa, title_fr, title_ar, description_en, description_fa, description_fr, description_ar, updated_at) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->bind_param("ssssssss", $title_en, $title_fa, $title_fr, $title_ar, $description_en, $description_fa, $description_fr, $description_ar);
        }

        if ($stmt->execute()) {
            $success = $lang['hero_text_updated'] ?? "Hero text updated successfully.";
        } else {
            $errors[] = $lang['db_error'] ?? "Database error: " . $stmt->error;
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Fetch hero text (always id=1)
$hero_text = [
    'title_en' => '',
    'title_fa' => '',
    'title_fr' => '',
    'title_ar' => '',
    'description_en' => '',
    'description_fa' => '',
    'description_fr' => '',
    'description_ar' => '',
    'updated_at' => ''
];
$stmt = $conn->prepare("SELECT * FROM hero_texts WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $hero_text = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://v1.fontapi.ir/css/Vazir" rel="stylesheet">
    <style>
        body {
            font-family: <?php echo $is_rtl ? "'Vazir', sans-serif" : "'Roboto', sans-serif"; ?>;
            background-color: <?php echo $theme === 'light' ? '#f4f4f4' : '#333'; ?>;
            color: <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            margin: 0;
            padding: 0;
        }
        .admin-header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .admin-header h1 {
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
        .admin-sidebar {
            width: 250px;
            background-color: <?php echo $theme === 'light' ? '#fff' : '#444'; ?>;
            position: fixed;
            top: 0;
            <?php echo $is_rtl ? 'right: 0' : 'left: 0'; ?>;
            height: 100%;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding-top: 70px;
        }
        .admin-sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .admin-sidebar ul li {
            border-bottom: 1px solid <?php echo $theme === 'light' ? '#eee' : '#555'; ?>;
        }
        .admin-sidebar ul li a {
            display: block;
            padding: 15px 20px;
            color: <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.3s ease;
        }
        .admin-sidebar ul li a:hover,
        .admin-sidebar ul li a.active {
            background-color: <?php echo $theme === 'light' ? '#f4f4f4' : '#555'; ?>;
            color: <?php echo $theme === 'light' ? '#2c3e50' : '#ddd'; ?>;
        }
        .admin-content {
            margin-left: <?php echo $is_rtl ? '0' : '250px'; ?>;
            margin-right: <?php echo $is_rtl ? '250px' : '0'; ?>;
            padding: 30px;
        }
        .admin-section {
            background-color: <?php echo $theme === 'light' ? '#fff' : '#444'; ?>;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .admin-section h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: <?php echo $theme === 'light' ? '#2c3e50' : '#ddd'; ?>;
            font-weight: 500;
        }
        .admin-section form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .admin-section label {
            font-size: 16px;
            font-weight: 500;
            color: <?php echo $theme === 'light' ? '#555' : '#bbb'; ?>;
        }
        .admin-section textarea {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid <?php echo $theme === 'light' ? '#ddd' : '#555'; ?>;
            border-radius: 5px;
            background-color: <?php echo $theme === 'light' ? '#fff' : '#555'; ?>;
            color: <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            box-sizing: border-box;
            font-family: <?php echo $is_rtl ? "'Vazir', sans-serif" : "'Roboto', sans-serif"; ?>;
            resize: vertical;
        }
        .admin-section textarea.title-field {
            height: 50px;
        }
        .admin-section textarea.description-field {
            height: 100px;
        }
        .admin-section textarea:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 5px rgba(44, 62, 80, 0.3);
        }
        .admin-section .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .admin-section button {
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s ease;
            background-color: #2c3e50;
            color: white;
        }
        .admin-section button:hover {
            background-color: #34495e;
        }
        .success {
            color: #27ae60;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .error {
            color: #e74c3c;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .updated-at {
            font-size: 14px;
            color: <?php echo $theme === 'light' ? '#777' : '#aaa'; ?>;
            margin-top: 10px;
        }
    </style>
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></h1>
        <div class="controls">
            <select onchange="window.location='manage_hero_texts.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="manage_hero_texts.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </header>

    <aside class="admin-sidebar">
        <ul>
            <li>
                <a href="manage_foods.php">
                    <i class="fas fa-utensils"></i> <?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?>
                </a>
            </li>
            <li>
                <a href="manage_categories.php">
                    <i class="fas fa-list"></i> <?php echo $lang['manage_categories'] ?? 'Manage Categories'; ?>
                </a>
            </li>
            <li>
                <a href="manage_orders.php">
                    <i class="fas fa-shopping-cart"></i> <?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?>
                </a>
            </li>
            <li>
                <a href="manage_hero_texts.php" class="active">
                    <i class="fas fa-heading"></i> <?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?>
                </a>
            </li>
        </ul>
    </aside>

    <main class="admin-content">
        <div class="admin-section">
            <h3><?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></h3>
            <?php if (isset($success)): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <form action="manage_hero_texts.php" method="POST">
                <label for="title_en"><?php echo $lang['title_en'] ?? 'Title (English)'; ?>:</label>
                <textarea name="title_en" id="title_en" class="title-field" required><?php echo htmlspecialchars($hero_text['title_en']); ?></textarea>

                <label for="title_fa"><?php echo $lang['title_fa'] ?? 'Title (Persian)'; ?>:</label>
                <textarea name="title_fa" id="title_fa" class="title-field" required><?php echo htmlspecialchars($hero_text['title_fa']); ?></textarea>

                <label for="title_fr"><?php echo $lang['title_fr'] ?? 'Title (French)'; ?>:</label>
                <textarea name="title_fr" id="title_fr" class="title-field" required><?php echo htmlspecialchars($hero_text['title_fr']); ?></textarea>

                <label for="title_ar"><?php echo $lang['title_ar'] ?? 'Title (Arabic)'; ?>:</label>
                <textarea name="title_ar" id="title_ar" class="title-field" required><?php echo htmlspecialchars($hero_text['title_ar']); ?></textarea>

                <label for="description_en"><?php echo $lang['description_en'] ?? 'Description (English)'; ?>:</label>
                <textarea name="description_en" id="description_en" class="description-field" required><?php echo htmlspecialchars($hero_text['description_en']); ?></textarea>

                <label for="description_fa"><?php echo $lang['description_fa'] ?? 'Description (Persian)'; ?>:</label>
                <textarea name="description_fa" id="description_fa" class="description-field" required><?php echo htmlspecialchars($hero_text['description_fa']); ?></textarea>

                <label for="description_fr"><?php echo $lang['description_fr'] ?? 'Description (French)'; ?>:</label>
                <textarea name="description_fr" id="description_fr" class="description-field" required><?php echo htmlspecialchars($hero_text['description_fr']); ?></textarea>

                <label for="description_ar"><?php echo $lang['description_ar'] ?? 'Description (Arabic)'; ?>:</label>
                <textarea name="description_ar" id="description_ar" class="description-field" required><?php echo htmlspecialchars($hero_text['description_ar']); ?></textarea>

                <?php if ($hero_text['updated_at']): ?>
                    <p class="updated-at"><?php echo $lang['updated_at'] ?? 'Updated At'; ?>: <?php echo htmlspecialchars($hero_text['updated_at']); ?></p>
                <?php endif; ?>

                <div class="button-group">
                    <button type="submit">
                        <i class="fas fa-save"></i> <?php echo $lang['save'] ?? 'Save'; ?>
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html><?php
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
    $title_en = $_POST['title_en'] ?? '';
    $title_fa = $_POST['title_fa'] ?? '';
    $title_fr = $_POST['title_fr'] ?? '';
    $title_ar = $_POST['title_ar'] ?? '';
    $description_en = $_POST['description_en'] ?? '';
    $description_fa = $_POST['description_fa'] ?? '';
    $description_fr = $_POST['description_fr'] ?? '';
    $description_ar = $_POST['description_ar'] ?? '';

    // Input validation
    $errors = [];
    if (empty($title_en) || empty($title_fa) || empty($title_fr) || empty($title_ar)) {
        $errors[] = $lang['title_required'] ?? "All title fields are required.";
    }
    if (empty($description_en) || empty($description_fa) || empty($description_fr) || empty($description_ar)) {
        $errors[] = $lang['description_required'] ?? "All description fields are required.";
    }

    if (empty($errors)) {
        // Check if record exists
        $stmt = $conn->prepare("SELECT id FROM hero_texts WHERE id = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE hero_texts SET title_en = ?, title_fa = ?, title_fr = ?, title_ar = ?, description_en = ?, description_fa = ?, description_fr = ?, description_ar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
            $stmt->bind_param("ssssssss", $title_en, $title_fa, $title_fr, $title_ar, $description_en, $description_fa, $description_fr, $description_ar);
        } else {
            // Insert new record with id=1
            $stmt = $conn->prepare("INSERT INTO hero_texts (id, title_en, title_fa, title_fr, title_ar, description_en, description_fa, description_fr, description_ar, updated_at) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->bind_param("ssssssss", $title_en, $title_fa, $title_fr, $title_ar, $description_en, $description_fa, $description_fr, $description_ar);
        }

        if ($stmt->execute()) {
            $success = $lang['hero_text_updated'] ?? "Hero text updated successfully.";
        } else {
            $errors[] = $lang['db_error'] ?? "Database error: " . $stmt->error;
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Fetch hero text (always id=1)
$hero_text = [
    'title_en' => '',
    'title_fa' => '',
    'title_fr' => '',
    'title_ar' => '',
    'description_en' => '',
    'description_fa' => '',
    'description_fr' => '',
    'description_ar' => '',
    'updated_at' => ''
];
$stmt = $conn->prepare("SELECT * FROM hero_texts WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $hero_text = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $direction; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://v1.fontapi.ir/css/Vazir" rel="stylesheet">
    <style>
        body {
            font-family: <?php echo $is_rtl ? "'Vazir', sans-serif" : "'Roboto', sans-serif"; ?>;
            background-color: <?php echo $theme === 'light' ? '#f4f4f4' : '#333'; ?>;
            color: <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            margin: 0;
            padding: 0;
        }
        .admin-header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .admin-header h1 {
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
        .admin-sidebar {
            width: 250px;
            background-color: <?php echo $theme === 'light' ? '#fff' : '#444'; ?>;
            position: fixed;
            top: 0;
            <?php echo $is_rtl ? 'right: 0' : 'left: 0'; ?>;
            height: 100%;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding-top: 70px;
        }
        .admin-sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .admin-sidebar ul li {
            border-bottom: 1px solid <?php echo $theme === 'light' ? '#eee' : '#555'; ?>;
        }
        .admin-sidebar ul li a {
            display: block;
            padding: 15px 20px;
            color: <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.3s ease;
        }
        .admin-sidebar ul li a:hover,
        .admin-sidebar ul li a.active {
            background-color: <?php echo $theme === 'light' ? '#f4f4f4' : '#555'; ?>;
            color: <?php echo $theme === 'light' ? '#2c3e50' : '#ddd'; ?>;
        }
        .admin-content {
            margin-left: <?php echo $is_rtl ? '0' : '250px'; ?>;
            margin-right: <?php echo $is_rtl ? '250px' : '0'; ?>;
            padding: 30px;
        }
        .admin-section {
            background-color: <?php echo $theme === 'light' ? '#fff' : '#444'; ?>;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .admin-section h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: <?php echo $theme === 'light' ? '#2c3e50' : '#ddd'; ?>;
            font-weight: 500;
        }
        .admin-section form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .admin-section label {
            font-size: 16px;
            font-weight: 500;
            color: <?php echo $theme === 'light' ? '#555' : '#bbb'; ?>;
        }
        .admin-section textarea {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid <?php echo $theme === 'light' ? '#ddd' : '#555'; ?>;
            border-radius: 5px;
            background-color: <?php echo $theme === 'light' ? '#fff' : '#555'; ?>;
            color: <?php echo $theme === 'light' ? '#333' : '#fff'; ?>;
            box-sizing: border-box;
            font-family: <?php echo $is_rtl ? "'Vazir', sans-serif" : "'Roboto', sans-serif"; ?>;
            resize: vertical;
        }
        .admin-section textarea.title-field {
            height: 50px;
        }
        .admin-section textarea.description-field {
            height: 100px;
        }
        .admin-section textarea:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 5px rgba(44, 62, 80, 0.3);
        }
        .admin-section .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .admin-section button {
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s ease;
            background-color: #2c3e50;
            color: white;
        }
        .admin-section button:hover {
            background-color: #34495e;
        }
        .success {
            color: #27ae60;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .error {
            color: #e74c3c;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .updated-at {
            font-size: 14px;
            color: <?php echo $theme === 'light' ? '#777' : '#aaa'; ?>;
            margin-top: 10px;
        }
    </style>
</head>
<body class="admin-body <?php echo $theme; ?>">
    <header class="admin-header">
        <h1><?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></h1>
        <div class="controls">
            <select onchange="window.location='manage_hero_texts.php?lang=' + this.value">
                <option value="en" <?php echo $_SESSION['lang'] == 'en' ? 'selected' : ''; ?>>English</option>
                <option value="fa" <?php echo $_SESSION['lang'] == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                <option value="fr" <?php echo $_SESSION['lang'] == 'fr' ? 'selected' : ''; ?>>Français</option>
                <option value="ar" <?php echo $_SESSION['lang'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
            <a href="manage_hero_texts.php?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>">
                <i class="fas <?php echo $theme === 'light' ? 'fa-moon' : 'fa-sun'; ?>"></i>
                <?php echo $theme === 'light' ? ($lang['dark_mode'] ?? 'Dark Mode') : ($lang['light_mode'] ?? 'Light Mode'); ?>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo $lang['logout'] ?? 'Logout'; ?>
            </a>
        </div>
    </header>

    <aside class="admin-sidebar">
        <ul>
            <li>
                <a href="manage_foods.php">
                    <i class="fas fa-utensils"></i> <?php echo $lang['manage_foods'] ?? 'Manage Foods'; ?>
                </a>
            </li>
            <li>
                <a href="manage_categories.php">
                    <i class="fas fa-list"></i> <?php echo $lang['manage_categories'] ?? 'Manage Categories'; ?>
                </a>
            </li>
            <li>
                <a href="manage_orders.php">
                    <i class="fas fa-shopping-cart"></i> <?php echo $lang['manage_orders'] ?? 'Manage Orders'; ?>
                </a>
            </li>
            <li>
                <a href="manage_hero_texts.php" class="active">
                    <i class="fas fa-heading"></i> <?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?>
                </a>
            </li>
        </ul>
    </aside>

    <main class="admin-content">
        <div class="admin-section">
            <h3><?php echo $lang['manage_hero_texts'] ?? 'Manage Hero Texts'; ?></h3>
            <?php if (isset($success)): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <form action="manage_hero_texts.php" method="POST">
                <label for="title_en"><?php echo $lang['title_en'] ?? 'Title (English)'; ?>:</label>
                <textarea name="title_en" id="title_en" class="title-field" required><?php echo htmlspecialchars($hero_text['title_en']); ?></textarea>

                <label for="title_fa"><?php echo $lang['title_fa'] ?? 'Title (Persian)'; ?>:</label>
                <textarea name="title_fa" id="title_fa" class="title-field" required><?php echo htmlspecialchars($hero_text['title_fa']); ?></textarea>

                <label for="title_fr"><?php echo $lang['title_fr'] ?? 'Title (French)'; ?>:</label>
                <textarea name="title_fr" id="title_fr" class="title-field" required><?php echo htmlspecialchars($hero_text['title_fr']); ?></textarea>

                <label for="title_ar"><?php echo $lang['title_ar'] ?? 'Title (Arabic)'; ?>:</label>
                <textarea name="title_ar" id="title_ar" class="title-field" required><?php echo htmlspecialchars($hero_text['title_ar']); ?></textarea>

                <label for="description_en"><?php echo $lang['description_en'] ?? 'Description (English)'; ?>:</label>
                <textarea name="description_en" id="description_en" class="description-field" required><?php echo htmlspecialchars($hero_text['description_en']); ?></textarea>

                <label for="description_fa"><?php echo $lang['description_fa'] ?? 'Description (Persian)'; ?>:</label>
                <textarea name="description_fa" id="description_fa" class="description-field" required><?php echo htmlspecialchars($hero_text['description_fa']); ?></textarea>

                <label for="description_fr"><?php echo $lang['description_fr'] ?? 'Description (French)'; ?>:</label>
                <textarea name="description_fr" id="description_fr" class="description-field" required><?php echo htmlspecialchars($hero_text['description_fr']); ?></textarea>

                <label for="description_ar"><?php echo $lang['description_ar'] ?? 'Description (Arabic)'; ?>:</label>
                <textarea name="description_ar" id="description_ar" class="description-field" required><?php echo htmlspecialchars($hero_text['description_ar']); ?></textarea>

                <?php if ($hero_text['updated_at']): ?>
                    <p class="updated-at"><?php echo $lang['updated_at'] ?? 'Updated At'; ?>: <?php echo htmlspecialchars($hero_text['updated_at']); ?></p>
                <?php endif; ?>

                <div class="button-group">
                    <button type="submit">
                        <i class="fas fa-save"></i> <?php echo $lang['save'] ?? 'Save'; ?>
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>