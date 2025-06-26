<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $userType = $_POST['user_type'] ?? '';

    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!validate_email($email)) {
        $errors[] = 'Invalid email format.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        $_SESSION['login_form_data'] = $_POST;
        header('Location: index.php#' . $userType . '-login');
        exit;
    }
    
    $result = null;

    if ($userType === 'customer') {
        $result = loginCustomer($email, $password);
        if ($result['success']) {
            header('Location: customer-dashboard.php');
            exit;
        }
    } else if ($userType === 'restaurant') {
        $result = loginRestaurant($email, $password);
        if ($result['success']) {
            header('Location: restaurant-dashboard.php');
            exit;
        }
    } else {
        $result = ['message' => 'Invalid user type specified.'];
    }
    
    $errorMessage = $result['message'] ?? 'An unknown error occurred.';
    $_SESSION['login_errors'] = [$errorMessage];
    $_SESSION['login_form_data'] = $_POST;
    header('Location: index.php#' . $userType . '-login');
    exit;

} else {
    header('Location: index.php');
    exit;
}