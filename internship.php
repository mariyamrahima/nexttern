<?php
// Start session to check login status
session_start();

// Function to clear user data and redirect to login
function redirectToLogin($message = "Please login to access this page") {
    session_unset();
    session_destroy();
    header("Location: login.html?message=" . urlencode($message));
    exit;
}

// Enhanced user authentication and session validation
$isLoggedIn = false;
$user_type = '';

// Check if user is logged in and validate session
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_type'])) {
    $user_type = $_SESSION['user_type'];
    
    // Check session timeout (optional - 24 hours)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 86400)) {
        redirectToLogin("Session expired. Please login again.");
    }
    
    // Validate user type and required session variables
    if ($user_type === 'student' && isset($_SESSION['email']) && isset($_SESSION['student_id'])) {
        $isLoggedIn = true;
    } elseif ($user_type === 'admin' && isset($_SESSION['admin_email']) && isset($_SESSION['admin_id'])) {
        // Redirect admin to their dashboard
        header("Location: admin_dashboard.php");
        exit;
    } else {
        // Invalid session data
        redirectToLogin("Invalid session. Please login again.");
    }
} elseif (isset($_SESSION['user_id']) || isset($_SESSION['email']) || isset($_SESSION['student_id'])) {
    // Clear any incomplete/invalid session data
    redirectToLogin("Session corrupted. Please login again.");
}

// Initialize user variables with safe defaults
$user_name = '';
$user_email = '';
$user_profile_picture = '';
$user_role = 'student';
$user_phone = '';
$user_location = '';
$user_id = '';
$user_joined = '';
$user_dob = '';
$unread_count = 0;

// Get user details only if properly logged in as student
if ($isLoggedIn && $user_type === 'student') {
    // Database connection for user details
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $user_conn = new mysqli($servername, $username, $password, $dbname);
    
    if (!$user_conn->connect_error) {
        $student_id = $_SESSION['student_id'];
        $email = $_SESSION['email'];
        
        // Get student details with proper error handling
        $user_stmt = $user_conn->prepare("SELECT student_id as id, CONCAT(first_name, ' ', last_name) as name, email, '' as profile_picture, 'student' as role, contact as phone, '' as location, created_at, dob FROM students WHERE student_id = ? AND email = ?");
        $user_stmt->bind_param("ss", $student_id, $email);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            $user_id = $user_data['id'] ?? '';
            $user_name = $user_data['name'] ?? 'Student';
            $user_email = $user_data['email'] ?? '';
            $user_profile_picture = $user_data['profile_picture'] ?? '';
            $user_role = 'student';
            $user_phone = $user_data['phone'] ?? '';
            $user_location = $user_data['location'] ?? '';
            $user_joined = $user_data['created_at'] ?? '';
            $user_dob = $user_data['dob'] ?? '';
        } else {
            // Student not found in database, invalid session
            $user_conn->close();
            redirectToLogin("User account not found. Please login again.");
        }
        $user_stmt->close();
        
        // Get unread messages count for the student
        if ($user_id) {
            $unread_stmt = $user_conn->prepare("SELECT COUNT(*) as unread_count FROM student_messages WHERE receiver_type = 'student' AND receiver_id = ? AND is_read = FALSE");
            $unread_stmt->bind_param("s", $user_id);
            $unread_stmt->execute();
            $unread_result = $unread_stmt->get_result();
            if ($unread_result) {
                $unread_data = $unread_result->fetch_assoc();
                $unread_count = $unread_data['unread_count'] ?? 0;
            }
            $unread_stmt->close();
        }
        
        $user_conn->close();
    } else {
        redirectToLogin("Database connection error. Please try again.");
    }
}

// Rest of your existing database connection and course fetching code...
// Database connection parameters for courses
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Your existing filtering and course fetching logic remains the same...
// Dynamic filtering logic
$course_filter = isset($_GET['course']) ? $conn->real_escape_string($_GET['course']) : '';
$mode_filter = isset($_GET['mode']) ? $conn->real_escape_string($_GET['mode']) : '';
$duration_filter = isset($_GET['duration']) ? $conn->real_escape_string($_GET['duration']) : '';

$where_clauses = [];
$params = [];
$types = '';

if (!empty($course_filter)) {
    $where_clauses[] = "course_category = ?";
    $params[] = $course_filter;
    $types .= 's';
}

if (!empty($duration_filter)) {
    $where_clauses[] = "duration = ?";
    $params[] = $duration_filter;
    $types .= 's';
}

$sql = "SELECT * FROM courses";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Prepare and execute the statement
if (!empty($where_clauses)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$courses_data = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row['posted_date'] = $row['created_at'] ?? 'N/A';
        
        // Mock a random mode since it's not in your database table
        $mock_modes = ['remote', 'onsite', 'hybrid'];
        $row['mode'] = $row['mode'] ?? $mock_modes[array_rand($mock_modes)];
        
        $courses_data[] = $row;
    }
}

// Close statement and connection
if (isset($stmt)) $stmt->close();
$conn->close();

// Filter for `mode` in PHP since it's not in the database schema
$filtered_internships = $courses_data;
if (!empty($mode_filter)) {
    $filtered_internships = array_filter($courses_data, function($course) use ($mode_filter) {
        return $course['mode'] === $mode_filter;
    });
}

$courses_categories = [
    'programming',
    'design', 
    'business',
    'marketing',
    'data_science',
    'ai_ml',
    'cybersecurity'
];

$available_durations = [
    '1_week' => '1 Week',
    '2_weeks' => '2 Weeks',
    '1_month' => '1 Month',
    '2_months' => '2 Months',
    '3_months' => '3 Months',
    '6_months' => '6 Months',
    'self_paced' => 'Self-Paced'
];

$available_modes = ['remote', 'onsite', 'hybrid'];
sort($courses_categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexttern - Internship Opportunities</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="internship.css">
    <style>
        /* Root Variables */
        :root {
            --primary: #035946;
            --primary-light: #0a7058;
            --primary-dark: #023d32;
            --secondary: #2e3944;
            --accent: #4ecdc4;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f5fbfa;
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
            --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
            --blur: 14px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        /* Enhanced Navbar with Profile */
        .nav-profile {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--primary);
            font-weight: 500;
            box-shadow: var(--shadow-light);
            border: none;
        }

        .profile-trigger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            background: rgba(255, 255, 255, 0.4);
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
        }

        .profile-avatar.default {
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
        }

        .profile-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .message-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: auto;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Enhanced Welcome Bar */
        .enhanced-welcome {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: var(--primary);
            padding: 1.5rem 2rem;
            margin: 1.5rem 2rem;
            border-radius: 16px;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 20px rgba(3, 89, 70, 0.1);
        }

        .enhanced-welcome h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .enhanced-welcome .welcome-details {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .welcome-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary);
            background: rgba(3, 89, 70, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(3, 89, 70, 0.15);
        }

        .welcome-detail i {
            color: var(--accent);
            font-size: 1rem;
        }

        /* Login Modal Styles (for non-logged users) */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-out;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 0;
            max-width: 450px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.8);
            opacity: 0;
            transition: all 0.2s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 1.5rem 0;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #999;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }

        .modal-close:hover {
            background: #f5f5f5;
            color: #333;
        }

        .modal-body {
            padding: 0 1.5rem 1.5rem;
            text-align: center;
        }

        .modal-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 1.5rem;
        }

        .modal-body p {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .modal-btn {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            text-align: center;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .modal-btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(3, 89, 70, 0.3);
        }

        .modal-btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .modal-btn-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(3, 89, 70, 0.2);
        }

        .modal-footer-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .modal-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .modal-link:hover {
            text-decoration: underline;
        }

        /* Content Blur Overlay - Enhanced for non-logged users */
        .content-blur-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(transparent 0%, rgba(255, 255, 255, 0.1) 20%, rgba(255, 255, 255, 0.9) 70%, rgba(255, 255, 255, 0.95) 100%);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .blur-message {
            pointer-events: auto;
            text-align: center;
            padding: 2rem;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .blur-content i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
            opacity: 0.8;
            display: block;
        }

        .blur-content h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.8rem;
        }

        .blur-content p {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .blur-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .blur-btn {
            padding: 0.8rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            min-width: 120px;
            text-align: center;
        }

        .blur-btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 15px rgba(3, 89, 70, 0.3);
        }

        .blur-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(3, 89, 70, 0.4);
        }

        .blur-btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .blur-btn-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(3, 89, 70, 0.3);
        }

        .blurred-content {
            position: relative;
            pointer-events: none;
            user-select: none;
        }

        /* Profile Dashboard Modal */
        .profile-dashboard-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        }

        .profile-dashboard {
            background: white;
            border-radius: 20px;
            width: 95%;
            max-width: 1200px;
            max-height: 95vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.8);
            opacity: 0;
            transition: all 0.3s ease-out;
            position: relative;
        }

        .profile-dashboard.show {
            transform: scale(1);
            opacity: 1;
        }

        .dashboard-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            animation: shimmer 6s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .dashboard-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: white;
            font-size: 1.2rem;
            z-index: 10;
        }

        .dashboard-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .dashboard-user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .dashboard-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            border: 3px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }

        .dashboard-user-details h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .user-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .meta-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .dashboard-content {
            padding: 2.5rem;
        }

        .dashboard-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 1rem;
        }

        .dashboard-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 500;
            cursor: pointer;
            border-radius: 12px;
            transition: var(--transition);
            position: relative;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dashboard-tab.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 15px rgba(3, 89, 70, 0.3);
        }

        .dashboard-tab:not(.active):hover {
            background: rgba(3, 89, 70, 0.1);
            color: var(--primary);
        }

        .dashboard-tab-content {
            display: none;
        }

        .dashboard-tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* Profile Section */
        .profile-section {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.75rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group input {
            padding: 1rem;
            border: 2px solid rgba(3, 89, 70, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.7);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        }

        .form-group input[readonly] {
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-secondary);
        }

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--primary);
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
            background: rgba(255, 255, 255, 0.4);
        }

        .quick-action i {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 1rem;
            display: block;
        }

        .quick-action h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-dark);
        }

        .quick-action p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            font-weight: bold;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .stat-icon {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Enhanced Access Badge for logged users */
        .enhanced-access-badge {
            background: var(--gradient-primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(3, 89, 70, 0.3);
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-dashboard {
                width: 95%;
                margin: 1rem;
                max-height: 95vh;
            }

            .dashboard-header {
                padding: 1.5rem;
            }

            .dashboard-user-info {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .dashboard-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .dashboard-user-details h2 {
                font-size: 1.5rem;
            }

            .dashboard-tabs {
                flex-wrap: wrap;
                gap: 0.25rem;
            }

            .dashboard-tab {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .profile-name {
                display: none;
            }

            .enhanced-welcome {
                margin: 1rem;
                padding: 2.5rem 2rem;
            }
            
            .enhanced-welcome h2 {
                font-size: 1.8rem;
                margin-bottom: 1.5rem;
            }
            
            .enhanced-welcome .welcome-details {
                gap: 1rem;
                margin: 1.5rem 0;
            }
            
            .welcome-detail {
                font-size: 0.9rem;
                padding: 0.8rem 1.2rem;
            }

            .content-blur-overlay {
                height: 60vh;
            }

            .blur-message {
                padding: 2rem 1rem;
                margin-top: 5rem;
            }

            .blur-content h3 {
                font-size: 1.5rem;
            }

            .blur-content i {
                font-size: 3rem;
            }

            .blur-content p {
                font-size: 1rem;
            }

            .blur-actions {
                flex-direction: column;
                align-items: center;
            }

            .blur-btn {
                width: 100%;
                max-width: 280px;
            }

            .modal-content {
                margin: 1rem;
                width: calc(100% - 2rem);
            }

            .modal-actions {
                flex-direction: column;
            }

            .modal-btn {
                width: 100%;
            }

            .modal-header h3 {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 480px) {
            .enhanced-welcome {
                margin: 0.5rem;
                padding: 2rem 1.5rem;
            }

            .enhanced-welcome .welcome-details {
                flex-direction: column;
                gap: 0.8rem;
            }

            .welcome-detail {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>
    <div class="blob blob3"></div>
    <div class="blob blob4"></div>
    <div class="blob blob5"></div>

    <!-- Enhanced Navigation with Smart Profile/Login -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="nav-brand">
                <img src="nextternnavbar.png" alt="Nexttern Logo" class="nav-logo">
            </a>
            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="internship.php" class="nav-link active">Internships</a></li>
                <li><a href="#" class="nav-link">Companies</a></li>
                <li><a href="aboutus.php" class="nav-link">About</a></li>
                <li><a href="contactus.php" class="nav-link">Contact</a></li>
            </ul>
            
            <div class="nav-cta">
                <?php if ($isLoggedIn): ?>
                    <div class="nav-profile">
                        <button class="profile-trigger" onclick="openProfileDashboard()">
                            <?php if (!empty($user_profile_picture)): ?>
                                <img src="<?php echo htmlspecialchars($user_profile_picture); ?>" alt="Profile" class="profile-avatar">
                            <?php else: ?>
                                <div class="profile-avatar default">
                                    <?php echo strtoupper(substr($user_name ?: 'U', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <span class="profile-name"><?php echo htmlspecialchars($user_name ?: 'User'); ?></span>
                            <?php if ($unread_count > 0): ?>
                                <span class="message-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                <?php else: ?>
                    <a href="login.html" class="btn btn-primary">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>Internship Opportunities</h1>
            <p>Discover your perfect learning path with top companies</p>
            <?php if ($isLoggedIn): ?>
                <div class="enhanced-welcome">
                    <h2>Welcome back, <?php echo htmlspecialchars($user_name ?: 'Student'); ?>!</h2>
                    <div class="welcome-details">
                        <div class="welcome-detail">
                            <i class="fas fa-<?php echo $user_role === 'company' ? 'building' : 'graduation-cap'; ?>"></i>
                            <span>Role: <?php echo ucfirst(htmlspecialchars($user_role ?: 'Student')); ?></span>
                        </div>
                        <div class="welcome-detail">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Joined: <?php echo !empty($user_joined) ? date('M Y', strtotime($user_joined)) : 'Recently'; ?></span>
                        </div>
                        <?php if ($unread_count > 0): ?>
                        <div class="welcome-detail">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo $unread_count; ?> New Messages</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="welcome-message">
                        Explore all available opportunities below - no restrictions!
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Profile Dashboard Modal (for logged users) -->
    <?php if ($isLoggedIn): ?>
    <div id="profileDashboardOverlay" class="profile-dashboard-overlay">
        <div class="profile-dashboard" id="profileDashboard">
            <div class="dashboard-header">
                <button class="dashboard-close" onclick="closeProfileDashboard()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="dashboard-user-info">
                    <?php if (!empty($user_profile_picture)): ?>
                        <img src="<?php echo htmlspecialchars($user_profile_picture); ?>" alt="Profile" class="dashboard-avatar">
                    <?php else: ?>
                        <div class="dashboard-avatar">
                            <?php echo strtoupper(substr($user_name ?: 'U', 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="dashboard-user-details">
                        <h2><?php echo htmlspecialchars($user_name ?: 'User'); ?></h2>
                        <p><?php echo htmlspecialchars($user_email); ?></p>
                        <div class="user-meta">
                            <span class="meta-badge">
                                <i class="fas fa-<?php echo $user_role === 'company' ? 'building' : 'graduation-cap'; ?>"></i>
                                <?php echo ucfirst(htmlspecialchars($user_role ?: 'Student')); ?>
                            </span>
                            <?php if (!empty($user_joined)): ?>
                                <span class="meta-badge">
                                <i class="fas fa-calendar"></i>
                                Joined <?php echo date('M Y', strtotime($user_joined)); ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($unread_count > 0): ?>
                                <span class="meta-badge" style="background: rgba(231, 76, 60, 0.2); color: var(--danger);">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo $unread_count; ?> New
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <!-- Dashboard Tabs -->
                <div class="dashboard-tabs">
                    <button class="dashboard-tab active" onclick="showDashboardTab('overview')">
                        <i class="fas fa-tachometer-alt"></i>
                        Overview
                    </button>
                    <button class="dashboard-tab" onclick="showDashboardTab('profile')">
                        <i class="fas fa-user"></i>
                        Profile
                    </button>
                    <button class="dashboard-tab" onclick="showDashboardTab('applications')">
                        <i class="fas fa-file-alt"></i>
                        Applications
                    </button>
                </div>

                <!-- Overview Tab -->
                <div id="overview-tab" class="dashboard-tab-content active">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-briefcase stat-icon"></i>
                            <div class="stat-number">0</div>
                            <div class="stat-label">Applications</div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-envelope stat-icon"></i>
                            <div class="stat-number"><?php echo $unread_count; ?></div>
                            <div class="stat-label">Unread Messages</div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-chart-line stat-icon"></i>
                            <div class="stat-number">Active</div>
                            <div class="stat-label">Status</div>
                        </div>
                    </div>

                    <div class="quick-actions-grid">
                        <a href="internship.php" class="quick-action">
                            <i class="fas fa-search"></i>
                            <h4>Browse Internships</h4>
                            <p>Find your perfect opportunity</p>
                        </a>
                        <a href="#" class="quick-action" onclick="showDashboardTab('profile')">
                            <i class="fas fa-edit"></i>
                            <h4>Edit Profile</h4>
                            <p>Update your information</p>
                        </a>
                        <a href="logout.php" class="quick-action" style="color: var(--danger);">
                            <i class="fas fa-sign-out-alt"></i>
                            <h4>Logout</h4>
                            <p>End your session</p>
                        </a>
                    </div>
                </div>

                <!-- Profile Tab -->
                <div id="profile-tab" class="dashboard-tab-content">
                    <div class="profile-section">
                        <h3 style="color: var(--primary); margin-bottom: 1.5rem; font-size: 1.4rem;">
                            <i class="fas fa-user" style="color: var(--accent); margin-right: 0.5rem;"></i>
                            Personal Information
                        </h3>
                        <form id="profileForm">
                            <div class="profile-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i>Full Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i>Email Address</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-phone"></i>Phone Number</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_phone); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-map-marker-alt"></i>Location</label>
                                    <input type="text" name="location" value="<?php echo htmlspecialchars($user_location); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-birthday-cake"></i>Date of Birth</label>
                                    <input type="date" name="dob" value="<?php echo htmlspecialchars($user_dob); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-user-tag"></i>Role</label>
                                    <input type="text" name="role" value="<?php echo htmlspecialchars(ucfirst($user_role)); ?>" readonly>
                                </div>
                            </div>

                            <div class="profile-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center;">
                                <button type="button" class="btn btn-primary" id="editBtn" onclick="toggleEdit()">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </button>
                                <button type="button" class="btn btn-success" id="saveBtn" onclick="saveProfile()" style="display: none;">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <button type="button" class="btn btn-secondary" id="cancelBtn" onclick="cancelEdit()" style="display: none;">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Applications Tab -->
                <div id="applications-tab" class="dashboard-tab-content">
                    <div class="profile-section">
                        <h3 style="color: var(--primary); margin-bottom: 1.5rem; font-size: 1.4rem;">
                            <i class="fas fa-file-alt" style="color: var(--accent); margin-right: 0.5rem;"></i>
                            My Applications
                        </h3>
                        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <i class="fas fa-file-alt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>No applications yet. Start exploring internships!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Login Modal (for non-logged users) -->
    <div id="loginModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Login Required</h3>
                <button class="modal-close" onclick="closeLoginModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <p id="modalMessage">Please login to access this feature</p>
                <div class="modal-actions">
                    <a href="login.html" class="modal-btn modal-btn-primary">Login</a>
                </div>
                <p class="modal-footer-text">
                    Don't have an account? <a href="registerstudent.html" class="modal-link">Create one now</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Search Section -->
        <section class="search-section">
            <div class="search-container">
                <input type="text" placeholder="What do you want to learn?" id="search-input">
                <button type="button" class="search-btn">🔍</button>
            </div>
        </section>

        <!-- Filter Section -->
        <section class="filter-section">
            <h2>Filter Your Search</h2>
            <form class="filter-form" method="GET" action="">
                <div class="filter-group">
                    <label for="course">Course</label>
                    <select id="course" name="course">
                        <option value="">All Courses</option>
                        <?php foreach ($courses_categories as $course): ?>
                            <option value="<?php echo htmlspecialchars($course); ?>" 
                                    <?php echo ($_GET['course'] ?? '') === $course ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $course))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="mode">Mode of Study</label>
                    <select id="mode" name="mode">
                        <option value="">All Modes</option>
                        <?php foreach ($available_modes as $mode): ?>
                            <option value="<?php echo htmlspecialchars($mode); ?>" 
                                    <?php echo ($_GET['mode'] ?? '') === $mode ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($mode)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="duration">Duration</label>
                    <select id="duration" name="duration">
                        <option value="">All Durations</option>
                        <?php foreach ($available_durations as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                    <?php echo ($_GET['duration'] ?? '') === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="filter-btn">
                        <span class="btn-text">Filter</span>
                    </button>
                    <a href="?" class="clear-btn">Clear All</a>
                </div>
            </form>
        </section>

        <!-- Main Content -->
        <main class="main-content">
            <div class="results-info">
                <div class="results-count">
                    <?php echo count($filtered_internships); ?> internship<?php echo count($filtered_internships) !== 1 ? 's' : ''; ?> found
                </div>
                <?php if ($isLoggedIn): ?>
                    <div class="enhanced-access-badge">
                        <i class="fas fa-crown"></i> Full Access
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($filtered_internships)): ?>
                <div class="no-results">
                    <h3>No internships found</h3>
                    <p>Try adjusting your filters to see more opportunities</p>
                </div>
            <?php else: ?>
                <div class="internships-grid" style="position: relative;">
                    <?php 
                    $card_index = 0;
                    $cards_per_row = 3; // Assuming 3 cards per row based on typical grid layout
                    $cards_before_blur = $cards_per_row * 3; // First 3 complete rows (9 cards)
                    
                    foreach ($filtered_internships as $course): 
                        $card_index++;
                        
                        // Only show blur overlay if user is NOT logged in and we have more than 9 cards
                        if (!$isLoggedIn && $card_index == $cards_before_blur + 1 && count($filtered_internships) > $cards_before_blur): ?>
                            </div>
                            
                            <!-- Container for blurred cards -->
                            <div class="internships-grid blurred-content" style="position: relative;">
                                <!-- Login prompt overlay for 4th row onwards -->
                                <div class="content-blur-overlay">
                                    <div class="blur-message">
                                        <div class="blur-content">
                                            <i class="fas fa-lock"></i>
                                            <h3>Login to See All Courses</h3>
                                            <p>Join thousands of students and access our complete library of courses and internships</p>
                                            <div class="blur-actions">
                                                <a href="login.html" class="blur-btn blur-btn-primary">Login Now</a>
                                                <a href="registerstudent.html" class="blur-btn blur-btn-secondary">Sign Up Free</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php endif; ?>
                        
                        <div class="internship-card" id="card-<?php echo htmlspecialchars($course['id']); ?>" onclick="<?php echo ($isLoggedIn || $card_index <= $cards_before_blur) ? 'showCourseDetails(' . $course['id'] . ')' : 'showLoginModal(\'view\', ' . $course['id'] . ')'; ?>">
                            <div class="mode-badge mode-<?php echo htmlspecialchars($course['mode']); ?>">
                                <?php echo ucfirst(htmlspecialchars($course['mode'])); ?>
                            </div>
                            
                            <div class="card-top">
                                <div class="card-company-logo">💼</div>
                                <div class="card-company-info">
                                    <div class="card-company-name"><?php echo htmlspecialchars($course['company_name']); ?></div>
                                    <div class="card-posted-date">Posted on <?php echo date('M d, Y', strtotime($course['posted_date'])); ?></div>
                                </div>
                                <div class="card-actions">
                                    <span class="action-icon share-btn" data-id="<?php echo htmlspecialchars($course['id']); ?>"><i class="fas fa-share-alt"></i></span>
                                </div>
                            </div>

                            <div class="card-header">
                                <h3 class="card-title"><?php echo htmlspecialchars($course['course_title']); ?></h3>
                            </div>

                            <div class="card-meta">
                                📚 <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $course['course_category']))); ?>
                                <div class="meta-item">⏱️ <?php echo htmlspecialchars($course['duration']); ?></div>
                                <div class="meta-item">📊 <?php echo htmlspecialchars(ucfirst($course['difficulty_level'])); ?></div>
                            </div>

                            <p class="card-description">
                                <?php echo htmlspecialchars($course['course_description']); ?>
                            </p>

                            <div class="card-skills">
                                <?php 
                                    $skills = explode(',', $course['skills']);
                                    foreach ($skills as $skill): 
                                ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                <?php endforeach; ?>
                            </div>

                            <div class="card-footer">
                                <div class="stipend">
                                    <?php 
                                        if ($course['course_price_type'] === 'free'|| $course['price_amount'] == '0.00') {
                                            echo 'Free';
                                        } else {
                                            echo '₹' . htmlspecialchars($course['price_amount']);
                                        }
                                    ?>
                                </div>
                                <button class="apply-btn" onclick="event.stopPropagation(); <?php echo $isLoggedIn ? 'applyToCourse(' . $course['id'] . ')' : 'showLoginModal(\'apply\', ' . $course['id'] . ')'; ?>">
                                    Apply Now
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (!$isLoggedIn && count($filtered_internships) > $cards_before_blur): ?>
                        </div> <!-- Close blurred internships-grid -->
                    <?php endif; ?>
                </div> <!-- Close main internships-grid -->
            <?php endif; ?>
        </main>
    </div>
    

    <script>
        // Pass PHP variables to JavaScript
        const isUserLoggedIn = <?php echo json_encode($isLoggedIn); ?>;
        const coursesData = <?php echo json_encode($filtered_internships); ?>;
        const userData = <?php echo json_encode([
            'id' => $user_id,
            'name' => $user_name,
            'email' => $user_email,
            'role' => $user_role,
            'profile_picture' => $user_profile_picture,
            'phone' => $user_phone,
            'location' => $user_location,
            'joined' => $user_joined,
            'dob' => $user_dob
        ]); ?>;
        const unreadMessagesCount = <?php echo json_encode($unread_count); ?>;

        // Profile Dashboard Functions (for logged users)
        <?php if ($isLoggedIn): ?>
        function openProfileDashboard() {
            const overlay = document.getElementById('profileDashboardOverlay');
            const dashboard = document.getElementById('profileDashboard');
            
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            setTimeout(() => {
                dashboard.classList.add('show');
            }, 10);
        }

        function closeProfileDashboard() {
            const overlay = document.getElementById('profileDashboardOverlay');
            const dashboard = document.getElementById('profileDashboard');
            
            dashboard.classList.remove('show');
            
            setTimeout(() => {
                overlay.style.display = 'none';
                document.body.style.overflow = 'auto';
            }, 300);
        }

        function showDashboardTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.dashboard-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            const selectedTab = document.getElementById(tabName + '-tab');
            if (selectedTab) {
                selectedTab.classList.add('active');
            }
            
            // Update tab buttons
            document.querySelectorAll('.dashboard-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            event.target.classList.add('active');
        }

        // Profile editing functionality
        let isEditing = false;
        let originalValues = {};

        function toggleEdit() {
            isEditing = true;
            const form = document.getElementById('profileForm');
            const editableInputs = form.querySelectorAll('input[name="phone"], input[name="location"], input[name="dob"]');
            const editBtn = document.getElementById('editBtn');
            const saveBtn = document.getElementById('saveBtn');
            const cancelBtn = document.getElementById('cancelBtn');

            // Store original values
            editableInputs.forEach(input => {
                originalValues[input.name] = input.value;
                input.removeAttribute('readonly');
                input.style.background = 'white';
                input.style.borderColor = 'var(--accent)';
            });

            editBtn.style.display = 'none';
            saveBtn.style.display = 'flex';
            cancelBtn.style.display = 'flex';
        }

        function saveProfile() {
            const saveBtn = document.getElementById('saveBtn');
            const originalContent = saveBtn.innerHTML;
            
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveBtn.disabled = true;
            
            setTimeout(() => {
                saveBtn.innerHTML = '<i class="fas fa-check"></i> Saved!';
                
                setTimeout(() => {
                    const form = document.getElementById('profileForm');
                    const editableInputs = form.querySelectorAll('input[name="phone"], input[name="location"], input[name="dob"]');
                    
                    editableInputs.forEach(input => {
                        input.setAttribute('readonly', true);
                        input.style.background = 'rgba(255, 255, 255, 0.5)';
                        input.style.borderColor = 'rgba(3, 89, 70, 0.1)';
                    });

                    document.getElementById('editBtn').style.display = 'flex';
                    document.getElementById('saveBtn').style.display = 'none';
                    document.getElementById('cancelBtn').style.display = 'none';
                    
                    saveBtn.innerHTML = originalContent;
                    saveBtn.disabled = false;
                    isEditing = false;
                    
                    showNotification('Profile updated successfully!', 'success');
                }, 1000);
            }, 1500);
        }

        function cancelEdit() {
            const form = document.getElementById('profileForm');
            const editableInputs = form.querySelectorAll('input[name="phone"], input[name="location"], input[name="dob"]');
            
            // Restore original values
            editableInputs.forEach(input => {
                if (originalValues[input.name] !== undefined) {
                    input.value = originalValues[input.name];
                }
                input.setAttribute('readonly', true);
                input.style.background = 'rgba(255, 255, 255, 0.5)';
                input.style.borderColor = 'rgba(3, 89, 70, 0.1)';
            });

            document.getElementById('editBtn').style.display = 'flex';
            document.getElementById('saveBtn').style.display = 'none';
            document.getElementById('cancelBtn').style.display = 'none';
            
            isEditing = false;
            originalValues = {};
        }
        <?php endif; ?>

        // Login Modal Functions (for non-logged users)
        function showLoginModal(action, courseId) {
            const modal = document.getElementById('loginModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            
            if (action === 'apply') {
                modalTitle.textContent = 'Login to Apply';
                modalMessage.textContent = 'You need to login to apply for this internship. Join thousands of students already learning!';
            } else if (action === 'view') {
                modalTitle.textContent = 'Login to View More';
                modalMessage.textContent = 'Login to view all available courses and internships. Unlock your learning potential!';
            } else {
                modalTitle.textContent = 'Login Required';
                modalMessage.textContent = 'Please login to access this feature and continue your learning journey.';
            }
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Add animation
            setTimeout(() => {
                modal.querySelector('.modal-content').style.transform = 'scale(1)';
                modal.querySelector('.modal-content').style.opacity = '1';
            }, 10);
        }

        function closeLoginModal() {
            const modal = document.getElementById('loginModal');
            const modalContent = modal.querySelector('.modal-content');
            
            modalContent.style.transform = 'scale(0.8)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }, 200);
        }

        // Search functionality
        document.getElementById('search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        document.querySelector('.search-btn').addEventListener('click', function() {
            performSearch();
        });

        function performSearch() {
            const searchTerm = document.getElementById('search-input').value.trim();
            if (searchTerm) {
                filterCardsBySearch(searchTerm);
            }
        }

        function filterCardsBySearch(searchTerm) {
            const cards = document.querySelectorAll('.internship-card');
            const searchLower = searchTerm.toLowerCase();
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.querySelector('.card-title').textContent.toLowerCase();
                const description = card.querySelector('.card-description').textContent.toLowerCase();
                const skills = Array.from(card.querySelectorAll('.skill-tag')).map(skill => skill.textContent.toLowerCase()).join(' ');
                const company = card.querySelector('.card-company-name').textContent.toLowerCase();

                if (title.includes(searchLower) || 
                    description.includes(searchLower) || 
                    skills.includes(searchLower) ||
                    company.includes(searchLower)) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            document.querySelector('.results-count').textContent = `${visibleCount} internship${visibleCount !== 1 ? 's' : ''} found`;

            const noResults = document.querySelector('.no-results');
            const internshipsGrid = document.querySelector('.internships-grid');
            
            if (visibleCount === 0) {
                if (!noResults) {
                    const noResultsDiv = document.createElement('div');
                    noResultsDiv.className = 'no-results';
                    noResultsDiv.innerHTML = `
                        <h3>No internships found</h3>
                        <p>Try different keywords or clear your search</p>
                    `;
                    internshipsGrid.parentNode.insertBefore(noResultsDiv, internshipsGrid.nextSibling);
                }
                internshipsGrid.style.display = 'none';
            } else {
                if (noResults) {
                    noResults.remove();
                }
                internshipsGrid.style.display = 'grid';
            }
        }

        // Clear search when input is empty
        document.getElementById('search-input').addEventListener('input', function() {
            if (this.value.trim() === '') {
                document.querySelectorAll('.internship-card').forEach(card => {
                    card.style.display = 'flex';
                });
                
                const totalCards = document.querySelectorAll('.internship-card').length;
                document.querySelector('.results-count').textContent = `${totalCards} internship${totalCards !== 1 ? 's' : ''} found`;
                
                const noResults = document.querySelector('.no-results');
                if (noResults) {
                    noResults.remove();
                }
                
                document.querySelector('.internships-grid').style.display = 'grid';
            }
        });

        // Share functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.share-btn')) {
                e.stopPropagation();
                const button = e.target.closest('.share-btn');
                const internshipId = button.getAttribute('data-id');
                const card = document.getElementById('card-' + internshipId);
                const title = card.querySelector('.card-title').textContent;
                const company = card.querySelector('.card-company-name').textContent;
                const shareText = `Check out this internship opportunity at ${company}: "${title}"!`;
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(shareText + ' ' + window.location.href)
                        .then(() => {
                            showNotification('Link copied to clipboard!');
                        })
                        .catch(err => {
                            showNotification('Could not copy link. Try again.');
                            console.error('Failed to copy text: ', err);
                        });
                } else {
                    const tempInput = document.createElement('textarea');
                    tempInput.value = shareText + ' ' + window.location.href;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    showNotification('Link copied to clipboard!');
                }
            }
        });

        // Course details and application functions
        function showCourseDetails(courseId) {
            if (isUserLoggedIn) {
                // Show detailed course information
                const course = coursesData.find(c => c.id == courseId);
                if (!course) return;

                // Create and show course details modal
                showCourseDetailsModal(course);
            } else {
                showLoginModal('view', courseId);
            }
        }

        function showCourseDetailsModal(course) {
            // Create course details modal dynamically
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.style.display = 'flex';
            
            const skills = course.skills.split(',').map(skill => skill.trim());
            const skillsHtml = skills.map(skill => `<span class="skill-tag">${skill}</span>`).join('');
            
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 600px;">
                    <div class="modal-header">
                        <h3>${course.course_title}</h3>
                        <button class="modal-close" onclick="this.closest('.modal-overlay').remove(); document.body.style.overflow = 'auto';">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="text-align: left;">
                        <div style="display: grid; gap: 1rem; margin-bottom: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="background: var(--primary); color: white; padding: 1rem; border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <h4 style="margin: 0; color: var(--primary);">${course.company_name}</h4>
                                    <p style="margin: 0; color: var(--text-secondary);">Posted on ${new Date(course.posted_date).toLocaleDateString()}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                            <div style="text-align: center; padding: 1rem; background: rgba(3, 89, 70, 0.1); border-radius: 10px;">
                                <i class="fas fa-clock" style="color: var(--primary); font-size: 1.2rem; margin-bottom: 0.5rem;"></i>
                                <div style="font-weight: 600; color: var(--primary);">${course.duration.replace('_', ' ')}</div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Duration</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: rgba(3, 89, 70, 0.1); border-radius: 10px;">
                                <i class="fas fa-map-marker-alt" style="color: var(--primary); font-size: 1.2rem; margin-bottom: 0.5rem;"></i>
                                <div style="font-weight: 600; color: var(--primary);">${course.mode.charAt(0).toUpperCase() + course.mode.slice(1)}</div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Mode</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: rgba(3, 89, 70, 0.1); border-radius: 10px;">
                                <i class="fas fa-signal" style="color: var(--primary); font-size: 1.2rem; margin-bottom: 0.5rem;"></i>
                                <div style="font-weight: 600; color: var(--primary);">${course.difficulty_level.charAt(0).toUpperCase() + course.difficulty_level.slice(1)}</div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">Difficulty</div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="color: var(--primary); margin-bottom: 0.5rem;">Description</h4>
                            <p style="color: var(--text-secondary); line-height: 1.6;">${course.course_description}</p>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="color: var(--primary); margin-bottom: 0.5rem;">Skills You'll Learn</h4>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">${skillsHtml}</div>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #f0f0f0;">
                            <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary);">
                                ${course.course_price_type === 'free' || course.price_amount == '0.00' ? 'Free' : '₹' + course.price_amount}
                            </div>
                            <button class="modal-btn modal-btn-primary" onclick="applyToCourse(${course.id}); this.closest('.modal-overlay').remove(); document.body.style.overflow = 'auto';" style="border: none; cursor: pointer;">
                                Apply Now
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            document.body.style.overflow = 'hidden';
            
            // Add click outside to close
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                    document.body.style.overflow = 'auto';
                }
            });
        }

        function applyToCourse(courseId) {
            if (isUserLoggedIn) {
                const course = coursesData.find(c => c.id == courseId);
                if (course) {
                    showNotification(`Application submitted for "${course.course_title}"!`, 'success');
                    // Here you can add actual application logic/API call
                }
            } else {
                showLoginModal('apply', courseId);
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background-color: ${type === 'success' ? 'var(--success)' : 'var(--primary-dark)'};
                color: white;
                padding: 12px 24px;
                border-radius: 25px;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.5s ease-in-out;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
                font-weight: 500;
            `;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.opacity = '1';
            }, 100);
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }

        // Close modals when clicking outside or with Escape key
        document.addEventListener('click', function(e) {
            const loginModal = document.getElementById('loginModal');
            if (e.target === loginModal) {
                closeLoginModal();
            }
            
            <?php if ($isLoggedIn): ?>
            const profileModal = document.getElementById('profileDashboardOverlay');
            if (e.target === profileModal) {
                closeProfileDashboard();
            }
            <?php endif; ?>
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLoginModal();
                <?php if ($isLoggedIn): ?>
                closeProfileDashboard();
                <?php endif; ?>
            }
        });

        // Auto-hide navbar functionality
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');

        function handleScroll() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                navbar.style.transform = 'translateY(-100%)';
                navbar.style.transition = 'transform 0.3s ease-in-out';
            } else if (scrollTop < lastScrollTop) {
                navbar.style.transform = 'translateY(0)';
                navbar.style.transition = 'transform 0.3s ease-in-out';
            }
            
            if (scrollTop <= 10) {
                navbar.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        }

        // Throttle scroll events for better performance
        function throttle(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            }
        }

        // Add scroll event listener with throttling
        window.addEventListener('scroll', throttle(handleScroll, 10));

        // Filter form enhancement
        document.querySelector('.filter-btn').addEventListener('click', function(e) {
            const btnText = this.querySelector('.btn-text');
            btnText.innerHTML = 'Filtering... <span class="loading"></span>';
        });

        // Initialize page based on login status
        document.addEventListener('DOMContentLoaded', function() {
            if (isUserLoggedIn) {
                console.log('User is logged in - full access granted');
                
                // Remove any blur overlays that might still exist
                const blurOverlays = document.querySelectorAll('.content-blur-overlay');
                blurOverlays.forEach(overlay => overlay.remove());
                
                // Remove blurred-content class from grids
                const blurredGrids = document.querySelectorAll('.blurred-content');
                blurredGrids.forEach(grid => {
                    grid.classList.remove('blurred-content');
                    grid.style.pointerEvents = 'auto';
                    grid.style.userSelect = 'auto';
                });
                
                // Enable all interactive elements
                const internshipCards = document.querySelectorAll('.internship-card');
                internshipCards.forEach(card => {
                    card.style.pointerEvents = 'auto';
                    card.style.userSelect = 'auto';
                });
            } else {
                console.log('User is not logged in - limited access');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>