<?php
// Start session to check login status
session_start();
// Prevent caching for proper session handling
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Enhanced session validation function (same as course.php)
function validateUserSession() {
    // Check for admin session first
    if (isset($_SESSION['admin_id'])) {
        return [
            'isLoggedIn' => true,
            'userType' => 'admin',
            'userId' => $_SESSION['admin_id'],
            'userName' => 'Admin User',
            'userRole' => 'admin'
        ];
    }
    
    // Check for regular user session
    if (isset($_SESSION['user_id']) || isset($_SESSION['logged_in']) || isset($_SESSION['email'])) {
        return validateRegularUser();
    }
    
    return [
        'isLoggedIn' => false,
        'userType' => null,
        'userId' => null,
        'userName' => '',
        'userRole' => ''
    ];
}

function validateRegularUser() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $user_conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($user_conn->connect_error) {
        return ['isLoggedIn' => false, 'userType' => null, 'userId' => null, 'userName' => '', 'userRole' => ''];
    }
    
    $user_data = null;
    
    // Check users table first
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $user_conn->prepare("SELECT id, name, email, profile_picture, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        }
        $stmt->close();
    }
    
    // Check students table if not found in users
    if (!$user_data && isset($_SESSION['email'])) {
        $email = $_SESSION['email'];
        $stmt = $user_conn->prepare("SELECT student_id as id, CONCAT(first_name, ' ', last_name) as name, email, profile_photo as profile_picture, 'student' as role FROM students WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        }
        $stmt->close();
    }
    
    $user_conn->close();
    
    if ($user_data) {
        return [
            'isLoggedIn' => true,
            'userType' => $user_data['role'],
            'userId' => $user_data['id'],
            'userName' => $user_data['name'],
            'userRole' => $user_data['role'],
            'userEmail' => $user_data['email'],
            'userProfilePicture' => $user_data['profile_picture'] ?? ''
        ];
    }
    
    return ['isLoggedIn' => false, 'userType' => null, 'userId' => null, 'userName' => '', 'userRole' => ''];
}

// Get user session data
$sessionData = validateUserSession();

// Extract variables for backward compatibility
$isLoggedIn = $sessionData['isLoggedIn'];
$user_id = $sessionData['userId'] ?? '';
$user_name = $sessionData['userName'] ?? 'User';
$user_email = $sessionData['userEmail'] ?? '';
$user_profile_picture = $sessionData['userProfilePicture'] ?? '';
$user_role = $sessionData['userRole'] ?? 'student';

// Additional user data for existing code compatibility
$user_phone = '';
$user_location = '';
$user_joined = '';
$user_dob = '';
$unread_count = 0;

// Get additional user details and unread messages only if user is logged in and not admin
if ($isLoggedIn && $user_id && $user_role !== 'admin') {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $user_conn = new mysqli($servername, $username, $password, $dbname);
    
    if (!$user_conn->connect_error) {
        // Get additional user details
        if ($user_role === 'student') {
            $user_stmt = $user_conn->prepare("SELECT contact as phone, '' as location, created_at, dob FROM students WHERE student_id = ?");
            $user_stmt->bind_param("s", $user_id);
        } else {
            $user_stmt = $user_conn->prepare("SELECT phone, location, created_at, dob FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $user_id);
        }
        
        if (isset($user_stmt)) {
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            if ($user_result->num_rows > 0) {
                $additional_data = $user_result->fetch_assoc();
                $user_phone = $additional_data['phone'] ?? '';
                $user_location = $additional_data['location'] ?? '';
                $user_joined = $additional_data['created_at'] ?? '';
                $user_dob = $additional_data['dob'] ?? '';
            }
            $user_stmt->close();
        }
        
        // Get unread messages count
        if ($user_role === 'student') {
            $unread_stmt = $user_conn->prepare("SELECT COUNT(*) as unread_count FROM student_messages WHERE receiver_type = 'student' AND receiver_id = ? AND is_read = FALSE");
            $unread_stmt->bind_param("s", $user_id);
        } else {
            $unread_stmt = $user_conn->prepare("SELECT COUNT(*) as unread_count FROM user_messages WHERE receiver_id = ? AND is_read = FALSE");
            $unread_stmt->bind_param("i", $user_id);
        }
        
        if (isset($unread_stmt)) {
            $unread_stmt->execute();
            $unread_result = $unread_stmt->get_result();
            if ($unread_result) {
                $unread_data = $unread_result->fetch_assoc();
                $unread_count = $unread_data['unread_count'] ?? 0;
            }
            $unread_stmt->close();
        }
        
        $user_conn->close();
    }
}

// Dashboard redirect function
function redirectToDashboard($userRole) {
    switch($userRole) {
        case 'admin':
            return 'admin_dashboard.php';
        case 'company':
            return 'company_dashboard.php';
        case 'student':
        default:
            return 'student_dashboard.php';
    }
}
// Handle AJAX form submission for course applications
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['is_ajax'])) {
    header('Content-Type: application/json');

    // Validate user session first
    $sessionData = validateUserSession();
    if (!$sessionData['isLoggedIn']) {
        echo json_encode(['status' => 'error', 'message' => "Please log in to apply for courses."]);
        exit();
    }

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        echo json_encode(['status' => 'error', 'message' => "Connection failed: " . $conn->connect_error]);
        exit();
    }

    // Get and sanitize form data
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : null;
    $name = isset($_POST['name']) ? trim($conn->real_escape_string($_POST['name'])) : '';
    $email = isset($_POST['email']) ? trim($conn->real_escape_string($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? trim($conn->real_escape_string($_POST['phone'])) : '';
    $learning_objective = isset($_POST['learning_objective']) ? trim($_POST['learning_objective']) : '';
    $motivation = isset($_POST['motivation']) ? trim($conn->real_escape_string($_POST['motivation'])) : '';
    $user_id = $sessionData['userId'];

    // Validate required fields
    if (empty($course_id) || empty($name) || empty($email) || empty($learning_objective)) {
        echo json_encode(['status' => 'error', 'message' => "Please fill in all required fields."]);
        $conn->close();
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => "Please enter a valid email address."]);
        $conn->close();
        exit();
    }

    // Validate learning_objective against database enum values
    $valid_objectives = [
        'job_preparation', 'interview_skills', 'certification', 'skill_enhancement',
        'career_switch', 'academic_project', 'personal_interest', 'startup_preparation'
    ];

    if (!in_array($learning_objective, $valid_objectives)) {
        echo json_encode(['status' => 'error', 'message' => "Invalid learning objective selected."]);
        $conn->close();
        exit();
    }

    // Check if user already applied for this course
    $check_sql = "SELECT id FROM course_applications WHERE course_id = ? AND email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $course_id, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => "You have already applied for this course. Please check your email for updates."]);
        $check_stmt->close();
        $conn->close();
        exit();
    }
    $check_stmt->close();

    // Use prepared statement to insert into course_applications table
    // Note: 'motivation' field is mapped to 'cover_letter' in database
    $sql = "INSERT INTO course_applications (
        student_id, course_id, applicant_name, email, phone, learning_objective, 
        cover_letter, application_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => "Error preparing statement: " . $conn->error]);
        $conn->close();
        exit();
    }

    // Bind parameters - note the correct order and types
    $stmt->bind_param("sisssss", $user_id, $course_id, $name, $email, $phone, $learning_objective, $motivation);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => "Successfully applied for the course! You will receive a confirmation email shortly. The company will review your application and contact you soon."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Error processing application: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit();
}

// Main database connection for course details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the course ID from the URL
$course_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
$course_data = null;
$error_message = null;

if ($course_id) {
    // Prepare and execute a query to fetch the course details from course table
    $sql = "SELECT * FROM course WHERE id = ? AND course_status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $course_data = $result->fetch_assoc();
    } else {
        $error_message = "Course not found or no longer available.";
    }
    $stmt->close();
} else {
    $error_message = "No course ID specified.";
}

// Function to format price display
function formatPrice($priceType, $priceAmount) {
    if ($priceType === 'free' || $priceType === 'Free' || $priceAmount == '0.00') {
        return 'Free';
    }
    return '₹' . number_format($priceAmount, 0);
}

// Function to get enrollment stats (using database data when available)
function getEnrollmentStats($courseData) {
    return [
        'students_trained' => $courseData['students_trained'] ?: '5,000+',
        'job_placement_rate' => $courseData['job_placement_rate'] ? $courseData['job_placement_rate'] . '%' : '73%',
        'student_rating' => $courseData['student_rating'] ? $courseData['student_rating'] . '/5' : '4.5/5',
        'enrollment_deadline' => $courseData['enrollment_deadline'] ? date('M j, Y', strtotime($courseData['enrollment_deadline'])) : 'Sep 30, 2025'
    ];
}

// Function to parse skills taught
function parseSkillsTaught($skillsString) {
    if (empty($skillsString)) {
        return [
            'technical' => [],
            'professional' => []
        ];
    }
    
    $skills = ['technical' => [], 'professional' => []];
    
    // Split by | for different categories
    $categories = explode('|', $skillsString);
    
    foreach ($categories as $category) {
        $category = trim($category);
        if (empty($category)) continue;
        
        if (stripos($category, 'technical') !== false) {
            $skillsList = explode(':', $category, 2);
            if (count($skillsList) > 1) {
                $skills['technical'] = array_map('trim', explode(',', $skillsList[1]));
            }
        } elseif (stripos($category, 'professional') !== false) {
            $skillsList = explode(':', $category, 2);
            if (count($skillsList) > 1) {
                $skills['professional'] = array_map('trim', explode(',', $skillsList[1]));
            }
        }
    }
    
    return $skills;
}

// Function to parse what you will learn
function parseWhatYouWillLearn($learningString) {
    if (empty($learningString)) {
        return [];
    }
    
    $items = [];
    $parts = explode('|', $learningString);
    
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;
        
        $colonPos = strpos($part, ':');
        if ($colonPos !== false) {
            $title = trim(substr($part, 0, $colonPos));
            $description = trim(substr($part, $colonPos + 1));
            $items[] = ['title' => $title, 'description' => $description];
        } else {
            $items[] = ['title' => $part, 'description' => 'Master the fundamentals and advanced concepts in this area.'];
        }
    }
    
    return $items;
}

// Function to parse program structure
function parseProgramStructure($structureString) {
    if (empty($structureString)) {
        return [];
    }
    
    $phases = [];
    $parts = explode('|', $structureString);
    
    foreach ($parts as $index => $part) {
        $part = trim($part);
        if (empty($part)) continue;
        
        $colonPos = strpos($part, ':');
        if ($colonPos !== false) {
            $title = trim(substr($part, 0, $colonPos));
            $description = trim(substr($part, $colonPos + 1));
        } else {
            $title = "Phase " . ($index + 1);
            $description = $part;
        }
        
        // Extract week information from title if present
        $weekLabel = "Week " . (($index * 3) + 1) . "-" . (($index * 3) + 3);
        if (preg_match('/week\s+(\d+(?:-\d+)?)/i', $title, $matches)) {
            $weekLabel = "Week " . $matches[1];
            $title = trim(preg_replace('/week\s+\d+(?:-\d+)?\s*/i', '', $title));
        }
        
        $phases[] = [
            'week_label' => $weekLabel,
            'title' => $title,
            'description' => $description
        ];
    }
    
    return $phases;
}

// Function to parse prerequisites
function parsePrerequisites($prereqString) {
    if (empty($prereqString)) {
        return [];
    }
    
    $prereqs = [];
    $parts = explode('|', $prereqString);
    
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;
        
        $colonPos = strpos($part, ':');
        if ($colonPos !== false) {
            $title = trim(substr($part, 0, $colonPos));
            $description = trim(substr($part, $colonPos + 1));
        } else {
            $title = $part;
            $description = "Important requirement for the course.";
        }
        
        $prereqs[] = ['title' => $title, 'description' => $description];
    }
    
    return $prereqs;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $course_data ? htmlspecialchars($course_data['course_title']) : 'Course Not Found'; ?> | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #035946;
            --primary-light: #0a7058;
            --primary-dark: #023d32;
            --secondary: #2e3944;
            --accent: #4ecdc4;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --text-muted: #95a5a6;
            --bg-light: #f8fafc;
            --white: #ffffff;
            --border: #e5e7eb;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 12px rgba(3, 89, 70, 0.08);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            --transition: all 0.2s ease;
            --text-dark: #1f2937;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Enhanced Navigation */
        .navbar {
            position: sticky;
            top: 0;
            width: 100%;
            padding: 1rem 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(3, 89, 70, 0.1);
            z-index: 1000;
            transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary);
            gap: 0.5rem;
        }

        .nav-logo {
            height: 50px;
            width: auto;
            transition: var(--transition);
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            padding: 0.5rem 0;
        }

        .nav-link:hover {
            color: var(--primary);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-primary);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-cta {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Profile Navigation */
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
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--primary);
            font-weight: 500;
            box-shadow: var(--shadow-md);
        }

        .profile-trigger:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .profile-avatar-container {
            position: relative;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
            transition: var(--transition);
        }

        .profile-avatar.default {
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.1rem;
        }

        .profile-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.9rem;
            line-height: 1.2;
        }

        .profile-id {
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
            color: var(--text-secondary);
            font-size: 0.75rem;
            line-height: 1;
        }

        .message-badge {
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
            position: absolute;
            top: -5px;
            right: -5px;
        }

        /* Standard Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: var(--white);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Menu Toggle for Mobile */
        .menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 0.5rem;
        }

        .menu-toggle span {
            width: 25px;
            height: 3px;
            background: var(--primary);
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 2px;
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .breadcrumb a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        /* Course Header */
        .course-header {
            background: var(--white);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .course-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .course-provider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .provider-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
        }

        .provider-info h3 {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .provider-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Course Tags */
        .course-tags {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .tag-duration {
            background: rgba(3, 89, 70, 0.1);
            color: var(--primary);
        }

        .tag-price {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
        }

        .tag-spots {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
        }

        .tag-level {
            background: rgba(243, 156, 18, 0.1);
            color: var(--warning);
        }

        /* Course Description */
        .course-description {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        /* Stats Section */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--white);
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        /* Main Content */
        .main-content {
            background: var(--white);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow);
            height: fit-content;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .learning-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }

        .learning-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .item-number {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .item-content h4 {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .item-content p {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Skills Taught Section */
        .skills-section {
            margin-top: 3rem;
        }

        .skills-categories {
            display: grid;
            gap: 2rem;
        }

        .skills-category {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 2rem;
        }

        .skills-category h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }

        .skill-card {
            background: var(--white);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
            border: 2px solid transparent;
        }

        .skill-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .skill-card.technical {
            border-left: 4px solid var(--primary);
        }

        .skill-card.professional {
            border-left: 4px solid var(--accent);
        }

        .skill-card i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .skill-card.professional i {
            color: var(--accent);
        }

        .skill-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        /* Skills Taught Section (Simple Cards) */
        .skills-taught-section {
            margin-top: 3rem;
        }

        .skills-taught-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .skill-taught-card {
            background: var(--white);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem 1rem;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow);
        }

        .skill-taught-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .skill-taught-card i {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .skill-taught-card span {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            line-height: 1.3;
        }

        /* Program Structure */
        .program-structure {
            margin-top: 3rem;
        }

        .program-phases {
            display: grid;
            gap: 1.5rem;
        }

        .phase-card {
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem;
            background: var(--bg-light);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            transition: transform 0.2s ease;
        }

        .phase-card:hover {
            transform: translateX(5px);
        }

        .phase-weeks {
            background: var(--primary);
            color: var(--white);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            min-width: 100px;
            font-weight: 600;
        }

        .phase-content h4 {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .phase-content p {
            color: var(--text-secondary);
        }

        /* Prerequisites */
        .prerequisites {
            margin-top: 3rem;
        }

        .prereq-list {
            display: grid;
            gap: 1rem;
        }

        .prereq-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 8px;
            transition: transform 0.2s ease;
        }

        .prereq-item:hover {
            transform: translateX(5px);
        }

        .prereq-icon {
            width: 50px;
            height: 50px;
            background: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.25rem;
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        .application-form h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            text-align: center;
        }

        .application-subtitle {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-label.required::after {
            content: ' *';
            color: var(--danger);
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--text-primary);
            background: var(--white);
            transition: border-color 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(3, 89, 70, 0.1);
        }

        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Quick Facts */
        .quick-facts h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .fact-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .fact-item:last-child {
            border-bottom: none;
        }

        .fact-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .fact-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        /* Login Required */
        .login-required {
            text-align: center;
            padding: 2rem;
        }

        .login-icon {
            width: 80px;
            height: 80px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--white);
            font-size: 2rem;
        }

        .btn-login {
            background: var(--primary);
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .btn-register {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        /* Application Success State */
        .application-success {
            text-align: center;
            padding: 2rem;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--white);
            font-size: 2rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .menu-toggle {
                display: flex;
            }

            .profile-info {
                display: none;
            }

            .profile-trigger {
                padding: 0.5rem;
            }

            .profile-avatar {
                width: 34px;
                height: 34px;
            }

            .main-container {
                padding: 1rem;
            }

            .course-title {
                font-size: 1.75rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-section {
                grid-template-columns: repeat(2, 1fr);
            }

            .course-tags {
                flex-direction: column;
                align-items: flex-start;
            }

            .nav-container {
                padding: 0 1rem;
            }
            
            .nav-logo {
                height: 45px;
            }

            .skills-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .skills-taught-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            .phase-card {
                flex-direction: column;
                text-align: center;
            }

            .phase-weeks {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <img src="nextternnavbar.png" alt="Nexttern Logo" class="nav-logo">
            </a>
            
            <div class="menu-toggle" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="internship.php" class="nav-link">Internships</a></li>
                <li><a href="course.php" class="nav-link active">Courses</a></li>
                <li><a href="aboutus.php" class="nav-link">About</a></li>
                <li><a href="contactus.php" class="nav-link">Contact</a></li>
            </ul>
            
            <div class="nav-cta">
                <?php if ($isLoggedIn): ?>
                    <div class="nav-profile">
                        <button class="profile-trigger" onclick="window.location.href='<?php echo redirectToDashboard($user_role); ?>'">
                            <div class="profile-avatar-container">
                                <?php if (!empty($user_profile_picture) && file_exists($user_profile_picture)): ?>
                                    <img src="<?php echo htmlspecialchars($user_profile_picture); ?>?v=<?php echo time(); ?>" alt="Profile" class="profile-avatar">
                                <?php else: ?>
                                    <div class="profile-avatar default">
                                        <?php echo strtoupper(substr($user_name ?: 'U', 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?php echo htmlspecialchars($user_name ?: 'User'); ?></span>
                                <span class="profile-id">ID: <?php echo htmlspecialchars($user_id ?: 'N/A'); ?></span>
                            </div>
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

    <div class="main-container">
        <?php if ($course_data): ?>
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php">Home</a>
                <i class="fas fa-chevron-right"></i>
                <a href="course.php">Courses</a>
                <i class="fas fa-chevron-right"></i>
                <span><?php echo htmlspecialchars($course_data['course_title']); ?></span>
            </div>

            <!-- Course Header -->
            <div class="course-header">
                <h1 class="course-title"><?php echo htmlspecialchars($course_data['course_title']); ?></h1>
                
                <div class="course-provider">
                    <div class="provider-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="provider-info">
                        <h3><?php echo htmlspecialchars($course_data['company_name']); ?></h3>
                        <p>Education • <?php echo htmlspecialchars($course_data['course_category'] ?? 'Technology'); ?></p>
                    </div>
                </div>

                <div class="course-tags">
                    <?php if ($course_data['duration']): ?>
                    <div class="tag tag-duration">
                        <i class="fas fa-clock"></i>
                        <?php echo htmlspecialchars($course_data['duration']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="tag tag-price">
                        <i class="fas fa-money-bill"></i>
                        <?php echo formatPrice($course_data['course_price_type'], $course_data['price_amount']); ?>
                    </div>
                    
                    <?php if ($course_data['max_students'] > 0): ?>
                    <div class="tag tag-spots">
                        <i class="fas fa-users"></i>
                        <?php echo htmlspecialchars($course_data['max_students']); ?> Spots Available
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($course_data['difficulty_level']): ?>
                    <div class="tag tag-level">
                        <i class="fas fa-signal"></i>
                        <?php echo htmlspecialchars(ucfirst($course_data['difficulty_level'])); ?> Level
                    </div>
                    <?php endif; ?>
                </div>

                <p class="course-description">
                    <?php echo htmlspecialchars($course_data['course_description']); ?>
                </p>
            </div>

            <!-- Stats Section -->
            <?php $stats = getEnrollmentStats($course_data); ?>
            <div class="stats-section">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['enrollment_deadline']; ?></div>
                    <div class="stat-label">Enrollment Deadline</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['students_trained']; ?></div>
                    <div class="stat-label">Students Trained</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['job_placement_rate']; ?></div>
                    <div class="stat-label">Job Placement Rate</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['student_rating']; ?></div>
                    <div class="stat-label">Student Rating</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Main Content -->
                <div class="main-content">
                    <?php 
                    $learning_items = parseWhatYouWillLearn($course_data['what_you_will_learn']);
                    if (!empty($learning_items)): 
                    ?>
                    <!-- What You'll Learn -->
                    <section>
                        <h2 class="section-title">
                            <i class="fas fa-graduation-cap"></i>
                            What You'll Learn
                        </h2>
                        
                        <?php foreach ($learning_items as $index => $item): ?>
                        <div class="learning-item">
                            <div class="item-number"><?php echo $index + 1; ?></div>
                            <div class="item-content">
                                <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                                <p><?php echo htmlspecialchars($item['description']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </section>
                    <?php endif; ?>

                    <?php 
                    $skills = parseSkillsTaught($course_data['skills_taught']);
                    if (!empty($skills['technical']) || !empty($skills['professional'])): 
                    ?>
                    <!-- Skills Taught Section -->
                    <section class="skills-section">
                        <h2 class="section-title">
                            <i class="fas fa-tools"></i>
                            Skills You'll Master
                        </h2>
                        
                        <div class="skills-categories">
                            <?php if (!empty($skills['technical'])): ?>
                            <div class="skills-category">
                                <h4>
                                    <i class="fas fa-code"></i>
                                    Technical Skills
                                </h4>
                                <div class="skills-grid">
                                    <?php foreach ($skills['technical'] as $skill): ?>
                                    <div class="skill-card technical">
                                        <i class="fas fa-laptop-code"></i>
                                        <div class="skill-name"><?php echo htmlspecialchars(trim($skill)); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($skills['professional'])): ?>
                            <div class="skills-category">
                                <h4>
                                    <i class="fas fa-user-tie"></i>
                                    Professional Skills
                                </h4>
                                <div class="skills-grid">
                                    <?php foreach ($skills['professional'] as $skill): ?>
                                    <div class="skill-card professional">
                                        <i class="fas fa-handshake"></i>
                                        <div class="skill-name"><?php echo htmlspecialchars(trim($skill)); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php 
                    $program_phases = parseProgramStructure($course_data['program_structure']);
                    if (!empty($program_phases)): 
                    ?>
                    <!-- Program Structure -->
                    <section class="program-structure">
                        <h2 class="section-title">
                            <i class="fas fa-list-check"></i>
                            Program Structure
                        </h2>
                        
                        <div class="program-phases">
                            <?php foreach ($program_phases as $phase): ?>
                            <div class="phase-card">
                                <div class="phase-weeks"><?php echo htmlspecialchars($phase['week_label']); ?></div>
                                <div class="phase-content">
                                    <h4><?php echo htmlspecialchars($phase['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($phase['description']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php else: ?>
                    <!-- Default Program Structure -->
                    <section class="program-structure">
                        <h2 class="section-title">
                            <i class="fas fa-list-check"></i>
                            Program Structure
                        </h2>
                        
                        <div class="program-phases">
                            <div class="phase-card">
                                <div class="phase-weeks">Week 1-3</div>
                                <div class="phase-content">
                                    <h4>Foundation Phase</h4>
                                    <p>Introduction to core concepts, basic principles, and fundamental skills.</p>
                                </div>
                            </div>
                            
                            <div class="phase-card">
                                <div class="phase-weeks">Week 4-6</div>
                                <div class="phase-content">
                                    <h4>Skill Development</h4>
                                    <p>Hands-on practice with real-world projects and advanced techniques.</p>
                                </div>
                            </div>
                            
                            <div class="phase-card">
                                <div class="phase-weeks">Week 7-9</div>
                                <div class="phase-content">
                                    <h4>Advanced Applications</h4>
                                    <p>Complex problem-solving, industry best practices, and specialization.</p>
                                </div>
                            </div>
                            
                            <div class="phase-card">
                                <div class="phase-weeks">Week 10-12</div>
                                <div class="phase-content">
                                    <h4>Final Projects</h4>
                                    <p>Complete portfolio projects, certification preparation, and career guidance.</p>
                                </div>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php 
                    $prerequisites = parsePrerequisites($course_data['prerequisites']);
                    if (!empty($prerequisites)): 
                    ?>
                    <!-- Prerequisites -->
                    <section class="prerequisites">
                        <h2 class="section-title">
                            <i class="fas fa-list-check"></i>
                            Prerequisites & Requirements
                        </h2>
                        
                        <div class="prereq-list">
                            <?php foreach ($prerequisites as $index => $prereq): ?>
                            <div class="prereq-item">
                                <div class="prereq-icon">
                                    <?php 
                                    $icons = ['fas fa-laptop', 'fas fa-brain', 'fas fa-clock', 'fas fa-book', 'fas fa-graduation-cap'];
                                    echo '<i class="' . $icons[$index % count($icons)] . '"></i>';
                                    ?>
                                </div>
                                <div>
                                    <h4><?php echo htmlspecialchars($prereq['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($prereq['description']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php 
                    // Parse skills taught as comma-separated values
                    if (!empty($course_data['skills_taught'])): 
                        $skills_list = array_map('trim', explode(',', $course_data['skills_taught']));
                    ?>
                    <!-- Skills Taught -->
                    <section class="skills-taught-section">
                        <h2 class="section-title">
                            <i class="fas fa-tools"></i>
                            Skills Taught
                        </h2>
                        
                        <div class="skills-taught-grid">
                            <?php foreach ($skills_list as $skill): ?>
                            <?php if (!empty($skill)): ?>
                            <div class="skill-taught-card">
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo htmlspecialchars($skill); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <?php if ($isLoggedIn): ?>
                    <!-- Application Form -->
                    <div class="sidebar-card application-form">
                        <h3>Apply for This Course</h3>
                        <p class="application-subtitle">Start your journey in <?php echo htmlspecialchars($course_data['course_category'] ?? 'Technology'); ?> today</p>
                        
                        <div id="message_area"></div>
                        
                        <form id="enrollmentForm">
                            <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_data['id']); ?>">
                            
                            <div class="form-group">
                                <label class="form-label required" for="name">Full Name</label>
                                <input type="text" id="name" name="name" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_name); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required" for="email">Email Address</label>
                                <input type="email" id="email" name="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_email); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($user_phone); ?>">
                            </div>
                            
                         <div class="form-group">
    <label class="form-label required" for="learning_objective">Learning Objective</label>
    <select id="learning_objective" name="learning_objective" class="form-select" required>
        <option value="">Select your primary goal</option>
        <option value="job_preparation">Job Preparation</option>
        <option value="interview_skills">Interview Skills</option>
        <option value="certification">Professional Certification</option>
        <option value="skill_enhancement">Skill Enhancement</option>
        <option value="career_switch">Career Switch</option>
        <option value="academic_project">Academic Project</option>
        <option value="personal_interest">Personal Interest</option>
        <option value="startup_preparation">Startup Preparation</option>
    </select>
</div>
                            
                            <div class="form-group">
                                <label class="form-label" for="motivation">Why are you interested?</label>
                                <textarea id="motivation" name="motivation" class="form-textarea" 
                                          placeholder="Tell us about your motivation and what you hope to achieve from this course..."></textarea>
                            </div>
                            
                            <button type="submit" id="submit_button" class="btn-submit">
                                <i class="fas fa-rocket"></i>
                                Submit Application
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <!-- Login Required -->
                    <div class="sidebar-card">
                        <div class="login-required">
                            <div class="login-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h3 style="margin-bottom: 1rem;">Ready to Start Learning?</h3>
                            <p style="margin-bottom: 1.5rem; color: var(--text-secondary);">
                                Join thousands of students mastering new skills through our professional courses.
                            </p>
                            <a href="login.html" class="btn-login">
                                <i class="fas fa-sign-in-alt"></i>
                                Login to Enroll
                            </a>
                            <br>
                            <a href="registerstudent.html" class="btn-register">
                                New here? Create your free account
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Facts -->
                    <div class="sidebar-card quick-facts">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            Quick Facts
                        </h3>
                        
                        <?php if ($course_data['start_date']): ?>
                        <div class="fact-item">
                            <span class="fact-label">
                                <i class="fas fa-calendar-start"></i>
                                Start Date:
                            </span>
                            <span class="fact-value"><?php echo date('M j, Y', strtotime($course_data['start_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($course_data['duration']): ?>
                        <div class="fact-item">
                            <span class="fact-label">
                                <i class="fas fa-clock"></i>
                                Duration:
                            </span>
                            <span class="fact-value"><?php echo htmlspecialchars($course_data['duration']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($course_data['mode']): ?>
                        <div class="fact-item">
                            <span class="fact-label">
                                <i class="fas fa-desktop"></i>
                                Format:
                            </span>
                            <span class="fact-value"><?php echo htmlspecialchars(ucfirst($course_data['mode'])); ?> <?php echo $course_data['course_format'] ? '+ ' . htmlspecialchars($course_data['course_format']) : '+ Live Sessions'; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($course_data['certificate_provided']): ?>
                        <div class="fact-item">
                            <span class="fact-label">
                                <i class="fas fa-certificate"></i>
                                Certificate:
                            </span>
                            <span class="fact-value">Industry Recognized</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($course_data['job_placement_support']): ?>
                        <div class="fact-item">
                            <span class="fact-label">
                                <i class="fas fa-handshake"></i>
                                Job Support:
                            </span>
                            <span class="fact-value">Placement Assistance</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Error State -->
            <div class="course-header" style="text-align: center;">
                <div style="width: 120px; height: 120px; border-radius: 50%; background: rgba(231, 76, 60, 0.1); 
                           display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; 
                           color: var(--danger); font-size: 3rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1 style="color: var(--danger); margin-bottom: 1rem;"><?php echo htmlspecialchars($error_message); ?></h1>
                <p style="margin-bottom: 2rem; color: var(--text-secondary);">
                    Please check the URL and try again, or browse our available courses.
                </p>
                <a href="course.php" class="btn-login">
                    <i class="fas fa-search"></i> Browse All Courses
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if ($isLoggedIn): ?>
        // Enhanced Form Submission with duplicate prevention
        document.getElementById('enrollmentForm').addEventListener('submit', function(event) {
            event.preventDefault();
            
            const submitButton = document.getElementById('submit_button');
            const messageArea = document.getElementById('message_area');
            const form = this;

            // Check if form is already being submitted
            if (submitButton.disabled) {
                return;
            }

            // Validate required fields
            const requiredFields = ['name', 'email', 'learning_objective'];
            let hasErrors = false;
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value.trim()) {
                    hasErrors = true;
                    field.style.borderColor = 'var(--danger)';
                } else {
                    field.style.borderColor = 'var(--border)';
                }
            });

            if (hasErrors) {
                messageArea.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        Please fill in all required fields.
                    </div>
                `;
                messageArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // Disable button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Processing Application...`;
            messageArea.innerHTML = '';

            const formData = new FormData(form);
            formData.append('is_ajax', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    messageArea.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            ${data.message}
                        </div>
                    `;
                    
                    // Replace form with success message
                    form.innerHTML = `
                        <div class="application-success">
                            <div class="success-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 style="margin-bottom: 1rem; color: var(--success);">Application Submitted!</h3>
                            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                                Thank you for applying. The company will review your application and contact you soon.
                            </p>
                            <a href="course.php" class="btn-submit" style="background: var(--success); text-decoration: none;">
                                <i class="fas fa-search"></i>
                                Browse More Courses
                            </a>
                        </div>
                    `;
                    
                } else {
                    messageArea.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            ${data.message}
                        </div>
                    `;
                    submitButton.disabled = false;
                    submitButton.innerHTML = `<i class="fas fa-rocket"></i> Submit Application`;
                }
                
                // Scroll to message
                messageArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            })
            .catch(error => {
                console.error('Error:', error);
                messageArea.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        Network error occurred. Please check your connection and try again.
                    </div>
                `;
                submitButton.disabled = false;
                submitButton.innerHTML = `<i class="fas fa-rocket"></i> Submit Application`;
                
                messageArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        // Real-time validation for required fields
        const requiredFields = ['name', 'email', 'learning_objective'];
        requiredFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.style.borderColor = 'var(--danger)';
                } else {
                    this.style.borderColor = 'var(--border)';
                }
            });
            
            field.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = 'var(--border)';
                }
            });
        });
        <?php endif; ?>

        // Mobile menu toggle function
        function toggleMobileMenu() {
            const navMenu = document.querySelector('.nav-menu');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (navMenu.style.display === 'flex') {
                navMenu.style.display = 'none';
                menuToggle.classList.remove('active');
            } else {
                navMenu.style.display = 'flex';
                navMenu.style.flexDirection = 'column';
                navMenu.style.position = 'absolute';
                navMenu.style.top = '100%';
                navMenu.style.left = '0';
                navMenu.style.right = '0';
                navMenu.style.background = 'var(--white)';
                navMenu.style.boxShadow = 'var(--shadow-lg)';
                navMenu.style.padding = '1rem';
                navMenu.style.zIndex = '999';
                menuToggle.classList.add('active');
            }
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navMenu = document.querySelector('.nav-menu');
            const menuToggle = document.querySelector('.menu-toggle');
            const navbar = document.querySelector('.navbar');
            
            if (!navbar.contains(event.target) && navMenu.style.display === 'flex') {
                navMenu.style.display = 'none';
                menuToggle.classList.remove('active');
            }
        });

        // Smooth scrolling and navbar effects
        document.addEventListener('DOMContentLoaded', function() {
            document.documentElement.style.scrollBehavior = 'smooth';
            
            // Add scroll effect to navbar
            let lastScrollTop = 0;
            const navbar = document.querySelector('.navbar');
            
            window.addEventListener('scroll', function() {
                let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                
                if (scrollTop > lastScrollTop && scrollTop > 100) {
                    // Scrolling down
                    navbar.style.transform = 'translateY(-100%)';
                } else {
                    // Scrolling up
                    navbar.style.transform = 'translateY(0)';
                }
                
                // Add shadow when scrolled
                if (scrollTop > 10) {
                    navbar.style.boxShadow = 'var(--shadow-lg)';
                } else {
                    navbar.style.boxShadow = '0 1px 3px rgba(3, 89, 70, 0.1)';
                }
                
                lastScrollTop = scrollTop;
            });

            // Add hover effects to skill cards
            const skillCards = document.querySelectorAll('.skill-card');
            skillCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-2px) scale(1)';
                });
            });
        });
    </script>
</body>
</html>