<?php
session_start();
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_POST['email']) || !isset($_POST['password'])) {
    die("Both fields are required.");
}

$email = $_POST['email'];
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT student_id, first_name, password FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid email or password.");
}

$row = $result->fetch_assoc();

if (!password_verify($password, $row['password'])) {
    die("Invalid email or password.");
}

// Success
$_SESSION['email'] = $email;
$_SESSION['student_id'] = $row['student_id'];
$_SESSION['first_name'] = $row['first_name'];

header("Location: student_dashboard.php");
exit;
?>