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

// Database connection for about content
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch current data for display
$about_data = [];
$result = $conn->query("SELECT section_key, content FROM about_content");
while ($row = $result->fetch_assoc()) {
    $about_data[$row['section_key']] = $row['content'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">  
    <style>
        /* Root Variables - Enhanced to match contact page */
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

        /* Enhanced Background Blobs - Matching contact page */
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

        /* Enhanced Welcome Bar for Logged Users */
        .enhanced-welcome {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: var(--primary);
            padding: 2rem;
            margin: 2rem auto;
            max-width: 1400px;
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

        /* Main About Container - Wide Layout */
        .about-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 100px 2rem 2rem;
        }

        /* Enhanced Hero Section */
        .about-hero {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 4rem 3rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }

        .about-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
        }

        .about-hero::after {
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
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.4rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: var(--secondary);
            opacity: 0.8;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* CHANGED: About Content - Vertical Layout */
        .about-content {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
            margin-bottom: 3rem;
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        /* Enhanced Content Panels - Optimized for Vertical Layout */
        .content-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 3rem 3rem;
            box-shadow: var(--shadow-light);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            width: 100%;
        }

        .content-panel:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .content-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent) 0%, var(--primary) 100%);
        }

        .panel-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .panel-icon {
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
            flex-shrink: 0;
        }

        .panel-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            color: var(--primary);
            font-weight: 600;
            margin: 0;
        }

        .panel-text {
            color: var(--secondary);
            line-height: 1.8;
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: none;
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
        @media (max-width: 1200px) {
            .about-container {
                max-width: 1200px;
            }
        }

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

            .about-container {
                padding: 90px 1rem 2rem;
                max-width: 100%;
            }

            .hero-title {
                font-size: 2.4rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }

            .about-content {
                gap: 1.5rem;
            }

            .about-hero {
                padding: 2.5rem 1.5rem;
            }

            .content-panel {
                padding: 2rem 1.5rem;
            }

            .panel-header {
                gap: 1rem;
            }

            .panel-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }

            .panel-title {
                font-size: 1.5rem;
            }

            .panel-text {
                font-size: 1rem;
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

           
        @media (max-width: 480px) {
            .about-container {
                padding: 80px 0.5rem 1rem;
            }

            .about-hero {
                margin: 0 0.5rem 2rem;
                padding: 2rem 1rem;
            }

            .hero-title {
                font-size: 2rem;
            }

            .about-content {
                margin: 0 0.5rem;
                gap: 1rem;
            }

            .content-panel {
                padding: 1.5rem 1rem;
            }

            .panel-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .panel-title {
                font-size: 1.4rem;
            }

            .enhanced-welcome {
                margin: 0.5rem;
                padding: 1.5rem 1rem;
            }
        }
    }
    /* Footer */
        .footer {
            background: var(--secondary);
            color: var(--white);
            padding: 4rem 0 2rem;
            position: relative;
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
            color: var(--white);
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
            color: var(--white);
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
            color: var(--white);
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

        /* AOS Animation Styles */
        [data-aos] {
            opacity: 0;
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        [data-aos].aos-animate {
            opacity: 1;
        }

        [data-aos="fade-up"] {
            transform: translateY(40px);
        }

        [data-aos="fade-up"].aos-animate {
            transform: translateY(0);
        }

        [data-aos="fade-right"] {
            transform: translateX(-40px);
        }

        [data-aos="fade-right"].aos-animate {
            transform: translateX(0);
        }

        [data-aos="fade-left"] {
            transform: translateX(40px);
        }

        [data-aos="fade-left"].aos-animate {
            transform: translateX(0);
        }
        :root {
            --primary: #035946;
            --primary-light: #0a7058;
            --primary-dark: #024238;
            --accent: #4ecdc4;
            --accent-light: #7dd3d8;
            --secondary: #2e3944;
            --text-light: #6b7280;
            --text-dark: #1f2937;
            --bg-light: #f8fafc;
            --white: #ffffff;
            --gradient-primary: linear-gradient(135deg, #035946 0%, #0a7058 100%);
            --gradient-accent: linear-gradient(135deg, #4ecdc4 0%, #7dd3d8 100%);
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 25px rgba(3, 89, 70, 0.1);
            --shadow-lg: 0 20px 40px rgba(3, 89, 70, 0.15);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

    </style>
</head>
<body>
    <!-- Background Blobs - Enhanced to match contact page -->
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>
    <div class="blob blob3"></div>
    <div class="blob blob4"></div>
    <div class="blob blob5"></div>

    <!-- Enhanced Navigation - Matching contact page exactly -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="nav-brand">
                <img src="nextternnavbar.png" alt="Nexttern Logo" class="nav-logo">
            </a>

            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="internship.php" class="nav-link">Internships</a></li>
                <li><a href="#" class="nav-link">Companies</a></li>
                <li><a href="aboutus.php" class="nav-link active">About</a></li>
                <li><a href="contactus.php" class="nav-link">Contact</a></li>
            </ul>

            
           <!-- NEW -->
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

   

    <div class="about-container">
        <!-- Hero Section -->
        <section class="about-hero">
            <div class="hero-content">
                <h1 class="hero-title">About Nexttern</h1>
                <p class="hero-subtitle">Empowering the next generation of professionals through meaningful internship experiences and industry connections.</p>
            </div>
        </section>

        <!-- About Content - Now Vertical Layout -->
        <div class="about-content">
            <!-- Mission Panel -->
            <section class="content-panel">
                <div class="panel-header">
                    <div class="panel-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h2 class="panel-title">Our Mission</h2>
                </div>
                <p class="panel-text"><?= htmlspecialchars($about_data['mission'] ?? 'To bridge the gap between academic learning and professional experience by providing students with access to quality internship opportunities that foster growth, skill development, and career advancement.') ?></p>
            </section>

            <!-- Vision Panel -->
            <section class="content-panel">
                <div class="panel-header">
                    <div class="panel-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h2 class="panel-title">Our Vision</h2>
                </div>
                <p class="panel-text"><?= htmlspecialchars($about_data['vision'] ?? 'To be the leading platform that transforms how students discover, apply for, and excel in internships, creating a seamless connection between talent and opportunity in the digital age.') ?></p>
            </section>

            <!-- Values Panel -->
            <section class="content-panel">
                <div class="panel-header">
                    <div class="panel-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h2 class="panel-title">Our Values</h2>
                </div>
                <p class="panel-text"><?= htmlspecialchars($about_data['values'] ?? 'Excellence in education, integrity in partnerships, innovation in technology, and inclusivity in opportunity. We believe every student deserves access to experiences that will shape their professional future.') ?></p>
            </section>
        </div>
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

        // Content panel hover effects
        document.querySelectorAll('.content-panel').forEach(panel => {
            panel.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            panel.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

      

        

        // Initialize page based on login status
        document.addEventListener('DOMContentLoaded', function() {
            if (isUserLoggedIn) {
                console.log('User is logged in - enhanced experience enabled');
            }
            
            // Add subtle animation delay to content panels
            document.querySelectorAll('.content-panel').forEach((panel, index) => {
                panel.style.animationDelay = `${0.2 + (index * 0.1)}s`;
                panel.style.animation = 'fadeInUp 0.6s ease-out both';
            });
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