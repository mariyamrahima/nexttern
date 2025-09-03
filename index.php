
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
            $user_stmt = $user_conn->prepare("SELECT name, email, profile_picture, role FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $user_id);
        } elseif (isset($_SESSION['email'])) {
            $email = $_SESSION['email'];
            // Try students table first
            $user_stmt = $user_conn->prepare("SELECT student_id as id, CONCAT(first_name, ' ', last_name) as name, email, '' as profile_picture, 'student' as role FROM students WHERE email = ?");
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
}?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexttern - Your Gateway to Professional Growth</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>/* Animations */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.6;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-15px);
            }
        }

        /* Blob Animations */
        @keyframes blobMove {
            0%, 100% {
                transform: translateX(0px) translateY(0px) scale(1);
            }
            33% {
                transform: translateX(30px) translateY(-50px) scale(1.1);
            }
            66% {
                transform: translateX(-20px) translateY(20px) scale(0.9);
            }
        }

        @keyframes marqueeMove {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }

        /* Section Styling */
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(78, 205, 196, 0.1);
            border: 1px solid rgba(78, 205, 196, 0.3);
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
            line-height: 1.2;
        }

        .section-description {
            font-size: 1.25rem;
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Blobs 
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 0;
            opacity: 0.3;
            animation: blobMove 20s ease-in-out infinite;
        }

        .blob-1 {
            width: 500px;
            height: 500px;
            background: var(--gradient-primary);
            top: -200px;
            right: -200px;
            animation-delay: 0s;
        }

        .blob-2 {
            width: 400px;
            height: 400px;
            background: var(--gradient-accent);
            bottom: -100px;
            left: -150px;
            animation-delay: 5s;
        }

        .blob-3 {
            width: 600px;
            height: 600px;
            background: var(--gradient-accent);
            top: -250px;
            left: -250px;
            animation-delay: 2s;
        }

        .blob-4 {
            width: 350px;
            height: 350px;
            background: var(--gradient-primary);
            bottom: -150px;
            right: -100px;
            animation-delay: 7s;
        }

        .blob-5 {
            width: 450px;
            height: 450px;
            background: var(--gradient-primary);
            top: -180px;
            right: -180px;
            animation-delay: 1s;
        }

        .blob-6 {
            width: 380px;
            height: 380px;
            background: var(--gradient-accent);
            bottom: -120px;
            left: -120px;
            animation-delay: 6s;
        }

        .blob-7 {
            width: 500px;
            height: 500px;
            background: var(--gradient-primary);
            top: -200px;
            left: -200px;
            animation-delay: 3s;
        }

        .blob-8 {
            width: 400px;
            height: 400px;
            background: var(--gradient-accent);
            bottom: -150px;
            right: -150px;
            animation-delay: 8s;
        }

        /* Features Section */
        .features-section {
            padding: 120px 0;
            position: relative;
            background: linear-gradient(135deg, #f8fafc 0%, #e0f2f1 50%, #f8fafc 100%);
            overflow: hidden;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 2;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

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

        .feature-icon i {
            font-size: 1.5rem;
            color: var(--white);
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .feature-card h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: var(--text-light);
            line-height: 1.6;
        }

        .feature-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .feature-card:hover .feature-overlay {
            opacity: 0.05;
        }

        /* Companies Section */
        .companies-section {
            padding: 120px 0;
            position: relative;
            background: var(--white);
            overflow: hidden;
        }

        .companies-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 2;
        }

        .companies-marquee {
            margin: 4rem 0;
            overflow: hidden;
        }

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

        .company-logo:hover {
            transform: scale(1.1);
        }

        .company-logo i {
            font-size: 3rem;
            color: var(--text-light);
            transition: var(--transition);
        }

        .company-logo:hover i {
            color: var(--primary);
        }

        .company-logo span {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.875rem;
        }

        .companies-cta {
            text-align: center;
            margin-top: 3rem;
        }

        /* Success Stories Section */
        .success-section {
            padding: 120px 0;
            position: relative;
            background: linear-gradient(135deg, #e0f2f1 0%, #f8fafc 50%, #e0f2f1 100%);
            overflow: hidden;
        }

        .success-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 2;
        }

        .stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2.5rem;
        }

        .story-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 2rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .story-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .story-card:hover::before {
            opacity: 0.05;
        }

        .story-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-lg);
        }

        .story-image {
            position: relative;
            margin-bottom: 2rem;
        }

        .story-image img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }

        .story-badge {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 30px;
            height: 30px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid var(--white);
        }

        .story-badge i {
            font-size: 0.75rem;
            color: var(--white);
        }

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
            margin-bottom: 0.25rem;
        }

        .story-author p {
            color: var(--text-light);
            font-size: 0.875rem;
        }

        .story-rating {
            margin-top: 1rem;
            display: flex;
            gap: 0.25rem;
        }

        .story-rating i {
            color: #fbbf24;
            font-size: 0.875rem;
        }

        /* CTA Section */
        .cta-section {
            padding: 120px 0;
            position: relative;
            background: var(--white);
            overflow: hidden;
        }

        .cta-container {
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

        .cta-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(78, 205, 196, 0.1);
            border: 1px solid rgba(78, 205, 196, 0.3);
            border-radius: 50px;
            color: var(--primary);
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .cta-title {
            font-family: 'Poppins', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .cta-description {
            font-size: 1.25rem;
            color: var(--text-light);
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.125rem;
        }

        .cta-features {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .cta-feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cta-feature i {
            color: var(--accent);
            font-size: 1.125rem;
        }

        .cta-feature span {
            color: var(--text-light);
            font-weight: 500;
        }

        .cta-visual {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .cta-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
            animation: float 6s ease-in-out infinite;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(3, 89, 70, 0.1);
        }

        .card-dots {
            display: flex;
            gap: 0.5rem;
        }

        .card-dots span {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--gradient-primary);
        }

        .card-header h4 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--text-dark);
        }

        .progress-item {
            margin-bottom: 1.5rem;
        }

        .progress-item span {
            display: block;
            font-size: 0.875rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(3, 89, 70, 0.1);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--gradient-primary);
            border-radius: 4px;
            animation: progressFill 2s ease-out;
        }

        @keyframes progressFill {
            from { width: 0; }
        }

        .notification {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: rgba(78, 205, 196, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(78, 205, 196, 0.2);
        }

        .notification i {
            color: var(--accent);
            font-size: 1.125rem;
        }

        .notification span {
            color: var(--text-dark);
            font-weight: 500;
            font-size: 0.875rem;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(3, 89, 70, 0.1);
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

        .nav-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
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
        }

        .nav-link:hover {
            color: var(--primary);
        }

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

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-cta {
            display: flex;
            align-items: center;
        }

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
            background: var(--gradient-primary);
            color: var(--white);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 100px;
            position: relative;
            background: linear-gradient(135deg, #f8fafc 0%, #e0f2f1 100%);
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 80%;
            height: 200%;
            background: radial-gradient(ellipse, rgba(78, 205, 196, 0.1) 0%, transparent 70%);
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

        .hero-content {
            animation: slideInLeft 1s ease-out;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(78, 205, 196, 0.1);
            border: 1px solid rgba(78, 205, 196, 0.3);
            border-radius: 50px;
            color: var(--primary);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .hero-badge i {
            color: var(--accent);
        }

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
/* Hero Section */
.hero-description {
    font-size: 1.25rem;
    color: var(--text-light);
    margin-bottom: 2rem;
    line-height: 1.7;
}

.hero-cta {
    display: flex;
    gap: 1rem;
    margin-bottom: 3rem;
    flex-wrap: wrap;
}

.hero-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(3, 89, 70, 0.1);
}

.stat {
    text-align: center;
}

.stat-number {
    font-family: 'Poppins', sans-serif;
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    display: block;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-light);
    font-weight: 500;
}

.hero-visual {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    animation: slideInRight 1s ease-out;
}

.hero-image {
    position: relative;
    z-index: 3;
}

.hero-image img {
    width: 100%;
    max-width: 500px;
    height: auto;
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
}

.floating-card {
    position: absolute;
    background: var(--white);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    z-index: 4;
    animation: float 6s ease-in-out infinite;
}

.card-1 {
    top: 10%;
    left: -10%;
    animation-delay: 0s;
}

.card-2 {
    bottom: 20%;
    right: -10%;
    animation-delay: 2s;
}

.card-3 {
    top: 60%;
    left: -5%;
    animation-delay: 4s;
}

.floating-card h4 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.floating-card p {
    font-size: 0.75rem;
    color: var(--text-light);
    margin: 0;
}

.floating-card .icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--gradient-accent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    margin-bottom: 1rem;
}

/* Decorative Elements */
.decoration {
    position: absolute;
    z-index: 1;
    opacity: 0.6;
}

.decoration-1 {
    top: 20%;
    left: 5%;
    width: 60px;
    height: 60px;
    background: var(--gradient-accent);
    border-radius: 50%;
    animation: pulse 4s ease-in-out infinite;
}

.decoration-2 {
    bottom: 30%;
    right: 10%;
    width: 80px;
    height: 80px;
    border: 3px solid var(--accent);
    border-radius: 50%;
    animation: rotate 20s linear infinite;
}

.decoration-3 {
    top: 50%;
    left: 2%;
    width: 40px;
    height: 40px;
    background: var(--primary);
    clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
    animation: bounce 3s ease-in-out infinite;
}

/* Animations */
@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-50px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(50px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-20px);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 0.6;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.8;
    }
}

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-15px);
    }
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .nav-menu {
        display: none;
    }

    .nav-logo {
        height: 35px;
    }

    .hero-container {
        grid-template-columns: 1fr;
        gap: 2rem;
        text-align: center;
    }

    .hero-title {
        font-size: 2.5rem;
    }

    .hero-description {
        font-size: 1.125rem;
    }

    .hero-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }

    .floating-card {
        display: none;
    }

    .decoration {
        display: none;
    }
}

@media (max-width: 480px) {
    .nav-container {
        padding: 0 1rem;
    }

    .nav-logo {
        height: 60px;
    }

    .hero-container {
        padding: 0 1rem;
    }

    .hero-title {
        font-size: 2rem;
    }

    .hero-cta {
        flex-direction: column;
        align-items: center;
    }

    .btn {
        width: 100%;
        justify-content: center;
        max-width: 300px;
    }
}

/* Navigation & Buttons */
.nav-link:hover::after,
.nav-link.active::after {
    width: 80%;
}

.nav-cta {
    display: flex;
    align-items: center;
    gap: 1rem;
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

/* Profile Trigger Styles */
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
    font-family: 'Poppins', sans-serif;
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

/* Animation for the message badge */
@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

/* Mobile Menu Toggle */
.menu-toggle {
    display: none;
    flex-direction: column;
    gap: 4px;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: var(--transition);
}

.menu-toggle:hover {
    background: rgba(3, 89, 70, 0.1);
}

.menu-toggle span {
    width: 25px;
    height: 3px;
    background: var(--primary);
    border-radius: 2px;
    transition: var(--transition);
}

/* Mobile Responsive Navigation */
@media (max-width: 768px) {
    .nav-container {
        padding: 0 1rem;
    }

    .nav-menu {
        position: fixed;
        top: 100%;
        left: 0;
        right: 0;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        flex-direction: column;
        padding: 2rem;
        gap: 1rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        transform: translateY(-100%);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .nav-menu.active {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
    }

    .menu-toggle {
        display: flex;
    }

    .profile-name {
        display: none;
    }

    .nav-link {
        padding: 1rem;
        border-radius: 12px;
        text-align: center;
    }

    .nav-cta {
        gap: 0.5rem;
    }

    .btn {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
    }

    .profile-trigger {
        padding: 0.4rem 0.8rem;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .nav-container {
        padding: 0 0.5rem;
    }

    .nav-logo {
        height: 35px;
    }

    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    .profile-avatar {
        width: 28px;
        height: 28px;
    }

    .profile-avatar.default {
        font-size: 0.8rem;
    }
}

    </style>
</head>
<body>
    <!-- Navigation -->
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
                <li><a href="contactus.php" class="nav-link">Contact</a></li>
            </ul>
            
       
<!-- NEW -->
<div class="nav-cta">
    <?php if ($isLoggedIn): ?>
        <div class="nav-profile">
            <button class="profile-trigger" onclick="window.location.href='internship.php'">
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

    <!-- Hero Section -->
    <section class="hero">
        <!-- Decorative Elements -->
        <div class="decoration decoration-1"></div>
        <div class="decoration decoration-2"></div>
        <div class="decoration decoration-3"></div>

        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-star"></i>
                    Trusted by 10,000+ Students & 500+ Companies
                </div>
                
                <h1 class="hero-title">
                    Your Gateway to<br>
                    <span class="highlight">Professional Growth</span>
                </h1>
                
                <p class="hero-description">
                    Whether you're a student seeking meaningful internships or a company looking for talented interns, Nexttern connects the right people at the right time. Join our thriving community of future professionals and industry leaders.
                </p>
                
                <div class="hero-cta">
                    <a href="registerstudent.html" class="btn btn-primary" id="studentBtn">
                        <i class="fas fa-graduation-cap"></i>
                        I'm a Student
                    </a>
                    <a href="registercompany.html" class="btn btn-outline" id="companyBtn">
                        <i class="fas fa-building"></i>
                        I'm a Company
                    </a>
                </div>
                
                <div class="hero-stats">
                    <div class="stat">
                        <span class="stat-number">10K+</span>
                        <span class="stat-label">Active Students</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">500+</span>
                        <span class="stat-label">Partner Companies</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">95%</span>
                        <span class="stat-label">Success Rate</span>
                    </div>
                </div>
            </div>
            
            <div class="hero-visual">
                <div class="hero-image">
                    <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Students collaborating">
                </div>
                
                <!-- Floating Cards -->
                <div class="floating-card card-1">
                    <div class="icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <h4>2,500+ Internships</h4>
                    <p>Available across industries</p>
                </div>
                
                <div class="floating-card card-2">
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>Expert Mentorship</h4>
                    <p>Guidance from industry professionals</p>
                </div>
                
                <div class="floating-card card-3">
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h4>Career Growth</h4>
                    <p>Track your professional development</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        
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
                    <h3>Smart Matching</h3>
                    <p>Our AI-powered algorithm matches you with internships that align perfectly with your skills, interests, and career goals.</p>
                    <div class="feature-overlay"></div>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Verified Companies</h3>
                    <p>All partner companies are thoroughly vetted to ensure legitimate opportunities and safe, professional work environments.</p>
                    <div class="feature-overlay"></div>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>Skill Development</h3>
                    <p>Access exclusive workshops, courses, and mentorship programs designed to enhance your professional skills.</p>
                    <div class="feature-overlay"></div>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Career Analytics</h3>
                    <p>Track your application progress, performance metrics, and career growth with detailed analytics dashboard.</p>
                    <div class="feature-overlay"></div>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Community Network</h3>
                    <p>Connect with fellow interns, alumni, and industry professionals to expand your professional network.</p>
                    <div class="feature-overlay"></div>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h3>Easy Applications</h3>
                    <p>Apply to multiple internships with one click using our streamlined application process and profile system.</p>
                    <div class="feature-overlay"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Companies Section -->
    <section class="companies-section">
        <div class="blob blob-3"></div>
        <div class="blob blob-4"></div>
        
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
                    <div class="company-logo">
                        <i class="fab fa-microsoft"></i>
                        <span>Microsoft</span>
                    </div>
                    <div class="company-logo">
                        <i class="fab fa-google"></i>
                        <span>Google</span>
                    </div>
                    <div class="company-logo">
                        <i class="fab fa-amazon"></i>
                        <span>Amazon</span>
                    </div>
                    <div class="company-logo">
                        <i class="fab fa-apple"></i>
                        <span>Apple</span>
                    </div>
                    <div class="company-logo">
                        <i class="fab fa-meta"></i>
                        <span>Meta</span>
                    </div>
                    <div class="company-logo">
                        <i class="fab fa-netflix"></i>
                        <span>Netflix</span>
                    </div>
                    <div class="company-logo">
                        <i class="fab fa-spotify"></i>
                        <span>Spotify</span>
                    </div>
                    <div class="company-logo">
                        <i class="fab fa-adobe"></i>
                        <span>Adobe</span>
                    </div>
                </div>
            </div>
            
            <div class="companies-cta">
                <a href="logincompany.html" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Partner With Us
                </a>
            </div>
        </div>
    </section>

    <!-- Success Stories Section -->
    <section class="success-section">
        <div class="blob blob-5"></div>
        <div class="blob blob-6"></div>
        
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
                <div class="story-card" data-aos="fade-right" data-aos-delay="0">
                    <div class="story-image">
                        <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Sarah Johnson">
                        <div class="story-badge">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="story-content">
                        <blockquote>
                            "Nexttern connected me with my dream internship at a leading tech company. The mentorship and support I received was incredible!"
                        </blockquote>
                        <div class="story-author">
                            <h4>Sarah Johnson</h4>
                            <p>Software Engineering Intern at Google</p>
                        </div>
                        <div class="story-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                
                <div class="story-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="story-image">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Michael Chen">
                        <div class="story-badge">
                            <i class="fas fa-trophy"></i>
                        </div>
                    </div>
                    <div class="story-content">
                        <blockquote>
                            "The platform's matching algorithm was spot-on. I found an internship that perfectly matched my skills and career aspirations."
                        </blockquote>
                        <div class="story-author">
                            <h4>Michael Chen</h4>
                            <p>Data Science Intern at Microsoft</p>
                        </div>
                        <div class="story-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                
                <div class="story-card" data-aos="fade-left" data-aos-delay="200">
                    <div class="story-image">
                        <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Emily Rodriguez">
                        <div class="story-badge">
                            <i class="fas fa-rocket"></i>
                        </div>
                    </div>
                    <div class="story-content">
                        <blockquote>
                            "From application to offer letter, the entire process was smooth. I'm now a full-time employee at my internship company!"
                        </blockquote>
                        <div class="story-author">
                            <h4>Emily Rodriguez</h4>
                            <p>Marketing Manager at Adobe</p>
                        </div>
                        <div class="story-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="blob blob-7"></div>
        <div class="blob blob-8"></div>
        
        <div class="cta-container">
            <div class="cta-content">
                <div class="cta-badge">
                    <i class="fas fa-lightning-bolt"></i>
                    Start Today
                </div>
                <h2 class="cta-title">Ready to Launch Your <span class="highlight">Career?</span></h2>
                <p class="cta-description">Join thousands of students who have already found their dream internships through Nexttern. Your future starts here.</p>
                
                <div class="cta-buttons">
                    <a href="registerstudent.html" class="btn btn-primary btn-large">
                        <i class="fas fa-user-plus"></i>
                        Sign Up as Student
                    </a>
                    <a href="registercompany.html" class="btn btn-outline btn-large">
                        <i class="fas fa-building"></i>
                        Register Company
                    </a>
                </div>
                
                <div class="cta-features">
                    <div class="cta-feature">
                        <i class="fas fa-check"></i>
                        <span>Free to join</span>
                    </div>
                    <div class="cta-feature">
                        <i class="fas fa-check"></i>
                        <span>Instant matching</span>
                    </div>
                    <div class="cta-feature">
                        <i class="fas fa-check"></i>
                        <span>24/7 support</span>
                    </div>
                </div>
            </div>
            
            <div class="cta-visual">
                <div class="cta-card">
                    <div class="card-header">
                        <div class="card-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <h4>Your Dashboard</h4>
                    </div>
                    <div class="card-content">
                        <div class="progress-item">
                            <span>Profile Completion</span>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 85%"></div>
                            </div>
                        </div>
                        <div class="progress-item">
                            <span>Applications Sent</span>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 60%"></div>
                            </div>
                        </div>
                        <div class="notification">
                            <i class="fas fa-bell"></i>
                            <span>3 new matches found!</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
                        <li><a href="#">Find Internships</a></li>
                        <li><a href="#">Resume Builder</a></li>
                        <li><a href="#">Career Resources</a></li>
                        <li><a href="#">Success Stories</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>For Companies</h4>
                    <ul>
                        <li><a href="#">Post Internships</a></li>
                        <li><a href="#">Find Talent</a></li>
                        <li><a href="#">Pricing</a></li>
                        <li><a href="#">Enterprise</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Community</a></li>
                        <li><a href="#">Blog</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Company</h4>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Press</a></li>
                        <li><a href="#">Legal</a></li>
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
        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 2px 20px rgba(3, 89, 70, 0.1)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.boxShadow = 'none';
            }
        });

        // Simple AOS (Animate On Scroll) implementation
        const observeElements = () => {
            const elements = document.querySelectorAll('[data-aos]');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('aos-animate');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            elements.forEach(element => {
                observer.observe(element);
            });
        };

        // Initialize AOS
        document.addEventListener('DOMContentLoaded', observeElements);

        // Animate stats on scroll
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -100px 0px'
        };

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
        }, observerOptions);

        const heroStats = document.querySelector('.hero-stats');
        if (heroStats) {
            statsObserver.observe(heroStats);
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

        // Progress bar animation
        const progressObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const progressBars = entry.target.querySelectorAll('.progress-fill');
                    progressBars.forEach((bar, index) => {
                        setTimeout(() => {
                            bar.style.animation = 'progressFill 2s ease-out forwards';
                        }, index * 200);
                    });
                    progressObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        const ctaCard = document.querySelector('.cta-card');
        if (ctaCard) {
            progressObserver.observe(ctaCard);
        }

        // Add hover effect to feature cards
        document.querySelectorAll('.feature-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Add parallax effect to blobs
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallax = scrolled * 0.5;
            
            document.querySelectorAll('.blob').forEach((blob, index) => {
                const speed = (index + 1) * 0.1;
                blob.style.transform = `translateY(${parallax * speed}px)`;
            });
        });

        // Intersection Observer for section animations
        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        });

        // Apply initial styles and observe sections
        document.querySelectorAll('section').forEach(section => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(20px)';
            section.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            sectionObserver.observe(section);
        });

        // Reset hero section visibility
        document.querySelector('.hero').style.opacity = '1';
        document.querySelector('.hero').style.transform = 'translateY(0)';
    </script>
</body>
</html>