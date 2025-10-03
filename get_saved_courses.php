<?php
// File 1: get_saved_courses.php
session_start();
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get parameters
$user_id = $_GET['user_id'] ?? '';
$user_type = $_GET['user_type'] ?? '';

if (empty($user_id) || empty($user_type)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

try {
    // Query to get saved courses count
    $sql = "SELECT course_id FROM saved_courses WHERE user_id = ? AND user_type = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $user_id, $user_type);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $saved_courses = [];
    
    while ($row = $result->fetch_assoc()) {
        $saved_courses[] = (int)$row['course_id'];
    }
    
    echo json_encode([
        'success' => true,
        'saved_courses' => $saved_courses,
        'count' => count($saved_courses)
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in get_saved_courses.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching saved courses'
    ]);
}

$conn->close();
?>