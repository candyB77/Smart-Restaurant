<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $userType = $_POST['user_type'] ?? '';

    // Sanitize and validate common fields
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $password = $_POST['password'];

    if (!validate_email($email)) {
        $errors[] = 'Invalid email format.';
    }
    if (!validate_phone($phone)) {
        $errors[] = 'Invalid phone number. Must be in the format +237XXXXXXXXX.';
    }
    if (!validate_password($password)) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header('Location: index.php#' . $userType . '-signup');
        exit;
    }
        if ($userType === 'customer') {
                $result = registerCustomer(
            sanitize_input($_POST['name']),
            $email,
            $phone,
            sanitize_input($_POST['address']),
            $password
        );
        
        if ($result['success']) {
            // Auto-login after successful registration
            $loginResult = loginCustomer($_POST['email'], $_POST['password']);
            if ($loginResult['success']) {
                header('Location: customer-dashboard.php');
                exit;
            }
        }
    } else if ($userType === 'restaurant') {
                $result = registerRestaurant(
            sanitize_input($_POST['restaurant_name']),
            sanitize_input($_POST['owner_name']),
            $email,
            $phone,
            sanitize_input($_POST['address']),
            sanitize_input($_POST['cuisine_type']),
            sanitize_input($_POST['description']),
            $password
        );
        
        if ($result['success']) {
            // Auto-login after successful registration
            $loginResult = loginRestaurant($_POST['email'], $_POST['password']);
            if ($loginResult['success']) {
                header('Location: restaurant-dashboard.php');
                exit;
            }
        }
    }
    
        // If registration failed, redirect back with error
    $_SESSION['errors'] = [$result['message']];
    $_SESSION['form_data'] = $_POST;
    header('Location: index.php#' . $userType . '-signup');
    exit;
}