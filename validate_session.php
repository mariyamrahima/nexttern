<?php
// validate_session.php
session_start();

// Set JSON response header
header('Content-Type: application/json');

// Check if company session is valid
$response = array();

if (isset($_SESSION['company_id']) && !empty($_SESSION['company_id'])) {
    $response['valid'] = true;
    $response['company_id'] = $_SESSION['company_id'];
    $response['company_name'] = $_SESSION['company_name'] ?? '';
} else {
    $response['valid'] = false;
    $response['message'] = 'Session expired';
}

echo json_encode($response);
exit();
?>