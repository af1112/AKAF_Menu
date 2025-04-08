<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Category</title>
    <style>
        body { font-family: 'Poppins', sans-serif; text-align: center; background: #f8f8f8; padding: 20px; }
        .form-container { max-width: 500px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        .form-group { margin: 15px 0; text-align: left; }
        label { font-weight: bold; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; margin-top: 5px; }
        .submit-btn { background: #c0392b; color: white; padding: 10px 20px; border: none; font-size: 1rem; border-radius: 5px; cursor: pointer; }
        .submit-btn:hover { background: #e74c3c; }
    </style>
</head>
<body>

    <h1>📌 Add a New Category Item</h1>

    <div class="form-container">
        <form action="add_Category.php" method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label for="description">Name:</label>
                <textarea name="Name" rows="3" required></textarea>
            </div>

            <div class="form-group">
                <label for="image">Upload Image:</label>
                <input type="file" name="image" accept="image/*" required>
            </div>

            <button type="submit" class="submit-btn">Add Category Item</button>
        </form>
    </div>

</body>
</html>
