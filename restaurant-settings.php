<?php
require_once 'includes/auth.php';
require_once 'includes/validation.php';

// Require restaurant login
requireRestaurantLogin();

$restaurantId = getCurrentUserId();
$error = '';
$success = '';

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = sanitize_input($_POST['name']);
    $owner_name = sanitize_input($_POST['owner_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    $cuisine_type = sanitize_input($_POST['cuisine_type']);
    $description = sanitize_input($_POST['description']);

    $errors = [];
    if (empty($name)) $errors[] = 'Restaurant name is required.';
    if (empty($owner_name)) $errors[] = 'Owner name is required.';
    if (!validate_email($email)) $errors[] = 'Invalid email format.';
    if (!validate_phone($phone)) $errors[] = 'Invalid phone number. Must be in the format +237XXXXXXXXX.';
    if (empty($address)) $errors[] = 'Address is required.';
    if (empty($cuisine_type)) $errors[] = 'Cuisine type is required.';

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("UPDATE restaurants SET name = ?, owner_name = ?, email = ?, phone = ?, address = ?, cuisine_type = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $owner_name, $email, $phone, $address, $cuisine_type, $description, $restaurantId]);
            $success = 'Profile updated successfully!';
            
            // Redirect to restaurant dashboard after successful update
            header("Location: restaurant-dashboard.php?settings_updated=true");
            exit;
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch current restaurant details
try {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurantId]);
    $restaurant = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Settings - FoodiFusion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .settings-header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 1rem;
        }
        
        .settings-header h2 {
            color: #2e7d32;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        
        .settings-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .settings-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background-color: #2e7d32;
            border-radius: 3px;
        }
        
        .settings-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .form-section {
            background-color: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .form-section:hover {
            transform: translateY(-5px);
        }
        
        .form-section h3 {
            color: #2e7d32;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-section h3 i {
            font-size: 1.2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #444;
            font-size: 0.95rem;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.5rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15);
            outline: none;
        }
        
        .form-group i {
            position: absolute;
            left: 10px;
            top: 38px;
            color: #666;
        }
        
        .form-actions {
            grid-column: 1 / -1;
            text-align: center;
            margin-top: 1rem;
        }
        
        .btn-primary {
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary:hover {
            background-color: #1b5e20;
            transform: translateY(-2px);
        }
        
        .notification {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .notification i {
            font-size: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .settings-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <!-- Header content -->
    </header>

    <main class="dashboard">
        <div class="settings-container">
            <div class="settings-header">
                <h2><i class="fas fa-utensils"></i> Restaurant Settings</h2>
                <p>Update your restaurant profile and preferences</p>
            </div>

            <?php if ($error): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i>
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>

            <form action="restaurant-settings.php" method="post" class="settings-form">
                <div class="form-section">
                    <h3><i class="fas fa-store"></i> Basic Information</h3>
                    
                    <div class="form-group">
                        <label for="name">Restaurant Name</label>
                        <i class="fas fa-signature"></i>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($restaurant['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="owner_name">Owner Name</label>
                        <i class="fas fa-user"></i>
                        <input type="text" id="owner_name" name="owner_name" value="<?php echo htmlspecialchars($restaurant['owner_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cuisine_type">Cuisine Type</label>
                        <i class="fas fa-utensils"></i>
                        <input type="text" id="cuisine_type" name="cuisine_type" value="<?php echo htmlspecialchars($restaurant['cuisine_type'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-address-card"></i> Contact Information</h3>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($restaurant['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($restaurant['phone'] ?? ''); ?>" required pattern="^\+237[6,2][0-9]{8}$" title="Phone number must be in the format +237XXXXXXXXX">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <i class="fas fa-map-marker-alt"></i>
                        <textarea id="address" name="address" required><?php echo htmlspecialchars($restaurant['address'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-section" style="grid-column: 1 / -1;">
                    <h3><i class="fas fa-info-circle"></i> Restaurant Description</h3>
                    
                    <div class="form-group">
                        <label for="description">Tell customers about your restaurant</label>
                        <i class="fas fa-pencil-alt"></i>
                        <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($restaurant['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <!-- Footer content -->
    </footer>
    
    <script>
        // Add animation and feedback effects
        document.addEventListener('DOMContentLoaded', function() {
            // Focus effect for inputs
            const inputs = document.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.closest('.form-group').querySelector('i').style.color = '#2e7d32';
                });
                
                input.addEventListener('blur', function() {
                    this.closest('.form-group').querySelector('i').style.color = '#666';
                });
            });
            
            // Auto-hide success message after 5 seconds
            const successMessage = document.querySelector('.notification.success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
