<?php 
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and check authentication
session_start();

// Security: Check if company is logged in
if (!isset($_SESSION['company_id'])) {
    // Clear any conflicting session data
    session_unset();
    session_destroy();
    header('Location: logincompany.html');
    exit();
}

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get the page parameter
$page = $_GET['page'] ?? 'home';

// Database connection function with error handling
function getDatabaseConnection() {
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "nexttern_db";
    
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }
    
    return $conn;
}

// Function to safely fetch count from database
function getCount($conn, $query, $params = [], $default = 0) {
    if (!$conn) return $default;
    
    try {
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return (int)($row['count'] ?? $row[array_key_first($row)] ?? 0);
                }
                $stmt->close();
            }
        } else {
            $result = $conn->query($query);
            if ($result && $row = $result->fetch_assoc()) {
                return (int)($row['count'] ?? $row[array_key_first($row)] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log("Query error: " . $e->getMessage());
    }
    
    return $default;
}

// Initialize company details from session
$company_id = $_SESSION['company_id'] ?? '';        // Display ID (like CO4) 
$company_name = $_SESSION['company_name'] ?? 'Company';
$industry_type = $_SESSION['industry_type'] ?? '';

// If session data is incomplete, fetch from database and update session
if (empty($company_id) || empty($company_name)) {
    $conn = getDatabaseConnection();
    
    if ($conn && !empty($_SESSION['company_id'])) {
        try {
            $stmt = $conn->prepare("SELECT company_id, company_name, industry_type FROM companies WHERE company_id = ? AND status = 'active'");
            if ($stmt) {
                $stmt->bind_param("s", $_SESSION['company_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    // Update session with complete company data
                    $_SESSION['company_id'] = $row['company_id'];
                    $_SESSION['company_name'] = $row['company_name'];
                    $_SESSION['industry_type'] = $row['industry_type'];
                    
                    // Update local variables
                    $company_id = $row['company_id'];
                    $company_name = $row['company_name'];
                    $industry_type = $row['industry_type'];
                    
                    error_log("Session updated with company data: ID={$company_id}, Name={$company_name}");
                } else {
                    // Company not found in database
                    error_log("Company not found in database for ID: {$_SESSION['company_id']}");
                    session_unset();
                    session_destroy();
                    header('Location: logincompany.html?error=company_not_found');
                    exit();
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error fetching company data: " . $e->getMessage());
        }
        
        if ($conn) $conn->close();
    }
}

// Initialize statistics
$active_internships_count = 0;
$total_applications_count = 0;
$monthly_payments_count = 0;
$hired_interns_count = 0;

// Only fetch data for home page to avoid unnecessary queries
if ($page === 'home' && !empty($company_id)) {
    $conn = getDatabaseConnection();
    
    if ($conn) {
        try {
            // Get active internships count using company_id
            $active_internships_count = getCount(
                $conn, 
                "SELECT COUNT(*) as count FROM course WHERE company_id = ? AND course_status = 'Active'", 
                [$company_id]
            );
            
            // Get total applications count
            $total_applications_count = getCount(
                $conn, 
                "SELECT COUNT(DISTINCT a.id) as count FROM applications a 
                 INNER JOIN course c ON a.course_id = c.id 
                 WHERE c.company_id = ?", 
                [$company_id]
            );
            
            // Get monthly payments count (assuming you have a payments table)
            $monthly_payments_count = getCount(
                $conn, 
                "SELECT COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END), 0) as count
                 FROM company_payments WHERE company_id = ? 
                 AND payment_date >= DATE_FORMAT(NOW(), '%Y-%m-01')", 
                [$company_id]
            );
            
            // Get hired interns count
            $hired_interns_count = getCount(
                $conn, 
                "SELECT COUNT(DISTINCT a.id) as count FROM applications a 
                 INNER JOIN course c ON a.course_id = c.id 
                 WHERE c.company_id = ? AND a.status = 'hired'", 
                [$company_id]
            );
            
            error_log("Statistics fetched for company {$company_id}: Active={$active_internships_count}, Applications={$total_applications_count}");
            
        } catch (Exception $e) {
            error_log("Dashboard stats error: " . $e->getMessage());
        } finally {
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company_name); ?> Dashboard | Nexttern</title>
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
            --sidebar-width: 280px;
            --sidebar-collapsed: 70px;
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
            display: flex;
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
            width: 600px; 
            height: 600px; 
            background: rgba(3, 89, 70, 0.12); 
            top: -150px; 
            right: -200px; 
        }

        .blob2 { 
            width: 400px; 
            height: 400px; 
            background: rgba(78, 205, 196, 0.15); 
            top: 200px; 
            right: -150px; 
            animation-delay: 2s; 
        }

        .blob3 { 
            width: 350px; 
            height: 350px; 
            background: rgba(3, 89, 70, 0.08); 
            bottom: 100px; 
            left: -180px; 
            animation-delay: 4s; 
        }

        .blob4 { 
            width: 250px; 
            height: 250px; 
            background: rgba(78, 205, 196, 0.12); 
            bottom: -100px; 
            left: 150px; 
            animation-delay: 1s; 
        }

        @keyframes moveBlob {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.1); }
            100% { transform: translate(-30px, 30px) scale(0.9); }
        }

        /* Enhanced Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary-dark);
            color: white;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Sidebar States */
        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
            padding: 1.5rem 0.8rem;
        }

        /* Mobile Sidebar */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 300px;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .sidebar.collapsed {
                width: 300px;
                padding: 1.5rem;
            }
        }

        /* Sidebar Header with Toggle Button */
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }

        .sidebar-toggle-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.1rem;
        }

        .sidebar-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .sidebar.collapsed .sidebar-toggle-btn {
            margin: 0 auto;
        }

        /* Navigation - Scrollable content area */
        .nav-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .nav-section {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }

        .nav-section h4 {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 0.5rem;
            font-weight: 500;
            transition: var(--transition);
            padding-left: 1rem;
        }

        .sidebar.collapsed .nav-section h4 {
            opacity: 0;
            height: 0;
            margin: 0;
            overflow: hidden;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
            margin: 2px 0;
            background: transparent;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 0.8rem;
            margin: 4px 0;
        }

        .sidebar.collapsed .nav-link span {
            opacity: 0;
            width: 0;
            overflow: hidden;
            transition: var(--transition);
        }

        .nav-link i {
            font-size: 1rem;
            width: 18px;
            text-align: center;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .nav-link:hover {
            background: var(--primary-light);
            transform: translateX(5px);
            color: white;
        }

        .sidebar.collapsed .nav-link:hover {
            transform: scale(1.08);
        }

        .nav-link.active {
            background: var(--accent);
            color: var(--primary-dark);
            transform: translateX(5px);
        }

        .sidebar.collapsed .nav-link.active {
            transform: scale(1.08);
        }

        .nav-link:hover i,
        .nav-link.active i {
            transform: scale(1.15);
        }

        /* Logout Button */
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
            width: 100%;
            justify-content: center;
            text-decoration: none;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }

        .sidebar.collapsed .logout-btn span {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        /* Main Content */
        .main {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            overflow-y: auto;
            background: transparent;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
            min-height: 100vh;
        }

        .sidebar.collapsed ~ .main {
            margin-left: var(--sidebar-collapsed);
        }

        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 1.5rem;
                width: 100%;
            }
            
            .sidebar.collapsed ~ .main {
                margin-left: 0;
            }
        }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: var(--transition);
            display: none;
        }

        .mobile-menu-btn:hover {
            background: var(--primary-light);
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        /* Welcome Container */
        .welcome-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-header {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow-light);
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(78, 205, 196, 0.08) 50%, transparent 70%);
            animation: shimmer 8s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .welcome-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .company-info {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
            flex-wrap: wrap;
        }

        .company-name-display {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            background: rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1rem;
            border-radius: 12px;
            border: 1px solid var(--glass-border);
        }

        .company-id-display {
            font-size: 0.9rem;
            color: var(--secondary);
            background: rgba(78, 205, 196, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(78, 205, 196, 0.3);
            font-weight: 500;
        }

        .company-industry-display {
            font-size: 0.85rem;
            color: var(--info);
            background: rgba(52, 152, 219, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 10px;
            border: 1px solid rgba(52, 152, 219, 0.2);
            font-weight: 500;
        }

        .welcome-header p {
            font-size: 1.1rem;
            color: var(--secondary);
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
            position: relative;
            z-index: 2;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            padding: 1.8rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 18px 18px 0 0;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary);
            opacity: 0.8;
            font-weight: 500;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            transition: var(--transition);
        }

        .stat-icon.internships {
            background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
        }

        .stat-icon.applications {
            background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%);
        }

        .stat-icon.payments {
            background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
        }

        .stat-icon.hired {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        /* Quick Actions */
        .quick-actions {
            margin-top: 3rem;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--accent);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(78, 205, 196, 0.05) 0%, rgba(3, 89, 70, 0.05) 100%);
            opacity: 0;
            transition: var(--transition);
            z-index: 1;
        }

        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover::before {
            opacity: 1;
        }

        .action-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            transition: var(--transition);
        }

        .action-card:nth-child(1) .action-icon {
            background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%);
        }

        .action-card:nth-child(2) .action-icon {
            background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
        }

        .action-card:nth-child(3) .action-icon {
            background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
        }

        .action-card:nth-child(4) .action-icon {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }

        .action-card:hover .action-icon {
            transform: scale(1.15) rotate(-5deg);
        }

        .action-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }

        .action-desc {
            color: var(--secondary);
            opacity: 0.85;
            line-height: 1.5;
            position: relative;
            z-index: 2;
        }

        /* Error Message */
        .error-message {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid var(--danger);
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
            
            .actions-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .welcome-header {
                padding: 2rem 1.5rem;
            }
            
            .welcome-header h1 {
                font-size: 2rem;
            }

            .company-info {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>
    <div class="blob blob3"></div>
    <div class="blob blob4"></div>
    
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <nav class="sidebar" id="sidebar">
        <!-- Sidebar Header with Toggle -->
        <div class="sidebar-header">
            <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="nav-content">
            <div class="nav-section">
                <h4>Main</h4>
                <a href="?page=home" class="nav-link <?= ($page === 'home') ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Courses</h4>
                <a href="?page=post-internship" class="nav-link <?= ($page === 'post-internship') ? 'active' : '' ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Post New Course</span>
                </a>
                <a href="?page=manage-internships" class="nav-link <?= ($page === 'manage-internships') ? 'active' : '' ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Manage Courses</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Applications</h4>
                <a href="?page=applications" class="nav-link <?= ($page === 'applications') ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>View Applications</span>
                </a>
                <a href="?page=hired-interns" class="nav-link <?= ($page === 'hired-interns') ? 'active' : '' ?>">
                    <i class="fas fa-user-tie"></i>
                    <span>Hired Students</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Account</h4>
                <a href="?page=payments" class="nav-link <?= ($page === 'payments') ? 'active' : '' ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
                <a href="?page=profile" class="nav-link <?= ($page === 'profile') ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
                <a href="?page=settings" class="nav-link <?= ($page === 'settings') ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            
            <div class="nav-section">
                <a href="logoutcompany.php" class="logout-btn" onclick="return confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="main">
        <?php
        // Dynamic page loading based on the page parameter
        switch ($page) {
            case 'post-internship':
                if (file_exists('course_posting.php')) {
                    include 'course_posting.php';
                } else {
                    echo '<div class="page-container">
                            <div class="page-header">
                                <h1 class="page-title"><i class="fas fa-plus-circle"></i> Post New Course</h1>
                                <p class="page-subtitle">Create and publish new learning opportunities for students.</p>
                            </div>
                            <div class="error-message">Course posting page not found. Please check if internship_posting.php exists.</div>
                          </div>';
                }
                break;

            case 'manage-internships':
                if (file_exists('company_manage_internships.php')) {
                    include 'company_manage_internships.php';
                } else {
                    echo '<div class="page-container">
                            <div class="page-header">
                                <h1 class="page-title"><i class="fas fa-tasks"></i> Manage Courses</h1>
                                <p class="page-subtitle">View and manage your posted courses.</p>
                            </div>
                            <div class="error-message">Manage courses page not found. Please check if company_manage_internships.php exists.</div>
                          </div>';
                }
                break;

            case 'applications':
                if (file_exists('company_applications.php')) {
                    include 'company_applications.php';
                } else {
                    echo '<div class="page-container">
                            <div class="page-header">
                                <h1 class="page-title"><i class="fas fa-file-alt"></i> Applications</h1>
                                <p class="page-subtitle">Review and manage student applications for your courses.</p>
                            </div>
                            <div class="error-message">Applications page not found. Please check if company_applications.php exists.</div>
                          </div>';
                }
                break;

            case 'hired-interns':
                if (file_exists('company_hired_interns.php')) {
                    include 'company_hired_interns.php';
                } else {
                    echo '<div class="page-container">
                            <div class="page-header">
                                <h1 class="page-title"><i class="fas fa-user-tie"></i> Hired Students</h1>
                                <p class="page-subtitle">Manage your selected students and track their progress.</p>
                            </div>
                            <div class="error-message">Hired students page not found. Please check if company_hired_interns.php exists.</div>
                          </div>';
                }
                break;

            case 'payments':
                if (file_exists('company_payments.php')) {
                    include 'company_payments.php';
                } else {
                    echo '<div class="page-container">
                            <div class="page-header">
                                <h1 class="page-title"><i class="fas fa-credit-card"></i> Payments</h1>
                                <p class="page-subtitle">Manage student payments and view payment history.</p>
                            </div>
                            <div class="error-message">Payments page not found. Please check if company_payments.php exists.</div>
                          </div>';
                }
                break;

            case 'profile':
                if (file_exists('company_profile.php')) {
                    include 'company_profile.php';
                } else {
                    echo '<div class="page-container">
                            <div class="page-header">
                                <h1 class="page-title"><i class="fas fa-user-circle"></i> Company Profile</h1>
                                <p class="page-subtitle">Update your company information and contact details.</p>
                            </div>
                            <div class="error-message">Profile page not found. Please check if company_profile.php exists.</div>
                          </div>';
                }
                break;

            case 'settings':
                if (file_exists('company_settings.php')) {
                    include 'company_settings.php';
                } else {
                    echo '<div class="page-container">
                            <div class="page-header">
                                <h1 class="page-title"><i class="fas fa-cog"></i> Settings</h1>
                                <p class="page-subtitle">Configure your account settings and preferences.</p>
                            </div>
                            <div class="error-message">Settings page not found. Please check if company_settings.php exists.</div>
                          </div>';
                }
                break;

            default:
                // Home page - Dashboard with statistics
                ?>
                <div class="welcome-container">
                    <div class="welcome-header">
                        <h1>Welcome to Your Company Dashboard</h1>
                        <div class="company-info">
                            <div class="company-name-display"><?php echo htmlspecialchars($company_name); ?></div>
                            <div class="company-id-display">ID: <?php echo htmlspecialchars($company_id); ?></div>
                            <?php if (!empty($industry_type)): ?>
                            <div class="company-industry-display"><?php echo htmlspecialchars($industry_type); ?></div>
                            <?php endif; ?>
                        </div>
                        <p>Manage your course programs, review applications, and track your company's engagement with talented students.</p>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-number" id="internships-count"><?= number_format($active_internships_count) ?></div>
                                    <div class="stat-label">Active Courses</div>
                                </div>
                                <div class="stat-icon internships">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-number" id="applications-count"><?= number_format($total_applications_count) ?></div>
                                    <div class="stat-label">Total Applications</div>
                                </div>
                                <div class="stat-icon applications">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-number" id="payments-count"><?= number_format($monthly_payments_count) ?></div>
                                    <div class="stat-label">Monthly Payments</div>
                                </div>
                                <div class="stat-icon payments">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-number" id="hired-count"><?= number_format($hired_interns_count) ?></div>
                                    <div class="stat-label">Enrolled Students</div>
                                </div>
                                <div class="stat-icon hired">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($active_internships_count == 0 && $total_applications_count == 0 && $hired_interns_count == 0): ?>
                    <div class="error-message">
                        <i class="fas fa-info-circle"></i>
                        No data available yet. Start by posting your first course to attract talented students!
                    </div>
                    <?php endif; ?>
                    
                    <div class="quick-actions">
                        <h3 class="section-title">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h3>
                        
                        <div class="actions-grid">
                            <a href="?page=post-internship" class="action-card">
                                <div class="action-header">
                                    <div class="action-icon">
                                        <i class="fas fa-plus"></i>
                                    </div>
                                    <div class="action-title">Post New Course</div>
                                </div>
                                <div class="action-desc">Create new learning programs, define requirements, and attract talented students to your courses.</div>
                            </a>
                            
                            <a href="?page=manage-internships" class="action-card">
                                <div class="action-header">
                                    <div class="action-icon">
                                        <i class="fas fa-list-ul"></i>
                                    </div>
                                    <div class="action-title">Manage Courses</div>
                                </div>
                                <div class="action-desc">View and manage your published courses. Edit details, update status, and track performance.</div>
                            </a>
                            
                            <a href="?page=applications" class="action-card">
                                <div class="action-header">
                                    <div class="action-icon">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <div class="action-title">Review Applications</div>
                                </div>
                                <div class="action-desc">View and review student applications. Evaluate candidates and select the best fit for your programs.</div>
                            </a>
                            
                            <a href="?page=payments" class="action-card">
                                <div class="action-header">
                                    <div class="action-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div class="action-title">Payment Management</div>
                                </div>
                                <div class="action-desc">Handle course payments, track payment history, and manage billing information efficiently.</div>
                            </a>
                        </div>
                    </div>
                </div>
                <?php
                break;
        }
        ?>
    </main>

    <script>
        // Enhanced session validation
        function validateSession() {
            fetch('validate_session.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.valid) {
                        alert('Your session has expired. Please log in again.');
                        window.location.href = 'logincompany.html';
                    }
                })
                .catch(error => {
                    console.error('Session validation error:', error);
                });
        }

        // Enhanced Sidebar Toggle Functionality
        let sidebarCollapsed = false;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle-btn i');
            
            if (window.innerWidth <= 768) {
                toggleMobileSidebar();
                return;
            }
            
            sidebarCollapsed = !sidebarCollapsed;
            sidebar.classList.toggle('collapsed', sidebarCollapsed);
            
            if (sidebarCollapsed) {
                toggleBtn.className = 'fas fa-chevron-right';
            } else {
                toggleBtn.className = 'fas fa-bars';
            }
        }

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const mobileBtn = document.querySelector('.mobile-menu-btn i');
            
            const isOpen = sidebar.classList.contains('show');
            
            if (isOpen) {
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('active');
                mobileBtn.className = 'fas fa-bars';
            } else {
                sidebar.classList.add('show');
                if (overlay) overlay.classList.add('active');
                mobileBtn.className = 'fas fa-times';
            }
        }

        function confirmLogout() {
            const dialog = document.createElement('div');
            dialog.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px);
                display: flex; justify-content: center; align-items: center; z-index: 2000;
            `;
            
            dialog.innerHTML = `
                <div style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); text-align: center; max-width: 400px; margin: 1rem;">
                    <h3 style="color: #e74c3c; margin-bottom: 1rem; font-size: 1.3rem;"><i class="fas fa-sign-out-alt"></i> Confirm Logout</h3>
                    <p style="color: #666; margin-bottom: 2rem; line-height: 1.6;">Are you sure you want to logout from your company dashboard? Any unsaved changes will be lost.</p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button onclick="closeLogoutDialog()" style="padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; background: #95a5a6; color: white;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button onclick="proceedLogout()" style="padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; background: #e74c3c; color: white;">
                            <i class="fas fa-sign-out-alt"></i> Yes, Logout
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(dialog);
            
            window.closeLogoutDialog = function() {
                document.body.removeChild(dialog);
            };
            
            window.proceedLogout = function() {
                const confirmBtn = dialog.querySelector('button:last-child');
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
                confirmBtn.disabled = true;
                
                setTimeout(() => {
                    window.location.href = 'logoutcompany.php';
                }, 800);
            };
            
            return false;
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Create mobile overlay if it doesn't exist
            if (!document.getElementById('mobile-overlay')) {
                const overlay = document.createElement('div');
                overlay.id = 'mobile-overlay';
                overlay.className = 'mobile-overlay';
                overlay.style.cssText = `
                    display: none; position: fixed; inset: 0;
                    background: rgba(0, 0, 0, 0.5); z-index: 999;
                    opacity: 0; transition: opacity 0.3s ease;
                `;
                overlay.addEventListener('click', toggleMobileSidebar);
                document.body.appendChild(overlay);
            }
            
            // Validate session every 5 minutes
            validateSession();
            setInterval(validateSession, 300000);
            
            // Handle window resize
            window.addEventListener('resize', function() {
                clearTimeout(window.resizeTimer);
                window.resizeTimer = setTimeout(function() {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('mobile-overlay');
                    
                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('show');
                        if (overlay) {
                            overlay.style.display = 'none';
                            overlay.classList.remove('active');
                        }
                    }
                }, 100);
            });
            
            // Close mobile sidebar when clicking outside
            document.addEventListener('click', function(e) {
                const sidebar = document.getElementById('sidebar');
                const mobileBtn = document.querySelector('.mobile-menu-btn');
                
                if (window.innerWidth <= 768 && 
                    sidebar.classList.contains('show') &&
                    !sidebar.contains(e.target) && 
                    !mobileBtn?.contains(e.target)) {
                    toggleMobileSidebar();
                }
            });
        });
    </script>
</body>
</html>