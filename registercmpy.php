<?php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Helper: sanitize input
function clean($str) {
    global $conn;
    return htmlspecialchars(trim($conn->real_escape_string($str)));
}

// Validate required POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$company_name     = clean($_POST['company_name'] ?? '');
$industry_type    = clean($_POST['industry_type'] ?? '');
$company_email    = clean($_POST['company_email'] ?? '');
$year_established = intval($_POST['year_established'] ?? 0);
$password         = $_POST['password'] ?? '';
$contact_name     = clean($_POST['contact_name'] ?? '');
$designation      = clean($_POST['designation'] ?? '');
$contact_phone    = clean($_POST['contact_phone'] ?? '');

// Server-side validation
$errors = [];

// Check required fields
if (empty($company_name)) $errors[] = 'Company name is required';
if (empty($industry_type)) $errors[] = 'Industry type is required';
if (empty($company_email)) $errors[] = 'Company email is required';
if ($year_established <= 0) $errors[] = 'Valid year of establishment is required';
if (empty($password)) $errors[] = 'Password is required';
if (empty($contact_name)) $errors[] = 'Contact person name is required';
if (empty($designation)) $errors[] = 'Designation is required';
if (empty($contact_phone)) $errors[] = 'Contact phone is required';

// Validate email format
if (!empty($company_email) && !filter_var($company_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

// Validate password strength
if (!empty($password) && strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters long';
}

// Validate phone number
if (!empty($contact_phone)) {
    $clean_phone = preg_replace('/\D/', '', $contact_phone);
    if (strlen($clean_phone) < 10 || strlen($clean_phone) > 15) {
        $errors[] = 'Invalid phone number';
    }
}

// Validate year
$current_year = date('Y');
if ($year_established > $current_year || $year_established < 1900) {
    $errors[] = 'Invalid year of establishment';
}

// If there are validation errors, return them
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    $conn->close();
    exit;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM companies WHERE company_email = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    $conn->close();
    exit;
}

$stmt->bind_param("s", $company_email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This email is already registered. Please use a different email or login.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Hash password securely
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Generate unique company_id
$company_id = "NXCO" . date("Ymd") . sprintf("%04d", rand(1, 9999));

// Ensure company_id is unique
$attempts = 0;
while ($attempts < 10) {
    $check_stmt = $conn->prepare("SELECT id FROM companies WHERE company_id = ?");
    $check_stmt->bind_param("s", $company_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows == 0) {
        $check_stmt->close();
        break;
    }
    
    $check_stmt->close();
    $company_id = "NXCO" . date("Ymd") . sprintf("%04d", rand(1, 9999));
    $attempts++;
}

// Insert into the single companies table
$insert_stmt = $conn->prepare("INSERT INTO companies (company_id, company_name, industry_type, company_email, year_established, password, contact_name, designation, contact_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");

if (!$insert_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    $conn->close();
    exit;
}

$insert_stmt->bind_param(
    "ssssissss",
    $company_id,
    $company_name,
    $industry_type,
    $company_email,
    $year_established,
    $hashed_password,
    $contact_name,
    $designation,
    $contact_phone
);

if ($insert_stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Registration successful! Redirecting to login page...', 
        'company_id' => $company_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again later.']);
}

$insert_stmt->close();
$conn->close();
?>