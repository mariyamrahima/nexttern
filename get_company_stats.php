<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Start session and check authentication
session_start();

// Check if company is logged in
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit();
}

// Database connection
function getDatabaseConnection() {
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "nexttern_db";
    
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }
    
    return $conn;
}

// Function to safely fetch count
function getCount($conn, $query, $params = [], $default = 0) {
    if (!$conn) return $default;
    
    try {
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return (int)($row['count'] ?? $row[array_key_first($row)] ?? 0);
                }
                $stmt->close();
            }
        } else {
            $result = $conn->query($query);
            if ($result && $row = $result->fetch_assoc()) {
                return (int)($row['count'] ?? $row[array_key_first($row)] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log("Query error: " . $e->getMessage());
    }
    
    return $default;
}

$response = [
    'success' => false,
    'internships' => 0,
    'applications' => 0,
    'payments' => 0,
    'hired' => 0
];

try {
    $company_db_id = $_SESSION['id'] ?? '';  // Primary key from companies table
    
    if (empty($company_db_id)) {
        throw new Exception('Company ID not found in session');
    }
    
    $conn = getDatabaseConnection();
    
    if ($conn) {
        // Get display company_id from database using primary key
        $company_id = '';
        $stmt = $conn->prepare("SELECT company_id FROM companies WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $company_db_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $company_id = $row['company_id'] ?? '';
            }
            $stmt->close();
        }
        
        // Fallback if no display company_id found
        if (empty($company_id)) {
            $company_id = 'COMP' . str_pad($company_db_id, 4, '0', STR_PAD_LEFT);
        }
        
        if (!empty($company_id)) {
            // Get active internships count
            $response['internships'] = getCount(
                $conn, 
                "SELECT COUNT(*) as count FROM Course WHERE company_id = ? AND course_status = 'active'", 
                [$company_id]
            );
            
            // Get total applications count
            $response['applications'] = getCount(
                $conn, 
                "SELECT COUNT(DISTINCT a.id) as count FROM applications a 
                 INNER JOIN Course c ON a.course_id = c.course_id 
                 WHERE c.company_id = ?", 
                [$company_id]
            );
            
            // Get monthly payments count
            $response['payments'] = getCount(
                $conn, 
                "SELECT COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END), 0) as count
                 FROM company_payment WHERE company_id = ? 
                 AND payment_date >= DATE_FORMAT(NOW(), '%Y-%m-01')", 
                [$company_id]
            );
            
            // Get hired interns count
            $response['hired'] = getCount(
                $conn, 
                "SELECT COUNT(DISTINCT a.id) as count FROM applications a 
                 INNER JOIN Course c ON a.course_id = c.course_id 
                 WHERE c.company_id = ? AND a.status = 'hired'", 
                [$company_id]
            );
            
            $response['success'] = true;
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    error_log("Stats API error: " . $e->getMessage());
    $response['error'] = 'Failed to fetch statistics';
}

echo json_encode($response);
?>