<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

// Collect inputs
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$dob_raw = trim($_POST['dob'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// Validate required
if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $confirm_password === '') {
    echo json_encode(["success" => false, "field" => "general", "message" => "Please fill all required fields"]);
    exit;
}

// Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "field" => "email", "message" => "Invalid email format"]);
    exit;
}

// Phone format
if ($contact && !preg_match('/^\d{10}$/', $contact)) {
    echo json_encode(["success" => false, "field" => "contact", "message" => "Phone must be 10 digits"]);
    exit;
}

// Password match
if ($password !== $confirm_password) {
    echo json_encode(["success" => false, "field" => "confirm_password", "message" => "Passwords do not match"]);
    exit;
}

// Check duplicate email
$check = $conn->query("SELECT 1 FROM students WHERE email='$email'");
if ($check && $check->num_rows > 0) {
    echo json_encode(["success" => false, "field" => "email", "message" => "Email already exists"]);
    exit;
}

// Prepare other values
$dob = $dob_raw ? date('Y-m-d', strtotime($dob_raw)) : null;
$password_hashed = password_hash($password, PASSWORD_DEFAULT);

// Generate student ID
function generateStudentID($conn) {
    do {
        $id = 'ST' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $chk = $conn->query("SELECT 1 FROM students WHERE student_id='$id'");
    } while ($chk && $chk->num_rows > 0);
    return $id;
}
$student_id = generateStudentID($conn);

// Insert
$sql = "INSERT INTO students (student_id, first_name, last_name, email, contact, gender, dob, password)
VALUES ('$student_id', '$first_name', '$last_name', '$email', '$contact', '$gender', '$dob', '$password_hashed')";

if ($conn->query($sql)) {
    $_SESSION['email'] = $email;
    $_SESSION['student_id'] = $student_id;
    echo json_encode(["success" => true, "message" => "Registration successful"]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
}
?>