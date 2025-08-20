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

// Fetch company by email
$stmt = $conn->prepare("SELECT id, company_id, company_name, password FROM companies WHERE company_email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'field' => 'email', 'message' => 'This email is not registered.']);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->bind_result($id, $company_id, $company_name, $hashed_password);
$stmt->fetch();

// Verify password
if (!password_verify($password, $hashed_password)) {
    echo json_encode(['success' => false, 'field' => 'password', 'message' => 'Incorrect password.']);
    $stmt->close();
    $conn->close();
    exit;
}

// Set session variables if needed
$_SESSION['company_id'] = $company_id;
$_SESSION['company_name'] = $company_name;

// Success: redirect to company dashboard (change as needed)
echo json_encode([
    'success' => true,
    'redirect' => 'company_dash.html' // <-- update this line
]);

$stmt->close();
$conn->close();
?>