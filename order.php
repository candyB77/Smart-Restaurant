<?php
/**
 * Order Page
 * 
 * This page allows customers to browse a restaurant's menu,
 * add items to cart, and place orders.
 */

// Include authentication functions and database connection
require_once 'includes/auth.php';
require_once 'config/database.php';

// Require customer login
requireCustomerLogin();

// Get customer information
$customerId = getCurrentUserId();
$customerName = $_SESSION['user_name'];

// Initialize variables
$restaurant = null;
$menuCategories = [];
$menuItems = [];
$cart = [];
$messages = [];

// Check if restaurant ID is provided
if (!isset($_GET['restaurant_id']) || empty($_GET['restaurant_id'])) {
    header('Location: customer-dashboard.php');
    exit;
}

$restaurantId = $_GET['restaurant_id'];

// Get restaurant information
try {
    $db = getDbConnection();
    
    // Get restaurant details
    $stmt = $db->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurantId]);
    $restaurant = $stmt->fetch();
    
    if (!$restaurant) {
        header('Location: customer-dashboard.php');
        exit;
    }
    
    // Get menu categories
    // Get menu categories that have items for this restaurant
    $stmt = $db->prepare("SELECT DISTINCT mc.* FROM menu_categories mc JOIN menu_items mi ON mc.id = mi.category_id WHERE mi.restaurant_id = ? ORDER BY mc.name");
    $stmt->execute([$restaurantId]);
    $menuCategories = $stmt->fetchAll();
    
    // Get menu items
    $stmt = $db->prepare("SELECT * FROM menu_items WHERE restaurant_id = ? AND is_available = 1 ORDER BY category_id, name");
    $stmt->execute([$restaurantId]);
    $menuItems = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $messages[] = ["type" => "error", "text" => "Database error: " . $e->getMessage()];
}

// Initialize or retrieve cart from session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add item to cart
    if (isset($_POST['add_to_cart'])) {
        $itemId = $_POST['item_id'];
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        $specialInstructions = isset($_POST['special_instructions']) ? trim($_POST['special_instructions']) : '';
        
        // Find the item in menu items
        $itemToAdd = null;
        foreach ($menuItems as $item) {
            if ($item['id'] == $itemId) {
                $itemToAdd = $item;
                break;
            }
        }
        
        if ($itemToAdd) {
            // Check if item already exists in cart
            $found = false;
            foreach ($_SESSION['cart'] as &$cartItem) {
                if ($cartItem['id'] == $itemId && $cartItem['special_instructions'] == $specialInstructions) {
                    // Update quantity
                    $cartItem['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                // Add new item to cart
                $_SESSION['cart'][] = [
                    'id' => $itemId,
                    'name' => $itemToAdd['name'],
                    'price' => $itemToAdd['price'],
                    'quantity' => $quantity,
                    'special_instructions' => $specialInstructions
                ];
            }
            
            $messages[] = ["type" => "success", "text" => "Item added to cart."];
        }
    }
    
    // Update cart item quantity
    if (isset($_POST['update_quantity'])) {
        $cartIndex = $_POST['cart_index'];
        $newQuantity = (int)$_POST['new_quantity'];
        
        if (isset($_SESSION['cart'][$cartIndex])) {
            if ($newQuantity > 0) {
                $_SESSION['cart'][$cartIndex]['quantity'] = $newQuantity;
                $messages[] = ["type" => "success", "text" => "Cart updated."];
            } else {
                // Remove item if quantity is 0
                array_splice($_SESSION['cart'], $cartIndex, 1);
                $messages[] = ["type" => "success", "text" => "Item removed from cart."];
            }
        }
    }
    
    // Remove item from cart
    if (isset($_POST['remove_item'])) {
        $cartIndex = $_POST['cart_index'];
        
        if (isset($_SESSION['cart'][$cartIndex])) {
            array_splice($_SESSION['cart'], $cartIndex, 1);
            $messages[] = ["type" => "success", "text" => "Item removed from cart."];
        }
    }
    
    // Clear cart
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        $messages[] = ["type" => "success", "text" => "Cart cleared."];
    }
    
    // Place order
    if (isset($_POST['place_order'])) {
        if (empty($_SESSION['cart'])) {
            $messages[] = ["type" => "error", "text" => "Your cart is empty."];
        } else {
            try {
                $db->beginTransaction();
                
                // Calculate total amount
                $totalAmount = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $totalAmount += $item['price'] * $item['quantity'];
                }
                
                // Add delivery fee (simulated)
                $deliveryFee = 1000; // FCFA 1000
                $totalAmount += $deliveryFee;
                
                // Handle file upload
                $paymentScreenshotPath = null;
                $specialInstructions = isset($_POST['order_instructions']) ? trim($_POST['order_instructions']) : '';

                // Use the verified payment screenshot
                if (!isset($_SESSION['verified_screenshot_path'])) {
                    throw new Exception("Payment screenshot not verified. Please upload and verify your screenshot.");
                }
                $verifiedScreenshotPath = $_SESSION['verified_screenshot_path'];

                // Move the verified screenshot to the final payments directory
                $finalUploadDir = __DIR__ . '/uploads/payments/';
                if (!is_dir($finalUploadDir)) {
                    mkdir($finalUploadDir, 0777, true);
                }
                $finalScreenshotPath = $finalUploadDir . basename($verifiedScreenshotPath);

                if (!rename($verifiedScreenshotPath, $finalScreenshotPath)) {
                    throw new Exception("Failed to process payment screenshot.");
                }
                $screenshotPath = $finalScreenshotPath;

                // Create order
                $stmt = $db->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, status, special_instructions, payment_screenshot_path, created_at) 
                                     VALUES (?, ?, ?, 'Pending', ?, ?, NOW())");
                $stmt->execute([
                    $customerId, 
                    $restaurantId, 
                    $totalAmount, 
                    $_POST['hidden_order_instructions'], 
                    $screenshotPath
                ]);

                // Clear the verified screenshot path from session
                unset($_SESSION['verified_screenshot_path']);
                $orderId = $db->lastInsertId();
                
                // Add order items
                $stmt = $db->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, special_instructions) 
                                     VALUES (?, ?, ?, ?, ?)");
                
                foreach ($_SESSION['cart'] as $item) {
                    $stmt->execute([
                        $orderId,
                        $item['id'],
                        $item['quantity'],
                        $item['price'],
                        $item['special_instructions']
                    ]);
                }
                
                $db->commit();
                
                // Clear cart after successful order
                $_SESSION['cart'] = [];
                
                // Redirect to order confirmation
                header("Location: order-confirmation.php?order_id=" . $orderId);
                exit;
                
            } catch (PDOException $e) {
                $db->rollBack();
                $messages[] = ["type" => "error", "text" => "Order failed: " . $e->getMessage()];
            }
        }
    }
}

// Get cart from session
$cart = $_SESSION['cart'];

// Calculate cart total
$cartTotal = 0;
foreach ($cart as $item) {
    $cartTotal += $item['price'] * $item['quantity'];
}

// Delivery fee (simulated)
$deliveryFee = empty($cart) ? 0 : 1000; // FCFA 1000
$orderTotal = $cartTotal + $deliveryFee;

// Organize menu items by category
$menuByCategory = [];
foreach ($menuCategories as $category) {
    $menuByCategory[$category['id']] = [
        'name' => $category['name'],
        'items' => []
    ];
}

foreach ($menuItems as $item) {
    if (isset($menuByCategory[$item['category_id']])) {
        $menuByCategory[$item['category_id']]['items'][] = $item;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($restaurant['name']); ?> - FoodiFusion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Order page specific styles */
        .restaurant-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .restaurant-info {
            flex: 1;
        }
        
        .restaurant-header h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .restaurant-meta {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .restaurant-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .menu-container {
            display: flex;
            gap: 30px;
        }
        
        .menu-categories {
            width: 200px;
            position: sticky;
            top: 20px;
            align-self: flex-start;
        }
        
        .menu-categories ul {
            list-style: none;
            padding: 0;
            margin: 0;
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .menu-categories li {
            padding: 0;
        }
        
        .menu-categories a {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        
        .menu-categories a:hover,
        .menu-categories a.active {
            background: #fff;
            border-left-color: #28a745;
            color: #28a745;
        }
        
        .menu-items {
            flex: 1;
        }
        
        .menu-category {
            margin-bottom: 30px;
        }
        
        .menu-category h3 {
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }
        
        .menu-item {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .menu-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .menu-item-image {
            width: 100%;
            height: 180px;
            overflow: hidden;
            background-color: #f0f0f0;
        }
        
        .menu-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .menu-item-details {
            padding: 15px;
        }
        
        .menu-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .menu-item-name {
            font-weight: bold;
            font-size: 1.1rem;
            color: #333;
        }
        
        .menu-item-price {
            font-weight: bold;
            color: #28a745;
        }
        
        .menu-item-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .menu-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .menu-item-actions input[type="number"] {
            width: 60px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .menu-item-actions button {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .menu-item-actions button:hover {
            background-color: #218838;
        }
        
        .special-instructions {
            margin-top: 10px;
        }
        
        .special-instructions textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 60px;
        }
        
        .cart-container {
            width: 350px;
            position: sticky;
            top: 20px;
            align-self: flex-start;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .cart-header h3 {
            margin: 0;
        }
        
        .cart-items {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .cart-item-instructions {
            font-size: 0.8rem;
            color: #666;
            font-style: italic;
        }
        
        .cart-item-price {
            text-align: right;
            font-weight: bold;
        }
        
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
        }
        
        .cart-item-actions input {
            width: 50px;
            padding: 3px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cart-item-actions button {
            background: none;
            border: none;
            color: #ff6b35;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .cart-summary {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .cart-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .cart-total {
            font-weight: bold;
            font-size: 1.1rem;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .order-instructions {
            margin: 15px 0;
        }
        
        .order-instructions textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 80px;
        }
        
        .place-order-btn {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .place-order-btn:hover {
            background-color: #45a049;
        }
        
        .place-order-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .empty-cart-message {
            text-align: center;
            padding: 20px 0;
            color: #666;
        }
        
        @media (max-width: 992px) {
            .menu-container {
                flex-direction: column;
            }
            
            .menu-categories {
                width: 100%;
                position: static;
                margin-bottom: 20px;
            }
            
            .menu-categories ul {
                display: flex;
                overflow-x: auto;
                white-space: nowrap;
                padding: 10px;
            }
            
            .menu-categories li {
                display: inline-block;
            }
            
            .menu-categories a {
                border-left: none;
                border-bottom: 3px solid transparent;
            }
            
            .menu-categories a:hover,
            .menu-categories a.active {
                border-left-color: transparent;
                border-bottom-color: #28a745;
            }
            
            .cart-container {
                width: 100%;
                position: static;
                margin-top: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .restaurant-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .restaurant-meta {
                margin-top: 10px;
                flex-wrap: wrap;
            }
            
            .menu-item {
                flex-direction: column;
            }
            
            .menu-item-image {
                width: 100%;
                height: 200px;
                margin-right: 0;
                margin-bottom: 15px;
            }
        }

        /* Payment Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.6);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto; 
            padding: 30px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            position: relative;
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .payment-method {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .payment-method img {
            width: 40px;
            height: 40px;
            margin-right: 15px;
        }

        .upload-area {
            margin-top: 20px;
            text-align: center;
        }

        .upload-area input[type="file"] {
            margin-bottom: 15px;
        }

        #confirm-payment-btn {
            width: 100%;
            padding: 12px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="container">
            <div class="logo">
                <i class="fas fa-utensils"></i>
                <h1>FoodiFusion</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="customer-dashboard.php">Dashboard</a></li>
                    <li><a href="customer-profile.php">My Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="dashboard">
        <div class="container">
            <!-- Display Messages -->
            <?php if (!empty($messages)): ?>
                <div class="messages">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['type']; ?>">
                            <?php echo $message['text']; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Restaurant Header -->
            <div class="restaurant-header">
                <div class="restaurant-info">
                    <h2><?php echo htmlspecialchars($restaurant['name']); ?></h2>
                    <div class="restaurant-meta">
                        <span><i class="fas fa-utensils"></i> <?php echo htmlspecialchars($restaurant['cuisine_type']); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($restaurant['address']); ?></span>
                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($restaurant['phone']); ?></span>
                    </div>
                </div>
                <div class="restaurant-rating">
                    <span class="rating">
                        <?php 
                        // Simulated rating
                        $rating = 4.5;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                echo '<i class="fas fa-star"></i>';
                            } elseif ($i - 0.5 <= $rating) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                        <?php echo $rating; ?>
                    </span>
                </div>
            </div>
            
            <!-- Menu and Cart -->
            <div class="menu-container">
                <!-- Menu Categories -->
                <div class="menu-categories">
                    <h3>Menu</h3>
                    <ul>
                        <?php foreach ($menuCategories as $index => $category): ?>
                            <li>
                                <a href="#category-<?php echo $category['id']; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Menu Items -->
                <div class="menu-items">
                    <?php foreach ($menuByCategory as $categoryId => $category): ?>
                        <div id="category-<?php echo $categoryId; ?>" class="menu-category">
                            <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                            
                            <?php if (empty($category['items'])): ?>
                                <p>No items available in this category.</p>
                            <?php else: ?>
                                <?php foreach ($category['items'] as $item): ?>
                                     <div class="menu-item">
                                         <div class="menu-item-image">
                                             <img src="assets/images/menu/<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                         </div>
                                         <div class="menu-item-details">
                                             <div class="menu-item-header">
                                                 <div class="menu-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                 <div class="menu-item-price">FCFA <?php echo number_format($item['price'], 0); ?></div>
                                             </div>
                                             <div class="menu-item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                             
                                             <form method="post" action="">
                                                 <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                 
                                                 <div class="menu-item-actions">
                                                     <input type="number" name="quantity" value="1" min="1" max="10">
                                                     <button type="submit" name="add_to_cart">Add to Cart</button>
                                                 </div>
                                                 
                                                 <div class="special-instructions">
                                                     <textarea name="special_instructions" placeholder="Special instructions (optional)"></textarea>
                                                 </div>
                                             </form>
                                         </div>
                                     </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Cart -->
                <div class="cart-container">
                    <div class="cart-header">
                        <h3>Your Cart</h3>
                        <?php if (!empty($cart)): ?>
                            <form method="post" action="">
                                <button type="submit" name="clear_cart" class="btn-text">Clear Cart</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($cart)): ?>
                        <div class="empty-cart-message">
                            <i class="fas fa-shopping-cart fa-3x"></i>
                            <p>Your cart is empty</p>
                        </div>
                    <?php else: ?>
                        <div class="cart-items">
                            <?php foreach ($cart as $index => $item): ?>
                                <div class="cart-item">
                                    <div class="cart-item-details">
                                        <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <?php if (!empty($item['special_instructions'])): ?>
                                            <div class="cart-item-instructions"><?php echo htmlspecialchars($item['special_instructions']); ?></div>
                                        <?php endif; ?>
                                        <div class="cart-item-actions">
                                            <form method="post" action="" class="update-quantity-form">
                                                <input type="hidden" name="cart_index" value="<?php echo $index; ?>">
                                                <input type="number" name="new_quantity" value="<?php echo $item['quantity']; ?>" min="1" max="10" onchange="this.form.submit()">
                                                <input type="hidden" name="update_quantity" value="1">
                                            </form>
                                            <form method="post" action="" class="remove-item-form">
                                                <input type="hidden" name="cart_index" value="<?php echo $index; ?>">
                                                <button type="submit" name="remove_item">Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="cart-item-price">
                                        FCFA <?php echo number_format($item['price'] * $item['quantity'], 0); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="cart-summary">
                            <div class="cart-row">
                                <span>Subtotal:</span>
                                <span>FCFA <?php echo number_format($cartTotal, 0); ?></span>
                            </div>
                            <div class="cart-row">
                                <span>Delivery Fee:</span>
                                <span>FCFA <?php echo number_format($deliveryFee, 0); ?></span>
                            </div>
                            <div class="cart-total cart-row">
                                <span>Total:</span>
                                <span>FCFA <?php echo number_format($orderTotal, 0); ?></span>
                            </div>
                        </div>
                        
                        <form method="post" action="">
                            <div class="order-instructions">
                                <label for="order-instructions">Delivery Instructions (Optional)</label>
                                <textarea id="order-instructions" name="order_instructions" placeholder="E.g., Apartment number, gate code, or special delivery instructions"></textarea>
                            </div>
                            
                            <button type="button" id="place-order-modal-btn" class="place-order-btn">Place Order</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; 2023 FoodiFusion. All rights reserved.</p>
        </div>
    </footer>

    <!-- Payment Modal -->
    <div id="payment-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Complete Your Payment</h3>
            <p>Please send the total amount to one of the following accounts and upload a screenshot of the payment.</p>
            
            <div class="payment-method">
                <i class="fas fa-money-bill-wave" style="color: #ffcc00; font-size: 36px; margin-right: 15px;"></i>
                <div>
                    <strong>MTN Money:</strong> 672777761
                </div>
            </div>
            
            <div class="payment-method">
                <i class="fas fa-money-bill-wave" style="color: #ff6600; font-size: 36px; margin-right: 15px;"></i>
                <div>
                    <strong>Orange Money:</strong> 698652503
                </div>
            </div>

            <form method="post" action="" enctype="multipart/form-data" class="upload-area">
                <!-- Pass original form data through hidden inputs -->
                <input type="hidden" name="order_instructions" id="hidden_order_instructions">

                <label for="payment_screenshot">Upload Payment Screenshot:</label>
                <input type="file" id="payment_screenshot" name="payment_screenshot" accept="image/*" required>
                
                <button type="submit" name="place_order" id="confirm-payment-btn" disabled>Confirm Payment</button>
                <small id="payment-status-message">Please upload a screenshot to enable the confirmation button.</small>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menu category navigation
            const categoryLinks = document.querySelectorAll('.menu-categories a');
            
            categoryLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    categoryLinks.forEach(l => l.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Scroll to category section
                    const targetId = this.getAttribute('href').substring(1);
                    const targetElement = document.getElementById(targetId);
                    
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 100,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            // Update active category based on scroll position
            window.addEventListener('scroll', function() {
                const categories = document.querySelectorAll('.menu-category');
                let currentCategory = null;
                
                categories.forEach(category => {
                    const rect = category.getBoundingClientRect();
                    
                    if (rect.top <= 150 && rect.bottom >= 150) {
                        currentCategory = category.id;
                    }
                });
                
                if (currentCategory) {
                    categoryLinks.forEach(link => {
                        const linkTarget = link.getAttribute('href').substring(1);
                        
                        if (linkTarget === currentCategory) {
                            link.classList.add('active');
                        } else {
                            link.classList.remove('active');
                        }
                    });
                }
            });
        });

        // Payment Modal Logic
        const modal = document.getElementById('payment-modal');
        const placeOrderBtn = document.getElementById('place-order-modal-btn');
        const closeBtn = document.querySelector('.close-button');
        const confirmPaymentBtn = document.getElementById('confirm-payment-btn');
        const screenshotInput = document.getElementById('payment_screenshot');
        const orderInstructionsTextarea = document.getElementById('order-instructions');
        const hiddenOrderInstructionsInput = document.getElementById('hidden_order_instructions');

        placeOrderBtn.onclick = function() {
            // Pass the delivery instructions to the modal form
            hiddenOrderInstructionsInput.value = orderInstructionsTextarea.value;
            modal.style.display = 'block';
        }

        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        screenshotInput.onchange = function() {
            const file = screenshotInput.files[0];
            if (!file) return;

            confirmPaymentBtn.disabled = true;
            const statusMessage = document.getElementById('payment-status-message');
            statusMessage.style.color = '#555';
            statusMessage.textContent = 'Analyzing screenshot...';

            const formData = new FormData();
            formData.append('payment_screenshot', file);

            fetch('verify-payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusMessage.style.color = 'green';
                    statusMessage.textContent = data.message;
                    confirmPaymentBtn.disabled = false;
                } else {
                    statusMessage.style.color = 'red';
                    statusMessage.textContent = data.message;
                    screenshotInput.value = ''; // Reset the file input
                }
            })
            .catch(error => {
                statusMessage.style.color = 'red';
                statusMessage.textContent = 'An error occurred. Please try again.';
                console.error('Error:', error);
            });
        };
    </script>
</body>
</html>