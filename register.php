<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB connection
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// Required fields
$fields = ['first_name', 'last_name', 'email', 'contact', 'gender', 'dob', 'password'];
foreach ($fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        echo json_encode(["success" => false, "message" => "Missing field: $field"]);
        exit;
    }
}

// Sanitize and prepare inputs
$first_name = $conn->real_escape_string($_POST['first_name']);
$last_name  = $conn->real_escape_string($_POST['last_name']);
$email      = $conn->real_escape_string($_POST['email']);
$contact    = $conn->real_escape_string($_POST['contact']);
$gender     = $conn->real_escape_string($_POST['gender']);
$dob        = date('Y-m-d', strtotime($_POST['dob']));
$password   = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Check for duplicate email
$check = $conn->query("SELECT 1 FROM students WHERE email = '$email'");
if ($check && $check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email already exists"]);
    exit;
}

// Insert into database
$sql = "INSERT INTO students (first_name, last_name, email, contact, gender, dob, password)
        VALUES ('$first_name', '$last_name', '$email', '$contact', '$gender', '$dob', '$password')";

if ($conn->query($sql)) {
    session_start();
    $_SESSION['email'] = $email;
    $_SESSION['first_name'] = $first_name;
    echo json_encode(["success" => true, "message" => "Registration successful"]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
}
?>