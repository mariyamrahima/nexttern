<?php
session_start();
header('Content-Type: application/json');

// Connect to DB
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Helper: sanitize input
function clean($str) {
    global $conn;
    return htmlspecialchars(trim($conn->real_escape_string($str)));
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
$payment_type     = clean($_POST['payment_type'] ?? '');
$transaction_id   = clean($_POST['transaction_id'] ?? '');
$payment_amount   = floatval($_POST['payment_amount'] ?? 0);
$payment_date     = clean($_POST['payment_date'] ?? '');
$payment_status   = clean($_POST['payment_status'] ?? '');
$invoice_number   = clean($_POST['invoice_number'] ?? '');
$gst_number       = clean($_POST['gst_number'] ?? '');
$billing_email    = clean($_POST['billing_email'] ?? '');

// Check required fields
if (!$company_name || !$industry_type || !$company_email || !$year_established || !$password || !$contact_name || !$designation || !$contact_phone) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
    $conn->close();
    exit;
}

// Check if email exists
$stmt = $conn->prepare("SELECT id FROM companies WHERE company_email = ?");
$stmt->bind_param("s", $company_email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Generate unique company_id (e.g., NXCO202508081234)
$company_id = "NXCO" . date("Ymd") . rand(1000,9999);

// Insert into DB
$stmt = $conn->prepare("INSERT INTO companies (
    company_id, company_name, industry_type, company_email, year_established, password, contact_name, designation, contact_phone,
    payment_type, transaction_id, payment_amount, payment_date, payment_status, invoice_number, gst_number, billing_email
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    "ssssissssssssssss",
    $company_id, $company_name, $industry_type, $company_email, $year_established, $hashed_password, $contact_name, $designation, $contact_phone,
    $payment_type, $transaction_id, $payment_amount, $payment_date, $payment_status, $invoice_number, $gst_number, $billing_email
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Registration successful! Redirecting to login...', 'company_id' => $company_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}
$stmt->close();
$conn->close();
?>