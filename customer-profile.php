<?php
/**
 * Customer Profile
 * 
 * This page allows customers to manage their profile information,
 * dietary preferences, and allergies which are used by the AI recommendation system.
 */

// Include authentication functions
require_once 'includes/auth.php';
require_once 'includes/validation.php';

// Require customer login
requireCustomerLogin();

// Get customer information
$customerId = getCurrentUserId();
$customerName = $_SESSION['user_name'];

// Initialize messages array
$messages = [];

// Get customer data from database
try {
    $db = getDbConnection();
    
    // Get customer details
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    // Get customer preferences
    $stmt = $db->prepare("SELECT * FROM customer_preferences WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $preferences = $stmt->fetch();
    
    // If no preferences exist, create default preferences
    if (!$preferences) {
        $stmt = $db->prepare("INSERT INTO customer_preferences (customer_id) VALUES (?)");
        $stmt->execute([$customerId]);
        
        // Get the newly created preferences
        $stmt = $db->prepare("SELECT * FROM customer_preferences WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $preferences = $stmt->fetch();
    }
    
    // Get customer allergies
    $stmt = $db->prepare("SELECT * FROM customer_allergies WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $allergies = $stmt->fetchAll();
    
    // Get order history
    $stmt = $db->prepare("SELECT o.*, r.name as restaurant_name 
                         FROM orders o 
                         JOIN restaurants r ON o.restaurant_id = r.id 
                         WHERE o.customer_id = ? 
                         ORDER BY o.created_at DESC LIMIT 10");
    $stmt->execute([$customerId]);
    $orderHistory = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $messages[] = ["type" => "error", "text" => "Database error: " . $e->getMessage()];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update personal information
    if (isset($_POST['update_personal'])) {
                $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $address = sanitize_input($_POST['address']);
        
        // Validate inputs
        if (empty($name) || empty($email) || empty($phone) || empty($address)) {
            $messages[] = ["type" => "error", "text" => "All fields are required."];
        } elseif (!validate_email($email)) {
            $messages[] = ["type" => "error", "text" => "Invalid email format."];
        } elseif (!validate_phone($phone)) {
            $messages[] = ["type" => "error", "text" => "Invalid phone number. Must be in the format +237XXXXXXXXX."];
        } else {
            try {
                $stmt = $db->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                $result = $stmt->execute([$name, $email, $phone, $address, $customerId]);
                
                if ($result) {
                    $_SESSION['user_name'] = $name; // Update session name
                    $messages[] = ["type" => "success", "text" => "Personal information updated successfully."];
                    
                    // Refresh customer data
                    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
                    $stmt->execute([$customerId]);
                    $customer = $stmt->fetch();
                } else {
                    $messages[] = ["type" => "error", "text" => "Failed to update personal information."];
                }
            } catch (PDOException $e) {
                $messages[] = ["type" => "error", "text" => "Database error: " . $e->getMessage()];
            }
        }
    }
    
    // Update dietary preferences
    if (isset($_POST['update_preferences'])) {
        $dietaryPreference = $_POST['dietary_preference'];
        $cuisinePreference = isset($_POST['cuisine_preference']) ? implode(',', $_POST['cuisine_preference']) : '';
        $spiceTolerance = $_POST['spice_tolerance'];
        
        try {
            $stmt = $db->prepare("UPDATE customer_preferences 
                                 SET dietary_preference = ?, cuisine_preference = ?, spice_tolerance = ? 
                                 WHERE customer_id = ?");
            $result = $stmt->execute([$dietaryPreference, $cuisinePreference, $spiceTolerance, $customerId]);
            
            if ($result) {
                $messages[] = ["type" => "success", "text" => "Dietary preferences updated successfully."];
                
                // Refresh preferences data
                $stmt = $db->prepare("SELECT * FROM customer_preferences WHERE customer_id = ?");
                $stmt->execute([$customerId]);
                $preferences = $stmt->fetch();
            } else {
                $messages[] = ["type" => "error", "text" => "Failed to update dietary preferences."];
            }
        } catch (PDOException $e) {
            $messages[] = ["type" => "error", "text" => "Database error: " . $e->getMessage()];
        }
    }
    
    // Add new allergy
    if (isset($_POST['add_allergy'])) {
        $allergyName = trim($_POST['allergy_name']);
        $allergySeverity = $_POST['allergy_severity'];
        
        if (empty($allergyName)) {
            $messages[] = ["type" => "error", "text" => "Allergy name is required."];
        } else {
            try {
                // Check if allergy already exists
                $stmt = $db->prepare("SELECT id FROM customer_allergies WHERE customer_id = ? AND allergy_name = ?");
                $stmt->execute([$customerId, $allergyName]);
                
                if ($stmt->rowCount() > 0) {
                    $messages[] = ["type" => "error", "text" => "This allergy is already in your list."];
                } else {
                    $stmt = $db->prepare("INSERT INTO customer_allergies (customer_id, allergy_name, severity) VALUES (?, ?, ?)");
                    $result = $stmt->execute([$customerId, $allergyName, $allergySeverity]);
                    
                    if ($result) {
                        $messages[] = ["type" => "success", "text" => "Allergy added successfully."];
                        
                        // Refresh allergies data
                        $stmt = $db->prepare("SELECT * FROM customer_allergies WHERE customer_id = ?");
                        $stmt->execute([$customerId]);
                        $allergies = $stmt->fetchAll();
                    } else {
                        $messages[] = ["type" => "error", "text" => "Failed to add allergy."];
                    }
                }
            } catch (PDOException $e) {
                $messages[] = ["type" => "error", "text" => "Database error: " . $e->getMessage()];
            }
        }
    }
    
    // Remove allergy
    if (isset($_POST['remove_allergy'])) {
        $allergyId = $_POST['allergy_id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM customer_allergies WHERE id = ? AND customer_id = ?");
            $result = $stmt->execute([$allergyId, $customerId]);
            
            if ($result) {
                $messages[] = ["type" => "success", "text" => "Allergy removed successfully."];
                
                // Refresh allergies data
                $stmt = $db->prepare("SELECT * FROM customer_allergies WHERE customer_id = ?");
                $stmt->execute([$customerId]);
                $allergies = $stmt->fetchAll();
            } else {
                $messages[] = ["type" => "error", "text" => "Failed to remove allergy."];
            }
        } catch (PDOException $e) {
            $messages[] = ["type" => "error", "text" => "Database error: " . $e->getMessage()];
        }
    }
    
    // Change password
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
                $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $messages[] = ["type" => "error", "text" => "All password fields are required."];
        } elseif (!validate_password($newPassword)) {
            $messages[] = ["type" => "error", "text" => "New password must be at least 8 characters long."];
        } elseif ($newPassword !== $confirmPassword) {
            $messages[] = ["type" => "error", "text" => "New passwords do not match."];
        } else {
            try {
                // Verify current password
                if (password_verify($currentPassword, $customer['password'])) {
                    // Hash the new password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("UPDATE customers SET password = ? WHERE id = ?");
                    $result = $stmt->execute([$hashedPassword, $customerId]);
                    
                    if ($result) {
                        $messages[] = ["type" => "success", "text" => "Password changed successfully."];
                    } else {
                        $messages[] = ["type" => "error", "text" => "Failed to change password."];
                    }
                } else {
                    $messages[] = ["type" => "error", "text" => "Current password is incorrect."];
                }
            } catch (PDOException $e) {
                $messages[] = ["type" => "error", "text" => "Database error: " . $e->getMessage()];
            }
        }
    }
}

// Define cuisine types for preferences
$cuisineTypes = [
    'Italian', 'Chinese', 'Mexican', 'Indian', 'Japanese', 'American', 'African', 
    'Thai', 'French', 'Mediterranean', 'Greek', 'Spanish', 'Korean', 'Vietnamese'
];

// Define common allergies for dropdown
$commonAllergies = [
    'Peanuts', 'Tree Nuts', 'Milk', 'Eggs', 'Fish', 'Shellfish', 'Soy', 
    'Wheat', 'Gluten', 'Sesame', 'Mustard', 'Celery', 'Sulphites'
];

// Parse selected cuisine preferences into array
$selectedCuisines = [];
if (isset($preferences['cuisine_preference']) && !empty($preferences['cuisine_preference'])) {
    $selectedCuisines = explode(',', $preferences['cuisine_preference']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FoodiFusion</title>
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
                    <li><a href="customer-dashboard.php">Dashboard</a></li>
                    <li><a href="customer-profile.php" class="active">My Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Profile -->
    <main class="dashboard customer-profile">
        <div class="container">
            <div class="welcome-section">
                <h1>My Profile</h1>
                <p>Manage your personal information, preferences, and allergies</p>
            </div>
            
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

            <div class="profile-section">
                <!-- Profile Sidebar -->
                <div class="profile-sidebar">
                    <div class="profile-image">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-details">
                        <h3><?php echo htmlspecialchars($customer['name']); ?></h3>
                        <p><?php echo htmlspecialchars($customer['email']); ?></p>
                    </div>
                    <div class="profile-nav">
                        <ul>
                            <li><a href="#personal-info" class="active"><i class="fas fa-user"></i> Personal Information</a></li>
                            <li><a href="#dietary-preferences"><i class="fas fa-utensils"></i> Dietary Preferences</a></li>
                            <li><a href="#allergies"><i class="fas fa-allergies"></i> Allergies</a></li>
                            <li><a href="#order-history"><i class="fas fa-history"></i> Order History</a></li>
                            <li><a href="#account-settings"><i class="fas fa-cog"></i> Account Settings</a></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Profile Main Content -->
                <div class="profile-main">
                    <!-- Personal Information Section -->
                    <div id="personal-info" class="preference-section">
                        <h3>Personal Information</h3>
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required pattern="^\+237[6,2][0-9]{8}$" title="Phone number must be in the format +237XXXXXXXXX">
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" required><?php echo htmlspecialchars($customer['address']); ?></textarea>
                            </div>
                            <button type="submit" name="update_personal" class="btn-primary">Update Information</button>
                        </form>
                    </div>
                    
                    <!-- Dietary Preferences Section -->
                    <div id="dietary-preferences" class="preference-section">
                        <h3>Dietary Preferences</h3>
                        <form method="post" action="">
                            <div class="form-group">
                                <label>Dietary Type</label>
                                <select name="dietary_preference" class="form-control">
                                    <option value="None" <?php echo $preferences['dietary_preference'] === 'None' ? 'selected' : ''; ?>>No Specific Diet</option>
                                    <option value="Vegetarian" <?php echo $preferences['dietary_preference'] === 'Vegetarian' ? 'selected' : ''; ?>>Vegetarian</option>
                                    <option value="Vegan" <?php echo $preferences['dietary_preference'] === 'Vegan' ? 'selected' : ''; ?>>Vegan</option>
                                    <option value="Keto" <?php echo $preferences['dietary_preference'] === 'Keto' ? 'selected' : ''; ?>>Keto</option>
                                    <option value="Paleo" <?php echo $preferences['dietary_preference'] === 'Paleo' ? 'selected' : ''; ?>>Paleo</option>
                                    <option value="Gluten-Free" <?php echo $preferences['dietary_preference'] === 'Gluten-Free' ? 'selected' : ''; ?>>Gluten-Free</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Cuisine Preferences</label>
                                <div class="preference-options">
                                    <?php foreach ($cuisineTypes as $cuisine): ?>
                                        <div class="preference-option <?php echo in_array($cuisine, $selectedCuisines) ? 'selected' : ''; ?>">
                                            <input type="checkbox" name="cuisine_preference[]" value="<?php echo $cuisine; ?>" id="cuisine-<?php echo $cuisine; ?>" <?php echo in_array($cuisine, $selectedCuisines) ? 'checked' : ''; ?> style="display: none;">
                                            <label for="cuisine-<?php echo $cuisine; ?>"><?php echo $cuisine; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Spice Tolerance</label>
                                <div class="preference-options">
                                    <div class="preference-option <?php echo $preferences['spice_tolerance'] === 'Mild' ? 'selected' : ''; ?>">
                                        <input type="radio" name="spice_tolerance" value="Mild" id="spice-mild" <?php echo $preferences['spice_tolerance'] === 'Mild' ? 'checked' : ''; ?> style="display: none;">
                                        <label for="spice-mild">Mild</label>
                                    </div>
                                    <div class="preference-option <?php echo $preferences['spice_tolerance'] === 'Medium' ? 'selected' : ''; ?>">
                                        <input type="radio" name="spice_tolerance" value="Medium" id="spice-medium" <?php echo $preferences['spice_tolerance'] === 'Medium' ? 'checked' : ''; ?> style="display: none;">
                                        <label for="spice-medium">Medium</label>
                                    </div>
                                    <div class="preference-option <?php echo $preferences['spice_tolerance'] === 'Spicy' ? 'selected' : ''; ?>">
                                        <input type="radio" name="spice_tolerance" value="Spicy" id="spice-spicy" <?php echo $preferences['spice_tolerance'] === 'Spicy' ? 'checked' : ''; ?> style="display: none;">
                                        <label for="spice-spicy">Spicy</label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_preferences" class="btn-primary">Save Preferences</button>
                        </form>
                    </div>
                    
                    <!-- Allergies Section -->
                    <div id="allergies" class="preference-section">
                        <h3>Allergy Management</h3>
                        <p>Add allergies to help our AI recommend suitable restaurants for you.</p>
                        
                        <!-- Current Allergies -->
                        <div class="allergy-list">
                            <?php if (empty($allergies)): ?>
                                <p>No allergies added yet.</p>
                            <?php else: ?>
                                <?php foreach ($allergies as $allergy): ?>
                                    <div class="allergy-item">
                                        <div class="allergy-name">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <?php echo htmlspecialchars($allergy['allergy_name']); ?>
                                        </div>
                                        <div class="allergy-actions">
                                            <span class="allergy-severity severity-<?php echo strtolower($allergy['severity']); ?>">
                                                <?php echo $allergy['severity']; ?>
                                            </span>
                                            <form method="post" action="" style="display: inline;">
                                                <input type="hidden" name="allergy_id" value="<?php echo $allergy['id']; ?>">
                                                <button type="submit" name="remove_allergy" class="btn-remove"><i class="fas fa-times"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Add New Allergy -->
                        <form method="post" action="" class="add-allergy-form">
                            <input type="text" name="allergy_name" placeholder="Allergy name" list="common-allergies">
                            <datalist id="common-allergies">
                                <?php foreach ($commonAllergies as $allergy): ?>
                                    <option value="<?php echo $allergy; ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <select name="allergy_severity">
                                <option value="Mild">Mild</option>
                                <option value="Moderate" selected>Moderate</option>
                                <option value="Severe">Severe</option>
                            </select>
                            <button type="submit" name="add_allergy">Add Allergy</button>
                        </form>
                    </div>
                    
                    <!-- Order History Section -->
                    <div id="order-history" class="preference-section">
                        <h3>Order History</h3>
                        
                        <?php if (empty($orderHistory)): ?>
                            <p>No order history available.</p>
                        <?php else: ?>
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Restaurant</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderHistory as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['restaurant_name']); ?></td>
                                            <td>FCFA <?php echo number_format($order['total_amount'], 0); ?></td>
                                            <td>
                                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Account Settings Section -->
                    <div id="account-settings" class="preference-section">
                        <h3>Account Settings</h3>
                        
                        <!-- Change Password -->
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="current-password">Current Password</label>
                                <input type="password" id="current-password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new-password">New Password</label>
                                                                <input type="password" id="new-password" name="new_password" required minlength="8" title="Password must be at least 8 characters long">
                            </div>
                            <div class="form-group">
                                <label for="confirm-password">Confirm New Password</label>
                                <input type="password" id="confirm-password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                        </form>
                    </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Navigation tabs
            const navLinks = document.querySelectorAll('.profile-nav a');
            const sections = document.querySelectorAll('.preference-section');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Show the corresponding section
                    const targetId = this.getAttribute('href').substring(1);
                    sections.forEach(section => {
                        if (section.id === targetId) {
                            section.style.display = 'block';
                        } else {
                            section.style.display = 'none';
                        }
                    });
                });
            });
            
            // Cuisine preference options
            const preferenceOptions = document.querySelectorAll('.preference-option');
            preferenceOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const input = this.querySelector('input');
                    
                    if (input.type === 'checkbox') {
                        // For checkboxes (cuisine preferences)
                        input.checked = !input.checked;
                        this.classList.toggle('selected');
                    } else if (input.type === 'radio') {
                        // For radio buttons (spice tolerance)
                        input.checked = true;
                        
                        // Remove selected class from all options in the same group
                        const name = input.getAttribute('name');
                        document.querySelectorAll(`input[name="${name}"]`).forEach(radio => {
                            radio.closest('.preference-option').classList.remove('selected');
                        });
                        
                        // Add selected class to this option
                        this.classList.add('selected');
                    }
                });
            });
            
            // Show first section by default
            sections.forEach((section, index) => {
                if (index === 0) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>