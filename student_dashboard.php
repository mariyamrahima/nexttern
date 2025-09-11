<?php
$page = $_GET['page'] ?? 'dashboard';
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if student is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.html");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$email = $_SESSION['email'];
// Updated SELECT query to fetch 'qualifications' as well
$stmt = $conn->prepare("SELECT student_id, first_name, last_name, contact, gender, dob,qualifications FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}

$row = $result->fetch_assoc();
$student_id = htmlspecialchars($row['student_id']);
$first_name = htmlspecialchars($row['first_name']);
$last_name = htmlspecialchars($row['last_name']);
$contact = htmlspecialchars($row['contact']);
$gender = htmlspecialchars($row['gender']);
$dob = htmlspecialchars(date('Y-m-d', strtotime($row['dob'])));

// 'qualifications' data is now being fetched.
// Using null coalescing operator to avoid "Deprecated" warning
$qualifications = htmlspecialchars($row['qualifications'] ?? '');

$error_message = '';
$success_message = '';

// Handle profile update functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_first_name = $_POST['first_name'];
    $new_last_name = $_POST['last_name'];
    $new_contact = $_POST['contact'];
    $new_gender = $_POST['gender'];
    $new_dob = $_POST['dob'];
    $new_qualifications = $_POST['qualifications'];
    
    // Assume success until proven otherwise
    $update_success = true;

    // Updated query with the 'qualifications' column
    if ($update_success) {
        $stmt_update = $conn->prepare("UPDATE students SET first_name = ?, last_name = ?, contact = ?, gender = ?, dob = ?, qualifications = ? WHERE student_id = ?");
        $stmt_update->bind_param("sssssss", $new_first_name, $new_last_name, $new_contact, $new_gender, $new_dob, $new_qualifications, $student_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['email'] = $email;
            $success_message = "Profile updated successfully!";
            // Redirect to prevent form resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . "?page=profile&success=1");
            exit;
        } else {
            $error_message = "Error updating record: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
}

// Handle course application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $course_id = $_POST['course_id'];
    $learning_objective = $_POST['learning_objective'];
    $cover_letter = $_POST['cover_letter'];
    $applicant_name = $first_name . ' ' . $last_name;
    
    // Check if student already applied for this course
    $check_stmt = $conn->prepare("SELECT id FROM course_applications WHERE course_id = ? AND student_id = ?");
    $check_stmt->bind_param("is", $course_id, $student_id);
    $check_stmt->execute();
    $existing_application = $check_stmt->get_result();
    
    if ($existing_application->num_rows > 0) {
        $error_message = "You have already applied for this course!";
    } else {
        $app_stmt = $conn->prepare("INSERT INTO course_applications (course_id, student_id, applicant_name, email, phone, learning_objective, cover_letter) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $app_stmt->bind_param("issssss", $course_id, $student_id, $applicant_name, $email, $contact, $learning_objective, $cover_letter);
        
        if ($app_stmt->execute()) {
            $success_message = "Application submitted successfully!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?page=applications&success=1");
            exit;
        } else {
            $error_message = "Error submitting application: " . $app_stmt->error;
        }
        $app_stmt->close();
    }
    $check_stmt->close();
}

// Handle mark as read functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $message_id = $_POST['message_id'];
    $stmt = $conn->prepare("UPDATE student_messages SET is_read = TRUE WHERE id = ? AND receiver_id = ?");
    $stmt->bind_param("is", $message_id, $student_id);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $_SERVER['PHP_SELF'] . "?page=messages");
    exit;
}

// Handle mark all as read functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE student_messages SET is_read = TRUE WHERE receiver_type = 'student' AND receiver_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $_SERVER['PHP_SELF'] . "?page=messages");
    exit;
}

// Fetch messages for the current student with improved query
$messages_stmt = $conn->prepare("
    SELECT id, sender_type, subject, message, is_read, created_at 
    FROM student_messages 
    WHERE receiver_type = 'student' AND receiver_id = ? 
    ORDER BY is_read ASC, created_at DESC
");
$messages_stmt->bind_param("s", $student_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();

// Get unread message count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM student_messages WHERE receiver_type = 'student' AND receiver_id = ? AND is_read = FALSE");
$unread_stmt->bind_param("s", $student_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['unread_count'];

// Get total messages count
$total_messages = $messages_result->num_rows;

// Get total applications count for this student
$app_count_stmt = $conn->prepare("SELECT COUNT(*) as app_count FROM course_applications WHERE student_id = ?");
$app_count_stmt->bind_param("s", $student_id);
$app_count_stmt->execute();
$app_count_result = $app_count_stmt->get_result();
$total_applications = $app_count_result->fetch_assoc()['app_count'];

// Fetch student's applications with course details
$student_apps_stmt = $conn->prepare("
    SELECT ca.*, c.course_title, c.course_description as course_description
    FROM course_applications ca 
    JOIN course c ON ca.course_id = c.id 
    WHERE ca.student_id = ? 
    ORDER BY ca.created_at DESC
");
$student_apps_stmt->bind_param("s", $student_id);
$student_apps_stmt->execute();
$student_apps_result = $student_apps_stmt->get_result();

// Function to get sender display name
function getSenderDisplayName($sender_type) {
    switch($sender_type) {
        case 'admin':
            return 'System Administrator';
        case 'company':
            return 'Company Representative';
        case 'student':
            return 'Student';
        default:
            return ucfirst($sender_type);
    }
}

// Function to get sender icon
function getSenderIcon($sender_type) {
    switch($sender_type) {
        case 'admin':
            return 'fas fa-user-shield';
        case 'company':
            return 'fas fa-building';
        case 'student':
            return 'fas fa-user-graduate';
        default:
            return 'fas fa-user';
    }
}

// Function to get time ago format
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return date('M j, Y', strtotime($datetime));
}

// Function to get application status badge
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="status-badge pending"><i class="fas fa-clock"></i> Pending</span>',
        'approved' => '<span class="status-badge approved"><i class="fas fa-check"></i> Approved</span>',
        'rejected' => '<span class="status-badge rejected"><i class="fas fa-times"></i> Rejected</span>',
        'waitlisted' => '<span class="status-badge waitlisted"><i class="fas fa-hourglass-half"></i> Waitlisted</span>'
    ];
    return $badges[$status] ?? '<span class="status-badge">' . ucfirst($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
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
            --bg-light: #f5fbfa;
            --glass-bg: rgba(255, 255, 255, 0.2);
            --glass-border: rgba(255, 255, 255, 0.3);
            --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
            --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
            --blur: 14px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: #f5fbfa;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }

        /* Background Blobs */
        .blob {
            position: fixed;
            border-radius: 50%;
            z-index: 0;
            animation: moveBlob 20s infinite alternate ease-in-out;
        }
        .blob1 {
            width: 400px;
            height: 400px;
            background: rgba(3, 89, 70, 0.08);
            top: -150px;
            right: -150px;
        }
        .blob2 {
            width: 300px;
            height: 300px;
            background: rgba(78, 205, 196, 0.1);
            bottom: -100px;
            left: -100px;
            animation-delay: 2s;
        }

        @keyframes moveBlob {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(20px, -20px) scale(1.05); }
            100% { transform: translate(-20px, 20px) scale(0.95); }
        }

        /* Professional Header */
        .header {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border-bottom: 1px solid var(--glass-border);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-light);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            min-height: 70px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            letter-spacing: -0.02em;
        }

        .system-info {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .system-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .system-subtitle {
            font-size: 0.75rem;
            color: var(--secondary);
            opacity: 0.7;
        }

        .header-center {
            flex: 1;
            display: flex;
            justify-content: center;
            max-width: 500px;
            margin: 0 2rem;
        }

        .search-container {
            position: relative;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid rgba(3, 89, 70, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(8px);
            font-size: 0.9rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
            background: rgba(255, 255, 255, 0.9);
        }

        .search-input::placeholder {
            color: var(--secondary);
            opacity: 0.6;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent);
            font-size: 0.9rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notification-bell {
            position: relative;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(78, 205, 196, 0.3);
            color: var(--primary);
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0.75rem;
            border-radius: 10px;
            transition: var(--transition);
        }

        .notification-bell:hover {
            background: rgba(78, 205, 196, 0.2);
            color: var(--accent);
            transform: translateY(-2px);
            box-shadow: var(--shadow-light);
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid white;
            animation: pulse 2s infinite;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(78, 205, 196, 0.3);
            transition: var(--transition);
        }

        .user-section:hover {
            background: rgba(255, 255, 255, 0.7);
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: var(--shadow-light);
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: bold;
            color: white;
        }

        .user-info h3 {
            font-size: 0.95rem;
            color: var(--primary);
            margin-bottom: 0.1rem;
            font-weight: 600;
        }

        .user-info p {
            font-size: 0.75rem;
            color: var(--secondary);
            opacity: 0.8;
            font-weight: 500;
        }

        .logout-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        /* Welcome Card */
        .welcome-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-light);
            text-align: center;
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: var(--secondary);
            opacity: 0.8;
        }

        /* Stats Grid - Navigation Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.8rem 1.5rem;
            box-shadow: var(--shadow-light);
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 16px 16px 0 0;
        }

        .stat-card.dashboard::before {
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        .stat-card.profile::before {
            background: linear-gradient(90deg, var(--secondary) 0%, #34495e 100%);
        }

        .stat-card.messages::before {
            background: linear-gradient(90deg, var(--accent) 0%, #45b7b8 100%);
        }

        .stat-card.applications::before {
            background: linear-gradient(90deg, var(--info) 0%, #2980b9 100%);
        }

        .stat-card.active {
            border-color: var(--accent);
            background: rgba(78, 205, 196, 0.1);
            transform: translateY(-3px);
        }

        .stat-card.active::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 2px solid var(--accent);
            border-radius: 16px;
            pointer-events: none;
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .stat-icon.dashboard { color: var(--primary); }
        .stat-icon.profile { color: var(--secondary); }
        .stat-icon.messages { color: var(--accent); }
        .stat-icon.applications { color: var(--info); }

        .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--secondary);
            opacity: 0.9;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-subtitle {
            font-size: 0.8rem;
            color: var(--secondary);
            opacity: 0.7;
            margin-top: 0.3rem;
        }

        /* Content Sections */
        .content-section {
            display: none;
            animation: fadeIn 0.5s ease-out;
        }

        .content-section.active {
            display: block;
        }

        /* Content Cards */
        .content-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Profile Form */
        .profile-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            padding: 0.7rem;
            border: 1px solid rgba(3, 89, 70, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        }

        .profile-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
        }

        .profile-actions button, .form-actions button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .save-btn, .submit-btn {
            background: var(--primary);
            color: white;
        }

        .cancel-btn {
            background: #e74c3c;
            color: white;
        }
        
        .edit-btn {
            background: var(--accent);
            color: var(--primary-dark);
        }
        
        .save-btn:hover, .cancel-btn:hover, .edit-btn:hover, .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Enhanced Messages Styles */
        .messages-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .messages-stats {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .message-stat {
            text-align: center;
        }

        .message-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .message-stat-label {
            font-size: 0.8rem;
            color: var(--secondary);
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .mark-all-read-btn {
            background: linear-gradient(135deg, var(--accent) 0%, #45b7b8 100%);
            color: var(--primary-dark);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .mark-all-read-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(78, 205, 196, 0.3);
        }

        .mark-all-read-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .messages-container {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        .messages-container::-webkit-scrollbar {
            width: 6px;
        }

        .messages-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .messages-container::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 3px;
        }

        .message-item {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .message-item:hover {
            transform: translateX(8px);
            box-shadow: var(--shadow-light);
        }

        .message-item.unread {
            border-left: 4px solid var(--accent);
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.15) 0%, rgba(255, 255, 255, 0.8) 100%);
            box-shadow: 0 4px 20px rgba(78, 205, 196, 0.2);
        }

        .message-item.unread::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--accent) 0%, #45b7b8 100%);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .sender-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            font-weight: 600;
        }

        .sender-avatar.admin {
            background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
        }

        .sender-avatar.company {
            background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
        }

        .sender-avatar.student {
            background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
        }

        .sender-details h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.2rem;
        }

        .sender-type {
            font-size: 0.8rem;
            color: var(--secondary);
            opacity: 0.7;
            font-weight: 500;
        }

        .message-time-info {
            text-align: right;
            flex-shrink: 0;
        }

        .message-time {
            font-size: 0.8rem;
            color: var(--secondary);
            opacity: 0.8;
            margin-bottom: 0.2rem;
        }

        .message-date {
            font-size: 0.75rem;
            color: var(--secondary);
            opacity: 0.6;
        }

        .unread-indicator {
            width: 8px;
            height: 8px;
            background: var(--accent);
            border-radius: 50%;
            display: inline-block;
            margin-left: 0.5rem;
            animation: pulse 2s infinite;
        }

        .message-subject {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .message-content {
            font-size: 0.9rem;
            color: var(--secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            border-left: 3px solid var(--accent);
        }

        .message-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            align-items: center;
        }

        .mark-read-btn {
            background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .mark-read-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.3);
        }

        .read-status {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            color: var(--success);
            font-weight: 500;
        }

        /* Applications Styles */
        .applications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .status-badge.pending {
            background: rgba(241, 196, 15, 0.2);
            color: #b7950b;
        }

        .status-badge.approved {
            background: rgba(39, 174, 96, 0.2);
            color: #27ae60;
        }

        .status-badge.rejected {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .status-badge.waitlisted {
            background: rgba(155, 89, 182, 0.2);
            color: #9b59b6;
        }

        /* Application List */
        .application-item {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .application-item:hover {
            transform: translateX(8px);
            box-shadow: var(--shadow-light);
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .application-course {
            flex: 1;
        }

        .application-course h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .application-instructor {
            font-size: 0.9rem;
            color: var(--accent);
            font-weight: 500;
        }

        .application-meta {
            text-align: right;
            flex-shrink: 0;
        }

        .application-date {
            font-size: 0.8rem;
            color: var(--secondary);
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }

        .application-objective {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
            opacity: 0.7;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
            color: var(--accent);
        }

        .empty-state h3 {
            margin-bottom: 0.75rem;
            font-size: 1.3rem;
            color: var(--primary);
        }

        .empty-state p {
            font-size: 1rem;
            line-height: 1.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .header-center {
                order: 3;
                width: 100%;
                margin: 0;
            }

            .search-container {
                max-width: none;
            }

            .header-right {
                order: 2;
                width: 100%;
                justify-content: space-between;
            }

            .system-info {
                display: none;
            }

            .container {
                padding: 1rem;
            }

            .welcome-title {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .profile-form {
                grid-template-columns: 1fr;
            }

            .messages-header, .applications-header {
                flex-direction: column;
                align-items: stretch;
            }

            .messages-stats {
                justify-content: space-around;
            }

            .message-header, .application-header {
                flex-direction: column;
                gap: 0.5rem;
            }

            .message-time-info, .application-meta {
                text-align: left;
            }

            .user-section {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .sender-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .message-actions, .form-actions {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Background Blobs -->
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>

    <!-- Professional Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <div class="brand-section">
                    <div class="brand-logo">Nexttern</div>
                    <div class="system-info">
                        <div class="system-title">Student Portal</div>
                        <div class="system-subtitle">Academic Management System</div>
                    </div>
                </div>
            </div>

            <div class="header-center">
                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Search courses, messages, applications...">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>

            <div class="header-right">
                <div class="header-actions">
                    <button class="notification-bell" onclick="goToMessages()">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <div class="user-section">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($first_name, 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <h3><?php echo $first_name . ' ' . $last_name; ?></h3>
                        <p>ID: <?php echo $student_id; ?></p>
                    </div>
                </div>

                <form action="logout.php" method="post" style="margin: 0;">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-card">
            <h1 class="welcome-title">Welcome back, <?php echo $first_name; ?>!</h1>
            <p class="welcome-subtitle">Click on any card below to navigate to different sections of your dashboard</p>
        </div>

        <!-- Navigation Cards Grid -->
        <div class="stats-grid">
            <div class="stat-card dashboard <?php echo ($page === 'dashboard' || !isset($_GET['page'])) ? 'active' : ''; ?>" onclick="showSection('dashboard', this)">
                <div class="stat-icon dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="stat-label">Dashboard</div>
                <div class="stat-subtitle">Main Overview</div>
            </div>

            <div class="stat-card profile <?php echo ($page === 'profile') ? 'active' : ''; ?>" onclick="showSection('profile', this)">
                <div class="stat-icon profile">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-label">Profile</div>
                <div class="stat-subtitle">Personal Info</div>
            </div>

            <div class="stat-card messages <?php echo ($page === 'messages') ? 'active' : ''; ?>" onclick="showSection('messages', this)">
                <div class="stat-icon messages">
                    <i class="fas fa-envelope"></i>
                    <?php if ($unread_count > 0): ?>
                    <span class="notification-badge">
                        <?php echo $unread_count; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="stat-value"><?php echo $total_messages; ?></div>
                <div class="stat-label">Messages</div>
                <div class="stat-subtitle"><?php echo $unread_count > 0 ? $unread_count . ' unread' : 'All caught up'; ?></div>
            </div>

            <div class="stat-card applications <?php echo ($page === 'applications') ? 'active' : ''; ?>" onclick="showSection('applications', this)">
                <div class="stat-icon applications">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value"><?php echo $total_applications; ?></div>
                <div class="stat-label">Applications</div>
                <div class="stat-subtitle"><?php echo $total_applications > 0 ? 'Submitted' : 'None yet'; ?></div>
            </div>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboard" class="content-section <?php echo ($page === 'dashboard' || !isset($_GET['page'])) ? 'active' : ''; ?>">
            <div class="content-card">
                <h2 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    Quick Overview
                </h2>
                <p>Welcome to your student dashboard! You have access to all your important information through the navigation cards above. Currently, you have <?php echo $unread_count; ?> unread messages and <?php echo $total_applications; ?> course applications.</p>
                <br>
                <p><strong>Navigation Guide:</strong></p>
                <ul style="margin-top: 0.5rem; padding-left: 1.5rem; color: var(--secondary);">
                    <li>Click <strong>Dashboard</strong> to return to this overview</li>
                    <li>Click <strong>Profile</strong> to view and edit your personal information</li>
                    <li>Click <strong>Messages</strong> to read and manage your messages</li>
                    <li>Click <strong>Applications</strong> to track your course applications</li>
                </ul>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile" class="content-section <?php echo ($page === 'profile') ? 'active' : ''; ?>">
            <div class="content-card">
                <h2 class="card-title">
                    <i class="fas fa-user"></i>
                    My Profile
                </h2>
                <?php if (!empty($error_message) && $page === 'profile'): ?>
                    <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                        <?php echo $error_message; ?>
                    </div>
                <?php elseif (!empty($success_message) && $page === 'profile'): ?>
                    <div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                <form id="profile-form" action="student_dashboard.php" method="post">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="profile-form">
                        <div class="form-group">
                            <label>Student ID</label>
                            <input type="text" id="student_id" name="student_id" value="<?php echo $student_id; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo $first_name; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo $last_name; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="email" name="email" value="<?php echo $email; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Contact</label>
                            <input type="text" id="contact" name="contact" value="<?php echo $contact; ?>">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select id="gender" name="gender">
                                <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" id="dob" name="dob" value="<?php echo $dob; ?>">
                        </div>
                        <div class="form-group">
                            <label>Qualifications</label>
                            <select id="qualifications" name="qualifications">
                                <option value="">Select Qualification</option>
                                <option value="High School" <?php echo ($qualifications === 'High School') ? 'selected' : ''; ?>>High School</option>
                                <option value="Undergraduate" <?php echo ($qualifications === 'Undergraduate') ? 'selected' : ''; ?>>Undergraduate</option>
                                <option value="Graduate" <?php echo ($qualifications === 'Graduate') ? 'selected' : ''; ?>>Graduate</option>
                                <option value="Postgraduate" <?php echo ($qualifications === 'Postgraduate') ? 'selected' : ''; ?>>Postgraduate</option>
                                <option value="PhD" <?php echo ($qualifications === 'PhD') ? 'selected' : ''; ?>>PhD</option>
                            </select>
                        </div>

                        <div class="profile-actions">
                            <button type="submit" class="save-btn">
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Enhanced Messages Section -->
        <div id="messages" class="content-section <?php echo ($page === 'messages') ? 'active' : ''; ?>">
            <div class="content-card">
                <div class="messages-header">
                    <div class="card-title" style="margin-bottom: 0;">
                        <i class="fas fa-envelope"></i>
                        My Messages
                    </div>
                    
                    <div class="messages-stats">
                        <div class="message-stat">
                            <span class="message-stat-value"><?php echo $total_messages; ?></span>
                            <span class="message-stat-label">Total</span>
                        </div>
                        <div class="message-stat">
                            <span class="message-stat-value"><?php echo $unread_count; ?></span>
                            <span class="message-stat-label">Unread</span>
                        </div>
                        <div class="message-stat">
                            <span class="message-stat-value"><?php echo $total_messages - $unread_count; ?></span>
                            <span class="message-stat-label">Read</span>
                        </div>
                    </div>
                    
                    <?php if ($unread_count > 0): ?>
                    <form method="post" style="margin: 0;">
                        <button type="submit" name="mark_all_read" class="mark-all-read-btn">
                            <i class="fas fa-check-double"></i>
                            Mark All Read
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if ($total_messages > 0): ?>
                    <div class="messages-container">
                        <?php
                        // Reset the result pointer and fetch messages again
                        $messages_stmt->execute();
                        $messages_result = $messages_stmt->get_result();
                        
                        while ($message = $messages_result->fetch_assoc()): 
                            $sender_display = getSenderDisplayName($message['sender_type']);
                            $sender_icon = getSenderIcon($message['sender_type']);
                            $time_ago = timeAgo($message['created_at']);
                            $formatted_date = date('M j, Y g:i A', strtotime($message['created_at']));
                        ?>
                            <div class="message-item <?php echo !$message['is_read'] ? 'unread' : ''; ?>">
                                <div class="message-header">
                                    <div class="sender-info">
                                        <div class="sender-avatar <?php echo $message['sender_type']; ?>">
                                            <i class="<?php echo $sender_icon; ?>"></i>
                                        </div>
                                        <div class="sender-details">
                                            <h4>
                                                <?php echo $sender_display; ?>
                                                <?php if (!$message['is_read']): ?>
                                                    <span class="unread-indicator"></span>
                                                <?php endif; ?>
                                            </h4>
                                            <div class="sender-type"><?php echo ucfirst($message['sender_type']); ?></div>
                                        </div>
                                    </div>
                                    <div class="message-time-info">
                                        <div class="message-time"><?php echo $time_ago; ?></div>
                                        <div class="message-date"><?php echo $formatted_date; ?></div>
                                    </div>
                                </div>
                                
                                <div class="message-subject">
                                    <?php echo htmlspecialchars($message['subject']); ?>
                                </div>
                                
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                </div>
                                
                                <div class="message-actions">
                                    <?php if (!$message['is_read']): ?>
                                        <form method="post" style="margin: 0;">
                                            <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                            <button type="submit" name="mark_read" class="mark-read-btn">
                                                <i class="fas fa-check"></i>
                                                Mark as Read
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="read-status">
                                            <i class="fas fa-check-circle"></i>
                                            Read
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-envelope-open"></i>
                        <h3>No Messages Yet</h3>
                        <p>Your messages from administrators and companies will appear here.<br>Check back later for updates!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Applications Section (Only My Applications) -->
        <div id="applications" class="content-section <?php echo ($page === 'applications') ? 'active' : ''; ?>">
            <div class="content-card">
                <div class="applications-header">
                    <div class="card-title" style="margin-bottom: 0;">
                        <i class="fas fa-file-alt"></i>
                        My Applications
                    </div>
                </div>

                <?php if (!empty($error_message) && $page === 'applications'): ?>
                    <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                        <?php echo $error_message; ?>
                    </div>
                <?php elseif (!empty($success_message) && $page === 'applications'): ?>
                    <div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <!-- My Applications -->
                <div id="submitted-applications">
                    <?php if ($total_applications > 0): ?>
                        <?php while ($app = $student_apps_result->fetch_assoc()): 
                            $formatted_date = date('M j, Y g:i A', strtotime($app['created_at']));
                            $learning_objective_display = str_replace('_', ' ', ucwords($app['learning_objective'], '_'));
                        ?>
                            <div class="application-item">
                                <div class="application-header">
                                    <div class="application-course">
                                        <h4><?php echo htmlspecialchars($app['course_title']); ?></h4>
                                        
                                    </div>
                                    <div class="application-meta">
                                        <div class="application-date">Applied: <?php echo $formatted_date; ?></div>
                                        <?php echo getStatusBadge($app['application_status']); ?>
                                    </div>
                                </div>

                                <div class="application-objective">
                                    <strong>Learning Objective:</strong> <?php echo htmlspecialchars($learning_objective_display); ?>
                                </div>

                                <?php if (!empty($app['cover_letter'])): ?>
                                    <div class="application-objective">
                                        <strong>Cover Letter:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>No Applications Yet</h3>
                            <p>You haven't submitted any course applications yet.<br>Your application history will appear here once you start applying for courses!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to handle section navigation
        function showSection(sectionId, cardElement) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all navigation cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.classList.remove('active');
            });

            // Show the selected section and add the active class to the card
            document.getElementById(sectionId).classList.add('active');
            cardElement.classList.add('active');
            
            // Update the URL in the address bar without reloading the page
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?page=" + sectionId;
            window.history.pushState({ path: newUrl }, '', newUrl);
        }

        // Function to handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 'dashboard';

            // Find the corresponding navigation card
            const cardElement = document.querySelector(`.stat-card.${page}`);
            if (cardElement) {
                showSection(page, cardElement);
            }
        });

        // Initial check on page load to set the correct active state based on URL
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 'dashboard';
            const cardElement = document.querySelector(`.stat-card.${page}`);
            if (cardElement) {
                showSection(page, cardElement);
            }
        });

        // Enhanced form validation with visual feedback
        function validateForm(formElement) {
            let isValid = true;
            const requiredFields = formElement.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--danger)';
                    field.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
                    isValid = false;
                    
                    // Remove error styling when user starts typing
                    field.addEventListener('input', function() {
                        this.style.borderColor = '';
                        this.style.boxShadow = '';
                    }, { once: true });
                } else {
                    field.style.borderColor = 'var(--success)';
                    field.style.boxShadow = '0 0 0 3px rgba(39, 174, 96, 0.1)';
                }
            });
            
            return isValid;
        }

        // Apply form validation to profile form
        document.getElementById('profile-form').addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                const firstErrorField = this.querySelector('[required]:invalid, [required][value=""]');
                if (firstErrorField) {
                    firstErrorField.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                    firstErrorField.focus();
                }
            }
        });

        // Auto-refresh messages every 30 seconds when on messages page
        setInterval(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page');
            if (currentPage === 'messages') {
                // Only refresh if user hasn't interacted recently
                const lastInteraction = Date.now() - (window.lastUserInteraction || 0);
                if (lastInteraction > 10000) { // 10 seconds since last interaction
                    location.reload();
                }
            }
        }, 30000);

        // Track user interactions
        document.addEventListener('click', function() {
            window.lastUserInteraction = Date.now();
        });

        document.addEventListener('scroll', function() {
            window.lastUserInteraction = Date.now();
        });

        // Handle keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Navigate with number keys
            if (e.key >= '1' && e.key <= '4') {
                const cards = document.querySelectorAll('.stat-card');
                const cardIndex = parseInt(e.key) - 1;
                if (cards[cardIndex]) {
                    cards[cardIndex].click();
                }
            }
        });
    </script>
</body>
</html>