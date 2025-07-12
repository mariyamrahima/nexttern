<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// Get POST data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($email === '' || $password === '') {
    echo json_encode(["success" => false, "message" => "Email and password required"]);
    exit;
}

// Check if user exists
$sql = "SELECT student_id, first_name, password FROM students WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    if (password_verify($password, $row['password'])) {
        // Save info in session
        $_SESSION['student_id'] = $row['student_id'];
        $_SESSION['first_name'] = $row['first_name'];
        
        echo json_encode([
            "success" => true,
            "message" => "Login successful",
            "student_id" => $row['student_id'],
            "first_name" => $row['first_name']
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Incorrect password"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "No account found with that email"]);
}
?>