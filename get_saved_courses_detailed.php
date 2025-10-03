<?php
session_start();

// Prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database connection
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Get parameters
$user_id = $_GET['user_id'] ?? '';
$user_type = $_GET['user_type'] ?? '';

if (empty($user_id) || empty($user_type)) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id or user_type parameters']);
    exit();
}

try {
    // First check if saved_courses table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'saved_courses'");
    if ($table_check->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'saved_courses table does not exist']);
        exit();
    }

    // Query to get saved courses with detailed information
    $sql = "SELECT 
                sc.course_id,
                sc.saved_at,
                c.id,
                c.course_title,
                c.course_description,
                c.course_category,
                c.duration,
                c.difficulty_level,
                c.course_price_type,
                c.price_amount,
                c.skills_taught,
                c.certificate_provided,
                c.company_name,
                c.created_at
            FROM saved_courses sc
            JOIN course c ON sc.course_id = c.id
            WHERE sc.user_id = ? AND sc.user_type = ?
            ORDER BY sc.saved_at DESC";
    
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
        $saved_courses[] = [
            'id' => (int)$row['id'],
            'course_id' => (int)$row['course_id'],
            'course_title' => htmlspecialchars($row['course_title'] ?? ''),
            'course_description' => htmlspecialchars($row['course_description'] ?? ''),
            'course_category' => htmlspecialchars($row['course_category'] ?? ''),
            'duration' => htmlspecialchars($row['duration'] ?? 'Not specified'),
            'difficulty_level' => htmlspecialchars($row['difficulty_level'] ?? 'Beginner'),
            'course_price_type' => htmlspecialchars($row['course_price_type'] ?? 'free'),
            'price_amount' => $row['price_amount'] ?? 0,
            'skills_taught' => htmlspecialchars($row['skills_taught'] ?? ''),
            'certificate_provided' => (bool)$row['certificate_provided'],
            'company_name' => htmlspecialchars($row['company_name'] ?? ''),
            'saved_at' => $row['saved_at'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'saved_courses' => $saved_courses,
        'count' => count($saved_courses),
        'user_id' => $user_id,
        'user_type' => $user_type
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in get_saved_courses_detailed.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching saved courses: ' . $e->getMessage(),
        'user_id' => $user_id,
        'user_type' => $user_type
    ]);
}

$conn->close();
?>