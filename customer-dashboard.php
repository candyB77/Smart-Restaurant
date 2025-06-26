<?php
/**
 * Customer Dashboard
 * 
 * This is the main interface for customers to discover and order food
 * with AI-powered recommendations based on preferences and order history.
 */

// Include authentication functions
require_once 'includes/auth.php';
require_once 'includes/ai_recommendation_service.php';

// Require customer login
requireCustomerLogin();

// Get customer information
$customerId = getCurrentUserId();
$customerName = $_SESSION['user_name'];

// Get customer preferences and allergies
try {
    $db = getDbConnection();
    
    // Get customer preferences
    $stmt = $db->prepare("SELECT * FROM customer_preferences WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $preferences = $stmt->fetch();
    
    // Get customer allergies
    $stmt = $db->prepare("SELECT * FROM customer_allergies WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $allergies = $stmt->fetchAll();
    
    // Get all restaurants
    $stmt = $db->query("SELECT id, name, cuisine_type, rating, address FROM restaurants");
    $allRestaurants = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get recommendations
$recommendations = [];
if (isset($_GET['run_ai']) && !empty($allRestaurants)) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    if (!empty($_ENV['OPENROUTER_API_KEY'])) {
        $recommendations = getOpenRouterRecommendations($preferences ?? null, $allergies ?? [], $allRestaurants);
    }
}

// Handle cuisine filter
$filteredRestaurants = $allRestaurants;
$selectedCuisine = $_GET['cuisine'] ?? '';

if (!empty($selectedCuisine)) {
    $filteredRestaurants = array_filter($allRestaurants, function($restaurant) use ($selectedCuisine) {
        return strtolower($restaurant['cuisine_type']) === strtolower($selectedCuisine);
    });
}

// Get unique cuisine types for filter
$cuisineTypes = array_unique(array_column($allRestaurants, 'cuisine_type'));
sort($cuisineTypes);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - FoodiFusion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                    <li><a href="customer-dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="customer-profile.php">My Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Dashboard -->
    <main class="dashboard customer-dashboard">
        <div class="container">
            <div class="welcome-section">
                <h1>Welcome, <?php echo htmlspecialchars($customerName); ?>!</h1>
                <p>Discover restaurants tailored to your preferences</p>
            </div>

            <!-- AI Recommendations Section -->
            <section class="recommendations-section">
                <h2><i class="fas fa-brain"></i> AI-Powered Recommendations</h2>
                
                <?php 
                    // Handle AI Recommendations display
                    $apiKeyExists = !empty($_ENV['OPENROUTER_API_KEY']);
                    $runAi = isset($_GET['run_ai']);

                    if ($runAi && !$apiKeyExists): ?>
                        <div class="recommendation-placeholder">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>AI recommendations are not configured. The API key is missing.</p>
                            <span>Please add your <code>OPENROUTER_API_KEY</code> to the <code>.env</code> file.</span>
                        </div>
                    <?php elseif ($runAi && empty($recommendations)): ?>
                        <div class="recommendation-placeholder">
                            <i class="fas fa-sad-tear"></i>
                            <p>No matches found for your current preferences.</p>
                            <span>Try adjusting your profile to discover new recommendations.</span>
                            <a href="customer-profile.php" class="btn-secondary">Update Preferences</a>
                            <a href="?run_ai=true" class="btn-primary" style="margin-left:10px;">Refresh Recommendations</a>
                        </div>
                    <?php elseif (!empty($recommendations)): ?>
                        <div style="text-align:center; margin-bottom:20px;">
                            <a href="?run_ai=true" class="btn-primary">Refresh Recommendations</a>
                        </div>
                        <div class="recommendation-cards">
                            <?php foreach ($recommendations as $recommendation): ?>
                                <div class="recommendation-card">
                                    <div class="recommendation-header">
                                        <h3><?php echo htmlspecialchars($recommendation['restaurant']['name']); ?></h3>
                                        <div class="confidence-score"><?php echo $recommendation['score']; ?>%</div>
                                    </div>
                                    <div class="recommendation-details">
                                        <p><strong>Cuisine:</strong> <?php echo htmlspecialchars($recommendation['restaurant']['cuisine_type']); ?></p>
                                        <p><strong>Rating:</strong> <?php echo number_format($recommendation['restaurant']['rating'], 1); ?> <i class="fas fa-star"></i></p>
                                        <p><strong>Estimated Delivery:</strong> <?php echo $recommendation['estimated_delivery']; ?></p>
                                    </div>
                                    <div class="matching-factors">
                                        <?php foreach ($recommendation['matching_factors'] as $factor): ?>
                                            <span class="factor-tag"><?php echo htmlspecialchars($factor); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="recommendation-actions">
                                        <a href="view-menu.php?id=<?php echo $recommendation['restaurant']['id']; ?>" class="btn-secondary">View Menu</a>
                                        <a href="order.php?restaurant_id=<?php echo $recommendation['restaurant']['id']; ?>" class="btn-primary">Order Now</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="recommendation-placeholder interactive">
                            <i class="fas fa-magic"></i>
                            <p>Ready to discover your next favorite meal?</p>
                            <span>Click the button below and let our AI find the perfect restaurant for you based on your preferences and allergies.</span>
                            <a href="?run_ai=true" class="btn-primary">Find My Match</a>
                        </div>
                    <?php endif; ?>
            </section>

            <!-- Restaurant Discovery Section -->
            <section class="discovery-section">
                <h2><i class="fas fa-search"></i> Discover Restaurants</h2>
                
                <!-- Filter Options -->
                <div class="filter-options">
                    <form action="" method="get">
                        <div class="filter-group">
                            <label for="cuisine">Filter by Cuisine:</label>
                            <select name="cuisine" id="cuisine" onchange="this.form.submit()">
                                <option value="">All Cuisines</option>
                                <?php foreach ($cuisineTypes as $cuisine): ?>
                                    <option value="<?php echo htmlspecialchars($cuisine); ?>" <?php echo $selectedCuisine === $cuisine ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cuisine); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                
                <!-- Restaurant Grid -->
                <div class="restaurant-grid">
                    <?php if (empty($filteredRestaurants)): ?>
                        <p>No restaurants found matching your criteria.</p>
                    <?php else: ?>
                        <?php foreach ($filteredRestaurants as $restaurant): ?>
                            <div class="restaurant-card">
                                <h3><?php echo htmlspecialchars($restaurant['name']); ?></h3>
                                <p><strong>Cuisine:</strong> <?php echo htmlspecialchars($restaurant['cuisine_type']); ?></p>
                                <p><strong>Rating:</strong> <?php echo number_format($restaurant['rating'], 1); ?> <i class="fas fa-star"></i></p>
                                <p><strong>Estimated Delivery:</strong> <?php echo rand(15, 45); ?> mins</p>
                                <div class="restaurant-actions">
                                    <a href="view-menu.php?id=<?php echo $restaurant['id']; ?>" class="btn-secondary">View Menu</a>
                                    <a href="order.php?restaurant_id=<?php echo $restaurant['id']; ?>" class="btn-primary">Order Now</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
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
        // Simulate AI loading (for demo purposes)
        document.addEventListener('DOMContentLoaded', function() {
            const aiLoading = document.getElementById('ai-loading');
            const noRecommendations = document.getElementById('no-recommendations');
            
            if (aiLoading) {
                setTimeout(function() {
                    aiLoading.style.display = 'none';
                    if (noRecommendations) {
                        noRecommendations.style.display = 'block';
                    }
                }, 2000);
            }
        });
    </script>
</body>
</html>