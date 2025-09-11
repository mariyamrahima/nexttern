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

if (!empty($mode_filter)) {
    $where_clauses[] = "mode = ?";
    $params[] = $mode_filter;
    $types .= 's';
}

// Updated SQL query to fetch ONLY from Course table (no company joins)
$sql = "SELECT id, course_title, course_category, duration, difficulty_level, mode, 
               max_students, course_description, skills_taught, course_price_type, 
               price_amount, certificate_provided, featured, created_at, course_status
        FROM Course 
        WHERE course_status = 'Active'";

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY created_at DESC";

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
        $courses_data[] = $row;
    }
}

// Close statement and connection
if (isset($stmt)) $stmt->close();
$conn->close();

// Define available categories and durations for filters
$courses_categories = [
    'Programming',
    'Design', 
    'Business',
    'Marketing',
    'Data Science',
    'AI/ML',
    'Cybersecurity',
    'Finance',
    'Healthcare',
    'Engineering'
];

$available_durations = [
    '1 Week' => '1 Week',
    '2 Weeks' => '2 Weeks',
    '1 Month' => '1 Month',
    '2 Months' => '2 Months',
    '3 Months' => '3 Months',
    '6 Months' => '6 Months',
    'Self-Paced' => 'Self-Paced'
];

$available_modes = ['online', 'offline', 'hybrid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexttern - Course Opportunities</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            --text-muted: #95a5a6;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --border-light: #e9ecef;
            --border-medium: #dee2e6;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(3, 89, 70, 0.08);
            --shadow-lg: 0 8px 25px rgba(3, 89, 70, 0.12);
            --shadow-xl: 0 12px 48px rgba(3, 89, 70, 0.15);
            --blur: 14px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            --gradient-accent: linear-gradient(135deg, var(--accent) 0%, #7dd3d8 100%);
            --border-radius-sm: 6px;
            --border-radius: 12px;
            --border-radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e3f2fd 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Enhanced Navbar */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border-bottom: 1px solid var(--glass-border);
            z-index: 1000;
            padding: 0.75rem 0;
            transition: var(--transition);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand img {
            height: 50px;
            width: auto;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

      .nav-link {
    /* Set the default link color to a dark gray for good contrast */
    color: var(--text-dark); /* Using a variable is best practice */
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease; /* Transition only the color for a smoother effect */
    position: relative;
    padding: 0.5rem 0;
}

.nav-link:hover {
    /* Change the link text color to the primary green on hover */
    color: var(--primary);
}

.nav-link::after {
    content: '';
    position: absolute;
    bottom: -2px; /* A slight adjustment to place the line just below the text */
    left: 50%; /* Start from the center */
    transform: translateX(-50%); /* Center the line */
    width: 0;
    height: 2px;
    /* Use a solid color or a gradient for the line effect */
    background: var(--primary); 
    /* The original code had a gradient, but a solid color looks cleaner for a single line */
    transition: width 0.3s ease; /* Transition the width for the "grow" effect */
}

.nav-link:hover::after {
    /* Make the line grow to full width on hover */
    width: 100%;
}

       
/* Login Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
}

.btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 15px rgba(3, 89, 70, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(3, 89, 70, 0.4);
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
    box-shadow: 0 4px 15px rgba(3, 89, 70, 0.3);
}


        /* Enhanced Profile Navigation - Matching internship page */
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

        /* Header */
        .header {
            padding: 8rem 0 3rem;
            text-align: center;
            background: linear-gradient(135deg, rgba(3, 89, 70, 0.02) 0%, rgba(78, 205, 196, 0.02) 100%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 1rem;
        }

        .header p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto 2rem;
        }

        /* Enhanced Welcome */
        .enhanced-welcome {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            color: var(--primary);
            padding: 2rem;
            margin: 2rem auto;
            border-radius: var(--border-radius-lg);
            text-align: center;
            max-width: 800px;
            box-shadow: var(--shadow-md);
        }

        .enhanced-welcome h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .welcome-details {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .welcome-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary);
            background: rgba(3, 89, 70, 0.08);
            padding: 0.6rem 1.2rem;
            border-radius: var(--border-radius);
            border: 1px solid rgba(3, 89, 70, 0.15);
        }

        .welcome-detail i {
            color: var(--accent);
            font-size: 1rem;
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Search Section */
        .search-section {
            margin-bottom: 3rem;
        }

        .search-container {
            display: flex;
            max-width: 500px;
            margin: 0 auto;
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        .search-container input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            outline: none;
            font-size: 1rem;
            background: transparent;
        }

        .search-btn {
            padding: 1rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .search-btn:hover {
            background: var(--primary-dark);
        }

        /* Filter Section */
        .filter-section {
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
        }

        .filter-section h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-group select {
            padding: 0.8rem;
            border: 1px solid var(--border-medium);
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            background: var(--bg-white);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(3, 89, 70, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-btn {
            padding: 0.8rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .clear-btn {
            padding: 0.8rem 1.5rem;
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-medium);
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .clear-btn:hover {
            background: var(--bg-light);
            color: var(--text-primary);
        }

        /* Results Info */
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-count {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .enhanced-access-badge {
            background: var(--gradient-primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        /* Course Cards Grid - Compact Design */
       .internships-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); /* was 300px */
    gap: 1.5rem; /* optional: make gaps a bit larger for breathing room */
    margin-bottom: 3rem;
}


       .internship-card {
    background: var(--bg-white);
    border-radius: var(--border-radius-lg);
    padding: 2rem;                /* was 1.25rem */
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-light);
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    height: fit-content;
    min-height: 340px;            /* was 280px */
}


        .internship-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
            border-color: rgba(3, 89, 70, 0.2);
        }

        /* Card Header - Simplified */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .course-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .course-category {
            font-size: 0.85rem;
            color: var(--accent);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .action-icon:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        /* Card Meta - Compact */
        .card-meta {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
            background: var(--bg-light);
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-weight: 500;
        }

        .meta-item i {
            color: var(--accent);
            font-size: 0.8rem;
        }

        /* Card Description - Shorter */
        .card-description {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 0.75rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Skills Tags - Compact */
        .card-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }

        .skill-tag {
            background: rgba(3, 89, 70, 0.08);
            color: var(--primary);
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid rgba(3, 89, 70, 0.15);
        }

        /* Card Footer - Simplified */
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-light);
            margin-top: auto;
        }

        .price-info {
            display: flex;
            flex-direction: column;
        }

        .price-amount {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }

        .price-type {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .apply-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

      .mode-badge,
.status-badge {
    border-radius: 999px;           /* pill shape */
    padding: 0.2rem 0.9rem;         /* small, compact look */
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: none;
    position: static;               /* let container handle position */
    margin: 0;                      /* remove default margin */
    display: inline-block;
    min-width: 64px;                /* optional: ensures consistent width */
    text-align: center;
}


        .status-featured {
            background: linear-gradient(135deg, var(--warning) 0%, #ff9500 100%);
            color: white;
        }

        .status-new {
            background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%);
            color: white;
        }

       

        .mode-online {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success);
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .mode-offline {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.3);
        }

        .mode-hybrid {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
            border: 1px solid rgba(155, 89, 182, 0.3);
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .no-results h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        /* Content Blur Overlay for non-logged users */
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
            border-radius: var(--border-radius-lg);
        }

        .blur-message {
            pointer-events: auto;
            text-align: center;
            padding: 2rem;
            max-width: 500px;
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-light);
        }

        .blur-content i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
            opacity: 0.8;
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
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            min-width: 120px;
            text-align: center;
        }

        .blur-btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .blur-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
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
            box-shadow: var(--shadow-md);
        }

        .blurred-content {
            position: relative;
            pointer-events: none;
            user-select: none;
        }

        /* Login Modal */
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
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            padding: 0;
            max-width: 450px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            transform: scale(0.8);
            opacity: 0;
            transition: all 0.2s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 1.5rem 0;
            border-bottom: 1px solid var(--border-light);
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
            color: var(--text-muted);
            padding: 0.5rem;
            border-radius: 50%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }

        .modal-close:hover {
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 0 1.5rem 1.5rem;
            text-align: center;
        }

        .modal-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 1.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .modal-btn {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            text-align: center;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .modal-btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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

        /* Footer */
        .footer {
            background: var(--secondary);
            color: white;
            padding: 4rem 0 2rem;
            margin-top: 4rem;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 4rem;
            margin-bottom: 3rem;
        }

        .footer-brand {
            max-width: 300px;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Poppins', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .footer-logo i {
            color: var(--accent);
        }

        .footer-brand p {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .social-links a:hover {
            background: var(--gradient-primary);
            transform: translateY(-2px);
        }

        .footer-links {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }

        .footer-column h4 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: white;
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column ul li {
            margin-bottom: 0.75rem;
        }

        .footer-column ul li a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-column ul li a:hover {
            color: var(--accent);
            transform: translateX(5px);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 2rem;
        }

        .footer-bottom-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-bottom p {
            color: rgba(255, 255, 255, 0.5);
            margin: 0;
        }

        .footer-bottom-links {
            display: flex;
            gap: 2rem;
        }

        .footer-bottom-links a {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .footer-bottom-links a:hover {
            color: var(--accent);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2.2rem;
            }

            .header p {
                font-size: 1.1rem;
            }

            .enhanced-welcome {
                padding: 1.5rem;
                margin: 1rem;
            }

            .welcome-details {
                flex-direction: column;
                gap: 1rem;
            }

            .welcome-detail {
                width: 100%;
                justify-content: center;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .internships-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .card-header {
                flex-direction: column;
                gap: 1rem;
            }

            .card-meta {
                gap: 0.5rem;
            }

            .card-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .apply-btn {
                width: 100%;
            }

            .footer-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .footer-links {
                grid-template-columns: repeat(2, 1fr);
            }

            .nav-menu {
                display: none;
            }

            .profile-name {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 0 1rem;
            }

            .header {
                padding: 6rem 0 2rem;
            }

            .container {
                padding: 0 1rem;
            }

            .enhanced-welcome h2 {
                font-size: 1.4rem;
            }

            .blur-message {
                margin: 1rem;
                padding: 1.5rem;
            }

            .blur-actions {
                flex-direction: column;
            }

            .blur-btn {
                width: 100%;
            }
        }

        /* Animation Utilities */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-in {
            animation: slideIn 0.6s ease-out;
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

    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>Course Opportunities</h1>
            <p>Master new skills with industry-leading courses and certifications</p>
            <?php if ($isLoggedIn): ?>
                <div class="enhanced-welcome">
                    <h2>Welcome back, <?php echo htmlspecialchars($user_name ?: 'Student'); ?>!</h2>
                    <div class="welcome-details">
                        <div class="welcome-detail">
                            <i class="fas fa-graduation-cap"></i>
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
                        Explore all available courses below - full access enabled!
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Login Modal -->
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
                <input type="text" placeholder="Search for courses, skills, or topics..." id="search-input">
                <button type="button" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </section>

        <!-- Filter Section -->
        <section class="filter-section">
            <h2>Find Your Perfect Course</h2>
            <form class="filter-form" method="GET" action="">
                <div class="filter-group">
                    <label for="course">Category</label>
                    <select id="course" name="course">
                        <option value="">All Categories</option>
                        <?php foreach ($courses_categories as $course): ?>
                            <option value="<?php echo htmlspecialchars($course); ?>" 
                                    <?php echo ($_GET['course'] ?? '') === $course ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="mode">Mode</label>
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
                        <span class="btn-text">Apply Filters</span>
                    </button>
                    <a href="?" class="clear-btn">Clear All</a>
                </div>
            </form>
        </section>

        <!-- Main Content -->
        <main class="main-content">
            <div class="results-info">
                <div class="results-count">
                    <?php echo count($courses_data); ?> course<?php echo count($courses_data) !== 1 ? 's' : ''; ?> found
                </div>
                <?php if ($isLoggedIn): ?>
                    <div class="enhanced-access-badge">
                        <i class="fas fa-crown"></i> Full Access
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($courses_data)): ?>
                <div class="no-results">
                    <h3>No courses found</h3>
                    <p>Try adjusting your filters to discover more learning opportunities</p>
                </div>
            <?php else: ?>
                <div class="internships-grid" style="position: relative;">
                    <?php 
                    $card_index = 0;
                    $cards_per_row = 3; // Updated for smaller cards
                    $cards_before_blur = $cards_per_row * 2; // Show first 2 rows (8 cards)
                    
                    foreach ($courses_data as $course): 
                        $card_index++;
                        
                        // Show blur overlay for non-logged users after 8 cards
                        if (!$isLoggedIn && $card_index == $cards_before_blur + 1 && count($courses_data) > $cards_before_blur): ?>
                            </div>
                            
                            <div class="internships-grid blurred-content" style="position: relative;">
                                <div class="content-blur-overlay">
                                    <div class="blur-message">
                                        <div class="blur-content">
                                            <i class="fas fa-graduation-cap"></i>
                                            <h3>Unlock All Courses</h3>
                                            <p>Join our learning platform to access all courses and track your progress</p>
                                            <div class="blur-actions">
                                                <a href="login.html" class="blur-btn blur-btn-primary">Login Now</a>
                                                <a href="registerstudent.html" class="blur-btn blur-btn-secondary">Sign Up Free</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php endif; ?>
                        
                        <div class="internship-card" id="card-<?php echo htmlspecialchars($course['id']); ?>" 
                             onclick="<?php echo ($isLoggedIn || $card_index <= $cards_before_blur) ? 'redirectToDetail(' . $course['id'] . ')' : 'showLoginModal(\'view\', ' . $course['id'] . ')'; ?>">
                            
                            <!-- Mode Badge -->
                            <div class="mode-badge mode-<?php echo htmlspecialchars($course['mode'] ?? 'online'); ?>">
                                <?php echo ucfirst(htmlspecialchars($course['mode'] ?? 'online')); ?>
                            </div>
                            
                            <!-- Featured/New Badge -->
                            <?php if ($course['featured'] == 1): ?>
                                <div class="status-badge status-featured">Featured</div>
                            <?php elseif (strtotime($course['created_at']) > strtotime('-7 days')): ?>
                                <div class="status-badge status-new">New</div>
                            <?php endif; ?>
                            
                            <!-- Card Header - Simplified without company info -->
                            <div class="card-header">
                                <div>
                                    <h4 class="course-title"><?php echo htmlspecialchars($course['course_title']); ?></h4>
                                    <div class="course-category"><?php echo htmlspecialchars($course['course_category']); ?></div>
                                </div>
                                <div class="card-actions">
                                    <span class="action-icon share-btn" data-id="<?php echo htmlspecialchars($course['id']); ?>">
                                        <i class="fas fa-share-alt"></i>
                                    </span>
                                </div>
                            </div>

                            <!-- Card Meta -->
                            <div class="card-meta">
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <?php echo htmlspecialchars($course['duration']); ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-signal"></i>
                                    <?php echo htmlspecialchars(ucfirst($course['difficulty_level'])); ?>
                                </div>
                                <?php if ($course['max_students'] > 0): ?>
                                <div class="meta-item">
                                    <i class="fas fa-users"></i>
                                    Max: <?php echo htmlspecialchars($course['max_students']); ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Description -->
                            <p class="card-description">
                                <?php echo htmlspecialchars($course['course_description']); ?>
                            </p>

                            <!-- Skills -->
                            <?php if (!empty($course['skills_taught'])): ?>
                            <div class="card-skills">
                                <?php 
                                $skills = array_slice(explode(',', $course['skills_taught']), 0, 3);
                                foreach ($skills as $skill): 
                                ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                <?php endforeach; ?>
                                <?php if (count(explode(',', $course['skills_taught'])) > 3): ?>
                                    <span class="skill-tag">+<?php echo count(explode(',', $course['skills_taught'])) - 3; ?> more</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Card Footer -->
                            <div class="card-footer">
                                <div class="price-info">
                                    <div class="price-amount">
                                        <?php 
                                            if ($course['course_price_type'] === 'free' || $course['price_amount'] == 0) {
                                                echo 'Free';
                                            } else {
                                                echo '₹' . number_format($course['price_amount'], 0);
                                            }
                                        ?>
                                    </div>
                                    <?php if ($course['certificate_provided']): ?>
                                        <div class="price-type">Certificate Included</div>
                                    <?php endif; ?>
                                </div>
                                
                                <button class="apply-btn" onclick="event.stopPropagation(); <?php echo ($isLoggedIn || $card_index <= $cards_before_blur) ? 'redirectToDetail(' . $course['id'] . ')' : 'showLoginModal(\'apply\', ' . $course['id'] . ')'; ?>;">
                                    <?php echo $course['course_price_type'] === 'free' ? 'Enroll Free' : 'Enroll Now'; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (!$isLoggedIn && count($courses_data) > $cards_before_blur): ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
<!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand">
                <a href="#" class="footer-logo">
                    <i class="fas fa-graduation-cap"></i>
                    Nexttern
                </a>
                <p>Empowering the next generation of professionals through meaningful internship experiences.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-facebook"></i></a>
                </div>
            </div>
            
            <div class="footer-links">
                <div class="footer-column">
                    <h4>For Students</h4>
                    <ul>
                        <li><a href="internship.php">Find Internships</a></li>
                        <li><a href="aboutus.php">Career Resources</a></li>
                        <li><a href="#">Success Stories</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>For Companies</h4>
                    <ul>
                        <li><a href="internship_posting.php">Post Internships</a></li>
                        <li><a href="registercompany.html">Partner with us</a></li>
                        <li><a href="logincompany.html">Start Journey</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="contactus.php">Contact Us</a></li>
                        <li><a href="contactus.php">Help Center</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Nexttern</h4>
                    <ul>
                        <li><a href="aboutus.php">About Us</a></li>
                        <li><a href="contactus.php">FAQS</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-bottom-container">
                <p>&copy; 2025 Nexttern. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

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


        // Redirect function to internship detail page
        function redirectToDetail(courseId) {
            window.location.href = 'internship_detail.php?id=' + courseId;
        }

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
            // Redirect to detail page instead of showing modal
            redirectToDetail(courseId);
        }

        function applyToCourse(courseId) {
            // Redirect to detail page for application
            redirectToDetail(courseId);
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
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLoginModal();
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