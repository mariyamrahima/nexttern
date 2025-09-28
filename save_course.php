
<?php
// File 3: save_course.php
session_start();
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data
$action = $_POST['action'] ?? '';
$course_id = $_POST['course_id'] ?? '';
$user_id = $_POST['user_id'] ?? '';
$user_type = $_POST['user_type'] ?? '';

if (empty($action) || empty($course_id) || empty($user_id) || empty($user_type)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    if ($action === 'save') {
        // Check if already saved
        $check_sql = "SELECT id FROM saved_courses WHERE user_id = ? AND user_type = ? AND course_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssi", $user_id, $user_type, $course_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Course already saved']);
            exit();
        }
        
        // Save course
        $insert_sql = "INSERT INTO saved_courses (user_id, user_type, course_id, saved_at) VALUES (?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssi", $user_id, $user_type, $course_id);
        
        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Course saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save course']);
        }
        
        $insert_stmt->close();
        $check_stmt->close();
        
    } elseif ($action === 'unsave') {
        // Remove saved course
        $delete_sql = "DELETE FROM saved_courses WHERE user_id = ? AND user_type = ? AND course_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ssi", $user_id, $user_type, $course_id);
        
        if ($delete_stmt->execute()) {
            if ($delete_stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Course removed from saved list']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Course was not in saved list']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove course']);
        }
        
        $delete_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Error in save_course.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

$conn->close();
?>