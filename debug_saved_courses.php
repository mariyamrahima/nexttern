<?php
/**
 * Debug Saved Courses
 * Provides detailed debugging information for saved courses functionality
 */

session_start();
header('Content-Type: application/json');

// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$user_id = $_GET['user_id'] ?? '';
$user_type = $_GET['user_type'] ?? '';

$debug_info = [
    'request_info' => [
        'user_id' => $user_id,
        'user_type' => $user_type,
        'timestamp' => date('Y-m-d H:i:s'),
        'request_method' => $_SERVER['REQUEST_METHOD']
    ]
];

try {
    // 1. Check saved_courses table structure
    $result = $conn->query("DESCRIBE saved_courses");
    $debug_info['saved_courses_structure'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug_info['saved_courses_structure'][] = $row;
    }
    
    // 2. Check course table structure
    $result = $conn->query("DESCRIBE course");
    $debug_info['course_structure'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug_info['course_structure'][] = $row;
    }
    
    // 3. Check all saved courses for this user
    $stmt = $conn->prepare("SELECT * FROM saved_courses WHERE user_id = ? AND user_type = ?");
    $stmt->bind_param("ss", $user_id, $user_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $debug_info['user_saved_courses'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug_info['user_saved_courses'][] = $row;
    }
    $stmt->close();
    
    // 4. Check what user types exist in saved_courses
    $result = $conn->query("SELECT DISTINCT user_type FROM saved_courses");
    $debug_info['existing_user_types'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug_info['existing_user_types'][] = $row['user_type'];
    }
    
    // 5. Check all course statuses
    $result = $conn->query("SELECT DISTINCT course_status FROM course");
    $debug_info['existing_course_statuses'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug_info['existing_course_statuses'][] = $row['course_status'];
    }
    
    // 6. Count active courses
    $result = $conn->query("SELECT COUNT(*) as count FROM course WHERE course_status = 'Active'");
    $row = $result->fetch_assoc();
    $debug_info['active_courses_count'] = $row['count'];
    
    // 7. Count total courses
    $result = $conn->query("SELECT COUNT(*) as count FROM course");
    $row = $result->fetch_assoc();
    $debug_info['total_courses_count'] = $row['count'];
    
    // 8. Check if specific courses exist that this user has saved
    if (!empty($debug_info['user_saved_courses'])) {
        $course_ids = array_column($debug_info['user_saved_courses'], 'course_id');
        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
        $stmt = $conn->prepare("SELECT id, course_title, course_status FROM course WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($course_ids)), ...$course_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $debug_info['saved_course_details'] = [];
        while ($row = $result->fetch_assoc()) {
            $debug_info['saved_course_details'][] = $row;
        }
        $stmt->close();
    }
    
    // 9. Test the actual join query
    $stmt = $conn->prepare("
        SELECT c.id, c.course_title, c.course_status, sc.saved_at, sc.user_id, sc.user_type
        FROM saved_courses sc 
        JOIN course c ON sc.course_id = c.id 
        WHERE sc.user_id = ? AND sc.user_type = ?
    ");
    $stmt->bind_param("ss", $user_id, $user_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $debug_info['joined_results_all'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug_info['joined_results_all'][] = $row;
    }
    $stmt->close();
    
    // 10. Test the join query with Active filter
    $stmt = $conn->prepare("
        SELECT c.id, c.course_title, c.course_status, sc.saved_at
        FROM saved_courses sc 
        JOIN course c ON sc.course_id = c.id 
        WHERE sc.user_id = ? AND sc.user_type = ? AND c.course_status = 'Active'
    ");
    $stmt->bind_param("ss", $user_id, $user_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $debug_info['joined_results_active'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug_info['joined_results_active'][] = $row;
    }
    $stmt->close();
    
    // 11. Check for any saved courses for any user (to see if table has data)
    $result = $conn->query("SELECT COUNT(*) as count FROM saved_courses LIMIT 1");
    $row = $result->fetch_assoc();
    $debug_info['total_saved_courses'] = $row['count'];
    
    // 12. Sample saved courses data
    $result = $conn->query("SELECT * FROM saved_courses LIMIT 5");
    $debug_info['sample_saved_courses'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug_info['sample_saved_courses'][] = $row;
    }
    
    // 13. Check if user exists in any format
    $stmt = $conn->prepare("SELECT user_id, user_type, COUNT(*) as count FROM saved_courses WHERE user_id LIKE ? GROUP BY user_id, user_type");
    $like_user_id = "%$user_id%";
    $stmt->bind_param("s", $like_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $debug_info['similar_users'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug_info['similar_users'][] = $row;
    }
    $stmt->close();
    
    echo json_encode($debug_info, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $debug_info['error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    echo json_encode($debug_info, JSON_PRETTY_PRINT);
}

$conn->close();
?>