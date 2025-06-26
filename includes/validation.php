<?php

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    // Validates Cameroonian phone numbers, expecting format +237XXXXXXXXX
    return preg_match('/^\+237[6,2][0-9]{8}$/', $phone);
}

function validate_password($password) {
    // Enforces a minimum of 8 characters for the password
    return strlen($password) >= 8;
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

?>
