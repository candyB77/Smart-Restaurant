<?php
/**
 * Logout Page
 * 
 * This script handles the logout process for both customers and restaurants.
 * It destroys the session and redirects to the homepage.
 */

// Include authentication functions
require_once 'includes/auth.php';

// Call the logout function
logout();

// Redirect to the homepage
header('Location: index.php');
exit;
?>