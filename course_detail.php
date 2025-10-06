<?php
// Start session to check login status
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Enhanced session validation function with company support
function validateUserSession() {
    // Check admin session
    if (isset($_SESSION['admin_id'])) {
        return [
            'isLoggedIn' => true,
            'userType' => 'admin',
            'userId' => $_SESSION['admin_id'],
            'userName' => 'Admin User',
            'userRole' => 'admin'
        ];
    }
    
    // Check company session
    if (isset($_SESSION['company_id'])) {
        return validateCompanyUser();
    }
    
    // Check student/regular user session
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

function validateCompanyUser() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $company_conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($company_conn->connect_error) {
        return ['isLoggedIn' => false, 'userType' => null, 'userId' => null, 'userName' => '', 'userRole' => ''];
    }
    
    $company_id = $_SESSION['company_id'];
    $stmt = $company_conn->prepare("SELECT company_id, company_name, industry_type FROM companies WHERE company_id = ? AND status = 'active'");
    $stmt->bind_param("s", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $company_data = $result->fetch_assoc();
        $stmt->close();
        $company_conn->close();
        
        return [
            'isLoggedIn' => true,
            'userType' => 'company',
            'userId' => $company_data['company_id'],
            'userName' => $company_data['company_name'],
            'userRole' => 'company',
            'industryType' => $company_data['industry_type'] ?? ''
        ];
    }
    
    $stmt->close();
    $company_conn->close();
    
    return ['isLoggedIn' => false, 'userType' => null, 'userId' => null, 'userName' => '', 'userRole' => ''];
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
$isLoggedIn = $sessionData['isLoggedIn'];
$user_id = $sessionData['userId'] ?? '';
$user_name = $sessionData['userName'] ?? 'User';
$user_email = $sessionData['userEmail'] ?? '';
$user_profile_picture = $sessionData['userProfilePicture'] ?? '';
$user_role = $sessionData['userRole'] ?? 'student';
$industry_type = $sessionData['industryType'] ?? '';

// Additional user data
$user_phone = '';
$user_location = '';
$user_joined = '';
$user_dob = '';
$unread_count = 0;

// Get additional details and unread messages
if ($isLoggedIn && $user_id) {
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
        } elseif ($user_role === 'company') {
            // Companies don't have additional details in this system
            $user_phone = '';
            $user_location = '';
        } elseif ($user_role !== 'admin') {
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
        } elseif ($user_role === 'admin') {
            $unread_count = 0;
        } elseif ($user_role === 'company') {
            // Companies don't have messages yet
            $unread_count = 0;
        } else {
            $unread_stmt = $user_conn->prepare("SELECT COUNT(*) as unread_count FROM user_messages WHERE receiver_id = ? AND is_read = FALSE");
            $unread_stmt->bind_param("i", $user_id);
        }
        
        if (isset($unread_stmt)) {
            $unread_stmt->execute();
            $unread_result = $unread_stmt->get_result();
            $unread_count = $unread_result->fetch_assoc()['unread_count'];
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

// Handle AJAX form submission for course enrollment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['is_ajax'])) {
    header('Content-Type: application/json');

    $sessionData = validateUserSession();
    if (!$sessionData['isLoggedIn'] || $sessionData['userRole'] !== 'student') {
        echo json_encode(['status' => 'error', 'message' => "Only students can enroll in courses."]);
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

    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : null;
    $action_type = isset($_POST['action_type']) ? $_POST['action_type'] : '';
    $user_id = $sessionData['userId'];
    $user_email = $sessionData['userEmail'];

    // Get course details
    $course_stmt = $conn->prepare("SELECT course_type FROM course WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    
    if ($course_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => "Course not found."]);
        $course_stmt->close();
        $conn->close();
        exit();
    }
    
    $course = $course_result->fetch_assoc();
    $course_type = $course['course_type'];
    $course_stmt->close();
// Handle Self-Paced Course Enrollment
if ($action_type === 'self_paced' && $course_type === 'self_paced') {
    // Check if already enrolled
    $check_sql = "SELECT id FROM course_applications WHERE course_id = ? AND student_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $course_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['status' => 'info', 'message' => "You are already enrolled in this course. Check your messages for course materials."]);
        $check_stmt->close();
        $conn->close();
        exit();
    }
    $check_stmt->close();

    // GET FULL COURSE DETAILS - THIS IS CRITICAL!
    $course_details_stmt = $conn->prepare("SELECT * FROM course WHERE id = ?");
    $course_details_stmt->bind_param("i", $course_id);
    $course_details_stmt->execute();
    $course_details_result = $course_details_stmt->get_result();
    $full_course = $course_details_result->fetch_assoc();
    $course_details_stmt->close();

    if (!$full_course || empty($full_course['course_link'])) {
        echo json_encode(['status' => 'error', 'message' => "Course materials are not available yet. Please contact support."]);
        $conn->close();
        exit();
    }

    // Enroll student (auto-approved for self-paced)
    $sql = "INSERT INTO course_applications (
        student_id, course_id, applicant_name, email, application_status
    ) VALUES (?, ?, ?, ?, 'approved')";
    
    $stmt = $conn->prepare($sql);
    $name = $sessionData['userName'];
    $stmt->bind_param("siss", $user_id, $course_id, $name, $user_email);

    if ($stmt->execute()) {
        // NOW SEND THE MESSAGE WITH COURSE LINK
        $subject = "Welcome to: " . $full_course['course_title'];
        
        $message = "Congratulations! You have successfully enrolled in the course.\n\n";
        $message .= "====================================\n";
        $message .= "COURSE DETAILS\n";
        $message .= "====================================\n";
        $message .= "Course Title: " . $full_course['course_title'] . "\n";
        $message .= "Provider: " . $full_course['company_name'] . "\n";
        $message .= "Category: " . ($full_course['course_category'] ?? 'General') . "\n";
        
        if (!empty($full_course['difficulty_level'])) {
            $message .= "Level: " . $full_course['difficulty_level'] . "\n";
        }
        
        if (!empty($full_course['duration'])) {
            $message .= "Duration: " . $full_course['duration'] . "\n";
        }
        
        $message .= "\n====================================\n";
        $message .= "ACCESS YOUR COURSE MATERIALS\n";
        $message .= "====================================\n\n";
        $message .= "Click or copy the link below to access your course:\n\n";
        $message .= $full_course['course_link'] . "\n\n";
        $message .= "====================================\n\n";
        
        $message .= "ABOUT THIS COURSE\n";
        $message .= $full_course['course_description'] . "\n\n";
        
        if (!empty($full_course['what_you_will_learn'])) {
            $message .= "What You'll Learn:\n";
            $message .= str_replace('|', "\n- ", "- " . $full_course['what_you_will_learn']) . "\n\n";
        }
        
        if (!empty($full_course['skills_taught'])) {
            $message .= "Skills Covered:\n";
            $message .= "- " . str_replace(',', "\n- ", $full_course['skills_taught']) . "\n\n";
        }
        
        if (!empty($full_course['prerequisites'])) {
            $message .= "Prerequisites:\n";
            $message .= str_replace('|', "\n- ", "- " . $full_course['prerequisites']) . "\n\n";
        }
        
        $message .= "LEARNING TIPS\n";
        $message .= "- This is a self-paced course - learn at your own speed\n";
        $message .= "- Set aside dedicated time for learning each day\n";
        $message .= "- Take notes and practice what you learn\n";
        $message .= "- Complete exercises and projects for hands-on experience\n";
        
        if ($full_course['certificate_provided']) {
            $message .= "- Certificate will be provided upon course completion\n";
        }
        
        $message .= "\nNEED HELP?\n";
        $message .= "If you have any questions, contact: " . $full_course['company_name'] . "\n\n";
        $message .= "Happy Learning!\n";
        $message .= "- Nexttern Team";
        
        // INSERT MESSAGE INTO student_messages table
        $sender_type = 'company';
        $receiver_type = 'student';
       
        $msg_stmt = $conn->prepare("INSERT INTO student_messages 
                                     (sender_type, receiver_type, receiver_id, subject, message, is_read, created_at) 
                                     VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $msg_stmt->bind_param("sssss", $sender_type, $receiver_type, $user_id, $subject, $message);
        
        if (!$msg_stmt->execute()) {
            // Log error but don't fail enrollment
            error_log("Failed to send enrollment message: " . $msg_stmt->error);
        }
        $msg_stmt->close();
        
        echo json_encode([
            'status' => 'success', 
            'message' => "Enrollment successful! Check your messages for the course access link and materials.",
            'course_type' => 'self_paced'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Error processing enrollment: " . $stmt->error]);
    }
    $stmt->close();
}
    // Handle Live Course Application
    else if ($action_type === 'live' && $course_type === 'live') {
        $name = isset($_POST['name']) ? trim($conn->real_escape_string($_POST['name'])) : '';
        $email = isset($_POST['email']) ? trim($conn->real_escape_string($_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? trim($conn->real_escape_string($_POST['phone'])) : '';
        $learning_objective = isset($_POST['learning_objective']) ? trim($_POST['learning_objective']) : '';
        $motivation = isset($_POST['motivation']) ? trim($conn->real_escape_string($_POST['motivation'])) : '';

        if (empty($course_id) || empty($name) || empty($email) || empty($learning_objective)) {
            echo json_encode(['status' => 'error', 'message' => "Please fill in all required fields."]);
            $conn->close();
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => "Please enter a valid email address."]);
            $conn->close();
            exit();
        }

        $valid_objectives = [
            'job_preparation', 'interview_skills', 'certification', 'skill_enhancement',
            'career_switch', 'academic_project', 'personal_interest', 'startup_preparation'
        ];

        if (!in_array($learning_objective, $valid_objectives)) {
            echo json_encode(['status' => 'error', 'message' => "Invalid learning objective selected."]);
            $conn->close();
            exit();
        }

        $check_sql = "SELECT id FROM course_applications WHERE course_id = ? AND email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $course_id, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => "You have already applied for this course."]);
            $check_stmt->close();
            $conn->close();
            exit();
        }
        $check_stmt->close();

        $sql = "INSERT INTO course_applications (
            student_id, course_id, applicant_name, email, phone, learning_objective, 
            cover_letter, application_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssss", $user_id, $course_id, $name, $email, $phone, $learning_objective, $motivation);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success', 
                'message' => "Application submitted successfully! The company will review and contact you soon.",
                'course_type' => 'live'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Error processing application: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => "Invalid course type or action."]);
    }

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

$course_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
$course_data = null;
$error_message = null;

if ($course_id) {
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

function getEnrollmentStats($courseData) {
    return [
        'students_trained' => $courseData['students_trained'] ?: '5,000+',
        'student_rating' => $courseData['student_rating'] ? $courseData['student_rating'] . '/5' : '4.5/5',
        'enrollment_deadline' => $courseData['enrollment_deadline'] ? date('M j, Y', strtotime($courseData['enrollment_deadline'])) : 'Sep 30, 2025'
    ];
}

function parseSkillsTaught($skillsString) {
    if (empty($skillsString)) return ['technical' => [], 'professional' => []];
    
    $skills = ['technical' => [], 'professional' => []];
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

function parseWhatYouWillLearn($learningString) {
    if (empty($learningString)) return [];
    
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
            $items[] = ['title' => $part, 'description' => 'Master the fundamentals and advanced concepts.'];
        }
    }
    
    return $items;
}

function parseProgramStructure($structureString) {
    if (empty($structureString)) return [];
    
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
            $title = $part;
            $description = '';
        }
        
        $phaseLabel = "Phase " . ($index + 1);
        $title = preg_replace('/^weeks?\s+\d+(-\d+)?\s*/i', '', $title);
        
        $phases[] = [
            'week_label' => $phaseLabel,
            'title' => $title,
            'description' => $description
        ];
    }
    
    return $phases;
}

function parsePrerequisites($prereqString) {
    if (empty($prereqString)) return [];
    
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

$conn->close()
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

        /* Enhanced Profile Navigation with Photo Support */
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
            position: relative;
        }

        .profile-trigger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            background: rgba(255, 255, 255, 0.4);
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
            animation: pulse 2s infinite;
            position: absolute;
            top: -5px;
            right: -5px;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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
        }.breadcrumb a:hover {
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
            position: sticky;
            top: 100px;
            align-self: flex-start;
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
                <li><a href="course.php" class="nav-link">Internships</a></li>
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
                    <?php if ($course_data['course_type']): ?>
                    <div class="tag" style="background: rgba(78, 205, 196, 0.1); color: var(--accent);">
                        <i class="fas fa-<?php echo $course_data['course_type'] === 'self_paced' ? 'user-clock' : 'video'; ?>"></i>
                        <?php echo $course_data['course_type'] === 'self_paced' ? 'Self-Paced' : 'Live Sessions'; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($course_data['duration']): ?>
                    <div class="tag tag-duration">
                        <i class="fas fa-clock"></i>
                        <?php echo htmlspecialchars($course_data['duration']); ?>
                    </div>
                    <?php endif; ?>
                    
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
                <?php if ($course_data['enrollment_deadline']): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['enrollment_deadline']; ?></div>
                    <div class="stat-label">Enrollment Deadline</div>
                </div>
                <?php endif; ?>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['students_trained']; ?></div>
                    <div class="stat-label">Students Trained</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['student_rating']; ?></div>
                    <div class="stat-label">Student Rating</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-<?php echo $course_data['course_type'] === 'self_paced' ? 'infinity' : 'video'; ?>"></i>
                    </div>
                    <div class="stat-value"><?php echo $course_data['course_type'] === 'self_paced' ? 'Anytime' : 'Live'; ?></div>
                    <div class="stat-label"><?php echo $course_data['course_type'] === 'self_paced' ? 'Start Learning' : 'Sessions'; ?></div>
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
                                <div class="phase-weeks">Phase 1</div>
                                <div class="phase-content">
                                    <h4>Foundation Phase</h4>
                                    <p>Introduction to core concepts, basic principles, and fundamental skills.</p>
                                </div>
                            </div>
                            
                            <div class="phase-card">
                                <div class="phase-weeks">Phase 2</div>
                                <div class="phase-content">
                                    <h4>Skill Development</h4>
                                    <p>Hands-on practice with real-world projects and advanced techniques.</p>
                                </div>
                            </div>
                            
                            <div class="phase-card">
                                <div class="phase-weeks">Phase 3</div>
                                <div class="phase-content">
                                    <h4>Advanced Applications</h4>
                                    <p>Complex problem-solving, industry best practices, and specialization.</p>
                                </div>
                            </div>
                            
                            <div class="phase-card">
                                <div class="phase-weeks">Phase 4</div>
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
                    // Simple comma-separated skills section
                    if (!empty($course_data['skills_taught'])): 
                        $skills_list = array_filter(array_map('trim', explode(',', $course_data['skills_taught'])));
                        if (!empty($skills_list)):
                    ?>
                    <!-- Skills Taught Section -->
                    <section class="skills-taught-section">
                        <h2 class="section-title">
                            <i class="fas fa-tools"></i>
                            Skills You'll Master
                        </h2>
                        
                        <div class="skills-taught-grid">
                            <?php foreach ($skills_list as $skill): ?>
                            <div class="skill-taught-card">
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo htmlspecialchars($skill); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php 
                        endif;
                    endif; 
                    ?>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <?php if ($isLoggedIn && $user_role === 'student'): ?>
                        <?php if ($course_data['course_type'] === 'self_paced'): ?>
                        <!-- Self-Paced Course Enrollment -->
                        <div class="sidebar-card application-form">
                            <h3>Start Learning Now</h3>
                            <p class="application-subtitle">Instant access to self-paced course materials</p>
                            
                            <div id="message_area"></div>
                            
                            <form id="enrollmentForm">
                                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_data['id']); ?>">
                                <input type="hidden" name="action_type" value="self_paced">
                                
                                <div style="background: var(--bg-light); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                        <i class="fas fa-info-circle" style="color: var(--info); font-size: 1.5rem;"></i>
                                        <div>
                                            <h4 style="margin: 0; color: var(--text-primary); font-size: 0.95rem;">Self-Paced Learning</h4>
                                            <p style="margin: 0.25rem 0 0 0; color: var(--text-secondary); font-size: 0.85rem;">Learn at your own pace, anytime</p>
                                        </div>
                                    </div>
                                    <ul style="margin: 0; padding-left: 1.5rem; color: var(--text-secondary); font-size: 0.9rem;">
                                        <li style="margin-bottom: 0.5rem;">Instant course access</li>
                                        <li style="margin-bottom: 0.5rem;">Video playlists sent to email</li>
                                        <li style="margin-bottom: 0.5rem;">Study materials included</li>
                                        <li>Lifetime access to content</li>
                                    </ul>
                                </div>
                                
                                <button type="submit" id="submit_button" class="btn-submit">
                                    <i class="fas fa-play-circle"></i>
                                    Start Learning Now
                                </button>
                                
                                <p style="text-align: center; margin-top: 1rem; color: var(--text-secondary); font-size: 0.85rem;">
                                    <i class="fas fa-envelope"></i> Course links will be sent to <strong><?php echo htmlspecialchars($user_email); ?></strong>
                                </p>
                            </form>
                        </div>
                        
                        <?php else: ?>
                        <!-- Live Course Application Form -->
                        <div class="sidebar-card application-form">
                            <h3>Apply for This Course</h3>
                            <p class="application-subtitle">Join our upcoming live sessions</p>
                            
                            <div id="message_area"></div>
                            
                            <form id="enrollmentForm">
                                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_data['id']); ?>">
                                <input type="hidden" name="action_type" value="live">
                                
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
                        <?php endif; ?>
                    
                    <?php elseif ($isLoggedIn && ($user_role === 'admin' || $user_role === 'company')): ?>
                    <!-- Admin/Company View - No Application Form -->
                    <div class="sidebar-card">
                        <div style="text-align: center; padding: 2rem;">
                            <div style="width: 80px; height: 80px; background: var(--gradient-primary); 
                                 border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                                 margin: 0 auto 1.5rem; color: white; font-size: 2rem;">
                                <i class="fas fa-<?php echo $user_role === 'admin' ? 'shield-alt' : 'building'; ?>"></i>
                            </div>
                            <h3 style="margin-bottom: 1rem; color: var(--primary);">
                                <?php echo ucfirst($user_role); ?> Access
                            </h3>
                            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                                <?php if ($user_role === 'admin'): ?>
                                    You're viewing this course as an administrator. Students can enroll through the application form.
                                <?php else: ?>
                                    You're viewing this course as a company representative. Students can enroll through the application form.
                                <?php endif; ?>
                            </p>
                            <a href="<?php echo $user_role === 'admin' ? 'index.php?page=home' : 'company_dashboard.php'; ?>" 
                               class="btn-submit" style="text-decoration: none; background: var(--primary);">
                                <i class="fas fa-arrow-left"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Login Required - For Non-Logged In Users -->
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

                    <!-- Quick Facts - Show for Everyone -->
                    <div class="sidebar-card quick-facts">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            Quick Facts
                        </h3>
                        
                        <?php if ($course_data['course_type']): ?>
                        <div class="fact-item">
                            <span class="fact-label">
                                <i class="fas fa-graduation-cap"></i>
                                Course Type:
                            </span>
                            <span class="fact-value"><?php echo $course_data['course_type'] === 'self_paced' ? 'Self-Paced' : 'Live Sessions'; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($course_data['start_date'] && $course_data['course_type'] === 'live'): ?>
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
                        
                        <?php if ($course_data['course_format']): ?>
                        <div class="fact-item">
                            <span class="fact-label">
                                <i class="fas fa-desktop"></i>
                                Format:
                            </span>
                            <span class="fact-value"><?php echo htmlspecialchars(ucfirst($course_data['course_format'])); ?></span>
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
                        
                        <?php if ($course_data['course_type'] === 'self_paced'): ?>
                        <div class="fact-item">
                            <span class="fact-label">
                                <i class="fas fa-infinity"></i>
                                Access:
                            </span>
                            <span class="fact-value">Lifetime Access</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-error" style="text-align: center; padding: 2rem; margin-top: 2rem;">
                <i class="fas fa-exclamation-circle"></i>
                Course not found or has been removed.
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if ($isLoggedIn && $user_role === 'student'): ?>
        // Enhanced Form Submission with course type handling
        document.getElementById('enrollmentForm').addEventListener('submit', function(event) {
            event.preventDefault();
            
            const submitButton = document.getElementById('submit_button');
            const messageArea = document.getElementById('message_area');
            const form = this;
            const actionType = form.querySelector('input[name="action_type"]').value;

            // Check if form is already being submitted
            if (submitButton.disabled) {
                return;
            }

            // Validate required fields based on course type
            let requiredFields = [];
            if (actionType === 'live') {
                requiredFields = ['name', 'email', 'learning_objective'];
            }
            // No validation needed for self_paced, it's automatic
            
            let hasErrors = false;
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (field && !field.value.trim()) {
                    hasErrors = true;
                    field.style.borderColor = 'var(--danger)';
                } else if (field) {
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
            if (actionType === 'self_paced') {
                submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Processing Enrollment...`;
            } else {
                submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Submitting Application...`;
            }
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
                    
                    // Different success messages based on course type
                    if (data.course_type === 'self_paced') {
                        form.innerHTML = `
                            <div class="application-success">
                                <div class="success-icon">
                                    <i class="fas fa-envelope-open-text"></i>
                                </div>
                                <h3 style="margin-bottom: 1rem; color: var(--success);">Check Your Email!</h3>
                                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                                    We've sent course playlist links and learning materials to <strong><?php echo htmlspecialchars($user_email); ?></strong>
                                </p>
                                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.9rem;">
                                    <i class="fas fa-info-circle"></i> Check your inbox (and spam folder) for course access details.
                                </p>
                                <a href="course.php" class="btn-submit" style="background: var(--primary); text-decoration: none;">
                                    <i class="fas fa-search"></i>
                                    Explore More Courses
                                </a>
                            </div>
                        `;
                    } else {
                        form.innerHTML = `
                            <div class="application-success">
                                <div class="success-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3 style="margin-bottom: 1rem; color: var(--success);">Application Submitted!</h3>
                                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                                    Your application is under review. The company will contact you soon via email or phone.
                                </p>
                                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.9rem;">
                                    <i class="fas fa-clock"></i> Expected response time: 2-3 business days
                                </p>
                                <a href="course.php" class="btn-submit" style="background: var(--success); text-decoration: none;">
                                    <i class="fas fa-search"></i>
                                    Browse More Courses
                                </a>
                            </div>
                        `;
                    }
                    
                } else if (data.status === 'info') {
                    messageArea.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-info-circle"></i>
                            ${data.message}
                        </div>
                    `;
                    submitButton.disabled = true;
                    submitButton.innerHTML = `<i class="fas fa-check"></i> Already Enrolled`;
                } else {
                    messageArea.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            ${data.message}
                        </div>
                    `;
                    submitButton.disabled = false;
                    if (actionType === 'self_paced') {
                        submitButton.innerHTML = `<i class="fas fa-play-circle"></i> Start Learning Now`;
                    } else {
                        submitButton.innerHTML = `<i class="fas fa-rocket"></i> Submit Application`;
                    }
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
                if (actionType === 'self_paced') {
                    submitButton.innerHTML = `<i class="fas fa-play-circle"></i> Start Learning Now`;
                } else {
                    submitButton.innerHTML = `<i class="fas fa-rocket"></i> Submit Application`;
                }
                
                messageArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        // Real-time validation for required fields (only for live courses)
        const actionType = document.querySelector('input[name="action_type"]');
        if (actionType && actionType.value === 'live') {
            const requiredFields = ['name', 'email', 'learning_objective'];
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (field) {
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
                }
            });
        }
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
            const skillCards = document.querySelectorAll('.skill-taught-card');
            skillCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-3px) scale(1)';
                });
            });
        });
    </script>
</body>
</html>