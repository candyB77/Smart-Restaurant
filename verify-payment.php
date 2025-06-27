<?php
session_start();

require_once 'config/database.php';

/**
 * Calls the OpenRouter Vision API to analyze an image.
 *
 * @param string $apiKey Your OpenRouter API key.
 * @param string $imageBase64 The base64-encoded image.
 * @param string $prompt The prompt for the AI.
 * @return array The decoded JSON response from the API.
 */
function callVisionApi($apiKey, $imageBase64, $prompt) {
    $ch = curl_init();

    $payload = json_encode([
        'model' => 'openai/gpt-4o',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $imageBase64]]
                ]
            ]
        ]
    ]);

    curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'X-Title: FoodiFusion'
    ]);

    // Set the path to the CA certificate bundle to fix SSL issues on local servers
    $caCertPath = __DIR__ . '/certs/cacert.pem';
    if (file_exists($caCertPath)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caCertPath);
    }

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_message = 'cURL Error: ' . curl_error($ch);
        $error_details = curl_getinfo($ch);
        
        // Log detailed error information for debugging
        $log_message = date('Y-m-d H:i:s') . " - AI API Call Failed.\n";
        $log_message .= "Error: " . $error_message . "\n";
        $log_message .= "Details: " . print_r($error_details, true) . "\n";
        file_put_contents(__DIR__ . '/ai_debug.log', $log_message, FILE_APPEND);
        
        curl_close($ch);
        return ['error' => $error_message];
    }
    curl_close($ch);

    return json_decode($response, true);
}

header('Content-Type: application/json');

// Check for API key
if (empty($_ENV['OPENROUTER_API_KEY'])) {
    echo json_encode(['success' => false, 'message' => 'AI service is not configured.']);
    exit;
}

// Handle file upload
if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['payment_screenshot'];
    
    // Validate file type and size
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes) || $file['size'] > 5000000) { // 5MB limit
        echo json_encode(['success' => false, 'message' => 'Invalid file. Please upload a valid image (JPG, PNG, GIF) under 5MB.']);
        exit;
    }

    $uploadDir = __DIR__ . '/uploads/temp_payments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileName = uniqid() . '-' . basename($file['name']);
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $imageBase64 = base64_encode(file_get_contents($filePath));
        
        $prompt = "Analyze this payment screenshot. The payment must be made to either 'MTN Money: 672777761' or 'Orange Money: 69865203'. Respond with only a JSON object with two keys: 'payment_valid' (boolean) and 'reason' (string). The 'reason' should be a brief explanation.";

        $aiResponse = callVisionApi($_ENV['OPENROUTER_API_KEY'], $imageBase64, $prompt);

        if (isset($aiResponse['error'])) {
            unlink($filePath);
            // Log the raw AI response for debugging
            $log_message = date('Y-m-d H:i:s') . " - AI Response Error.\n";
            $log_message .= "Response: " . print_r($aiResponse, true) . "\n";
            file_put_contents(__DIR__ . '/ai_debug.log', $log_message, FILE_APPEND);

            echo json_encode(['success' => false, 'message' => 'AI service error. Please check ai_debug.log for details.']);
            exit;
        }

        // Extract JSON from the AI's response text
        $responseText = $aiResponse['choices'][0]['message']['content'] ?? '';
        preg_match('/\{.*\}/s', $responseText, $matches);
        $jsonResponse = isset($matches[0]) ? json_decode($matches[0], true) : null;

        if ($jsonResponse && isset($jsonResponse['payment_valid']) && $jsonResponse['payment_valid'] === true) {
            $_SESSION['verified_screenshot_path'] = $filePath;
            echo json_encode(['success' => true, 'message' => 'Payment verified.']);
        } else {
            unlink($filePath);
            $reason = $jsonResponse['reason'] ?? 'The AI could not confirm the payment details. Please ensure the screenshot is clear and shows the correct recipient.';
            echo json_encode(['success' => false, 'message' => 'Invalid Payment: ' . $reason]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or an upload error occurred.']);
}
