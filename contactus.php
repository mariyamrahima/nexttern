<?php
// Start session to check login status
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['logged_in']) || isset($_SESSION['email']);

// Get user details if logged in
$user_name = '';
$user_email = '';
$user_profile_picture = '';
$user_role = '';
$user_phone = '';
$user_location = '';
$user_id = '';
$user_joined = '';
$user_dob = '';
$unread_count = 0;

if ($isLoggedIn) {
    // Database connection for user details
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $user_conn = new mysqli($servername, $username, $password, $dbname);
    
    if (!$user_conn->connect_error) {
        // Check if user is in users table or students table
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $user_stmt = $user_conn->prepare("SELECT name, email, profile_picture, role, phone, location, created_at, dob FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $user_id);
        } elseif (isset($_SESSION['email'])) {
            $email = $_SESSION['email'];
            // Try students table first
            $user_stmt = $user_conn->prepare("SELECT student_id as id, CONCAT(first_name, ' ', last_name) as name, email, '' as profile_picture, 'student' as role, contact as phone, '' as location, created_at, dob FROM students WHERE email = ?");
            $user_stmt->bind_param("s", $email);
        }
        
        if (isset($user_stmt)) {
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows > 0) {
                $user_data = $user_result->fetch_assoc();
                $user_id = $user_data['id'] ?? '';
                $user_name = $user_data['name'] ?? 'User';
                $user_email = $user_data['email'] ?? '';
                $user_profile_picture = $user_data['profile_picture'] ?? '';
                $user_role = $user_data['role'] ?? 'student';
                $user_phone = $user_data['phone'] ?? '';
                $user_location = $user_data['location'] ?? '';
                $user_joined = $user_data['created_at'] ?? '';
                $user_dob = $user_data['dob'] ?? '';
            }
            $user_stmt->close();
        }
        
        // Get unread messages count for the user
        if ($user_id) {
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
                $unread_count = $unread_result->fetch_assoc()['unread_count'];
                $unread_stmt->close();
            }
        }
        $user_conn->close();
    }
}

// Handle contact form submission
$form_success = false;
$form_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_submitted'])) {
    // Database connection for contact form
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        $form_error = "Database connection failed. Please try again later.";
    } else {
        // Sanitize and validate input
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        
        // Basic validation
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $form_error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_error = "Please enter a valid email address.";
        } else {
            // Insert into contact_messages table
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            
            if ($stmt->execute()) {
                $form_success = true;
            } else {
                $form_error = "Failed to send message. Please try again.";
            }
            $stmt->close();
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">  
    <style>
        /* Root Variables - Enhanced to match internship page */
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
            --bg-light: #f5fbfa;
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
            --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
            --blur: 14px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            --border-radius: 16px;
            --white: #ffffff;
            --text-dark: #1f2937;
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #ffffff 100%);
            color: var(--secondary);
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Enhanced Background Blobs - Matching internship page */
        .blob {
            position: fixed;
            border-radius: 50%;
            z-index: -1;
            animation: moveBlob 20s infinite alternate ease-in-out;
            pointer-events: none;
        }

        .blob1 { 
            width: 600px; 
            height: 600px; 
            background: rgba(3, 89, 70, 0.15); 
            top: -200px; 
            right: -200px; 
        }
        .blob2 { 
            width: 400px; 
            height: 400px; 
            background: rgba(78, 205, 196, 0.12); 
            top: 20%; 
            left: -150px; 
            animation-delay: 2s; 
        }
        .blob3 { 
            width: 300px; 
            height: 300px; 
            background: rgba(3, 89, 70, 0.1); 
            bottom: 10%; 
            right: -100px; 
            animation-delay: 4s; 
        }
        .blob4 { 
            width: 250px; 
            height: 250px; 
            background: rgba(78, 205, 196, 0.15); 
            bottom: -100px; 
            left: 10%; 
            animation-delay: 1s; 
        }
        .blob5 { 
            width: 200px; 
            height: 200px; 
            background: rgba(3, 89, 70, 0.08); 
            top: 50%; 
            left: 50%; 
            animation-delay: 6s; 
        }

        @keyframes moveBlob {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); }
            25% { transform: translate(30px, -30px) scale(1.05) rotate(90deg); }
            50% { transform: translate(-20px, 20px) scale(0.95) rotate(180deg); }
            75% { transform: translate(40px, 10px) scale(1.02) rotate(270deg); }
            100% { transform: translate(-30px, -20px) scale(1) rotate(360deg); }
        }

        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 1rem 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(3, 89, 70, 0.1);
            z-index: 1000;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                        background-color 0.3s ease,
                        box-shadow 0.3s ease;
            transform: translateY(0);
        }

        .navbar.scrolled-down {
            transform: translateY(-100%);
        }

        .navbar.scrolled-up {
            transform: translateY(0);
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
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
           /* background: var(--gradient-primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            transition: var(--transition);*/
        }

        .nav-logo:hover {
            transform: scale(1.05);
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

        /* Standard Login Button */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
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
            box-shadow: var(--shadow-light);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }

        /* Profile Dashboard Modal - Enhanced to match internship page */
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

        /* Menu Toggle */
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

        /* Alert Messages - Enhanced */
        .alert {
            padding: 1.2rem 1.5rem;
            margin: 2rem auto;
            max-width: 1200px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInDown 0.3s ease-out;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            font-weight: 500;
            box-shadow: var(--shadow-light);
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.15);
            border: 1px solid rgba(39, 174, 96, 0.3);
            color: var(--success);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: var(--danger);
        }

        @keyframes slideInDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Enhanced Welcome Bar for Logged Users */
        .enhanced-welcome {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: var(--primary);
            padding: 2rem;
            margin: 2rem auto;
            max-width: 1200px;
            border-radius: 16px;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 20px rgba(3, 89, 70, 0.1);
        }

        .enhanced-welcome h2 {
            font-size: 1.6rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--primary-dark);
            font-family: 'Poppins', sans-serif;
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

        .welcome-message {
            margin-top: 1rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Main Contact Container */
        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 120px 2rem 2rem; /* Added top padding for navbar */
        }

        /* Enhanced Hero Section */
        .contact-hero {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 4rem 2rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }

        .contact-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
        }

        .contact-hero::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.05) 50%, transparent 70%);
            animation: shimmer 8s infinite;
            pointer-events: none;
        }

        .hero-content {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: var(--secondary);
            opacity: 0.8;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .hero-image {
            width: 320px;
            height: 200px;
            border-radius: var(--border-radius);
            object-fit: cover;
            margin: 2rem auto;
            box-shadow: 0 15px 35px rgba(3, 89, 70, 0.2);
            animation: fadeInUp 0.6s ease-out 0.3s both;
            transition: var(--transition);
        }

        .hero-image:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(3, 89, 70, 0.3);
        }

        /* Two Column Layout */
        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        /* Enhanced Contact Info Panel */
        .contact-info-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--shadow-light);
            height: fit-content;
            position: relative;
            overflow: hidden;
        }

        .contact-info-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent) 0%, var(--primary) 100%);
        }

        .info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: var(--shadow-light);
        }

        .info-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            color: var(--primary);
            font-weight: 600;
        }

        .contact-item {
            margin-bottom: 1.5rem;
            padding: 1.2rem;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            border-left: 4px solid var(--accent);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .contact-item:hover {
            background: rgba(255, 255, 255, 0.6);
            transform: translateX(5px);
            box-shadow: var(--shadow-light);
        }

        .contact-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .contact-item:hover::before {
            left: 100%;
        }

        .contact-item h4 {
            font-family: 'Poppins', sans-serif;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
        }

        .contact-item h4 i {
            color: var(--accent);
            width: 20px;
            font-size: 1.1rem;
        }

        .contact-item p {
            color: var(--secondary);
            line-height: 1.6;
            margin: 0;
            font-size: 0.95rem;
            position: relative;
            z-index: 2;
        }

        .contact-item p a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .contact-item p a:hover {
            color: var(--accent);
        }

        /* Enhanced Contact Form Panel */
        .contact-form-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--shadow-light);
            position: relative;
            overflow: hidden;
        }

        .contact-form-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
        }

        .form-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .form-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            color: var(--primary);
            font-weight: 600;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(3, 89, 70, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            font-size: 0.9rem;
            color: var(--secondary);
            transition: var(--transition);
            font-family: inherit;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
            transform: translateY(-2px);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
            grid-column: 1 / -1;
        }

        .btn-submit {
            width: 100%;
            padding: 1.2rem;
            border: none;
            border-radius: 12px;
            background: var(--gradient-primary);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
            margin-top: 1rem;
            font-family: 'Poppins', sans-serif;
            box-shadow: var(--shadow-light);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(3, 89, 70, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Enhanced FAQ Section */
        .faq-section {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--shadow-light);
            animation: fadeInUp 0.6s ease-out 0.4s both;
            position: relative;
            overflow: hidden;
        }

        .faq-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent) 0%, var(--primary) 50%, var(--accent) 100%);
        }

        .faq-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .faq-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .faq-subtitle {
            color: var(--secondary);
            opacity: 0.8;
            font-size: 1.1rem;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .faq-item {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            cursor: pointer;
            overflow: hidden;
            position: relative;
        }

        .faq-item:hover {
            background: rgba(255, 255, 255, 0.6);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(3, 89, 70, 0.1);
        }

        .faq-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .faq-item:hover::before {
            left: 100%;
        }

        .faq-question {
            padding: 1.2rem;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }

        .faq-question i {
            color: var(--primary);
            transition: transform 0.3s ease;
            font-size: 0.8rem;
            background: rgba(3, 89, 70, 0.1);
            padding: 0.3rem;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease;
        }

        .faq-item.active .faq-answer {
            max-height: 120px;
            padding: 0 1.2rem 1.2rem;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
            background: var(--accent);
            color: white;
        }

        .faq-answer p {
            color: var(--secondary);
            font-size: 0.85rem;
            line-height: 1.6;
            margin: 0;
            position: relative;
            z-index: 2;
        }

        /* Animation keyframes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Enhanced Responsive Design */
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }

            .nav-menu {
                display: none;
            }

            .menu-toggle {
                display: flex;
            }

            .profile-name {
                display: none;
            }

            .contact-container {
                padding: 90px 1rem 2rem;
            }

            .hero-title {
                font-size: 2.2rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .contact-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .faq-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .contact-hero {
                padding: 2.5rem 1.5rem;
            }

            .hero-image {
                width: 280px;
                height: 180px;
            }

            .enhanced-welcome {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }

            .enhanced-welcome h2 {
                font-size: 1.4rem;
            }

            .enhanced-welcome .welcome-details {
                flex-direction: column;
                gap: 0.8rem;
            }

            .welcome-detail {
                width: 100%;
                justify-content: center;
            }

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

            .alert {
                margin: 1rem;
                padding: 1rem;
            }

            .faq-title {
                font-size: 1.6rem;
            }

            .faq-subtitle {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .contact-container {
                padding: 80px 0.5rem 1rem;
            }

            .contact-hero {
                margin: 0 0.5rem 2rem;
                padding: 2rem 1rem;
            }

            .hero-title {
                font-size: 1.8rem;
            }

            .contact-content {
                margin: 0 0.5rem;
                gap: 1.5rem;
            }

            .contact-info-panel,
            .contact-form-panel,
            .faq-section {
                padding: 2rem 1.5rem;
            }

            .alert {
                margin: 0.5rem;
            }

            .enhanced-welcome {
                margin: 0.5rem;
                padding: 1.5rem 1rem;
            }
        }

        /* Loading Animation for Form Submission */
        .btn-submit.loading {
            background: var(--text-secondary);
            cursor: not-allowed;
        }

        .btn-submit.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Success Message Animation */
        .success-message {
            background: rgba(39, 174, 96, 0.1);
            border: 2px solid var(--success);
            color: var(--success);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 1rem;
            animation: successPulse 0.6s ease-out;
        }

        @keyframes successPulse {
            0% { transform: scale(0.95); opacity: 0; }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Background Blobs - Enhanced to match internship page -->
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>
    <div class="blob blob3"></div>
    <div class="blob blob4"></div>
    <div class="blob blob5"></div>

    <!-- Enhanced Navigation - Matching internship page exactly -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="nav-brand">
                <img src="nextternnavbar.png" alt="Nexttern Logo" class="nav-logo">
            </a>
            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="internship.php" class="nav-link">Internships</a></li>
                <li><a href="#" class="nav-link">Companies</a></li>
                <li><a href="aboutus.php" class="nav-link">About</a></li>
                <li><a href="contactus.php" class="nav-link active">Contact</a></li>
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

    <!-- Profile Dashboard Modal - Enhanced to match internship page -->
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
                    <button class="dashboard-tab" onclick="showDashboardTab('messages')">
                        <i class="fas fa-envelope"></i>
                        Messages
                        <?php if ($unread_count > 0): ?>
                            <span style="background: var(--danger); color: white; border-radius: 50%; padding: 0.2rem 0.5rem; font-size: 0.7rem; margin-left: 0.5rem;"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Overview Tab -->
                <div id="overview-tab" class="dashboard-tab-content active">
                    <div class="quick-actions-grid">
                        <a href="index.php" class="quick-action">
                            <i class="fas fa-home"></i>
                            <h4>Dashboard</h4>
                            <p>Go to main dashboard</p>
                        </a>
                        <a href="internship.php" class="quick-action">
                            <i class="fas fa-search"></i>
                            <h4>Browse Internships</h4>
                            <p>Find opportunities</p>
                        </a>
                        <a href="#" class="quick-action" onclick="showDashboardTab('profile')">
                            <i class="fas fa-edit"></i>
                            <h4>Edit Profile</h4>
                            <p>Update your information</p>
                        </a>
                        <a href="#" class="quick-action" onclick="showDashboardTab('messages')">
                            <i class="fas fa-envelope"></i>
                            <h4>Messages</h4>
                            <p>Check your messages</p>
                        </a>
                        <a href="settings.php" class="quick-action">
                            <i class="fas fa-cog"></i>
                            <h4>Settings</h4>
                            <p>Manage preferences</p>
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
                        </form>
                    </div>
                </div>

                <!-- Messages Tab -->
                <div id="messages-tab" class="dashboard-tab-content">
                    <div class="profile-section">
                        <h3 style="color: var(--primary); margin-bottom: 1.5rem; font-size: 1.4rem;">
                            <i class="fas fa-envelope" style="color: var(--accent); margin-right: 0.5rem;"></i>
                            Messages
                        </h3>
                        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <i class="fas fa-envelope" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>No new messages.</p>
                            <?php if ($unread_count > 0): ?>
                                <p>You have <?php echo $unread_count; ?> unread message(s).</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>



    <div class="contact-container">
        <!-- Alert Messages -->
        <?php if ($form_success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Thank you! Your message has been sent successfully. We'll get back to you soon.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($form_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($form_error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="contact-hero">
            <div class="hero-content">
                <h1 class="hero-title">Get in Touch</h1>
                <p class="hero-subtitle">Connect with our team for partnerships, support, or any inquiries about Nexttern's internship platform.</p>
                <img src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Professional Team Meeting" class="hero-image">
            </div>
        </section>

        <!-- Two Column Content -->
        <div class="contact-content">
            <!-- Contact Information Panel -->
            <section class="contact-info-panel">
                <div class="info-header">
                    <div class="info-icon">
                        <i class="fas fa-address-book"></i>
                    </div>
                    <h2 class="info-title">Contact Information</h2>
                </div>

                <div class="contact-item">
                    <h4><i class="fas fa-map-marker-alt"></i> Headquarters</h4>
                    <p>123 Innovation Street, Tech District<br>Bangalore, Karnataka 560001<br>India</p>
                </div>
                
                <div class="contact-item">
                    <h4><i class="fas fa-clock"></i> Business Hours</h4>
                    <p>Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday: 10:00 AM - 4:00 PM<br>Sunday: Closed</p>
                </div>
                
                <div class="contact-item">
                    <h4><i class="fas fa-envelope"></i> Email Contacts</h4>
                    <p>
                        <a href="mailto:university@nexttern.com">University Relations</a><br>
                        <a href="mailto:media@nexttern.com">Media & PR</a><br>
                        <a href="mailto:sponsorship@nexttern.com">Sponsorship</a><br>
                        <a href="mailto:support@nexttern.com">General Support</a>
                    </p>
                </div>
            </section>

            <!-- Contact Form Panel -->
            <section class="contact-form-panel">
                <div class="form-header">
                    <div class="info-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <h2 class="form-title">Send Message</h2>
                </div>

                <form method="post" action="" id="contactForm">
                    <input type="hidden" name="form_submitted" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   value="<?php echo $isLoggedIn ? htmlspecialchars($user_name) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?php echo $isLoggedIn ? htmlspecialchars($user_email) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="message">Message</label>
                        <textarea id="message" name="message" class="form-textarea" rows="4" required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>
            </section>
        </div>

        <!-- FAQ Section -->
        <section class="faq-section">
            <div class="faq-header">
                <h2 class="faq-title">Frequently Asked Questions</h2>
                <p class="faq-subtitle">Quick answers to common questions about our platform</p>
            </div>
            
            <div class="faq-grid">
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How do I apply for internships?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Register as a student, browse available internships, and apply directly through our platform following the application steps.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>What are the eligibility requirements?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>All enrolled university students are eligible. Specific requirements vary by internship listing.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Are internships compensated?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Compensation varies by company. Payment terms are clearly stated in each internship posting.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How do companies partner with us?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Companies register online, get approved by our team, then choose a premium or standard account to post internships.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        // Pass PHP variables to JavaScript
        const isUserLoggedIn = <?php echo json_encode($isLoggedIn); ?>;
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
        <?php endif; ?>

        // FAQ Toggle Functionality
        document.querySelectorAll('.faq-item').forEach(item => {
            item.addEventListener('click', () => {
                // Close all other FAQ items
                document.querySelectorAll('.faq-item').forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });
                
                // Toggle current item
                item.classList.toggle('active');
            });
        });

        // Enhanced Form Submission with Animation
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const submitBtn = document.querySelector('.btn-submit');
            const originalContent = submitBtn.innerHTML;
            
            // Add loading state
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Sending...';
            submitBtn.disabled = true;
            
            // Let the form submit naturally, but with visual feedback
            setTimeout(() => {
                if (!submitBtn.disabled) return; // If form completed quickly
                
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Sent!';
                submitBtn.classList.remove('loading');
                submitBtn.classList.add('success');
                
                setTimeout(() => {
                    submitBtn.innerHTML = originalContent;
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('success');
                }, 2000);
            }, 1000);
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

        // Form field focus animations
        document.querySelectorAll('.form-input, .form-textarea').forEach(field => {
            field.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
                this.parentElement.style.transition = 'transform 0.2s ease';
            });
            
            field.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Contact item hover effects
        document.querySelectorAll('.contact-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px) scale(1.02)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0) scale(1)';
            });
        });

        // Close modals when clicking outside or with Escape key
        document.addEventListener('click', function(e) {
            <?php if ($isLoggedIn): ?>
            const profileModal = document.getElementById('profileDashboardOverlay');
            if (e.target === profileModal) {
                closeProfileDashboard();
            }
            <?php endif; ?>
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                <?php if ($isLoggedIn): ?>
                closeProfileDashboard();
                <?php endif; ?>
            }
        });

        // Form validation enhancements
        document.querySelectorAll('input[required], textarea[required]').forEach(field => {
            field.addEventListener('invalid', function(e) {
                e.preventDefault();
                this.style.borderColor = 'var(--danger)';
                this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
                
                setTimeout(() => {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }, 3000);
            });
            
            field.addEventListener('input', function() {
                if (this.validity.valid) {
                    this.style.borderColor = 'var(--success)';
                    this.style.boxShadow = '0 0 0 3px rgba(39, 174, 96, 0.1)';
                    
                    setTimeout(() => {
                        this.style.borderColor = '';
                        this.style.boxShadow = '';
                    }, 1000);
                }
            });
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

        // Initialize page based on login status
        document.addEventListener('DOMContentLoaded', function() {
            if (isUserLoggedIn) {
                console.log('User is logged in - enhanced experience enabled');
            }
            
            // Auto-focus first form field for non-logged users
            if (!isUserLoggedIn) {
                const nameField = document.getElementById('name');
                if (nameField) {
                    setTimeout(() => nameField.focus(), 500);
                }
            }
            
            // Show success message animation if present
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.animation = 'successPulse 0.6s ease-out';
            }
        });

        // Notification function for user feedback
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background-color: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--primary-dark)'};
                color: white;
                padding: 12px 24px;
                border-radius: 25px;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.5s ease-in-out;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
                font-weight: 500;
                backdrop-filter: blur(10px);
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

        // Email link click tracking
        document.querySelectorAll('a[href^="mailto:"]').forEach(link => {
            link.addEventListener('click', function() {
                showNotification('Opening email client...', 'info');
            });
        });
    </script>
</body>
</html>