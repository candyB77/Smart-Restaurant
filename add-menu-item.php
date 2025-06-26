<?php
require_once 'includes/auth.php';

// Require restaurant login
requireRestaurantLogin();

$restaurantId = getCurrentUserId();
$error = '';
$success = '';

// Fetch menu categories
try {
    $db = getDbConnection();
    $stmt = $db->query("SELECT * FROM menu_categories ORDER BY name");
    $menuCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $categoryId = trim($_POST['category_id']);
    $imagePath = '';

    if (empty($name) || empty($price) || empty($categoryId)) {
        $error = 'Name, price, and category are required.';
    } else {
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'assets/images/menu/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $imageName = uniqid() . '-' . basename($_FILES['image']['name']);
            $imagePath = $uploadDir . $imageName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
                $error = 'Failed to upload image.';
                $imagePath = '';
            }
        }

        if (empty($error)) {
            try {
                $stmt = $db->prepare("INSERT INTO menu_items (restaurant_id, category_id, name, description, price, image_path) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$restaurantId, $categoryId, $name, $description, $price, $imagePath]);
                header('Location: restaurant-dashboard.php?item_added=true');
                exit;
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
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
    <title>Add Menu Item - FoodiFusion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <header>
        <!-- Header content -->
    </header>

    <main class="dashboard">
        <div class="container">
            <h2>Add New Menu Item</h2>

            <?php if ($error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>

            <?php if ($success): ?>
                <p class="success"><?php echo $success; ?></p>
            <?php endif; ?>

            <form action="add-menu-item.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Item Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="number" id="price" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select a category</option>
                        <?php foreach ($menuCategories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="image">Image</label>
                    <input type="file" id="image" name="image">
                </div>
                <button type="submit" class="btn-primary">Add Item</button>
            </form>
        </div>
    </main>

    <footer>
        <!-- Footer content -->
    </footer>
</body>
</html>
