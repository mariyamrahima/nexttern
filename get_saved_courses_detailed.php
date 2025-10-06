<?php
// DIAGNOSTIC VERSION - Replace your current get_saved_courses_detailed.php with this
session_start();

// Output buffering to catch any stray output
ob_start();

// Prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Clean any output buffer
ob_clean();

// Database connection
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error, 'step' => 'connection']);
    exit();
}

// Get parameters
$user_id = $_GET['user_id'] ?? '';
$user_type = $_GET['user_type'] ?? '';

if (empty($user_id) || empty($user_type)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters', 'step' => 'parameters', 'received' => ['user_id' => $user_id, 'user_type' => $user_type]]);
    exit();
}

try {
    // Step 1: Check saved_courses table
    $count_sql = "SELECT COUNT(*) as count FROM saved_courses WHERE user_id = ? AND user_type = ?";
    $count_stmt = $conn->prepare($count_sql);
    
    if (!$count_stmt) {
        throw new Exception("Count query prepare failed: " . $conn->error);
    }
    
    $count_stmt->bind_param("ss", $user_id, $user_type);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_saved = $count_row['count'];
    
    if ($total_saved == 0) {
        echo json_encode([
            'success' => true,
            'saved_courses' => [],
            'count' => 0,
            'message' => 'No saved courses in database',
            'step' => 'count_check',
            'query_params' => ['user_id' => $user_id, 'user_type' => $user_type]
        ]);
        exit();
    }

    // Step 2: Get detailed course information
    $sql = "SELECT 
                sc.course_id,
                sc.saved_at,
                c.id,
                c.company_name,
                c.course_title,
                c.course_description,
                c.course_category,
                c.course_type,
                c.duration,
                c.difficulty_level,
                c.skills_taught,
                c.certificate_provided
            FROM saved_courses sc
            JOIN course c ON sc.course_id = c.id
            WHERE sc.user_id = ? AND sc.user_type = ?
            ORDER BY sc.saved_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Main query prepare failed: " . $conn->error);
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
            'company_name' => $row['company_name'] ?? '',
            'course_title' => $row['course_title'] ?? '',
            'course_description' => $row['course_description'] ?? '',
            'course_category' => $row['course_category'] ?? '',
            'course_type' => $row['course_type'] ?? 'self_paced',
            'duration' => $row['duration'] ?? 'Not specified',
            'difficulty_level' => $row['difficulty_level'] ?? 'Beginner',
            'skills_taught' => $row['skills_taught'] ?? '',
            'certificate_provided' => (bool)($row['certificate_provided'] ?? false),
            'saved_at' => $row['saved_at']
        ];
    }
    
    $response = [
        'success' => true,
        'saved_courses' => $saved_courses,
        'count' => count($saved_courses),
        'user_id' => $user_id,
        'user_type' => $user_type,
        'step' => 'completed',
        'debug_info' => [
            'total_in_db' => $total_saved,
            'fetched' => count($saved_courses),
            'sql' => $sql
        ]
    ];
    
    echo json_encode($response);
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'step' => 'exception',
        'user_id' => $user_id,
        'user_type' => $user_type
    ]);
}

$conn->close();
ob_end_flush();
?>