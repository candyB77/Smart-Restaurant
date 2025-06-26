<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

requireRestaurantLogin();

$restaurantId = getCurrentUserId();
$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($itemId === 0) {
    header("Location: restaurant-dashboard.php");
    exit;
}

$db = getDbConnection();
$error = '';
$success = '';

// Fetch the menu item and verify it belongs to the restaurant
$stmt = $db->prepare("SELECT * FROM menu_items WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$itemId, $restaurantId]);
$item = $stmt->fetch();

if (!$item) {
    // Item not found or doesn't belong to the user
    header("Location: restaurant-dashboard.php");
    exit;
}

// Fetch categories for the dropdown
$categoriesStmt = $db->query("SELECT * FROM menu_categories ORDER BY name");
$categories = $categoriesStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $categoryId = (int)$_POST['category_id'];
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;
    $imagePath = $item['image_path']; // Keep old image by default

    if (empty($name) || $price === false || empty($categoryId)) {
        $error = 'Please fill in all required fields (Name, Price, Category).';
    } else {
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $targetDir = 'assets/images/menu/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetFilePath = $targetDir . $fileName;
            $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

            $allowTypes = ['jpg', 'png', 'jpeg', 'gif'];
            if (in_array(strtolower($fileType), $allowTypes)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                    // Delete old image if it exists and is not a default image
                    if (!empty($imagePath) && file_exists($imagePath) && strpos($imagePath, 'default') === false) {
                        unlink($imagePath);
                    }
                    $imagePath = $targetFilePath;
                } else {
                    $error = 'Sorry, there was an error uploading your file.';
                }
            } else {
                $error = 'Sorry, only JPG, JPEG, PNG, & GIF files are allowed.';
            }
        }

        if (empty($error)) {
            try {
                $updateStmt = $db->prepare(
                    "UPDATE menu_items SET name = ?, description = ?, price = ?, category_id = ?, is_available = ?, image_path = ? WHERE id = ? AND restaurant_id = ?"
                );
                $updateStmt->execute([$name, $description, $price, $categoryId, $isAvailable, $imagePath, $itemId, $restaurantId]);
                $success = 'Menu item updated successfully!';
                
                // Refresh item data to show updated values in the form
                $stmt->execute([$itemId, $restaurantId]);
                $item = $stmt->fetch();

                header("Location: restaurant-dashboard.php#menu-management");
                exit;

            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu Item - FoodiFusion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .edit-form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .edit-form-container h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-group .current-image {
            display: block;
            max-width: 150px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="edit-form-container">
        <h1>Edit Menu Item</h1>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="edit-menu-item.php?id=<?php echo $itemId; ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Item Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($item['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="price">Price (FCFA)</label>
                <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($item['price']); ?>" required step="any">
            </div>

            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo ($item['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="image">Item Image</label>
                <input type="file" id="image" name="image">
                <?php if (!empty($item['image_path'])): ?>
                    <p>Current image:</p>
                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Current Image" class="current-image">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="is_available">Availability</label>
                <input type="checkbox" id="is_available" name="is_available" value="1" <?php echo $item['is_available'] ? 'checked' : ''; ?>>
                <label for="is_available">Is this item currently available?</label>
            </div>

            <div class="form-actions">
                <a href="restaurant-dashboard.php#menu-management" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</body>
</html>
