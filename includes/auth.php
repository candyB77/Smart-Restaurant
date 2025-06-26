<?php
/**
 * Authentication Functions
 * 
 * This file contains functions for user authentication, registration, and session management
 * for both customers and restaurants in the FoodiFusion platform.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

/**
 * Register a new customer
 * 
 * @param string $name Customer's full name
 * @param string $email Customer's email address
 * @param string $phone Customer's phone number
 * @param string $address Customer's address
 * @param string $password Customer's password (will be hashed)
 * @return array Result of the registration attempt
 */
function registerCustomer($name, $email, $phone, $address, $password) {
    try {
        $db = getDbConnection();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => false,
                'message' => 'Email already registered. Please use a different email or login.'
            ];
        }
        
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new customer
        $stmt = $db->prepare("INSERT INTO customers (name, email, phone, address, password) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([$name, $email, $phone, $address, $hashedPassword]);
        
        if ($result) {
            $customerId = $db->lastInsertId();
            
            // Create default preferences
            $stmt = $db->prepare("INSERT INTO customer_preferences (customer_id) VALUES (?)");
            $stmt->execute([$customerId]);
            
            return [
                'success' => true,
                'message' => 'Registration successful! You can now login.',
                'customer_id' => $customerId
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Registration error: ' . $e->getMessage()
        ];
    }
}

/**
 * Register a new restaurant
 * 
 * @param string $name Restaurant name
 * @param string $ownerName Owner's name
 * @param string $email Restaurant email address
 * @param string $phone Restaurant phone number
 * @param string $address Restaurant address
 * @param string $cuisineType Type of cuisine
 * @param string $description Restaurant description
 * @param string $password Restaurant account password (will be hashed)
 * @return array Result of the registration attempt
 */
function registerRestaurant($name, $ownerName, $email, $phone, $address, $cuisineType, $description, $password) {
    try {
        $db = getDbConnection();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM restaurants WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => false,
                'message' => 'Email already registered. Please use a different email or login.'
            ];
        }
        
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new restaurant
        $stmt = $db->prepare("INSERT INTO restaurants (name, owner_name, email, phone, address, cuisine_type, description, password) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$name, $ownerName, $email, $phone, $address, $cuisineType, $description, $hashedPassword]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Restaurant registration successful! You can now login.',
                'restaurant_id' => $db->lastInsertId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Registration error: ' . $e->getMessage()
        ];
    }
}

/**
 * Login a customer
 * 
 * @param string $email Customer's email address
 * @param string $password Customer's password
 * @return array Result of the login attempt
 */
function loginCustomer($email, $password) {
    try {
        $db = getDbConnection();
        
        // Get customer by email
        $stmt = $db->prepare("SELECT id, name, email, password FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
        
        // Verify password
        if (password_verify($password, $customer['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $customer['id'];
            $_SESSION['user_name'] = $customer['name'];
            $_SESSION['user_email'] = $customer['email'];
            $_SESSION['user_type'] = 'customer';
            
            return [
                'success' => true,
                'message' => 'Login successful!',
                'customer' => [
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'email' => $customer['email']
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Login error: ' . $e->getMessage()
        ];
    }
}

/**
 * Login a restaurant
 * 
 * @param string $email Restaurant's email address
 * @param string $password Restaurant's password
 * @return array Result of the login attempt
 */
function loginRestaurant($email, $password) {
    try {
        $db = getDbConnection();
        
        // Get restaurant by email
        $stmt = $db->prepare("SELECT id, name, email, password FROM restaurants WHERE email = ?");
        $stmt->execute([$email]);
        $restaurant = $stmt->fetch();
        
        if (!$restaurant) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
        
        // Verify password
        if (password_verify($password, $restaurant['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $restaurant['id'];
            $_SESSION['user_name'] = $restaurant['name'];
            $_SESSION['user_email'] = $restaurant['email'];
            $_SESSION['user_type'] = 'restaurant';
            
            return [
                'success' => true,
                'message' => 'Login successful!',
                'restaurant' => [
                    'id' => $restaurant['id'],
                    'name' => $restaurant['name'],
                    'email' => $restaurant['email']
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Login error: ' . $e->getMessage()
        ];
    }
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if logged in user is a customer
 * 
 * @return bool True if user is a customer, false otherwise
 */
function isCustomer() {
    return isLoggedIn() && $_SESSION['user_type'] === 'customer';
}

/**
 * Check if logged in user is a restaurant
 * 
 * @return bool True if user is a restaurant, false otherwise
 */
function isRestaurant() {
    return isLoggedIn() && $_SESSION['user_type'] === 'restaurant';
}

/**
 * Get current user ID
 * 
 * @return int|null User ID if logged in, null otherwise
 */
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

/**
 * Get current user type
 * 
 * @return string|null User type ('customer' or 'restaurant') if logged in, null otherwise
 */
function getCurrentUserType() {
    return isLoggedIn() ? $_SESSION['user_type'] : null;
}

/**
 * Logout the current user
 * 
 * @return void
 */
function logout() {
    // Unset all session variables
    $_SESSION = [];
    
    // If it's desired to kill the session, also delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally, destroy the session.
    session_destroy();
}

/**
 * Require customer login
 * 
 * Redirects to login page if user is not logged in as a customer
 * 
 * @param string $redirectUrl URL to redirect to if not logged in
 * @return void
 */
function requireCustomerLogin($redirectUrl = '/smart rest/index.php') {
    if (!isCustomer()) {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * Require restaurant login
 * 
 * Redirects to login page if user is not logged in as a restaurant
 * 
 * @param string $redirectUrl URL to redirect to if not logged in
 * @return void
 */
function requireRestaurantLogin($redirectUrl = '/smart rest/index.php') {
    if (!isRestaurant()) {
        header("Location: $redirectUrl");
        exit;
    }
}