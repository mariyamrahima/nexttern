<?php
session_start();
header('Content-Type: application/json');

// Connect to DB
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'field' => null, 'message' => 'Database connection failed.']);
    exit;
}

// Sanitize input
function clean($str) {
    global $conn;
    return htmlspecialchars(trim($conn->real_escape_string($str)));
}

$email = clean($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (!$email) {
    echo json_encode(['success' => false, 'field' => 'email', 'message' => 'Email is required.']);
    $conn->close();
    exit;
}
if (!$password) {
    echo json_encode(['success' => false, 'field' => 'password', 'message' => 'Password is required.']);
    $conn->close();
    exit;
}

// Fetch company by email including status
$stmt = $conn->prepare("SELECT id, company_id, company_name, password, status FROM companies WHERE company_email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'field' => 'email', 'message' => 'This email is not registered.']);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->bind_result($id, $company_id, $company_name, $hashed_password, $status);
$stmt->fetch();

// Verify password first
if (!password_verify($password, $hashed_password)) {
    echo json_encode(['success' => false, 'field' => 'password', 'message' => 'Incorrect password.']);
    $stmt->close();
    $conn->close();
    exit;
}

// Check company status after password verification
switch ($status) {
    case 'pending':
        echo json_encode([
            'success' => false, 
            'field' => 'status', 
            'message' => 'Your application is pending admin approval. Please wait for review.',
            'status' => 'pending'
        ]);
        break;
        
    case 'rejected':
        echo json_encode([
            'success' => false, 
            'field' => 'status', 
            'message' => 'Your application was rejected. Contact support for details.',
            'status' => 'rejected'
        ]);
        break;
        
    case 'blocked':
        echo json_encode([
            'success' => false, 
            'field' => 'status', 
            'message' => 'Your account is blocked. Contact support to resolve this.',
            'status' => 'blocked'
        ]);
        break;
        
    case 'active':
        // Success: Company is active, allow login
        $_SESSION['company_id'] = $company_id;
        $_SESSION['company_name'] = $company_name;
        $_SESSION['company_status'] = $status;
        
        echo json_encode([
            'success' => true,
            'redirect' => 'company_dashboard.php',
            'company_name' => $company_name,
            'status' => 'active'
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false, 
            'field' => 'status', 
            'message' => 'Account status unclear. Contact support.',
            'status' => $status
        ]);
        break;
}

$stmt->close();
$conn->close();
?>