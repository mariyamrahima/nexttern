<?php
// Start session to check login status
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
// Fetch approved success stories for public display
$stories_to_display = [];
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

$stories_conn = new mysqli($servername, $username, $password, $dbname);

if (!$stories_conn->connect_error) {
    $stories_stmt = $stories_conn->prepare("
        SELECT story_id, story_title, story_category, story_content, 
               feedback_rating, submission_date, first_name, last_name
        FROM stories
        WHERE status = 'approved'
        ORDER BY submission_date DESC
        LIMIT 3
    ");
    
    if ($stories_stmt) {
        $stories_stmt->execute();
        $stories_result = $stories_stmt->get_result();
        
        while ($story = $stories_result->fetch_assoc()) {
            $stories_to_display[] = $story;
        }
        
        $stories_stmt->close();
    }
    
    $stories_conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexttern - Your Gateway to Professional Growth</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
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
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--white);
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 1rem 0;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(3, 89, 70, 0.08);
            z-index: 1000;
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

        .nav-brand { display: flex; align-items: center; text-decoration: none; }
        .nav-logo { height: 50px; width: auto; transition: var(--transition); }
        .nav-logo:hover { transform: scale(1.05); }
        .nav-menu { display: flex; list-style: none; gap: 2rem; align-items: center; }

        .nav-link {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover { color: var(--primary); }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-primary);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after { width: 100%; }
        .nav-cta { display: flex; align-items: center; gap: 1rem; }

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
            font-family: 'Poppins', sans-serif;
        }

        .btn-outline {
            color: var(--primary);
            border: 2px solid var(--primary);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary {
            background: var(--gradient-primary) !important;
            color: var(--white) !important;
            border: none !important;
            box-shadow: 0 4px 15px rgba(3, 89, 70, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(3, 89, 70, 0.4);
        }

        /* Profile Trigger */
        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            -webkit-backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            cursor: pointer;
            transition: var(--transition);
            color: var(--primary);
            font-weight: 500;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .profile-trigger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: rgba(255, 255, 255, 0.4);
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
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
            font-size: 1rem;
        }

        .profile-info { display: flex; flex-direction: column; gap: 0.1rem; }

        .profile-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.9rem;
        }

        .profile-id {
            font-family: 'Roboto', sans-serif;
            color: var(--text-light);
            font-size: 0.75rem;
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

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 100px;
            position: relative;
            background: linear-gradient(135deg, #f0f9f6 0%, #e0f2ee 100%);
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 80%;
            height: 200%;
            background: radial-gradient(ellipse, rgba(32, 201, 151, 0.08) 0%, transparent 70%);
            transform: rotate(-15deg);
            z-index: 1;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-content { animation: slideInLeft 1s ease-out; }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(32, 201, 151, 0.12);
            border: 1px solid rgba(32, 201, 151, 0.3);
            border-radius: 50px;
            color: var(--primary);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .hero-badge i { color: var(--accent); }

        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.1;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }

        .hero-title .highlight {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 1.25rem;
            color: var(--text-light);
            margin-bottom: 2rem;
            line-height: 1.7;
        }

        .hero-cta { display: flex; gap: 1rem; margin-bottom: 3rem; flex-wrap: wrap; }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(3, 89, 70, 0.1);
        }

        .stat { text-align: center; }

        .stat-number {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .stat-label { font-size: 0.875rem; color: var(--text-light); }

        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: slideInRight 1s ease-out;
        }

        /* Features Section */
        .features-section {
            padding: 120px 0;
            position: relative;
            background: linear-gradient(135deg, #f0f9f6 0%, #e0f2ee 50%, #f0f9f6 100%);
            overflow: hidden;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 2;
        }

        .section-header { text-align: center; margin-bottom: 4rem; }

        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(32, 201, 151, 0.12);
            border: 1px solid rgba(32, 201, 151, 0.3);
            border-radius: 50px;
            color: var(--primary);
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .section-description {
            font-size: 1.25rem;
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            cursor: pointer;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .feature-card:hover::before { transform: scaleX(1); }
        .feature-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-lg); }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .feature-icon i { font-size: 1.5rem; color: var(--white); }
        .feature-card:hover .feature-icon { transform: scale(1.1) rotate(5deg); }

        .feature-card h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .feature-card p { color: var(--text-light); line-height: 1.6; }

        /* Companies Section */
        .companies-section {
            padding: 120px 0;
            background: var(--white);
            overflow: hidden;
        }

        .companies-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .companies-marquee { margin: 4rem 0; overflow: hidden; }

        .marquee-track {
            display: flex;
            animation: marqueeMove 30s linear infinite;
            gap: 4rem;
        }

        .company-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            min-width: 120px;
            transition: var(--transition);
        }

        .company-logo:hover { transform: scale(1.1); }
        .company-logo i { font-size: 3rem; color: var(--text-light); transition: var(--transition); }
        .company-logo:hover i { color: var(--primary); }
        .company-logo span { font-weight: 600; color: var(--text-dark); font-size: 0.875rem; }
        .companies-cta { text-align: center; margin-top: 3rem; }

        /* Success Stories Section */
        .success-section {
            padding: 120px 0;
            background: linear-gradient(135deg, #e0f2ee 0%, #f0f9f6 50%, #e0f2ee 100%);
            overflow: hidden;
        }

        .success-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2.5rem;
        }

        .story-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 2rem;
            transition: var(--transition);
        }

        .story-card:hover { transform: translateY(-15px); box-shadow: var(--shadow-lg); }
        .story-image { margin-bottom: 2rem; }

        .story-badge {
            width: 30px;
            height: 30px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .story-badge i { font-size: 0.75rem; color: var(--white); }

        .story-content blockquote {
            font-size: 1.125rem;
            line-height: 1.6;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            font-style: italic;
        }

        .story-author h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .story-author p { color: var(--text-light); font-size: 0.875rem; }
        .story-rating { margin-top: 1rem; display: flex; gap: 0.25rem; }
        .story-rating i { color: #fbbf24; font-size: 0.875rem; }

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

        .footer-logo i { color: var(--accent); }
        .footer-brand p { color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem; }
        .social-links { display: flex; gap: 1rem; }

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
        }

        .footer-column ul { list-style: none; }
        .footer-column ul li { margin-bottom: 0.75rem; }

        .footer-column ul li a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-column ul li a:hover { color: var(--accent); }

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

        .footer-bottom p { color: rgba(255, 255, 255, 0.5); }
        .footer-bottom-links { display: flex; gap: 2rem; }

        .footer-bottom-links a {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .footer-bottom-links a:hover { color: var(--accent); }

        /* Animations */
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-50px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes marqueeMove {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        [data-aos] { opacity: 0; transition: opacity 0.8s ease, transform 0.8s ease; }
        [data-aos].aos-animate { opacity: 1; }
        [data-aos="fade-up"] { transform: translateY(40px); }
        [data-aos="fade-up"].aos-animate { transform: translateY(0); }
        [data-aos="fade-right"] { transform: translateX(-40px); }
        [data-aos="fade-right"].aos-animate { transform: translateX(0); }
        [data-aos="fade-left"] { transform: translateX(40px); }
        [data-aos="fade-left"].aos-animate { transform: translateX(0); }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .nav-menu { display: none; }
            .hero-container { grid-template-columns: 1fr; text-align: center; gap: 2rem; }
            .hero-title { font-size: 2.5rem; }
            .profile-name, .profile-id { display: none; }
            .profile-trigger { padding: 0.5rem; }
            .hero-visual div[style*="width: 500px"] { width: 100% !important; max-width: 400px; margin: 0 auto; }
            .features-grid { grid-template-columns: 1fr; }
            .stories-grid { grid-template-columns: 1fr; }
            .footer-container { grid-template-columns: 1fr; }
            .footer-links { grid-template-columns: repeat(2, 1fr); }
        }
         .company-initial {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid var(--primary-light);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="nav-brand">
                <img src="nextternnavbar.png" alt="Nexttern Logo" class="nav-logo">
            </a>
            
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
                                <?php if ($user_role === 'company'): ?>
                                    <!-- Company Avatar: Show first letter of company name -->
                                    <div class="company-initial">
                                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                    </div>
                                <?php elseif (!empty($user_profile_picture) && file_exists($user_profile_picture)): ?>
                                    <!-- Student/User Avatar: Show profile picture -->
                                    <img src="<?php echo htmlspecialchars($user_profile_picture); ?>?v=<?php echo time(); ?>" alt="Profile" class="profile-avatar">
                                <?php else: ?>
                                    <!-- Default Avatar: Show first letter -->
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
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-star"></i>
                    Trusted by 3k+ Students & 2k+ Companies
                </div>
                
                <h1 class="hero-title">
                    Your Gateway to<br>
                    <span class="highlight">Professional Growth</span>
                </h1>
                
                <p class="hero-description">
                    Whether you're a student seeking meaningful internships or a company looking for talented interns, Nexttern connects the right people at the right time. Join our thriving community of future professionals and industry leaders.
                </p>
                
                <div class="hero-cta">
                    <a href="registerstudent.html" class="btn btn-primary">
                        <i class="fas fa-graduation-cap"></i>
                        I'm a Student
                    </a>
                    <a href="registercompany.html" class="btn btn-outline">
                        <i class="fas fa-building"></i>
                        I'm a Company
                    </a>
                </div>
                
                <div class="hero-stats">
                    <div class="stat">
                        <span class="stat-number">3000+</span>
                        <span class="stat-label">Active Students</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">200+</span>
                        <span class="stat-label">Partner Companies</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">95%</span>
                        <span class="stat-label">Success Rate</span>
                    </div>
                </div>
            </div>
            
            <div class="hero-visual">
                <div class="hero-illustration">
                    <div style="position: relative; width: 500px; height: 550px; margin: 0 auto; transform: translateY(-80px);">
                        <div class="hero-variant hero-variant-1 active">
                            <div style="position: absolute; bottom: 0; right: 20px; width: 380px; height: 380px; background: linear-gradient(135deg, #93c5fd 0%, #a78bfa 100%); border-radius: 50%; box-shadow: 0 25px 60px rgba(147, 197, 253, 0.3); z-index: 1; transition: all 0.8s ease;"></div>
                            <div style="position: absolute; top: 20px; left: 20px; width: 420px; height: 340px; background: linear-gradient(135deg, #fbbf24 0%, #fcd34d 100%); border-radius: 48% 52% 58% 42% / 45% 55% 45% 55%; box-shadow: 0 25px 60px rgba(251, 191, 36, 0.35); z-index: 1; transition: all 0.8s ease;"></div>
                            <img src="student-hero1.png" alt="Student with Books" style="position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); width: 380px; height: auto; z-index: 2; filter: drop-shadow(0 20px 45px rgba(0,0,0,0.25)); transition: all 0.8s ease;">
                        </div>
                        <div class="hero-variant hero-variant-2">
                            <div style="position: absolute; bottom: 20px; left: 30px; width: 360px; height: 360px; background: linear-gradient(135deg, #fda4af 0%, #fb7185 100%); border-radius: 60px; box-shadow: 0 25px 60px rgba(253, 164, 175, 0.35); z-index: 1; transform: rotate(-5deg); transition: all 0.8s ease;"></div>
                            <div style="position: absolute; top: 30px; right: 30px; width: 400px; height: 320px; background: linear-gradient(135deg, #6ee7b7 0%, #34d399 100%); border-radius: 42% 58% 52% 48% / 55% 45% 55% 45%; box-shadow: 0 25px 60px rgba(110, 231, 183, 0.35); z-index: 1; transition: all 0.8s ease;"></div>
                            <img src="student-hero2.png" alt="Student Success" style="position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); width: 380px; height: auto; z-index: 2; filter: drop-shadow(0 20px 45px rgba(0,0,0,0.25)); transition: all 0.8s ease;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

  <section class="features-section">
        <div class="features-container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="fas fa-rocket"></i>
                    Why Choose Nexttern
                </div>
                <h2 class="section-title">Everything You Need to <span class="highlight">Succeed</span></h2>
                <p class="section-description">Our platform provides comprehensive tools and resources to help you find the perfect internship and accelerate your career growth.</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card" data-aos="fade-up" data-aos-delay="0">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Smart Matching Algorithm</h3>
                    <p>Our AI-powered system matches you with internships that align perfectly with your skills, interests, and career aspirations.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Verified Companies</h3>
                    <p>All partner companies are thoroughly vetted to ensure legitimate opportunities and safe, professional work environments.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>Skill Development Hub</h3>
                    <p>Access exclusive workshops, courses, and mentorship programs designed to enhance your professional skills.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="0">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Career Analytics Dashboard</h3>
                    <p>Track your application progress and career growth with detailed insights and performance metrics.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Community Network</h3>
                    <p>Connect with alumni and industry professionals to expand your professional network and opportunities.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h3>One-Click Applications</h3>
                    <p>Apply to multiple internships effortlessly using our streamlined application process and profile system.</p>
                </div>
            </div>
        </div>
    </section>
    <section class="companies-section">
        <div class="companies-container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="fas fa-building"></i>
                    Trusted Partners
                </div>
                <h2 class="section-title">Join <span class="highlight">Leading Companies</span></h2>
                <p class="section-description">Connect with industry leaders and innovative startups that are shaping the future.</p>
            </div>
            
            <div class="companies-marquee">
                <div class="marquee-track">
                    <div class="company-logo"><i class="fab fa-microsoft"></i><span>Microsoft</span></div>
                    <div class="company-logo"><i class="fab fa-google"></i><span>Google</span></div>
                    <div class="company-logo"><i class="fab fa-amazon"></i><span>Amazon</span></div>
                    <div class="company-logo"><i class="fab fa-apple"></i><span>Apple</span></div>
                    <div class="company-logo"><i class="fab fa-meta"></i><span>Meta</span></div>
                    <div class="company-logo"><i class="fab fa-salesforce"></i><span>Salesforce</span></div>
                    <div class="company-logo"><i class="fab fa-slack"></i><span>Slack</span></div>
                    <div class="company-logo"><i class="fab fa-uber"></i><span>Uber</span></div>
                    <div class="company-logo"><i class="fab fa-spotify"></i><span>Spotify</span></div>
                </div>
            </div>
            
            <div class="companies-cta">
                <a href="logincompany.html" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Partner With Us
                </a>
            </div>
        </div>
    </section>

 <section class="success-section">
    <div class="success-container">
        <div class="section-header">
            <div class="section-badge">
                <i class="fas fa-trophy"></i>
                Success Stories
            </div>
            <h2 class="section-title">Real Stories, <span class="highlight">Real Impact</span></h2>
            <p class="section-description">Hear from students who transformed their careers through Nexttern internships.</p>
        </div>
        
        <div class="stories-grid">
            <?php if (!empty($stories_to_display)): ?>
                <?php 
                $animations = ['fade-right', 'fade-up', 'fade-left'];
                foreach (array_slice($stories_to_display, 0, 3) as $index => $story): 
                    $author_name = htmlspecialchars(trim($story['first_name'] . ' ' . $story['last_name']));
                    $story_excerpt = strlen($story['story_content']) > 180 
                        ? htmlspecialchars(substr($story['story_content'], 0, 180)) . '...'
                        : htmlspecialchars($story['story_content']);
                    $rating = max(1, min(5, intval($story['feedback_rating'] ?? 5)));
                    $category = htmlspecialchars($story['story_category']);
                ?>
                    <div class="story-card" data-aos="<?php echo $animations[$index % 3]; ?>">
                        <div class="story-image">
                            <div class="story-badge"><i class="fas fa-star"></i></div>
                        </div>
                        <div class="story-content">
                            <blockquote>"<?php echo $story_excerpt; ?>"</blockquote>
                            <div class="story-author">
                                <h4><?php echo $author_name; ?></h4>
                                <p><?php echo $category; ?></p>
                            </div>
                            <div class="story-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $rating ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback stories when no approved stories exist -->
                <div class="story-card" data-aos="fade-right">
                    <div class="story-image">
                        <div class="story-badge"><i class="fas fa-star"></i></div>
                    </div>
                    <div class="story-content">
                        <blockquote>"Nexttern connected me with my dream internship at a leading tech company. The mentorship and support I received was incredible!"</blockquote>
                        <div class="story-author">
                            <h4>Sarah Johnson</h4>
                            <p>Software Engineering Intern at Google</p>
                        </div>
                        <div class="story-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                
                <div class="story-card" data-aos="fade-up">
                    <div class="story-image">
                        <div class="story-badge"><i class="fas fa-trophy"></i></div>
                    </div>
                    <div class="story-content">
                        <blockquote>"The platform's matching algorithm was spot-on. I found an internship that perfectly matched my skills and career aspirations."</blockquote>
                        <div class="story-author">
                            <h4>Michael Chen</h4>
                            <p>Data Science Intern at Microsoft</p>
                        </div>
                        <div class="story-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                
                <div class="story-card" data-aos="fade-left">
                    <div class="story-image">
                        <div class="story-badge"><i class="fas fa-rocket"></i></div>
                    </div>
                    <div class="story-content">
                        <blockquote>"From application to offer letter, the entire process was smooth. I'm now a full-time employee at my internship company!"</blockquote>
                        <div class="story-author">
                            <h4>Emily Rodriguez</h4>
                            <p>Marketing Manager at Adobe</p>
                        </div>
                        <div class="story-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand">
                <a href="#" class="footer-logo">
                    <i class="fas fa-graduation-cap"></i> Nexttern
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
                    <a href="about_us.php">Privacy Policy</a>
                    <a href="aboutus.php">Terms of Service</a>
                    <a href="aboutus.php">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
      function redirectToDashboard(userRole) {
            const dashboards = {
                'admin': 'admin_dashboard.php',
                'company': 'company_dashboard.php',
                'student': 'student_dashboard.php'
            };
            window.location.href = dashboards[userRole] || 'student_dashboard.php';
        }

        // Hero visual transition
        let currentVariant = 1;
        const transitionInterval = 5000;
        
        document.querySelectorAll('.hero-variant').forEach((variant, index) => {
            variant.style.position = 'absolute';
            variant.style.top = '0';
            variant.style.left = '0';
            variant.style.width = '100%';
            variant.style.height = '100%';
            variant.style.transition = 'opacity 1s ease-in-out';
            variant.style.opacity = index === 0 ? '1' : '0';
            variant.style.zIndex = index === 0 ? '2' : '1';
        });
        
        function transitionHeroVisual() {
            const currentEl = document.querySelector('.hero-variant.active');
            const nextVariant = currentVariant === 1 ? 2 : 1;
            const nextEl = document.querySelector(`.hero-variant-${nextVariant}`);
            
            if (currentEl && nextEl) {
                nextEl.style.zIndex = '2';
                currentEl.style.zIndex = '1';
                currentEl.style.opacity = '0';
                nextEl.style.opacity = '1';
                
                setTimeout(() => {
                    currentEl.classList.remove('active');
                    nextEl.classList.add('active');
                }, 1000);
                
                currentVariant = nextVariant;
            }
        }
        
        setTimeout(() => {
            setInterval(transitionHeroVisual, transitionInterval);
        }, transitionInterval);

        // Navbar scroll behavior
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');

        function handleNavbarScroll() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                navbar.style.transform = 'translateY(-100%)';
            } else if (scrollTop < lastScrollTop) {
                navbar.style.transform = 'translateY(0)';
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
            }
            
            if (scrollTop <= 10) {
                navbar.style.transform = 'translateY(0)';
                navbar.style.background = 'rgba(255, 255, 255, 0.85)';
                navbar.style.boxShadow = 'none';
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        }

        function throttle(func, limit) {
            let inThrottle;
            return function() {
                if (!inThrottle) {
                    func.apply(this, arguments);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            }
        }

        window.addEventListener('scroll', throttle(handleNavbarScroll, 10));

        // AOS Animation
        const observeElements = () => {
            const elements = document.querySelectorAll('[data-aos]');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('aos-animate');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            elements.forEach(element => observer.observe(element));
        };

        // Stats animation
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const stats = entry.target.querySelectorAll('.stat-number');
                    stats.forEach(stat => {
                        const target = stat.textContent;
                        const number = parseInt(target.replace(/[^\d]/g, ''));
                        const suffix = target.replace(/[\d]/g, '');
                        let current = 0;
                        const increment = number / 30;
                        
                        const timer = setInterval(() => {
                            current += increment;
                            if (current >= number) {
                                stat.textContent = target;
                                clearInterval(timer);
                            } else {
                                stat.textContent = Math.floor(current) + suffix;
                            }
                        }, 50);
                    });
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        document.addEventListener('DOMContentLoaded', () => {
            observeElements();
            const heroStats = document.querySelector('.hero-stats');
            if (heroStats) statsObserver.observe(heroStats);
        });
    </script>
</body>
</html>