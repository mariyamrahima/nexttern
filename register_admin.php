<?php
// Set a higher level of error reporting for development.
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- Database Connection ---
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "nexttern_db";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
$conn->set_charset("utf8mb4");

// Check for connection error
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Process registration ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['username']); // The form input 'username' now maps to your 'name' column
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $email_id = 'placeholder@example.com'; // Placeholder, as your form doesn't have an email field

    // Basic server-side validation
    if (empty($name) || empty($password) || empty($confirmPassword)) {
        die("<p style='color:red;'>Please fill in all fields.</p>");
    }
    if ($password !== $confirmPassword) {
        die("<p style='color:red;'>Passwords do not match.</p>");
    }

    // Check if the username already exists
    $stmt = $conn->prepare("SELECT id FROM admins WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->close();
        die("<p style='color:red;'>Username already exists.</p>");
    }
    $stmt->close();

    // Insert new admin into the database
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO admins (name, password, email_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $hashedPassword, $email_id);
    
    $stmt->execute();
    $stmt->close();

    echo "<p style='color:green;'>Admin registered successfully!</p>";
}

$conn->close();
?>