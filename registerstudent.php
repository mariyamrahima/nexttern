<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $conn = new mysqli("localhost", "root", "", "nexttern_db");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Log received data for debugging
    error_log("POST data received: " . print_r($_POST, true));

    // Check if all required fields are present and not empty
    $required_fields = ['first_name', 'last_name', 'email', 'contact', 'gender', 'dob', 'password'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("Missing or empty required field: $field");
        }
    }

    // Sanitize input data
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $last_name = $conn->real_escape_string(trim($_POST['last_name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $contact = $conn->real_escape_string(trim($_POST['contact']));
    $gender = $conn->real_escape_string(trim($_POST['gender']));
    $raw_dob = trim($_POST['dob']);

    // Validate email format (Gmail only as per frontend validation)
    if (!preg_match('/^[^\s@]+@gmail\.com$/i', $email)) {
        throw new Exception("Only Gmail addresses are allowed");
    }

    // Validate and parse DOB from "d/m/Y" to "Y-m-d"
    $date_obj = DateTime::createFromFormat('d/m/Y', $raw_dob);
    if (!$date_obj) {
        throw new Exception("Invalid date format. Please use DD/MM/YYYY format");
    }
    
    // Check if date is not in the future
    $today = new DateTime();
    if ($date_obj > $today) {
        throw new Exception("Date of birth cannot be in the future");
    }
    
    $dob = $date_obj->format('Y-m-d');

    // Validate password
    $password = $_POST['password'];
    if (strlen($password) < 6) {
        throw new Exception("Password must be at least 6 characters long");
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception("Email already exists. Please use a different email address.");
    }
    $check_stmt->close();

    // Generate unique student ID
    function generateStudentID($conn) {
        $max_attempts = 10;
        $attempts = 0;
        
        do {
            $id = 'ST' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $check_stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
            $check_stmt->bind_param("s", $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $exists = $result->num_rows > 0;
            $check_stmt->close();
            $attempts++;
        } while ($exists && $attempts < $max_attempts);
        
        if ($exists) {
            throw new Exception("Could not generate unique student ID");
        }
        
        return $id;
    }

    $student_id = generateStudentID($conn);

    // Insert new student record
    $stmt = $conn->prepare("INSERT INTO students (student_id, first_name, last_name, email, contact, gender, dob, password, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssssssss", $student_id, $first_name, $last_name, $email, $contact, $gender, $dob, $hashed_password);
    
    if ($stmt->execute()) {
        // Set session variables
        $_SESSION['email'] = $email;
        $_SESSION['student_id'] = $student_id;
        $_SESSION['user_type'] = 'student';
        
        $stmt->close();
        $conn->close();
        
        // Log success
        error_log("Registration successful for email: " . $email . ", student_id: " . $student_id);
        
        echo json_encode([
            "success" => true, 
            "message" => "Registration successful! Welcome to Nexttern.",
            "student_id" => $student_id
        ]);
    } else {
        throw new Exception("Failed to create account: " . $stmt->error);
    }

} catch (Exception $e) {
    // Log the error
    error_log("Registration error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage()
    ]);
    
    // Close connections if they exist
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>