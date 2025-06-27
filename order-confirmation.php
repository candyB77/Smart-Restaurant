<?php
/**
 * Order Confirmation Page
 * 
 * This page displays the confirmation details after a customer successfully places an order.
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
$order = null;
$orderItems = [];
$restaurant = null;
$messages = [];

// Check if order ID is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header('Location: customer-dashboard.php');
    exit;
}

$orderId = $_GET['order_id'];

// Get order information
try {
    $db = getDbConnection();
    
    // Get order details
    $stmt = $db->prepare("SELECT o.*, r.name as restaurant_name, r.phone as restaurant_phone, r.address as restaurant_address 
                         FROM orders o 
                         JOIN restaurants r ON o.restaurant_id = r.id 
                         WHERE o.id = ? AND o.customer_id = ?");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        // Order not found or doesn't belong to this customer
        header('Location: customer-dashboard.php');
        exit;
    }
    
    // Get order items
    $stmt = $db->prepare("SELECT oi.*, mi.name as item_name 
                         FROM order_items oi 
                         JOIN menu_items mi ON oi.menu_item_id = mi.id 
                         WHERE oi.order_id = ?");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $messages[] = ["type" => "error", "text" => "Database error: " . $e->getMessage()];
}

// Calculate estimated delivery time (simulated)
$estimatedDeliveryMinutes = rand(30, 60);
$estimatedDeliveryTime = date('H:i', strtotime('+' . $estimatedDeliveryMinutes . ' minutes'));

// Calculate subtotal
$subtotal = 0;
foreach ($orderItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

// Delivery fee (simulated)
$deliveryFee = 1000; // FCFA 1000

// Order total
$orderTotal = $order['total_amount'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - FoodiFusion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Order confirmation specific styles */
        .confirmation-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .confirmation-header i {
            font-size: 4rem;
            color: #4CAF50;
            margin-bottom: 15px;
            display: block;
        }
        
        .confirmation-header h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .confirmation-header p {
            color: #666;
            margin: 0;
        }
        
        .order-details {
            margin-bottom: 30px;
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .order-detail-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .order-detail-box h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1rem;
        }
        
        .order-detail-box p {
            margin: 5px 0;
            color: #666;
        }
        
        .order-detail-box strong {
            color: #333;
        }
        
        .order-items {
            margin-bottom: 30px;
        }
        
        .order-items table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .order-items th {
            text-align: left;
            padding: 10px;
            border-bottom: 2px solid #eee;
            color: #333;
        }
        
        .order-items td {
            padding: 15px 10px;
            border-bottom: 1px solid #eee;
        }
        
        .order-items .item-name {
            width: 50%;
        }
        
        .order-items .item-instructions {
            font-size: 0.8rem;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        
        .order-summary {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .order-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .order-total {
            font-weight: bold;
            font-size: 1.1rem;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .delivery-status {
            margin-top: 30px;
            text-align: center;
        }
        
        .status-tracker {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }
        
        .status-tracker::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 4px;
            background-color: #eee;
            z-index: 1;
        }
        
        .status-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 80px;
        }
        
        .status-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: #fff;
        }
        
        .status-text {
            font-size: 0.8rem;
            color: #666;
        }
        
        .status-active .status-icon {
            background-color: #4CAF50;
        }
        
        .status-active .status-text {
            color: #333;
            font-weight: bold;
        }
        
        .status-completed .status-icon {
            background-color: #4CAF50;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-buttons a {
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #45a049;
        }
        
        .btn-secondary {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background-color: #e9ecef;
        }
        
        @media (max-width: 768px) {
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .status-tracker {
                flex-wrap: wrap;
                justify-content: center;
                gap: 20px;
            }
            
            .status-tracker::before {
                display: none;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons a {
                width: 100%;
                text-align: center;
            }
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
            
            <div class="confirmation-container">
                <div class="confirmation-header">
                    <i class="fas fa-check-circle"></i>
                    <h2>Order Confirmed!</h2>
                    <p>Your order has been successfully placed and is being processed.</p>
                </div>
                
                <div class="order-details">
                    <h3>Order Details</h3>
                    <div class="order-details-grid">
                        <div class="order-detail-box">
                            <h4>Order Information</h4>
                            <p><strong>Order ID:</strong> #<?php echo $order['id']; ?></p>
                            <p><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></p>
                            <p><strong>Status:</strong> <?php echo $order['status']; ?></p>
                            <p><strong>Estimated Delivery:</strong> Today at <?php echo $estimatedDeliveryTime; ?></p>
                        </div>
                        
                        <div class="order-detail-box">
                            <h4>Restaurant</h4>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['restaurant_phone']); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($order['restaurant_address']); ?></p>
                        </div>
                        
                        <div class="order-detail-box">
                            <h4>Delivery Address</h4>
                            <p><?php echo htmlspecialchars($customerName); ?></p>
                            <p><?php 
                                // Get customer address from database
                                try {
                                    $stmt = $db->prepare("SELECT address, phone FROM customers WHERE id = ?");
                                    $stmt->execute([$customerId]);
                                    $customerDetails = $stmt->fetch();
                                    echo htmlspecialchars($customerDetails['address']);
                                    echo "<p><strong>Phone:</strong> " . htmlspecialchars($customerDetails['phone']) . "</p>";
                                } catch (PDOException $e) {
                                    echo "Address information unavailable";
                                }
                            ?></p>
                        </div>
                        
                        <div class="order-detail-box">
                            <h4>Payment</h4>
                            <p><strong>Method:</strong> Cash on Delivery</p>
                            <p><strong>Amount:</strong> FCFA <?php echo number_format($orderTotal, 0); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="order-items">
                    <h3>Order Items</h3>
                    <table>
                        <thead>
                            <tr>
                                <th class="item-name">Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                        <?php if (!empty($item['special_instructions'])): ?>
                                            <div class="item-instructions"><?php echo htmlspecialchars($item['special_instructions']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>FCFA <?php echo number_format($item['price'], 0); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>FCFA <?php echo number_format($item['price'] * $item['quantity'], 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="order-summary">
                        <div class="order-summary-row">
                            <span>Subtotal:</span>
                            <span>FCFA <?php echo number_format($subtotal, 0); ?></span>
                        </div>
                        <div class="order-summary-row">
                            <span>Delivery Fee:</span>
                            <span>FCFA <?php echo number_format($deliveryFee, 0); ?></span>
                        </div>
                        <div class="order-total order-summary-row">
                            <span>Total:</span>
                            <span>FCFA <?php echo number_format($orderTotal, 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="delivery-status">
                    <h3>Order Status</h3>
                    
                    <div class="status-tracker">
                        <div class="status-step status-active status-completed">
                            <div class="status-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="status-text">Order Placed</div>
                        </div>
                        
                        <div class="status-step <?php echo $order['status'] != 'Pending' ? 'status-active' : ''; ?>">
                            <div class="status-icon">
                                <?php if ($order['status'] != 'Pending'): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <i class="fas fa-utensils"></i>
                                <?php endif; ?>
                            </div>
                            <div class="status-text">Preparing</div>
                        </div>
                        
                        <div class="status-step <?php echo $order['status'] == 'Ready' || $order['status'] == 'Delivered' ? 'status-active' : ''; ?>">
                            <div class="status-icon">
                                <?php if ($order['status'] == 'Ready' || $order['status'] == 'Delivered'): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>
                            <div class="status-text">Ready</div>
                        </div>
                        
                        <div class="status-step <?php echo $order['status'] == 'Delivered' ? 'status-active' : ''; ?>">
                            <div class="status-icon">
                                <?php if ($order['status'] == 'Delivered'): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <i class="fas fa-truck"></i>
                                <?php endif; ?>
                            </div>
                            <div class="status-text">Delivered</div>
                        </div>
                    </div>
                    
                    <?php if ($order['status'] == 'Pending'): ?>
                        <p>Your order is being prepared by the restaurant.</p>
                    <?php elseif ($order['status'] == 'Preparing'): ?>
                        <p>Your order is being prepared by the restaurant.</p>
                    <?php elseif ($order['status'] == 'Ready'): ?>
                        <p>Your order is ready and out for delivery.</p>
                    <?php elseif ($order['status'] == 'Delivered'): ?>
                        <p>Your order has been delivered. Enjoy your meal!</p>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="customer-dashboard.php" class="btn-primary">Back to Dashboard</a>
                    <a href="order.php?restaurant_id=<?php echo $order['restaurant_id']; ?>" class="btn-secondary">Order Again</a>
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

    <script>
        // Refresh the page every 30 seconds to update order status
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>