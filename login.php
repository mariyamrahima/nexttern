<?php
session_start();

// Clear any existing session data to prevent conflicts
session_unset();
session_destroy();
session_start(); // Start fresh session

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(["success" => false, "message" => "Please fill all fields"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email format"]);
    exit;
}

/* ---- 1. Check if email exists in students table ---- */
$stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        // Clear all session data first
        $_SESSION = array();
        
        // Set student-specific session variables
        $_SESSION['user_type'] = 'student';
        $_SESSION['email'] = $user['email'];
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Generate unique session ID for this user
        session_regenerate_id(true);
        
        echo json_encode([
            "success" => true, 
            "redirect" => "internship.php", 
            "message" => "Student login successful"
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "field" => "password",
            "message" => "Incorrect password"
        ]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

/* ---- 2. If not found in students, check admins table ---- */
$stmt = $conn->prepare("SELECT * FROM admins WHERE email_id = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    if (password_verify($password, $admin['password'])) {
        // Clear all session data first
        $_SESSION = array();
        
        // Set admin-specific session variables
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_email'] = $admin['email_id'];
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Generate unique session ID for this user
        session_regenerate_id(true);
        
        echo json_encode([
            "success" => true, 
            "redirect" => "admin_dashboard.php", 
            "message" => "Admin login successful"
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "field" => "password",
            "message" => "Incorrect password"
        ]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

/* ---- 3. Email not found in either table ---- */
echo json_encode([
    "success" => false, 
    "field" => "email",
    "message" => "Email not registered"
]);

$conn->close();
?>