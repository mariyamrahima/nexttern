<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
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
$dob = htmlspecialchars(date('d M Y', strtotime($row['dob'])));
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

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary-dark);
            color: white;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            position: relative;
            z-index: 10;
            transition: var(--transition);
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
        }

        .sidebar.collapsed .logo {
            justify-content: center;
            gap: 0;
        }

        .sidebar.collapsed .logo h2 {
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

        /* Student Profile Section in Sidebar */
        .student-profile {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .sidebar.collapsed .student-profile {
            padding: 0.5rem;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-dark);
            transition: var(--transition);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar.collapsed .student-avatar {
            width: 40px;
            height: 40px;
            font-size: 1rem;
            margin-bottom: 0;
        }

        .student-name {
            font-weight: 600;
            color: white;
            margin-bottom: 0.25rem;
            transition: var(--transition);
        }

        .student-id {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
            transition: var(--transition);
        }

        .sidebar.collapsed .student-name,
        .sidebar.collapsed .student-id {
            opacity: 0;
            height: 0;
            margin: 0;
            overflow: hidden;
        }

        /* Navigation */
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

        /* Logout Section */
        .logout-section {
            margin-top: auto;
            padding-top: 1rem;
        }

        .logout-btn {
            width: 100%;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

        .sidebar.collapsed .logout-btn {
            padding: 0.8rem 0.5rem;
        }

        .sidebar.collapsed .logout-btn span {
            display: none;
        }

        /* Main Content */
        .main {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: transparent;
            position: relative;
            transition: var(--transition);
            z-index: 1;
        }

        /* Sidebar Toggle */
        .sidebar-toggle {
            position: fixed;
            top: 1.5rem;
            left: calc(var(--sidebar-width) + 1rem);
            z-index: 1001;
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 0.7rem;
            cursor: pointer;
            color: var(--primary);
            font-size: 1.1rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar.collapsed + .main .sidebar-toggle {
            left: calc(var(--sidebar-collapsed) + 1rem);
        }

        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: scale(1.1);
            box-shadow: var(--shadow-medium);
        }

        /* Tab Content */
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Profile Section */
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .profile-header {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-light);
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
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

        .profile-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .profile-subtitle {
            color: var(--secondary);
            opacity: 0.85;
            position: relative;
            z-index: 2;
            font-size: 1rem;
        }

        .profile-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            padding: 2rem;
            box-shadow: var(--shadow-light);
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 18px 18px 0 0;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group label i {
            color: var(--accent);
            font-size: 0.8rem;
        }

        .form-group input {
            padding: 1rem;
            border: 2px solid rgba(3, 89, 70, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.7);
            color: var(--secondary);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
            transform: translateY(-2px);
        }

        .form-group input[readonly] {
            background: rgba(255, 255, 255, 0.5);
            color: var(--secondary);
            opacity: 0.8;
        }

        .profile-actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(3, 89, 70, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.25);
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.25);
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(149, 165, 166, 0.3);
        }

        /* Empty State */
        .empty-state {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            padding: 3rem;
            box-shadow: var(--shadow-light);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .empty-state::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 18px 18px 0 0;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--accent);
            margin-bottom: 1.5rem;
            opacity: 0.7;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .empty-state p {
            color: var(--secondary);
            opacity: 0.8;
            font-size: 1rem;
        }

        /* Section Headers */
        .section-header {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--accent);
        }

        .section-subtitle {
            color: var(--secondary);
            opacity: 0.85;
            font-size: 0.95rem;
        }

        /* Responsive Design */
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
            
            .main {
                padding: 1.5rem;
            }
            
            .sidebar-toggle {
                left: 1rem;
                top: 1rem;
            }
            
            .profile-header {
                padding: 1.5rem;
            }
            
            .profile-title {
                font-size: 1.6rem;
            }
            
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .profile-actions {
                flex-direction: column;
                align-items: center;
            }

            .blob1, .blob2, .blob3, .blob4 {
                display: none;
            }
        }

        /* Loading animations */
        .loading {
            opacity: 0;
            animation: slideInUp 0.6s ease-out 0.1s forwards;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
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
    <div class="blob blob3"></div>
    <div class="blob blob4"></div>

    <!-- Sidebar Toggle -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2>Nexttern Student</h2>
        </div>

        <div class="student-profile">
            <div class="student-avatar">
                <?php echo strtoupper(substr($first_name, 0, 1)); ?>
            </div>
            <div class="student-name"><?php echo $first_name . ' ' . $last_name; ?></div>
            <div class="student-id">ID: <?php echo $student_id; ?></div>
        </div>
        
        <div class="nav-section">
            <h4>Dashboard</h4>
            <a href="#" class="nav-link active" onclick="showTab('profile')">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
        </div>
        
        <div class="nav-section">
            <h4>Internships</h4>
            <a href="#" class="nav-link" onclick="showTab('internships')">
                <i class="fas fa-briefcase"></i>
                <span>Available</span>
            </a>
            <a href="#" class="nav-link" onclick="showTab('applications')">
                <i class="fas fa-file-alt"></i>
                <span>Applications</span>
            </a>
            <a href="#" class="nav-link" onclick="showTab('shortlisted')">
                <i class="fas fa-check-circle"></i>
                <span>Shortlisted</span>
            </a>
            <a href="#" class="nav-link" onclick="showTab('offers')">
                <i class="fas fa-trophy"></i>
                <span>Offers</span>
            </a>
        </div>
        
        <div class="nav-section">
            <h4>Communication</h4>
            <a href="#" class="nav-link" onclick="showTab('messages')">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>
        </div>

        <div class="logout-section">
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main">
        <!-- Profile Tab -->
        <div id="profile" class="tab-content active loading">
            <div class="profile-container">
                <div class="profile-header">
                    <h1 class="profile-title">Welcome, <?php echo $first_name . ' ' . $last_name; ?></h1>
                    <p class="profile-subtitle">Manage your personal information and track your internship journey</p>
                </div>

                <div class="profile-card">
                    <form id="profileForm">
                        <div class="profile-grid">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i>Full Name</label>
                                <input type="text" name="name" value="<?php echo $first_name . ' ' . $last_name; ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i>Email Address</label>
                                <input type="email" name="email" value="<?php echo $email; ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i>Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo $contact; ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-venus-mars"></i>Gender</label>
                                <input type="text" name="gender" value="<?php echo $gender; ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i>Date of Birth</label>
                                <input type="text" name="dob" value="<?php echo $dob; ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-id-card"></i>Student ID</label>
                                <input type="text" name="student_id" value="<?php echo $student_id; ?>" readonly>
                            </div>
                        </div>

                        <div class="profile-actions">
                            <button type="button" class="btn btn-primary" id="editBtn" onclick="toggleEdit()">
                                <i class="fas fa-edit"></i> Edit Profile
                            </button>
                            <button type="button" class="btn btn-success" id="saveBtn" onclick="saveProfile()" style="display: none;">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancelBtn" onclick="cancelEdit()" style="display: none;">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Internships Tab -->
        <div id="internships" class="tab-content">
            <div class="section-header">
                <h1 class="section-title">
                    <i class="fas fa-briefcase"></i>
                    Available Internships
                </h1>
                <p class="section-subtitle">Explore internship opportunities that match your skills and interests</p>
            </div>
            <div class="empty-state">
                <i class="fas fa-briefcase"></i>
                <h3>No Internships Available</h3>
                <p>Check back later for new opportunities that match your profile</p>
            </div>
        </div>

        <!-- Applications Tab -->
        <div id="applications" class="tab-content">
            <div class="section-header">
                <h1 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    My Applications
                </h1>
                <p class="section-subtitle">Track your internship applications and their current status</p>
            </div>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No Applications Yet</h3>
                <p>Start applying to internships to see them here</p>
            </div>
        </div>

        <!-- Shortlisted Tab -->
        <div id="shortlisted" class="tab-content">
            <div class="section-header">
                <h1 class="section-title">
                    <i class="fas fa-check-circle"></i>
                    Shortlisted Applications
                </h1>
                <p class="section-subtitle">Applications that have been shortlisted for further review</p>
            </div>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Shortlisted Applications</h3>
                <p>Your shortlisted applications will appear here when companies show interest</p>
            </div>
        </div>

        <!-- Offers Tab -->
        <div id="offers" class="tab-content">
            <div class="section-header">
                <h1 class="section-title">
                    <i class="fas fa-trophy"></i>
                    Internship Offers
                </h1>
                <p class="section-subtitle">Manage your internship offers and responses</p>
            </div>
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <h3>No Offers Yet</h3>
                <p>Your internship offers will be displayed here when you receive them</p>
            </div>
        </div>

        <!-- Messages Tab -->
        <div id="messages" class="tab-content">
            <div class="section-header">
                <h1 class="section-title">
                    <i class="fas fa-envelope"></i>
                    Messages
                </h1>
                <p class="section-subtitle">Communicate with recruiters and coordinators</p>
            </div>
            <div class="empty-state">
                <i class="fas fa-envelope"></i>
                <h3>No Messages</h3>
                <p>Your messages and communications will appear here</p>
            </div>
        </div>
    </main>

    <script>
        let sidebarCollapsed = false;
        let isEditing = false;
        let originalValues = {};

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle i');
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile) {
                sidebar.classList.toggle('show');
                toggleBtn.className = sidebar.classList.contains('show') ? 'fas fa-times' : 'fas fa-bars';
            } else {
                sidebarCollapsed = !sidebarCollapsed;
                sidebar.classList.toggle('collapsed');
                
                if (sidebarCollapsed) {
                    toggleBtn.className = 'fas fa-arrow-right';
                } else {
                    toggleBtn.className = 'fas fa-bars';
                }
            }
        }

        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.classList.add('active');
                selectedTab.classList.add('loading');
                
                // Remove loading class after animation
                setTimeout(() => {
                    selectedTab.classList.remove('loading');
                }, 100);
            }
            
            // Update navigation
            document.querySelectorAll('.nav-link').forEach(item => {
                item.classList.remove('active');
            });
            
            event.target.classList.add('active');
        }

        function toggleEdit() {
            isEditing = true;
            const form = document.getElementById('profileForm');
            const inputs = form.querySelectorAll('input[name="phone"]'); // Only allow phone editing
            const editBtn = document.getElementById('editBtn');
            const saveBtn = document.getElementById('saveBtn');
            const cancelBtn = document.getElementById('cancelBtn');

            // Store original values
            inputs.forEach(input => {
                originalValues[input.name] = input.value;
                input.removeAttribute('readonly');
                input.style.background = 'white';
                input.focus();
            });

            editBtn.style.display = 'none';
            saveBtn.style.display = 'flex';
            cancelBtn.style.display = 'flex';
        }

        function saveProfile() {
            // In a real application, you would send this data to the server
            // Show success message with animation
            const saveBtn = document.getElementById('saveBtn');
            const originalContent = saveBtn.innerHTML;
            
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveBtn.disabled = true;
            
            setTimeout(() => {
                saveBtn.innerHTML = '<i class="fas fa-check"></i> Saved!';
                
                setTimeout(() => {
                    const form = document.getElementById('profileForm');
                    const inputs = form.querySelectorAll('input');
                    
                    inputs.forEach(input => {
                        input.setAttribute('readonly', true);
                        input.style.background = 'rgba(255, 255, 255, 0.5)';
                    });

                    document.getElementById('editBtn').style.display = 'flex';
                    document.getElementById('saveBtn').style.display = 'none';
                    document.getElementById('cancelBtn').style.display = 'none';
                    
                    saveBtn.innerHTML = originalContent;
                    saveBtn.disabled = false;
                    isEditing = false;
                }, 1000);
            }, 1500);
        }

        function cancelEdit() {
            const form = document.getElementById('profileForm');
            const inputs = form.querySelectorAll('input');
            
            // Restore original values
            inputs.forEach(input => {
                if (originalValues[input.name]) {
                    input.value = originalValues[input.name];
                }
                input.setAttribute('readonly', true);
                input.style.background = 'rgba(255, 255, 255, 0.5)';
            });

            document.getElementById('editBtn').style.display = 'flex';
            document.getElementById('saveBtn').style.display = 'none';
            document.getElementById('cancelBtn').style.display = 'none';
            
            isEditing = false;
            originalValues = {};
        }

        // Close mobile sidebar when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.querySelector('.sidebar-toggle i').className = 'fas fa-bars';
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle i');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                if (sidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    toggleBtn.className = 'fas fa-arrow-right';
                } else {
                    sidebar.classList.remove('collapsed');
                    toggleBtn.className = 'fas fa-bars';
                }
            } else {
                sidebar.classList.remove('collapsed');
                toggleBtn.className = 'fas fa-bars';
            }
        });

        // Prevent accidental navigation when editing
        window.addEventListener('beforeunload', function(e) {
            if (isEditing) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        // Add loading animations on page load
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.loading');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.classList.remove('loading');
                }, index * 200);
            });
        });

        // Add click event listeners to nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get the tab name from onclick attribute or href
                const onclickAttr = this.getAttribute('onclick');
                if (onclickAttr) {
                    const tabMatch = onclickAttr.match(/showTab\('([^']+)'\)/);
                    if (tabMatch) {
                        const tabName = tabMatch[1];
                        
                        // Hide all tabs
                        document.querySelectorAll('.tab-content').forEach(tab => {
                            tab.classList.remove('active');
                        });
                        
                        // Show selected tab
                        const selectedTab = document.getElementById(tabName);
                        if (selectedTab) {
                            selectedTab.classList.add('active');
                        }
                        
                        // Update navigation
                        document.querySelectorAll('.nav-link').forEach(item => {
                            item.classList.remove('active');
                        });
                        
                        this.classList.add('active');
                    }
                }
            });
        });
    </script>
</body>
</html>