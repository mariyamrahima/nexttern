<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$fields = ['first_name', 'last_name', 'email', 'contact', 'gender', 'dob', 'password'];
foreach ($fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        echo json_encode(["success" => false, "message" => "Missing field: $field"]);
        exit;
    }
}

$first_name = $conn->real_escape_string($_POST['first_name']);
$last_name  = $conn->real_escape_string($_POST['last_name']);
$email      = $conn->real_escape_string($_POST['email']);
$contact    = $conn->real_escape_string($_POST['contact']);
$gender     = $conn->real_escape_string($_POST['gender']);
$dob        = !empty($_POST['dob']) ? date('Y-m-d', strtotime($_POST['dob'])) : null;
$password   = password_hash($_POST['password'], PASSWORD_DEFAULT);

$check = $conn->query("SELECT 1 FROM students WHERE email = '$email'");
if ($check && $check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email already exists"]);
    exit;
}

function generateStudentID($conn) {
    do {
        $id = 'ST' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT 1 FROM students WHERE student_id = '$id'");
    } while ($check && $check->num_rows > 0);
    return $id;
}
$student_id = generateStudentID($conn);

$sql = "INSERT INTO students (student_id, first_name, last_name, email, contact, gender, dob, password)
        VALUES ('$student_id', '$first_name', '$last_name', '$email', '$contact', '$gender', '$dob', '$password')";

if ($conn->query($sql)) {
    $_SESSION['email'] = $email;
    $_SESSION['student_id'] = $student_id;
    echo json_encode(["success" => true, "message" => "Registration successful"]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: ".$conn->error]);
}
?>