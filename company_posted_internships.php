<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['company_id']) || !isset($_SESSION['company_name'])) {
    header('Location: company_login.php');
    exit;
}

$company_name = $_SESSION['company_name'] ?? 'Company';
$company_id = $_SESSION['company_id'] ?? '';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

$courses = [];
$message = '';
$message_type = '';

if (isset($_SESSION['success_message'])) {
    $message_type = 'success';
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Fetch posted courses
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    $message_type = 'error';
    $message = "DATABASE CONNECTION FAILED: " . $conn->connect_error;
} else {
    $sql = "SELECT * FROM course WHERE company_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
        
        $stmt->close();
    } else {
        $message_type = 'error';
        $message = "Error preparing statement: " . $conn->error;
    }
    
    $conn->close();
}

// Handle course status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if (!$conn->connect_error) {
        if ($_POST['action'] == 'toggle_status') {
            $course_id = (int)$_POST['course_id'];
            $new_status = $_POST['new_status'];
            
            $sql = "UPDATE course SET course_status = ? WHERE course_id = ? AND company_id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("sii", $new_status, $course_id, $company_id);
                if ($stmt->execute()) {
                    $message_type = 'success';
                    $message = "Course status updated successfully!";
                } else {
                    $message_type = 'error';
                    $message = "Error updating status: " . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] == 'delete_course') {
            $course_id = (int)$_POST['course_id'];
            
            $sql = "DELETE FROM course WHERE course_id = ? AND company_id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("ii", $course_id, $company_id);
                if ($stmt->execute()) {
                    $message_type = 'success';
                    $message = "Course deleted successfully!";
                } else {
                    $message_type = 'error';
                    $message = "Error deleting course: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        $conn->close();
        
        header("Location: company_posted_internships.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posted Internships - <?php echo htmlspecialchars($company_name); ?> | Nexttern</title>
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
            --danger: #e74c3c;
            --warning: #f39c12;
            --bg-light: #f5fbfa;
            --glass-bg: rgba(255, 255, 255, 0.2);
            --glass-border: rgba(255, 255, 255, 0.3);
            --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
            --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
            --blur: 14px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            --border-radius: 12px;
            --border-radius-lg: 18px;
            --border-radius-xl: 20px;
            --sidebar-width: 240px;
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

        /* FIXED SINGLE SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary-dark);
            color: white;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 10;
            transition: var(--transition);
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
            padding: 1.5rem 0.8rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            transition: var(--transition);
            padding: 0.75rem;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            flex-direction: column;
        }

        .sidebar.collapsed .logo {
            justify-content: center;
            gap: 0;
        }

        .logo-main {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
        }

        .sidebar.collapsed .logo-main {
            justify-content: center;
        }

        .sidebar.collapsed .logo h2,
        .sidebar.collapsed .company-id {
            opacity: 0;
            width: 0;
            overflow: hidden;
            transition: var(--transition);
        }

        .logo i {
            font-size: 1.75rem;
            color: var(--accent);
            transition: var(--transition);
        }

        .sidebar.collapsed .logo i {
            font-size: 1.5rem;
        }

        .logo h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            transition: var(--transition);
        }

        .company-id {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 400;
            margin-top: 0.25rem;
            transition: var(--transition);
        }

        .nav-section {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
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
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
            margin: 2px 0;
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
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
        }

        .nav-link:hover {
            background: var(--primary-light);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateX(5px);
            color: white;
        }

        .sidebar.collapsed .nav-link:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 25px rgba(0,0,0,0.2);
        }

        .nav-link.active {
            background: var(--accent);
            color: var(--primary-dark);
            box-shadow: 0 8px 32px rgba(78, 205, 196, 0.3);
            transform: translateX(5px);
        }

        .sidebar.collapsed .nav-link.active {
            transform: scale(1.08);
        }

        .nav-link:hover i,
        .nav-link.active i {
            transform: scale(1.15);
        }

        .logout-link {
            color: rgba(255, 182, 193, 0.9) !important;
        }

        .logout-link:hover {
            background: rgba(231, 76, 60, 0.2) !important;
            color: #ff6b6b !important;
            border-left: 4px solid #e74c3c;
            padding-left: calc(1rem - 4px);
        }

        .sidebar.collapsed .logout-link:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 25px rgba(231, 76, 60, 0.3);
            border-left: none;
            padding-left: 0.8rem;
        }

        /* Main content - ADJUSTED FOR FIXED SIDEBAR */
        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            z-index: 1;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: var(--sidebar-collapsed);
        }

        .page-header {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-xl);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-light);
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }

        .page-header::before {
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

        .page-header h1 {
            color: var(--primary);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .page-header p {
            font-size: 1rem;
            color: var(--secondary);
            opacity: 0.85;
            position: relative;
            z-index: 2;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--secondary);
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .controls-bar {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: var(--primary-dark);
            font-size: 0.9rem;
            min-width: 150px;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            box-shadow: var(--shadow-light);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
        }

        .course-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .course-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-medium);
            background: rgba(255, 255, 255, 0.25);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .course-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .course-category {
            background: var(--accent);
            color: var(--primary-dark);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: inline-block;
        }

        .course-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: var(--secondary);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-item i {
            color: var(--accent);
            width: 16px;
        }

        .course-description {
            color: var(--secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
            opacity: 0.85;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-active {
            background: rgba(39, 174, 96, 0.2);
            color: var(--success);
        }

        .status-draft {
            background: rgba(243, 156, 18, 0.2);
            color: var(--warning);
        }

        .status-paused {
            background: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .btn-edit {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
        }

        .btn-edit:hover {
            background: rgba(52, 152, 219, 0.3);
            transform: scale(1.1);
        }

        .btn-toggle {
            background: rgba(243, 156, 18, 0.2);
            color: var(--warning);
        }

        .btn-toggle:hover {
            background: rgba(243, 156, 18, 0.3);
            transform: scale(1.1);
        }

        .btn-delete {
            background: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }

        .btn-delete:hover {
            background: rgba(231, 76, 60, 0.3);
            transform: scale(1.1);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-light);
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--accent);
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .empty-desc {
            color: var(--secondary);
            opacity: 0.8;
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 1px solid rgba(39, 174, 96, 0.3);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-medium);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--secondary);
            cursor: pointer;
            padding: 0.25rem;
        }

        .modal-body {
            margin-bottom: 2rem;
            color: var(--secondary);
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--secondary);
            border: 1px solid var(--glass-border);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
                position: fixed;
                height: 100vh;
                z-index: 1000;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .controls-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters {
                justify-content: center;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .course-meta {
                grid-template-columns: 1fr;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .course-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .course-card:nth-child(1) { animation-delay: 0.1s; }
        .course-card:nth-child(2) { animation-delay: 0.2s; }
        .course-card:nth-child(3) { animation-delay: 0.3s; }
    </style>
</head>
<body>

    <!-- FIXED SINGLE SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-main">
                <i class="fas fa-building"></i>
                <h2><?php echo htmlspecialchars($company_name); ?></h2>
            </div>
            <div class="company-id"><?php echo htmlspecialchars($company_id); ?></div>
        </div>
        
        <div class="nav-section">
            <h4>Main</h4>
            <a href="company_dashboard.php" class="nav-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <div class="nav-section">
            <h4>Internships</h4>
            <a href="internship_posting.php" class="nav-link">
                <i class="fas fa-plus"></i>
                <span>Post New</span>
            </a>
            <a href="company_posted_internships.php" class="nav-link active">
                <i class="fas fa-list-ul"></i>
                <span>Posted Internships</span>
            </a>
        </div>
        
        <div class="nav-section">
            <h4>Applications</h4>
            <a href="company_applications.php" class="nav-link">
                <i class="fas fa-file-alt"></i>
                <span>View Applications</span>
            </a>
        </div>
        
        <div class="nav-section">
            <h4>Account</h4>
            <a href="company_payment.php" class="nav-link">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="company_profile.php" class="nav-link">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
            <a href="company_settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logoutcompany.php" class="nav-link logout-link" onclick="return confirmLogout()">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
        
    <div class="main-content">
        <div class="page-header">
            <h1>
                <i class="fas fa-list-ul"></i>
                Posted Internships & Courses
            </h1>
            <p>Manage your published internship programs and training courses. Monitor applications, update details, and track performance.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($courses)): ?>
            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($courses); ?></div>
                    <div class="stat-label">Total Posted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php
                        $activeCount = 0;
                        foreach ($courses as $c) {
                            if ($c['course_status'] === 'active') $activeCount++;
                        }
                        echo $activeCount;
                        ?>
                    </div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php
                        $draftCount = 0;
                        foreach ($courses as $c) {
                            if ($c['course_status'] === 'draft') $draftCount++;
                        }
                        echo $draftCount;
                        ?>
                    </div>
                    <div class="stat-label">Drafts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo array_sum(array_column($courses, 'max_students')); ?></div>
                    <div class="stat-label">Total Capacity</div>
                </div>
            </div>

            <!-- Controls Bar -->
            <div class="controls-bar">
                <div class="filters">
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="paused">Paused</option>
                    </select>
                    <select class="filter-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <?php
                        $categories = array_unique(array_column($courses, 'course_category'));
                        foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <a href="internship_posting.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Post New Internship
                </a>
            </div>

            <!-- Courses Grid -->
            <div class="courses-grid" id="coursesGrid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card" data-status="<?php echo htmlspecialchars($course['course_status']); ?>" 
                         data-category="<?php echo htmlspecialchars($course['course_category']); ?>">
                        <div class="course-header">
                            <div class="course-info">
                                <h3 class="course-title"><?php echo htmlspecialchars($course['course_title']); ?></h3>
                                <span class="course-category"><?php echo htmlspecialchars($course['course_category']); ?></span>
                                <span style="font-size:0.9rem;color:#666;margin-left:10px;">
                                    <strong>ID:</strong>
                                    <?php
                                        if (isset($course['course_id']) && $course['course_id'] !== null) {
                                            echo htmlspecialchars($course['course_id']);
                                        } else {
                                            echo '<span style="color:#e74c3c;">Not set</span>';
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="course-meta">
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo htmlspecialchars($course['duration'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-users"></i>
                                <span>Max: <?php echo number_format($course['max_students']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-signal"></i>
                                <span><?php echo ucfirst(htmlspecialchars($course['difficulty_level'] ?: 'beginner')); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-<?php echo $course['mode'] === 'online' ? 'laptop' : ($course['mode'] === 'offline' ? 'building' : 'globe'); ?>"></i>
                                <span><?php echo ucfirst(htmlspecialchars($course['mode'] ?: 'online')); ?></span>
                            </div>
                        </div>

                        <div class="course-description">
                            <?php echo nl2br(htmlspecialchars($course['course_description'])); ?>
                        </div>
                        <div style="margin-top:1rem;">
                            <strong>Skills Taught:</strong> <?php echo htmlspecialchars($course['skills_taught']); ?><br>
                            <strong>Program Structure:</strong> <?php echo nl2br(htmlspecialchars($course['program_structure'])); ?><br>
                            <strong>Prerequisites:</strong> <?php echo nl2br(htmlspecialchars($course['prerequisites'])); ?><br>
                            <strong>What You Will Learn:</strong> <?php echo nl2br(htmlspecialchars($course['what_you_will_learn'])); ?><br>
                            <strong>Format:</strong> <?php echo htmlspecialchars($course['course_format']); ?><br>
                            <strong>Compensation:</strong> <?php echo ucfirst(htmlspecialchars($course['course_price_type'])); ?> 
                            <?php if ($course['course_price_type'] !== 'free') echo '₹' . number_format($course['price_amount']); ?><br>
                            <strong>Application Deadline:</strong> <?php echo htmlspecialchars($course['enrollment_deadline']); ?><br>
                            <strong>Start Date:</strong> <?php echo htmlspecialchars($course['start_date']); ?><br>
                            <strong>Certificate Provided:</strong> <?php echo $course['certificate_provided'] ? 'Yes' : 'No'; ?><br>
                            <strong>Job Placement Support:</strong> <?php echo $course['job_placement_support'] ? 'Yes' : 'No'; ?><br>
                        </div>

                        <div class="course-footer">
                            <span class="status-badge status-<?php echo htmlspecialchars($course['course_status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($course['course_status'])); ?>
                            </span>
                            <div class="course-actions">
                                <?php if (isset($course['course_id']) && $course['course_id'] !== null && $course['course_id'] !== ''): ?>
                                    <button class="action-btn btn-edit" title="Edit Course"
                                        onclick="editCourse(<?php echo (int)$course['course_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn btn-toggle" title="Toggle Status"
                                        onclick="toggleStatus(<?php echo (int)$course['course_id']; ?>, '<?php echo addslashes($course['course_status']); ?>')">
                                        <i class="fas fa-<?php echo $course['course_status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                    </button>
                                    <button class="action-btn btn-delete" title="Delete Course"
                                        onclick="confirmDelete(<?php echo (int)$course['course_id']; ?>, '<?php echo addslashes($course['course_title']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="action-btn btn-edit" title="Edit Course" disabled>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn btn-toggle" title="Toggle Status" disabled>
                                        <i class="fas fa-<?php echo $course['course_status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                    </button>
                                    <button class="action-btn btn-delete" title="Delete Course" disabled>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h3 class="empty-title">No Internships Posted Yet</h3>
                <p class="empty-desc">
                    Start building your talent pipeline by posting your first internship program. 
                    Share opportunities and connect with talented students.
                </p>
                <a href="internship_posting.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Post Your First Internship
                </a>
            </div>
        <?php endif; ?>

        <!-- Delete Confirmation Modal -->
        <div class="modal" id="deleteModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Confirm Deletion</h3>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body" id="deleteMessage">
                    Are you sure you want to delete this course? This action cannot be undone.
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>

        <!-- Hidden forms for actions -->
        <form id="statusForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="course_id" id="statusCourseId">
            <input type="hidden" name="new_status" id="newStatus">
        </form>

        <form id="deleteForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete_course">
            <input type="hidden" name="course_id" id="deleteCourseId">
        </form>
    </div>

    <script>
        // Filter functionality
        const statusFilter = document.getElementById('statusFilter');
        const categoryFilter = document.getElementById('categoryFilter');
        const coursesGrid = document.getElementById('coursesGrid');

        function filterCourses() {
            const statusValue = statusFilter.value.toLowerCase();
            const categoryValue = categoryFilter.value.toLowerCase();
            const courseCards = document.querySelectorAll('.course-card');

            courseCards.forEach(card => {
                const cardStatus = card.dataset.status.toLowerCase();
                const cardCategory = card.dataset.category.toLowerCase();
                
                const statusMatch = !statusValue || cardStatus === statusValue;
                const categoryMatch = !categoryValue || cardCategory === categoryValue;
                
                if (statusMatch && categoryMatch) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        statusFilter.addEventListener('change', filterCourses);
        categoryFilter.addEventListener('change', filterCourses);

        // Course actions
        function editCourse(courseId) {
            window.location.href = `edit_course.php?id=${courseId}`;
        }

        function toggleStatus(courseId, currentStatus) {
            let newStatus;
            
            if (currentStatus === 'active') {
                newStatus = 'paused';
            } else if (currentStatus === 'paused') {
                newStatus = 'active';
            } else {
                newStatus = 'active';
            }

            document.getElementById('statusCourseId').value = courseId;
            document.getElementById('newStatus').value = newStatus;
            document.getElementById('statusForm').submit();
        }

        function confirmDelete(courseId, courseName) {
            document.getElementById('deleteMessage').textContent = 
                `Are you sure you want to delete "${courseName}"? This action cannot be undone.`;
            document.getElementById('deleteCourseId').value = courseId;
            document.getElementById('deleteModal').classList.add('show');

            document.getElementById('confirmDeleteBtn').onclick = function() {
                document.getElementById('deleteForm').submit();
            };
        }

        function closeModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        // Close modal on outside click
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Mobile responsive sidebar
        function checkMobile() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('collapsed');
            }
        }

        window.addEventListener('resize', checkMobile);
        document.addEventListener('DOMContentLoaded', checkMobile);

        // Mobile sidebar toggle
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Logout confirmation
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
                    <p style="color: #666; margin-bottom: 2rem; line-height: 1.6;">Are you sure you want to logout from your company dashboard?</p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button onclick="closeLogoutDialog()" style="padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; background: #95a5a6; color: white;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button onclick="proceedLogout()" style="padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; background: #e74c3c; color: white;">
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
                window.location.href = 'logoutcompany.php';
            };
            
            return false;
        }
    </script>
</body>
</html>