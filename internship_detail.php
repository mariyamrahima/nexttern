<?php
// Add at the very top after session_start() to enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// MOVE APPLICATION HANDLING BEFORE ANY HTML OUTPUT
// Handle application submission - MOVED TO TOP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    // Set proper JSON header
    header('Content-Type: application/json');
    
    // Database connection parameters
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";

    // Establish database connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }
    
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Please login to apply for this course.']);
        exit;
    }
    
    // Get the authenticated student's ID from session
    $student_id = $_SESSION['student_id'];
    $submitted_course_id = (int)$_POST['course_id'];
    
    // Collect and validate form data
    $applicant_name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $learning_objective = trim($_POST['learning_objective'] ?? '');
    $cover_letter = trim($_POST['cover_letter'] ?? '');
    
    // Validate required fields
    if (empty($applicant_name) || empty($email) || empty($learning_objective)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        $conn->close();
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        $conn->close();
        exit;
    }
    
    // Validate learning objective against enum values
    $valid_objectives = [
        'job_preparation', 'interview_skills', 'certification', 'skill_enhancement',
        'career_switch', 'academic_project', 'personal_interest', 'startup_preparation'
    ];
    
    if (!in_array($learning_objective, $valid_objectives)) {
        echo json_encode(['success' => false, 'message' => 'Invalid learning objective selected.']);
        $conn->close();
        exit;
    }
    
    // Check if student has already applied for this course (check by both student_id and email)
    $check_sql = "SELECT id FROM course_applications WHERE course_id = ? AND (student_id = ? OR email = ?)";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
        $conn->close();
        exit;
    }
    
    $check_stmt->bind_param("iss", $submitted_course_id, $student_id, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already applied for this course.']);
        $check_stmt->close();
        $conn->close();
        exit;
    }
    $check_stmt->close();
    
    // Insert application into database WITH student_id
    $insert_sql = "INSERT INTO course_applications (
        course_id, 
        student_id,
        applicant_name, 
        email, 
        phone, 
        learning_objective, 
        cover_letter, 
        application_status,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $insert_stmt = $conn->prepare($insert_sql);
    
    if (!$insert_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
        $conn->close();
        exit;
    }
    
    $insert_stmt->bind_param("issssss", 
        $submitted_course_id, 
        $student_id,          
        $applicant_name, 
        $email, 
        $phone, 
        $learning_objective, 
        $cover_letter
    );
    
    if ($insert_stmt->execute()) {
        // Get the application ID for reference
        $application_id = $conn->insert_id;
        
        // Log the application for debugging
        error_log("New course application: ID $application_id, Course: $submitted_course_id, Student: $student_id, Email: $email");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Application submitted successfully! We will contact you soon.',
            'application_id' => $application_id
        ]);
    } else {
        // Log the error for debugging
        error_log("Course application failed: " . $insert_stmt->error);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to submit application. Please try again later. Error: ' . $insert_stmt->error
        ]);
    }
    
    $insert_stmt->close();
    $conn->close();
    exit;
}

// Continue with the rest of your page logic AFTER form processing...
// Database connection parameters for page display
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

// Get course ID from URL parameter
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($course_id <= 0) {
    header("Location: internship.php");
    exit;
}

// Fetch course details from database - FIXED TABLE NAME
$sql = "SELECT * FROM course WHERE id = ? AND course_status = 'Active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: internship.php");
    exit;
}

$course = $result->fetch_assoc();
$stmt->close();

// Format course data
$skills_array = !empty($course['skills_taught']) ? array_map('trim', explode(',', $course['skills_taught'])) : [];
$learning_outcomes = !empty($course['what_you_will_learn']) ? array_map('trim', explode('|', $course['what_you_will_learn'])) : [];
$program_structure_items = !empty($course['program_structure']) ? array_map('trim', explode('|', $course['program_structure'])) : [];
$prerequisites_list = !empty($course['prerequisites']) ? array_map('trim', explode('|', $course['prerequisites'])) : [];

// Default company info (since courses don't have companies)
$company_name = "EduTech Solutions";
$company_location = "Bangalore, India";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_title']); ?> | Nexttern</title>
    <!-- Rest of your HTML head content... -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Include your CSS styles here -->
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
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
    --bg-light: #f8fafc;
    --bg-white: #ffffff;
    --bg-gray-50: #f9fafb;
    --bg-gray-100: #f3f4f6;
    --border-light: #e2e8f0;
    --border-medium: #cbd5e1;
    --glass-bg: rgba(255, 255, 255, 0.9);
    --glass-border: rgba(255, 255, 255, 0.2);
    --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    --border-radius: 12px;
    --border-radius-lg: 16px;
    --border-radius-xl: 20px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: var(--bg-light);
    color: var(--text-primary);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}
/* Revise the main navbar to a more compact, flexible height */
.navbar {
    background: var(--bg-white);
    border-bottom: 1px solid var(--border-light);
    /* Changed position to fixed to be on top of other content */
    position: fixed; 
    top: 0;
    width: 100%;
    z-index: 100;
    box-shadow: var(--shadow-sm);
}

.nav-container {
    max-width: 1280px;
    margin: 0 auto;
    /* Use padding to control height instead of fixed height */
    padding: 0.75rem 1.5rem; 
    /* Removed height: 72px; */
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Make the main content container start below the navbar */
.main-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
    /* Add top padding to create space for the fixed navbar */
    padding-top: calc(72px + 2rem); 
    /* The 72px value is an example. Adjust this to match the final height of your navbar. 
    A more robust solution would be to use a variable for the navbar height. */
}

.nav-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 1.5rem;
    color: var(--primary);
}

.nav-logo {
    height: 40px;
    width: auto;
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 2rem;
    list-style: none;
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

.nav-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: var(--bg-gray-50);
    border-radius: 50px;
    cursor: pointer;
    transition: var(--transition);
}

.nav-profile:hover {
    background: var(--bg-gray-100);
}

.profile-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--border-radius);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: inherit;
}

.btn-primary {
    background: var(--primary);
    color: white;
    box-shadow: var(--shadow-sm);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}



.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 2rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

/* Hero Section */
.hero-section {
    background: var(--bg-white);
    border-radius: var(--border-radius-xl);
    padding: 3rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
}

.hero-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
}

.hero-content h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 2.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 1rem;
    line-height: 1.2;
}

.company-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.company-logo {
    width: 48px;
    height: 48px;
    border-radius: var(--border-radius);
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}

.company-details h3 {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.company-details span {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.hero-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 500;
}

.badge-primary {
    background: rgba(3, 89, 70, 0.1);
    color: var(--primary);
}

.badge-success {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success);
}

.badge-info {
    background: rgba(52, 152, 219, 0.1);
    color: var(--info);
}

.badge-warning {
    background: rgba(243, 156, 18, 0.1);
    color: var(--warning);
}

.hero-description {
    font-size: 1.1rem;
    line-height: 1.7;
    color: var(--text-secondary);
    max-width: 65ch;
}

/* Stats Section */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}

.stat-card {
    background: var(--bg-gray-50);
    padding: 1.5rem;
    border-radius: var(--border-radius-lg);
    text-align: center;
}

.stat-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Enhanced Two Column Layout with Fixed Dimensions */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 2.5rem;
    margin-top: 2rem;
    max-width: 1280px;
    width: 100%;
}

.content-main {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    min-width: 0;
    max-width: 800px;
}

.content-sidebar {
    position: sticky;
    top: 100px;
    height: fit-content;
    width: 420px;
    min-width: 420px;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--border-medium) transparent;
}

.content-sidebar::-webkit-scrollbar {
    width: 6px;
}

.content-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.content-sidebar::-webkit-scrollbar-thumb {
    background-color: var(--border-medium);
    border-radius: 3px;
}

.content-sidebar::-webkit-scrollbar-thumb:hover {
    background-color: var(--border-light);
}

/* Content Cards */
.content-card {
    background: var(--bg-white);
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.content-card h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.content-card h2 i {
    color: var(--primary);
}

/* Skills Section */
.skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.skill-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--bg-gray-50);
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.skill-item:hover {
    background: var(--bg-gray-100);
    transform: translateY(-2px);
}

.skill-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--accent), #45b7b8);
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* Learning Outcomes */
.learning-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border-light);
}

.learning-item:last-child {
    border-bottom: none;
}

.learning-number {
    width: 32px;
    height: 32px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.learning-content h4 {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.learning-content p {
    color: var(--text-secondary);
    line-height: 1.6;
}

/* Timeline items */
.program-timeline {
    position: relative;
    max-width: 100%;
}

.timeline-item {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
    position: relative;
    flex-wrap: wrap;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 60px;
    top: 60px;
    bottom: -40px;
    width: 2px;
    background: var(--border-light);
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-marker {
    background: var(--primary);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius);
    font-weight: 600;
    font-size: 0.9rem;
    white-space: nowrap;
    min-width: 120px;
    max-width: 200px;
    text-align: center;
    flex-shrink: 0;
}

.timeline-content {
    flex: 1;
    min-width: 200px;
}

.timeline-content h4 {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.timeline-content p {
    color: var(--text-secondary);
    line-height: 1.6;
}

/* Prerequisites */
.prerequisites-grid {
    display: grid;
    gap: 1.5rem;
}

.prerequisite-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.prerequisite-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--accent), #45b7b8);
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.prerequisite-item h4 {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.prerequisite-item p {
    color: var(--text-secondary);
    line-height: 1.6;
}

/* Application Form Fixed Dimensions */
.application-form {
    background: var(--bg-white);
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-light);
    position: relative;
    width: 100%;
    max-width: 420px;
}

.application-form::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
}

.form-header {
    text-align: center;
    margin-bottom: 2rem;
}

.form-header h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.form-header p {
    color: var(--text-secondary);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-label.required::after {
    content: '*';
    color: var(--danger);
    margin-left: 0.25rem;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    max-width: 100%;
    padding: 0.875rem 1rem;
    border: 1px solid var(--border-medium);
    border-radius: var(--border-radius);
    background: var(--bg-white);
    font-size: 0.95rem;
    color: var(--text-primary);
    transition: var(--transition);
    font-family: inherit;
    box-sizing: border-box;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(3, 89, 70, 0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
}


.btn-submit {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: var(--border-radius);
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-sizing: border-box;
}

.btn-submit:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

.btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Alert Messages */
.alert {
    padding: 1rem;
    border-radius: var(--border-radius);
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

/* Quick Facts */
.quick-facts {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.fact-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-gray-50);
    border-radius: var(--border-radius);
}

.fact-item i {
    color: var(--primary);
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.fact-item div {
    flex: 1;
    min-width: 0;
}

.fact-item strong {
    display: block;
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.fact-item span {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Enhanced Responsive Design */
@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr 380px;
        gap: 2rem;
    }
    
    .content-sidebar {
        width: 380px;
        min-width: 380px;
    }
    
    .application-form {
        max-width: 380px;
    }
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr 350px;
        gap: 1.5rem;
    }
    
    .content-sidebar {
        width: 350px;
        min-width: 350px;
    }
    
    .application-form {
        max-width: 350px;
    }
    
    .skills-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 968px) {
    .content-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .content-sidebar {
        position: static;
        width: 100%;
        min-width: unset;
        max-width: 600px;
        margin: 0 auto;
        max-height: none;
        overflow-y: visible;
    }
    
    .application-form {
        max-width: 100%;
        width: 100%;
    }
    
    .content-main {
        max-width: 100%;
    }
    
    .timeline-item {
        flex-direction: column;
        gap: 1rem;
    }
    
    .timeline-marker {
        min-width: unset;
        max-width: 100%;
    }
    
    .timeline-item::before {
        display: none;
    }
}

@media (max-width: 768px) {
    .nav-menu {
        display: none;
    }

    .main-container {
        padding: 1rem;
    }

    .hero-section {
        padding: 2rem 1.5rem;
    }

    .hero-header {
        flex-direction: column;
        gap: 1rem;
    }

    .hero-content h1 {
        font-size: 1.75rem;
    }

    .content-grid {
        gap: 1rem;
    }

    .skills-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .hero-content h1 {
        font-size: 1.5rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .content-card {
        padding: 1.5rem;
    }

    .application-form {
        padding: 1.5rem;
    }
    
    .hero-section {
        padding: 1.5rem;
    }
    
    .nav-container {
        padding: 0 1rem;
    }
    
    .main-container {
        padding: 0.5rem;
    }
}
/* Reduce the vertical padding of the main navbar */
.navbar {
    position: fixed;
    top: 0;
    width: 100%;
    /* Reduced vertical padding from 1rem to 0.5rem */
    padding: 0.5rem 0; 
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(3, 89, 70, 0.1);
    z-index: 1000;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                background-color 0.3s ease,
                box-shadow 0.3s ease;
    transform: translateY(0);
}

/* Ensure consistent spacing and center alignment */
.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Adjust the logo height to be proportional to the new navbar size */
.nav-logo {
    /* Reduced height to a more compact size */
    height: 35px; 
    width: auto;
}

/* Make the profile trigger button more compact */
.profile-trigger {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    /* Reduced padding for a smaller pill shape */
    padding: 0.3rem 0.8rem; 
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
</style>
</head>
<body>
   <!-- Enhanced Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="nav-brand">
                <img src="nextternnavbar.png" alt="Nexttern Logo" class="nav-logo">
            </a>
            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="internship.php" class="nav-link active">Internships</a></li>
              
                <li><a href="aboutus.php" class="nav-link">About</a></li>
                <li><a href="contactus.php" class="nav-link">Contact</a></li>
            </ul>
            
          <div class="nav-cta">
    <?php if ($isLoggedIn): ?>
        <div class="nav-profile">
            <button class="profile-trigger" onclick="window.location.href='student_dashboard.php'">
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

    <!-- Main Content -->
    <main class="main-container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="index.php">Home</a>
            <i class="fas fa-chevron-right"></i>
            <a href="internship.php">Courses</a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($course['course_title']); ?></span>
        </nav>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-header">
                <div class="hero-content">
                    <h1><?php echo htmlspecialchars($course['course_title']); ?></h1>
                    <div class="company-info">
                        <div class="company-logo">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="company-details">
                            <h3><?php echo htmlspecialchars($company_name); ?></h3>
                            <span>Education • <?php echo htmlspecialchars($company_location); ?></span>
                        </div>
                    </div>
                    <div class="hero-badges">
                        <span class="badge badge-primary">
                            <i class="fas fa-clock"></i>
                            <?php echo htmlspecialchars($course['duration']); ?>
                        </span>
                        <span class="badge badge-success">
                            <i class="fas fa-money-bill"></i>
                            <?php echo $course['course_price_type'] === 'free' ? 'Free Course' : '₹' . number_format($course['price_amount']); ?>
                        </span>
                        <?php if ($course['max_students'] > 0): ?>
                        <span class="badge badge-info">
                            <i class="fas fa-users"></i>
                            <?php echo $course['max_students']; ?> Spots Available
                        </span>
                        <?php endif; ?>
                        <span class="badge badge-warning">
                            <i class="fas fa-signal"></i>
                            <?php echo ucfirst($course['difficulty_level']); ?> Level
                        </span>
                    </div>
                    <p class="hero-description">
                        <?php echo htmlspecialchars($course['course_description']); ?>
                    </p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo $course['enrollment_deadline'] ? date('M j, Y', strtotime($course['enrollment_deadline'])) : 'Open'; ?>
                    </div>
                    <div class="stat-label">Enrollment Deadline</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($course['students_trained'] ?: 500); ?>+</div>
                    <div class="stat-label">Students Trained</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($course['job_placement_rate'] ?: 85); ?>%</div>
                    <div class="stat-label">Job Placement Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($course['student_rating'] ?: 4.6, 1); ?>/5</div>
                    <div class="stat-label">Student Rating</div>
                </div>
            </div>
        </section>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Main Content -->
            <div class="content-main">
                <!-- Course Overview -->
                <div class="content-card">
                    <h2>
                        <i class="fas fa-info-circle"></i>
                        Course Overview
                    </h2>
                    <p><?php echo nl2br(htmlspecialchars($course['course_description'])); ?></p>
                </div>

                <!-- Skills You'll Learn -->
                <?php if (!empty($skills_array)): ?>
                <div class="content-card">
                    <h2>
                        <i class="fas fa-code"></i>
                        Skills You'll Master
                    </h2>
                    <div class="skills-grid">
                        <?php 
                        $skill_icons = [
                            'HTML' => 'fab fa-html5', 'CSS' => 'fab fa-css3-alt', 'JavaScript' => 'fab fa-js',
                            'React' => 'fab fa-react', 'Node.js' => 'fab fa-node-js', 'Python' => 'fab fa-python',
                            'Java' => 'fab fa-java', 'PHP' => 'fab fa-php', 'Database' => 'fas fa-database',
                            'Git' => 'fab fa-git-alt', 'AWS' => 'fab fa-aws', 'Docker' => 'fab fa-docker'
                        ];
                        
                        foreach ($skills_array as $skill): 
                            $skill = trim($skill);
                            $icon = 'fas fa-code';
                            foreach ($skill_icons as $key => $value) {
                                if (stripos($skill, $key) !== false) {
                                    $icon = $value;
                                    break;
                                }
                            }
                        ?>
                            <div class="skill-item">
                                <div class="skill-icon">
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <div>
                                    <h4><?php echo htmlspecialchars($skill); ?></h4>
                                    <span>Professional level</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Learning Outcomes -->
                <?php if (!empty($learning_outcomes)): ?>
                <div class="content-card">
                    <h2>
                        <i class="fas fa-graduation-cap"></i>
                        What You'll Learn
                    </h2>
                    <div class="learning-outcomes">
                        <?php foreach ($learning_outcomes as $index => $outcome): ?>
                            <div class="learning-item">
                                <div class="learning-number"><?php echo $index + 1; ?></div>
                                <div class="learning-content">
                                    <p><?php echo htmlspecialchars(trim($outcome)); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Program Structure -->
                <?php if (!empty($program_structure_items)): ?>
                <div class="content-card">
                    <h2>
                        <i class="fas fa-calendar-check"></i>
                        Program Structure
                    </h2>
                    <div class="program-timeline">
                        <?php foreach ($program_structure_items as $index => $item): 
                            $parts = explode(':', $item, 2);
                            $phase = isset($parts[0]) ? trim($parts[0]) : "Phase " . ($index + 1);
                            $description = isset($parts[1]) ? trim($parts[1]) : $item;
                        ?>
                            <div class="timeline-item">
                                <div class="timeline-marker"><?php echo htmlspecialchars($phase); ?></div>
                                <div class="timeline-content">
                                    <p><?php echo htmlspecialchars($description); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Prerequisites -->
                <?php if (!empty($prerequisites_list)): ?>
                <div class="content-card">
                    <h2>
                        <i class="fas fa-list-check"></i>
                        Prerequisites
                    </h2>
                    <div class="prerequisites-grid">
                        <?php 
                        $prereq_icons = ['fas fa-laptop-code', 'fas fa-brain', 'fas fa-clock', 'fas fa-graduation-cap'];
                        foreach ($prerequisites_list as $index => $prerequisite): 
                            $icon = $prereq_icons[$index % count($prereq_icons)];
                        ?>
                            <div class="prerequisite-item">
                                <div class="prerequisite-icon">
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <div>
                                    <p><?php echo htmlspecialchars(trim($prerequisite)); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="content-sidebar">
                <!-- Application Form -->
                <div class="application-form">
                    <div class="form-header">
                        <h2>Apply for This Course</h2>
                        <p>Start your learning journey today</p>
                    </div>

                    <div id="message_area"></div>

                    <form id="applicationForm" enctype="multipart/form-data">
                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">

                        <div class="form-group">
                            <label class="form-label required" for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   value="<?php echo $isLoggedIn ? htmlspecialchars($user_name) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?php echo $isLoggedIn ? htmlspecialchars($user_email) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-input" 
                                   value="<?php echo $isLoggedIn ? htmlspecialchars($user_phone) : ''; ?>" placeholder="+91 XXXXX XXXXX">
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
                            <label class="form-label" for="cover_letter">Why are you interested?</label>
                            <textarea id="cover_letter" name="cover_letter" class="form-textarea" 
                                      placeholder="Tell us about your motivation and what you hope to achieve from this course..."></textarea>
                        </div>

                        <button type="submit" id="submit_button" class="btn-submit">
                            <i class="fas fa-paper-plane"></i>
                            Submit Application
                        </button>
                    </form>
                </div>

                <!-- Quick Facts -->
                <div class="content-card" style="margin-top: 2rem;">
                    <h2>
                        <i class="fas fa-info-circle"></i>
                        Quick Facts
                    </h2>
                    <div class="quick-facts">
                        <div class="fact-item">
                            <i class="fas fa-calendar"></i>
                            <div>
                                <strong>Start Date:</strong>
                                <span><?php echo $course['start_date'] ? date('M j, Y', strtotime($course['start_date'])) : 'Flexible'; ?></span>
                            </div>
                        </div>
                        <div class="fact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>Duration:</strong>
                                <span><?php echo htmlspecialchars($course['duration']); ?></span>
                            </div>
                        </div>
                        <div class="fact-item">
                            <i class="fas fa-laptop"></i>
                            <div>
                                <strong>Format:</strong>
                                <span><?php echo htmlspecialchars($course['course_format'] ?: ucfirst($course['mode'])); ?></span>
                            </div>
                        </div>
                        <?php if ($course['certificate_provided']): ?>
                        <div class="fact-item">
                            <i class="fas fa-certificate"></i>
                            <div>
                                <strong>Certificate:</strong>
                                <span>Industry Recognized</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($course['job_placement_support']): ?>
                        <div class="fact-item">
                            <i class="fas fa-handshake"></i>
                            <div>
                                <strong>Job Support:</strong>
                                <span>Placement Assistance</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // FIXED JavaScript - Added better error handling and debugging
        document.getElementById('applicationForm').addEventListener('submit', function(event) {
            event.preventDefault();
            
            <?php if (!$isLoggedIn): ?>
                alert('Please login to apply for this course.');
                window.location.href = 'login.html';
                return;
            <?php endif; ?>
            
            const submitButton = document.getElementById('submit_button');
            const messageArea = document.getElementById('message_area');
            const formData = new FormData(this);

            // Debug: Log form data
            console.log('Submitting form with data:');
            for (let [key, value] of formData.entries()) {
                console.log(key, value);
            }

            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Submitting Application...`;
            messageArea.innerHTML = '';

            // Submit form
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server did not return JSON response');
                }
                
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                if (data.success) {
                    messageArea.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            ${data.message}
                        </div>
                    `;
                    
                    submitButton.innerHTML = `<i class="fas fa-check"></i> Application Submitted!`;
                    submitButton.style.background = 'var(--success)';
                    
                    // Reset form after 3 seconds
                    setTimeout(() => {
                        this.reset();
                        submitButton.innerHTML = `<i class="fas fa-paper-plane"></i> Submit Application`;
                        submitButton.style.background = '';
                        submitButton.disabled = false;
                    }, 3000);
                } else {
                    messageArea.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            ${data.message}
                        </div>
                    `;
                    
                    submitButton.innerHTML = `<i class="fas fa-paper-plane"></i> Submit Application`;
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                messageArea.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        An error occurred: ${error.message}. Please check the console and try again.
                    </div>
                `;
                
                submitButton.innerHTML = `<i class="fas fa-paper-plane"></i> Submit Application`;
                submitButton.disabled = false;
            });
        });
    </script>
</body>
</html>