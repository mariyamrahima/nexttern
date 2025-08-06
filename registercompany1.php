<?php
<?php
header('Content-Type: application/json');

// Helper function to sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Validate email
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate phone (basic)
function is_valid_phone($phone) {
    return preg_match('/^\+?\d{10,15}$/', preg_replace('/\D/', '', $phone));
}

// Validate password (at least 8 chars, upper, lower, number, special)
function is_valid_password($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password);
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

// Sanitize and assign variables
$company_name      = sanitize($data['company_name'] ?? '');
$industry_type     = sanitize($data['industry_type'] ?? '');
$company_email     = sanitize($data['company_email'] ?? '');
$year_established  = sanitize($data['year_established'] ?? '');
$password          = $data['password'] ?? '';
$contact_name      = sanitize($data['contact_name'] ?? '');
$designation       = sanitize($data['designation'] ?? '');
$contact_phone     = sanitize($data['contact_phone'] ?? '');
$payment_type      = sanitize($data['payment_type'] ?? '');
$transaction_id    = sanitize($data['transaction_id'] ?? '');
$payment_amount    = sanitize($data['payment_amount'] ?? '');
$payment_date      = sanitize($data['payment_date'] ?? '');
$payment_status    = sanitize($data['payment_status'] ?? '');
$invoice_number    = sanitize($data['invoice_number'] ?? '');
$gst_number        = sanitize($data['gst_number'] ?? '');
$billing_email     = sanitize($data['billing_email'] ?? '');

// Validate required fields
$errors = [];
if (!$company_name) $errors[] = 'Company name is required';
if (!$industry_type) $errors[] = 'Industry type is required';
if (!$company_email || !is_valid_email($company_email)) $errors[] = 'Valid company email is required';
if (!$year_established) $errors[] = 'Year of establishment is required';
if (!$password || !is_valid_password($password)) $errors[] = 'Password does not meet requirements';
if (!$contact_name) $errors[] = 'Contact name is required';
if (!$designation) $errors[] = 'Designation is required';
if (!$contact_phone || !is_valid_phone($contact_phone)) $errors[] = 'Valid contact phone is required';
if (!$billing_email || !is_valid_email($billing_email)) $errors[] = 'Valid billing email is required';

if ($errors) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Hash password (never store plain text passwords!)
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Load existing companies
$file = __DIR__ . '/companies.json';
$companies = [];
if (file_exists($file)) {
    $companies = json_decode(file_get_contents($file), true) ?: [];
}

// Check for duplicate email
foreach ($companies as $company) {
    if (strtolower($company['company_email']) === strtolower($company_email)) {
        echo json_encode(['success' => false, 'errors' => ['This email is already registered']]);
        exit;
    }
}

// Prepare new company data
$new_company = [
    'id' => time(),
    'company_name' => $company_name,
    'industry_type' => $industry_type,
    'company_email' => $company_email,
    'year_established' => $year_established,
    'password' => $hashed_password,
    'contact_name' => $contact_name,
    'designation' => $designation,
    'contact_phone' => $contact_phone,
    'payment_type' => $payment_type,
    'transaction_id' => $transaction_id,
    'payment_amount' => $payment_amount,
    'payment_date' => $payment_date,
    'payment_status' => $payment_status,
    'invoice_number' => $invoice_number,
    'gst_number' => $gst_number,
    'billing_email' => $billing_email,
    'registration_date' => date('c'),
    'status' => 'active'
];

// Save to file
$companies[] = $new_company;
file_put_contents($file, json_encode($companies, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'message' => 'Company registered successfully', 'transaction_id' => $transaction_id]);
?>