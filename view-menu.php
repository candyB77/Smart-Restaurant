<?php
require_once 'includes/auth.php';

// Require customer login
requireCustomerLogin();

$restaurantId = $_GET['id'] ?? null;

if (!$restaurantId) {
    header('Location: customer-dashboard.php');
    exit;
}

$error = '';
$restaurant = null;
$menu = [];

try {
    $db = getDbConnection();

    // Get restaurant details
    $stmt = $db->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurantId]);
    $restaurant = $stmt->fetch();

    if (!$restaurant) {
        $error = 'Restaurant not found.';
    } else {
        // Get menu categories
        $stmt = $db->prepare("SELECT * FROM menu_categories ORDER BY name");
        $stmt->execute();
        $categories = $stmt->fetchAll();

        // Get menu items for the restaurant
        $stmt = $db->prepare("SELECT * FROM menu_items WHERE restaurant_id = ? AND is_available = 1 ORDER BY category_id, name");
        $stmt->execute([$restaurantId]);
        $items = $stmt->fetchAll();

        // Group items by category
        foreach ($categories as $category) {
            $categoryItems = array_filter($items, function($item) use ($category) {
                return $item['category_id'] == $category['id'];
            });

            if (!empty($categoryItems)) {
                $menu[$category['name']] = $categoryItems;
            }
        }
        
        // Get restaurant ratings
        $stmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE restaurant_id = ?");
        $stmt->execute([$restaurantId]);
        $ratingData = $stmt->fetch();
        $avgRating = round($ratingData['avg_rating'] ?? 0, 1);
        $reviewCount = $ratingData['review_count'] ?? 0;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $restaurant ? htmlspecialchars($restaurant['name']) . ' - Menu' : 'View Menu'; ?> - FoodiFusion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* Menu Page Specific Styles */
        .menu-header {
            background-size: cover;
            background-position: center;
            color: white;
            padding: 60px 0;
            position: relative;
            border-radius: 10px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .menu-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7));
            z-index: 1;
        }
        
        .menu-header-content {
            position: relative;
            z-index: 2;
            padding: 0 30px;
        }
        
        .menu-header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .menu-header-details {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 15px;
        }
        
        .menu-header-detail {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .rating-stars {
            color: #FFD700;
            margin-right: 5px;
        }
        
        .category-tabs {
            display: flex;
            overflow-x: auto;
            gap: 10px;
            padding: 10px 0;
            margin-bottom: 20px;
            scrollbar-width: thin;
        }
        
        .category-tab {
            padding: 10px 20px;
            background-color: #f5f5f5;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s ease;
            border: none;
            color: #333;
        }
        
        .category-tab:hover, .category-tab.active {
            background-color: #4CAF50;
            color: white;
        }
        
        .menu-content {
            padding: 20px 0;
        }
        
        .menu-category {
            margin-bottom: 40px;
            scroll-margin-top: 100px;
        }
        
        .menu-category h2 {
            font-size: 2rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4CAF50;
            color: #333;
        }
        
        .menu-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .menu-item-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .menu-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .menu-item-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .menu-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .menu-item-card:hover .menu-item-image img {
            transform: scale(1.05);
        }
        
        .menu-item-image.no-image {
            background: linear-gradient(45deg, #f1f1f1, #e0e0e0);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .menu-item-image.no-image i {
            font-size: 3rem;
            color: #aaa;
        }
        
        .menu-item-content {
            padding: 20px;
        }
        
        .menu-item-content h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: #333;
        }
        
        .menu-item-description {
            color: #666;
            margin-bottom: 15px;
            font-size: 0.95rem;
            line-height: 1.5;
            height: 60px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .menu-item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .menu-item-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #4CAF50;
        }
        
        .add-to-cart {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .add-to-cart:hover {
            background-color: #388E3C;
            transform: translateY(-2px);
        }
        
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: #4CAF50;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
            transform: translateY(20px);
            z-index: 1000;
        }
        
        .back-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .back-to-top i {
            font-size: 1.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .menu-header {
                padding: 40px 0;
            }
            
            .menu-header h1 {
                font-size: 2.2rem;
            }
            
            .menu-items-grid {
                grid-template-columns: 1fr;
            }
            
            .menu-header-details {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <i class="fas fa-utensils"></i>
                <h1>FoodiFusion</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="customer-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="customer-orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a></li>
                    <li><a href="customer-profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <?php if ($error): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php echo $error; ?></p>
                </div>
            <?php elseif ($restaurant): ?>
                <!-- Restaurant Menu Header -->
                <div class="menu-header" style="background-image: url('assets/images/restaurant-bg.jpg');">
                    <div class="menu-header-content">
                        <a href="customer-dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Restaurants</a>
                        <h1><?php echo htmlspecialchars($restaurant['name']); ?></h1>
                        <p><?php echo htmlspecialchars($restaurant['cuisine_type'] ?? 'Various Cuisines'); ?></p>
                        
                        <div class="menu-header-details">
                            <div class="menu-header-detail">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($restaurant['address'] ?? 'Location not specified'); ?></span>
                            </div>
                            
                            <div class="menu-header-detail">
                                <div class="rating-stars">
                                    <?php 
                                    $fullStars = floor($avgRating);
                                    $halfStar = $avgRating - $fullStars >= 0.5;
                                    
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $fullStars) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($i == $fullStars + 1 && $halfStar) {
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <span><?php echo $avgRating; ?> (<?php echo $reviewCount; ?> reviews)</span>
                            </div>
                            
                            <div class="menu-header-detail">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($restaurant['phone'] ?? 'Phone not available'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($menu)): ?>
                    <div class="notification info">
                        <i class="fas fa-info-circle"></i>
                        <p>This restaurant has not added any menu items yet.</p>
                    </div>
                <?php else: ?>
                    <!-- Category Navigation Tabs -->
                    <div class="category-tabs">
                        <?php $index = 0; foreach (array_keys($menu) as $catName): ?>
                            <button class="category-tab <?php echo $index === 0 ? 'active' : ''; ?>" data-target="category-<?php echo $index; ?>">
                                <?php echo htmlspecialchars($catName); ?>
                            </button>
                        <?php $index++; endforeach; ?>
                    </div>
                    
                    <!-- Menu Content -->
                    <div class="menu-content">
                        <?php $index = 0; foreach ($menu as $categoryName => $items): ?>
                            <section id="category-<?php echo $index; ?>" class="menu-category">
                                <h2><?php echo htmlspecialchars($categoryName); ?></h2>
                                <div class="menu-items-grid">
                                    <?php foreach ($items as $item): ?>
                                        <div class="menu-item-card">
                                            <div class="menu-item-image <?php echo !$item['image_path'] ? 'no-image' : ''; ?>">
                                                <?php if ($item['image_path']): ?>
                                                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-utensils"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="menu-item-content">
                                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                                <div class="menu-item-description">
                                                    <?php echo !empty($item['description']) ? htmlspecialchars($item['description']) : 'No description available'; ?>
                                                </div>
                                                <div class="menu-item-footer">
                                                    <div class="menu-item-price">FCFA <?php echo number_format($item['price'], 2); ?></div>
                                                    <button class="add-to-cart" data-item-id="<?php echo $item['id']; ?>">
                                                        <i class="fas fa-plus"></i> Add
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php $index++; endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Back to top button -->
        <div class="back-to-top">
            <i class="fas fa-chevron-up"></i>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> FoodiFusion. All rights reserved.</p>
        </div>
    </footer>
    
    <script>
        // Category tabs functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.category-tab');
            const categories = document.querySelectorAll('.menu-category');
            
            // Hide all categories except the first one
            for (let i = 1; i < categories.length; i++) {
                categories[i].style.display = 'none';
            }
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Hide all categories
                    categories.forEach(cat => cat.style.display = 'none');
                    
                    // Show the selected category
                    const targetId = this.getAttribute('data-target');
                    document.getElementById(targetId).style.display = 'block';
                });
            });
            
            // Back to top functionality
            const backToTopBtn = document.querySelector('.back-to-top');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopBtn.classList.add('visible');
                } else {
                    backToTopBtn.classList.remove('visible');
                }
            });
            
            backToTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Add to cart functionality (placeholder)
            const addToCartBtns = document.querySelectorAll('.add-to-cart');
            
            addToCartBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-item-id');
                    // Here you would typically add the item to a cart via AJAX
                    // For now, just show a simple alert
                    alert('Item added to cart!'); 
                });
            });
        });
    </script>
</body>
</html>
