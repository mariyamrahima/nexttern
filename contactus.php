<?php
// Start session to check login status
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Initialize form status variables
$form_success = false;
$form_error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_submitted'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $form_error = 'All fields are required. Please fill in all the information.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_error = 'Please enter a valid email address.';
    } else {
        // Database connection
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "nexttern_db";
        
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        if ($conn->connect_error) {
            $form_error = 'Database connection failed. Please try again later.';
        } else {
            // Prepare and execute insert statement
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, 'new', NOW())");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            
            if ($stmt->execute()) {
                $form_success = true;
                // Clear POST data to prevent resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $form_error = 'Failed to send message. Please try again later.';
            }
            
            $stmt->close();
            $conn->close();
        }
    }
}

// Check for success parameter in URL
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $form_success = true;
}
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

$sessionData = validateUserSession();
$isLoggedIn = $sessionData['isLoggedIn'];
$user_id = $sessionData['userId'] ?? '';
$user_name = $sessionData['userName'] ?? 'User';
$user_email = $sessionData['userEmail'] ?? '';
$user_profile_picture = $sessionData['userProfilePicture'] ?? '';
$user_role = $sessionData['userRole'] ?? 'student';
$industry_type = $sessionData['industryType'] ?? '';

$unread_count = 0;
if ($isLoggedIn && $user_id) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $user_conn = new mysqli($servername, $username, $password, $dbname);
    
    if (!$user_conn->connect_error) {
        if ($user_role === 'student') {
            $unread_stmt = $user_conn->prepare("SELECT COUNT(*) as unread_count FROM student_messages WHERE receiver_type = 'student' AND receiver_id = ? AND is_read = FALSE");
            $unread_stmt->bind_param("s", $user_id);
        } elseif ($user_role === 'admin') {
            $unread_count = 0;
        } elseif ($user_role === 'company') {
            // Companies don't have messages in this system yet
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
            transition: var(--transition);
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

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
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

            .profile-name,
            .profile-id {
                display: none;
            }

            .profile-trigger {
                padding: 0.5rem;
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
    </style>
</head>
<body>
    <!-- Background Blobs -->
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>
    <div class="blob blob3"></div>
    <div class="blob blob4"></div>
    <div class="blob blob5"></div>

    <!-- Enhanced Navigation with Profile Photo Support -->
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
                        <button class="profile-trigger" onclick="redirectToDashboard('<?php echo $user_role; ?>')">
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
             <!--  <img src="Subject.png" alt="Professional Team Meeting" class="hero-image">-->
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

        // Toggle mobile menu
        function toggleMobileMenu() {
            const navMenu = document.querySelector('.nav-menu');
            if (navMenu) {
                navMenu.style.display = navMenu.style.display === 'flex' ? 'none' : 'flex';
            }
        }

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
        // Add this function to handle role-based dashboard redirection
function redirectToDashboard(userRole) {
    // Add debugging
    console.log('Redirecting user with role:', userRole);
    
    switch(userRole) {
        case 'admin':
            window.location.href = 'admin_dashboard.php';
            break;
        case 'company':
            window.location.href = 'company_dashboard.php';
            break;
        case 'student':
        default:
            window.location.href = 'student_dashboard.php';
            break;
    }
}
    </script>
</body>
</html>