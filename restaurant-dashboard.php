<?php
/**
 * Restaurant Dashboard
 * 
 * This is the command center for restaurant owners to manage orders,
 * menu items, view statistics, and handle customer reviews.
 */

// Include authentication functions
require_once 'includes/auth.php';

// Require restaurant login
requireRestaurantLogin();

// Get restaurant information
$restaurantId = getCurrentUserId();
$restaurantName = $_SESSION['user_name'];

// Get restaurant data from database
try {
    $db = getDbConnection();
    
    // Get today's orders count
    $stmt = $db->prepare("SELECT COUNT(*) as order_count FROM orders 
                         WHERE restaurant_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$restaurantId]);
    $todayOrdersCount = $stmt->fetch()['order_count'] ?? 0;
    
    // Get today's revenue
    $stmt = $db->prepare("SELECT SUM(total_amount) as revenue FROM orders 
                         WHERE restaurant_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$restaurantId]);
    $todayRevenue = $stmt->fetch()['revenue'] ?? 0;
    
    // Get average order value
    $stmt = $db->prepare("SELECT AVG(total_amount) as avg_order FROM orders 
                         WHERE restaurant_id = ?");
    $stmt->execute([$restaurantId]);
    $avgOrderValue = $stmt->fetch()['avg_order'] ?? 0;
    
    // Get pending orders count
    $stmt = $db->prepare("SELECT COUNT(*) as pending_count FROM orders 
                         WHERE restaurant_id = ? AND status IN ('Pending', 'Preparing')");
    $stmt->execute([$restaurantId]);
    $pendingOrdersCount = $stmt->fetch()['pending_count'] ?? 0;
    
    // Get recent orders
    $stmt = $db->prepare("SELECT o.*, c.name as customer_name 
                         FROM orders o 
                         JOIN customers c ON o.customer_id = c.id 
                         WHERE o.restaurant_id = ? 
                         ORDER BY o.created_at DESC LIMIT 10");
    $stmt->execute([$restaurantId]);
    $recentOrders = $stmt->fetchAll();
    
    // Get menu items by category
    $stmt = $db->prepare("SELECT mi.*, mc.name as category_name 
                         FROM menu_items mi 
                         JOIN menu_categories mc ON mi.category_id = mc.id 
                         WHERE mi.restaurant_id = ? 
                         ORDER BY mc.name, mi.name");
    $stmt->execute([$restaurantId]);
    $allMenuItems = $stmt->fetchAll();
    
    // Organize menu items by category
    $menuItemsByCategory = [];
    foreach ($allMenuItems as $item) {
        $menuItemsByCategory[$item['category_name']][] = $item;
    }
    
    // Get all menu categories
    $stmt = $db->query("SELECT * FROM menu_categories ORDER BY name");
    $menuCategories = $stmt->fetchAll();

    // --- Analytics Data ---

    // Initialize arrays to prevent errors if queries fail
    $revenueTrendLabels = [];
    $revenueTrendData = [];
    $weeklySalesData = array_fill(0, 7, 0); // Monday to Sunday
    $popularItemsLabels = [];
    $popularItemsData = [];
    $ratingsData = array_fill(0, 5, 0); // 1 to 5 stars

    // 1. Revenue Trend (Last 6 Months)
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as revenue
        FROM orders
        WHERE restaurant_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute([$restaurantId]);
    $revenueTrend = $stmt->fetchAll();
    foreach ($revenueTrend as $row) {
        $revenueTrendLabels[] = date("F", strtotime($row['month'] . "-01"));
        $revenueTrendData[] = $row['revenue'];
    }

    // 2. Weekly Sales (Last 7 days)
    $stmt = $db->prepare("
        SELECT DAYOFWEEK(created_at) as day, SUM(total_amount) as sales
        FROM orders
        WHERE restaurant_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY day
    ");
    $stmt->execute([$restaurantId]);
    $weeklySales = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    // Map MySQL DAYOFWEEK (1=Sun, 2=Mon...) to Chart.js labels (0=Mon...)
    if (isset($weeklySales[1])) $weeklySalesData[6] = $weeklySales[1]; // Sunday
    if (isset($weeklySales[2])) $weeklySalesData[0] = $weeklySales[2]; // Monday
    if (isset($weeklySales[3])) $weeklySalesData[1] = $weeklySales[3]; // Tuesday
    if (isset($weeklySales[4])) $weeklySalesData[2] = $weeklySales[4]; // Wednesday
    if (isset($weeklySales[5])) $weeklySalesData[3] = $weeklySales[5]; // Thursday
    if (isset($weeklySales[6])) $weeklySalesData[4] = $weeklySales[6]; // Friday
    if (isset($weeklySales[7])) $weeklySalesData[5] = $weeklySales[7]; // Saturday

    // 3. Popular Menu Items
    $stmt = $db->prepare("
        SELECT mi.name, SUM(oi.quantity) as total_sold
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.restaurant_id = ?
        GROUP BY mi.id, mi.name
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute([$restaurantId]);
    $popularItems = $stmt->fetchAll();
    $totalSold = array_sum(array_column($popularItems, 'total_sold'));
    foreach ($popularItems as $item) {
        $popularItemsLabels[] = $item['name'];
        $popularItemsData[] = $totalSold > 0 ? round(($item['total_sold'] / $totalSold) * 100) : 0;
    }

    // 4. Customer Ratings
    $stmt = $db->prepare("
        SELECT rating, COUNT(*) as count
        FROM reviews
        WHERE restaurant_id = ?
        GROUP BY rating
    ");
    $stmt->execute([$restaurantId]);
    $ratings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    for ($i = 1; $i <= 5; $i++) {
        if (isset($ratings[$i])) {
            $ratingsData[$i - 1] = $ratings[$i];
        }
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Function to get order items
function getOrderItems($orderId, $db) {
    $stmt = $db->prepare("SELECT oi.*, mi.name as item_name 
                         FROM order_items oi 
                         JOIN menu_items mi ON oi.menu_item_id = mi.id 
                         WHERE oi.order_id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

// Handle order status update
if (isset($_POST['update_order_status'])) {
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['new_status'];
    
    try {
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$newStatus, $orderId, $restaurantId]);
        
        // Redirect to avoid form resubmission
        header("Location: restaurant-dashboard.php");
        exit;
    } catch (PDOException $e) {
        $statusUpdateError = "Error updating order status: " . $e->getMessage();
    }
}

// Handle menu item deletion
if (isset($_POST['delete_item'])) {
    $itemId = $_POST['item_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$itemId, $restaurantId]);
        
        // Redirect to avoid form resubmission and stay on the menu section
        header("Location: restaurant-dashboard.php#menu-management");
        exit;
    } catch (PDOException $e) {
        $itemDeleteError = "Error deleting item: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Dashboard - FoodiFusion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <li><a href="restaurant-dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="restaurant-settings.php">Settings</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Dashboard -->
    <main class="dashboard restaurant-dashboard">
        <div class="container">
            <?php if (isset($_GET['settings_updated']) && $_GET['settings_updated'] === 'true'): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i>
                <p>Settings updated successfully!</p>
            </div>
            <script>
                // Auto-hide the success message after 5 seconds
                setTimeout(function() {
                    const notification = document.querySelector('.notification.success');
                    if (notification) {
                        notification.style.opacity = '0';
                        setTimeout(function() {
                            notification.style.display = 'none';
                        }, 500);
                    }
                }, 5000);
            </script>
            <?php endif; ?>
            
            <div class="welcome-section">
                <h1>Welcome, <?php echo htmlspecialchars($restaurantName); ?>!</h1>
                <p>Manage your restaurant, orders, and menu items</p>
            </div>

            <!-- Statistics Section -->
            <div class="stats-cards">
                <div class="stat-card">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Today's Orders</h3>
                    <div class="stat-value"><?php echo $todayOrdersCount; ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>Today's Revenue</h3>
                    <div class="stat-value currency"><?php echo number_format($todayRevenue, 0); ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Average Order Value</h3>
                    <div class="stat-value currency"><?php echo number_format($avgOrderValue, 0); ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-hourglass-half"></i>
                    <h3>Pending Orders</h3>
                    <div class="stat-value"><?php echo $pendingOrdersCount; ?></div>
                </div>
            </div>

            <!-- Order Management Section -->
            <section class="orders-section">
                <h2><i class="fas fa-clipboard-list"></i> Order Management</h2>
                
                <?php if (empty($recentOrders)): ?>
                    <p>No orders found.</p>
                <?php else: ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <?php $orderItems = getOrderItems($order['id'], $db); ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td>
                                        <?php foreach ($orderItems as $item): ?>
                                            <?php echo htmlspecialchars($item['item_name']); ?> (x<?php echo $item['quantity']; ?>)<br>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>FCFA <?php echo number_format($order['total_amount'], 0); ?></td>
                                    <td>
                                        <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('H:i', strtotime($order['created_at'])); ?></td>
                                    <td class="order-actions">
                                        <?php if ($order['status'] === 'Pending'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="new_status" value="Preparing">
                                                <button type="submit" name="update_order_status" class="btn-primary">Start Preparing</button>
                                            </form>
                                        <?php elseif ($order['status'] === 'Preparing'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="new_status" value="Ready">
                                                <button type="submit" name="update_order_status" class="btn-primary">Mark as Ready</button>
                                            </form>
                                        <?php elseif ($order['status'] === 'Ready'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="new_status" value="Delivered">
                                                <button type="submit" name="update_order_status" class="btn-primary">Mark as Delivered</button>
                                            </form>
                                        <?php else: ?>
                                            <span>Completed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <!-- Menu Management Section -->
            <section id="menu-management" class="menu-section">
                <?php if (isset($itemDeleteError)): ?>
                    <div class="error-message" style="margin-bottom: 15px; background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px;"><?php echo htmlspecialchars($itemDeleteError); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['item_added']) && $_GET['item_added'] === 'true'): ?>
                    <div class="success-message">
                        <p>New menu item added successfully!</p>
                    </div>
                <?php endif; ?>
                <h2><i class="fas fa-utensils"></i> Menu Management</h2>
                
                <!-- Category Tabs -->
                <div class="menu-categories">
                    <?php foreach ($menuCategories as $index => $category): ?>
                        <div class="category-tab <?php echo $index === 0 ? 'active' : ''; ?>" data-category="<?php echo htmlspecialchars($category['name']); ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Menu Items by Category -->
                <?php foreach ($menuCategories as $index => $category): ?>
                    <div class="menu-items-container" id="category-<?php echo htmlspecialchars($category['name']); ?>" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>">
                        <div class="menu-items">
                            <?php if (isset($menuItemsByCategory[$category['name']])): ?>
                                <?php foreach ($menuItemsByCategory[$category['name']] as $item): ?>
                                    <div class="menu-item">
                                        <div class="menu-item-image" style="background-image: url('<?php echo !empty($item['image_path']) ? htmlspecialchars($item['image_path']) : 'assets/images/default-food.jpg'; ?>');"></div>
                                        <div class="menu-item-details">
                                            <div class="menu-item-header">
                                                <div class="menu-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div class="menu-item-price"><?php echo number_format($item['price'], 0); ?></div>
                                            </div>
                                            <div class="menu-item-description">
                                                <?php echo htmlspecialchars($item['description'] ?? 'No description available.'); ?>
                                            </div>
                                            <div class="menu-item-actions">
                                                <div class="availability-toggle">
                                                    <span>Available</span>
                                                    <label class="toggle-switch">
                                                        <input type="checkbox" <?php echo $item['is_available'] ? 'checked' : ''; ?> data-item-id="<?php echo $item['id']; ?>">
                                                        <span class="toggle-slider"></span>
                                                    </label>
                                                </div>
                                                <div class="menu-item-buttons">
                                                    <a href="edit-menu-item.php?id=<?php echo $item['id']; ?>" class="btn-secondary">Edit</a>
                                                    <form method="post" style="display: inline-block; margin-left: 5px;">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" name="delete_item" class="btn-danger" onclick="return confirm('Are you sure you want to delete this item?');">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- Add New Item Button -->
                            <div class="menu-item">
                                <a href="add-menu-item.php?category=<?php echo $category['id']; ?>" class="add-item-btn">
                                    <i class="fas fa-plus"></i> Add New Item
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <!-- Analytics Section -->
            <section class="analytics-section">
                <h2><i class="fas fa-chart-bar"></i> Analytics Dashboard</h2>

                <?php if ($todayOrdersCount == 0): ?>
                    <div class="no-data-message">
                        <p>No analytics data available yet. Start taking orders to see your restaurant's performance.</p>
                    </div>
                <?php else: ?>
                
                <div class="analytics-grid">
                    <!-- Revenue Trend Chart -->
                    <div class="chart-container">
                        <h3>Revenue Trend (Last 6 Months)</h3>
                        <canvas id="revenueChart"></canvas>
                    </div>
                    
                    <!-- Weekly Sales Chart -->
                    <div class="chart-container">
                        <h3>Weekly Sales</h3>
                        <canvas id="weeklySalesChart"></canvas>
                    </div>
                    
                    <!-- Popular Items Chart -->
                    <div class="chart-container">
                        <h3>Popular Menu Items</h3>
                        <canvas id="popularItemsChart"></canvas>
                    </div>
                    
                    <!-- Customer Ratings Chart -->
                    <div class="chart-container">
                        <h3>Customer Ratings</h3>
                        <canvas id="ratingsChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; 2025 FoodiFusion. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Category Tab Switching
        document.addEventListener('DOMContentLoaded', function() {
            const categoryTabs = document.querySelectorAll('.category-tab');
            
            categoryTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    categoryTabs.forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Hide all menu item containers
                    const menuContainers = document.querySelectorAll('.menu-items-container');
                    menuContainers.forEach(container => container.style.display = 'none');
                    
                    // Show the selected category's menu items
                    const categoryName = this.getAttribute('data-category');
                    document.getElementById('category-' + categoryName).style.display = 'block';
                });
            });
            
            // Toggle menu item availability
            const availabilityToggles = document.querySelectorAll('.availability-toggle input');
            availabilityToggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const itemId = this.getAttribute('data-item-id');
                    const isAvailable = this.checked;
                    
                    // In a real app, this would be an AJAX request to update the database
                    console.log(`Updating item ${itemId} availability to ${isAvailable}`);
                });
            });
            

            
            // Revenue Trend Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($revenueTrendLabels); ?>,
                    datasets: [{
                        label: 'Revenue (FCFA)',
                        data: <?php echo json_encode($revenueTrendData); ?>,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'FCFA ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Weekly Sales Chart
            const weeklyCtx = document.getElementById('weeklySalesChart').getContext('2d');
            const weeklyChart = new Chart(weeklyCtx, {
                type: 'bar',
                data: {
                    labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                    datasets: [{
                        label: 'Sales (FCFA)',
                        data: <?php echo json_encode($weeklySalesData); ?>,
                        backgroundColor: '#4CAF50'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'FCFA ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Popular Items Chart
            const popularCtx = document.getElementById('popularItemsChart').getContext('2d');
            const popularChart = new Chart(popularCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($popularItemsLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($popularItemsData); ?>,
                        backgroundColor: [
                            '#4CAF50',
                            '#FF9800',
                            '#2196F3',
                            '#F44336',
                            '#9C27B0'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw + '%';
                                }
                            }
                        }
                    }
                }
            });
            
            // Ratings Chart
            const ratingsCtx = document.getElementById('ratingsChart').getContext('2d');
            const ratingsChart = new Chart(ratingsCtx, {
                type: 'bar',
                data: {
                    labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                    datasets: [{
                        label: 'Number of Ratings',
                        data: <?php echo json_encode($ratingsData); ?>,
                        backgroundColor: [
                            '#F44336',
                            '#FF9800',
                            '#FFEB3B',
                            '#8BC34A',
                            '#4CAF50'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>