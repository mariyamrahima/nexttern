<?php
// CRITICAL: Add these cache-busting headers FIRST
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$page = $_GET['page'] ?? 'dashboard';
session_start();

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

// Get student info first
$stmt = $conn->prepare("SELECT student_id, first_name, last_name, contact, gender, dob, qualifications FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}

$row = $result->fetch_assoc();
// FIXED: Don't use htmlspecialchars on IDs used for database queries
$student_id = $row['student_id'];  // Raw value for DB operations
$student_id_display = htmlspecialchars($row['student_id']); // For HTML display only
$first_name = htmlspecialchars($row['first_name']);
$last_name = htmlspecialchars($row['last_name']);
$contact = htmlspecialchars($row['contact']);
$gender = htmlspecialchars($row['gender']);
$dob = htmlspecialchars(date('Y-m-d', strtotime($row['dob'])));
$qualifications = htmlspecialchars($row['qualifications'] ?? '');

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_first_name = trim($_POST['first_name']);
    $new_last_name = trim($_POST['last_name']);
    $new_contact = trim($_POST['contact']);
    $new_gender = $_POST['gender'];
    $new_dob = $_POST['dob'];
    $new_qualifications = $_POST['qualifications'];
    
    if (empty($new_first_name) || empty($new_last_name)) {
        $error_message = "First name and last name are required.";
    } else {
        $stmt_update = $conn->prepare("UPDATE students SET first_name = ?, last_name = ?, contact = ?, gender = ?, dob = ?, qualifications = ? WHERE student_id = ?");
        $stmt_update->bind_param("sssssss", $new_first_name, $new_last_name, $new_contact, $new_gender, $new_dob, $new_qualifications, $student_id);
        
        if ($stmt_update->execute()) {
            $success_message = "Profile updated successfully!";
            $first_name = htmlspecialchars($new_first_name);
            $last_name = htmlspecialchars($new_last_name);
            header("Location: student_dashboard.php?page=profile&success=1");
            exit;
        } else {
            $error_message = "Error updating record: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
}

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
    $upload_dir = 'uploads/profile_photos/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024;
    
    $file_type = $_FILES['profile_photo']['type'];
    $file_size = $_FILES['profile_photo']['size'];
    $file_tmp = $_FILES['profile_photo']['tmp_name'];
    
    if (!in_array($file_type, $allowed_types)) {
        $error_message = "Invalid file type. Please upload JPG, PNG, or GIF files only.";
    } elseif ($file_size > $max_size) {
        $error_message = "File size too large. Maximum size is 5MB.";
    } else {
        $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $new_filename = 'profile_' . $student_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $upload_path)) {
            $stmt_photo = $conn->prepare("UPDATE students SET profile_photo = ? WHERE student_id = ?");
            $stmt_photo->bind_param("ss", $upload_path, $student_id);
            
            if ($stmt_photo->execute()) {
                $old_photo_stmt = $conn->prepare("SELECT profile_photo FROM students WHERE student_id = ?");
                $old_photo_stmt->bind_param("s", $student_id);
                $old_photo_stmt->execute();
                $old_photo_result = $old_photo_stmt->get_result();
                $old_photo_data = $old_photo_result->fetch_assoc();
                
                if ($old_photo_data['profile_photo'] && $old_photo_data['profile_photo'] !== $upload_path && file_exists($old_photo_data['profile_photo'])) {
                    unlink($old_photo_data['profile_photo']);
                }
                
                $success_message = "Profile photo updated successfully!";
                header("Location: student_dashboard.php?page=profile&success=1");
                exit;
            } else {
                $error_message = "Error updating profile photo in database.";
                unlink($upload_path);
            }
            $stmt_photo->close();
        } else {
            $error_message = "Error uploading file. Please try again.";
        }
    }
}

// Handle resume upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === 0) {
    $upload_dir = 'uploads/resume/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 10 * 1024 * 1024;
    
    $file_type = $_FILES['resume_file']['type'];
    $file_size = $_FILES['resume_file']['size'];
    $file_tmp = $_FILES['resume_file']['tmp_name'];
    
    if (!in_array($file_type, $allowed_types)) {
        $error_message = "Invalid file type. Please upload PDF, DOC, or DOCX files only.";
    } elseif ($file_size > $max_size) {
        $error_message = "File size too large. Maximum size is 10MB.";
    } else {
        $file_extension = pathinfo($_FILES['resume_file']['name'], PATHINFO_EXTENSION);
        $new_filename = 'resume_' . $student_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $upload_path)) {
            $stmt_resume = $conn->prepare("UPDATE students SET resume_path = ? WHERE student_id = ?");
            $stmt_resume->bind_param("ss", $upload_path, $student_id);
            
            if ($stmt_resume->execute()) {
                $old_resume_stmt = $conn->prepare("SELECT resume_path FROM students WHERE student_id = ?");
                $old_resume_stmt->bind_param("s", $student_id);
                $old_resume_stmt->execute();
                $old_resume_result = $old_resume_stmt->get_result();
                $old_resume_data = $old_resume_result->fetch_assoc();
                
                if ($old_resume_data['resume_path'] && $old_resume_data['resume_path'] !== $upload_path && file_exists($old_resume_data['resume_path'])) {
                    unlink($old_resume_data['resume_path']);
                }
                
                $success_message = "Resume updated successfully!";
                header("Location: student_dashboard.php?page=profile&success=1");
                exit;
            } else {
                $error_message = "Error updating resume in database.";
                unlink($upload_path);
            }
            $stmt_resume->close();
        } else {
            $error_message = "Error uploading file. Please try again.";
        }
    }
}

// Handle resume removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_resume'])) {
    $old_resume_stmt = $conn->prepare("SELECT resume_path FROM students WHERE student_id = ?");
    $old_resume_stmt->bind_param("s", $student_id);
    $old_resume_stmt->execute();
    $old_resume_result = $old_resume_stmt->get_result();
    $old_resume_data = $old_resume_result->fetch_assoc();
    
    if ($old_resume_data['resume_path'] && file_exists($old_resume_data['resume_path'])) {
        unlink($old_resume_data['resume_path']);
    }
    
    $remove_resume_stmt = $conn->prepare("UPDATE students SET resume_path = NULL WHERE student_id = ?");
    $remove_resume_stmt->bind_param("s", $student_id);
    
    if ($remove_resume_stmt->execute()) {
        $success_message = "Resume removed successfully!";
        header("Location: student_dashboard.php?page=profile&success=1");
        exit;
    } else {
        $error_message = "Error removing resume.";
    }
    $remove_resume_stmt->close();
}

// Handle course application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $course_id = $_POST['course_id'];
    $learning_objective = $_POST['learning_objective'];
    $cover_letter = $_POST['cover_letter'];
    $applicant_name = $first_name . ' ' . $last_name;
    
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
            header("Location: student_dashboard.php?page=applications&success=1");
            exit;
        } else {
            $error_message = "Error submitting application: " . $app_stmt->error;
        }
        $app_stmt->close();
    }
    $check_stmt->close();
}

// Handle story submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_story'])) {
    $story_title = trim($_POST['story_title']);
    $story_category = trim($_POST['story_category']);
    $story_content = trim($_POST['story_content']);
    $feedback_rating = (int)$_POST['feedback_rating'];
    
    if (empty($story_title) || empty($story_category) || empty($story_content)) {
        $error_message = "Please fill in all required fields.";
    } elseif ($feedback_rating < 1 || $feedback_rating > 5) {
        $error_message = "Please provide a valid rating (1-5 stars).";
    } else {
        $story_stmt = $conn->prepare("INSERT INTO stories (story_title, story_category, story_content, feedback_rating, student_id, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $story_stmt->bind_param("sssisss", $story_title, $story_category, $story_content, $feedback_rating, $student_id, $first_name, $last_name);

        if ($story_stmt->execute()) {
            $_SESSION['success_message'] = "Story submitted successfully! Your submission is under review.";
            header("Location: student_dashboard.php?page=stories");
            exit;
        } else {
            $error_message = "Error submitting story: " . $story_stmt->error;
        }
        $story_stmt->close();
    }
}

// ==========================================
// FETCH DATA FOR DISPLAY
// ==========================================

// Get current profile photo
$photo_stmt = $conn->prepare("SELECT profile_photo FROM students WHERE student_id = ?");
$photo_stmt->bind_param("s", $student_id);
$photo_stmt->execute();
$photo_result = $photo_stmt->get_result();
$photo_data = $photo_result->fetch_assoc();
$current_photo = $photo_data['profile_photo'] ?? '';

// Get current resume
$resume_stmt = $conn->prepare("SELECT resume_path FROM students WHERE student_id = ?");
$resume_stmt->bind_param("s", $student_id);
$resume_stmt->execute();
$resume_result = $resume_stmt->get_result();
$resume_data = $resume_result->fetch_assoc();
$current_resume = $resume_data['resume_path'] ?? '';

// Get student's submitted stories count
$stories_count_stmt = $conn->prepare("SELECT COUNT(*) as stories_count FROM stories WHERE student_id = ?");
$stories_count_stmt->bind_param("s", $student_id);
$stories_count_stmt->execute();
$stories_count_result = $stories_count_stmt->get_result();
$total_stories = $stories_count_result->fetch_assoc()['stories_count'];
$stories_count_stmt->close();

// Fetch student's submitted stories with status
$student_stories_stmt = $conn->prepare("
    SELECT story_id, story_title, story_category, story_content, feedback_rating, status, submission_date, updated_date
    FROM stories 
    WHERE student_id = ? 
    ORDER BY submission_date DESC
");
$student_stories_stmt->bind_param("s", $student_id);
$student_stories_stmt->execute();
$student_stories_result = $student_stories_stmt->get_result();

// Fetch messages for the current student
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
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM student_messages WHERE receiver_type = 'student' AND receiver_id = ? AND is_read = 0");
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
    SELECT ca.*, 
           c.course_title, 
           c.course_description, 
           c.company_name,
           c.mode
    FROM course_applications ca 
    JOIN course c ON ca.course_id = c.id 
    WHERE ca.student_id = ? 
    ORDER BY ca.created_at DESC
");
$student_apps_stmt->bind_param("s", $student_id);
$student_apps_stmt->execute();
$student_apps_result = $student_apps_stmt->get_result();

// Fetch course notifications for online courses the student has applied to
$course_notifications_stmt = $conn->prepare("
    SELECT 
        cn.*,
        c.course_title,
        c.company_name,
        ca.application_status
    FROM course_notifications cn
    JOIN course c ON cn.course_id = c.id
    JOIN course_applications ca ON ca.course_id = c.id
    WHERE ca.student_id = ? 
    AND c.mode = 'online'
    AND cn.notification_type = 'online_meeting'
    ORDER BY cn.created_at DESC
    LIMIT 20
");
$course_notifications_stmt->bind_param("s", $student_id);
$course_notifications_stmt->execute();
$course_notifications_result = $course_notifications_stmt->get_result();

// Get count of course notifications
$course_notifications_count = $course_notifications_result->num_rows;

// Helper functions
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

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return date('M j, Y', strtotime($datetime));
}

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="status-badge pending"><i class="fas fa-clock"></i> Pending</span>',
        'approved' => '<span class="status-badge approved"><i class="fas fa-check"></i> Approved</span>',
        'rejected' => '<span class="status-badge rejected"><i class="fas fa-times"></i> Rejected</span>',
        'waitlisted' => '<span class="status-badge waitlisted"><i class="fas fa-hourglass-half"></i> Waitlisted</span>'
    ];
    return $badges[$status] ?? '<span class="status-badge">' . ucfirst($status) . '</span>';
}

$titles = [
    'dashboard' => 'Student Dashboard',
    'profile' => 'My Profile', 
    'messages' => 'Messages',
    'applications' => 'Applications',
    'stories' => 'Success Stories',
    'saved_courses' => 'Saved Courses'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="student_dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobile-overlay" onclick="toggleMobileSidebar()"></div>

       <!-- Updated Sidebar HTML - Remove the arrow button -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <span class="logo-text">Nexttern</span>
        </div>
        <!-- Arrow button removed -->
    </div>
    
    <!-- Back to Home Button -->
    <div style="padding: 1.5rem 1rem 1rem 1rem;">
        <a href="index.php" class="back-to-home-btn">
            <i class="fas fa-arrow-left"></i>
            <span class="back-text">Back to Home</span>
        </a>
    </div>

    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar" id="sidebarAvatar">
                <?php if (!empty($current_photo) && file_exists($current_photo)): ?>
                    <img src="<?php echo htmlspecialchars($current_photo); ?>?v=<?php echo time(); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                <?php else: ?>
                    <?php echo strtoupper(substr($first_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo $first_name . ' ' . $last_name; ?></div>
                <div class="user-id">ID: <?php echo $student_id; ?></div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="#" class="nav-link <?php echo ($page === 'dashboard' || !isset($_GET['page'])) ? 'active' : ''; ?>" onclick="showSection('dashboard', this)">
                <div class="nav-icon">
                    <i class="fas fa-home"></i>
                </div>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="#" class="nav-link <?php echo ($page === 'profile') ? 'active' : ''; ?>" onclick="showSection('profile', this)">
                <div class="nav-icon">
                    <i class="fas fa-user"></i>
                </div>
                <span class="nav-text">Profile</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="#" class="nav-link <?php echo ($page === 'messages') ? 'active' : ''; ?>" onclick="showSection('messages', this)">
                <div class="nav-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <span class="nav-text">Messages</span>
                <?php if ($unread_count > 0): ?>
                <span class="nav-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="#" class="nav-link <?php echo ($page === 'applications') ? 'active' : ''; ?>" onclick="showSection('applications', this)">
                <div class="nav-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <span class="nav-text">Applications</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="#" class="nav-link <?php echo ($page === 'saved_courses') ? 'active' : ''; ?>" onclick="enhancedShowSection('saved_courses', this)">
                <div class="nav-icon">
                    <i class="fas fa-bookmark"></i>
                </div>
                <span class="nav-text">Saved Courses</span>
                <span class="nav-badge" id="savedCoursesCount" style="display: none;">0</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="#" class="nav-link <?php echo ($page === 'stories') ? 'active' : ''; ?>" onclick="showSection('stories', this)">
                <div class="nav-icon">
                    <i class="fas fa-star"></i>
                </div>
                <span class="nav-text">Success Stories</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span class="logout-text">Logout</span>
            </button>
        </form>
    </div>
</aside>
        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <button class="sidebar-toggle" onclick="toggleSidebarFromHeader()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="header-title">
                    <?php 
                    $titles = [
                        'dashboard' => 'Student Dashboard',
                        'profile' => 'My Profile', 
                        'messages' => 'Messages',
                        'applications' => 'Applications',
                        'stories' => 'Success Stories',
                        
                    ];
                    echo $titles[$page] ?? 'Dashboard';
                    ?>
                </h1>
                <div class="header-actions">
                    <div class="header-stat">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo $unread_count; ?> Unread</span>
                    </div>
                    <div class="header-stat">
                        <i class="fas fa-file-alt"></i>
                        <span><?php echo $total_applications; ?> Applications</span>
                    </div>
                </div>
            </header>

            <div class="content-container">
                <!-- Dashboard Section -->
                <div id="dashboard" class="content-section <?php echo ($page === 'dashboard' || !isset($_GET['page'])) ? 'active' : ''; ?>">
                    <!-- Welcome Message -->
                    <div class="content-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-wave-square"></i>
                                Welcome back, <?php echo $first_name; ?>!
                            </div>
                        </div>
                        <div class="card-body">
                            <p style="color: var(--slate-600); margin-bottom: 1.5rem; line-height: 1.6;">
                                Your comprehensive learning dashboard is ready. Navigate through your profile, messages, applications, and share your achievements with the community.
                            </p>
                            <div style="background: var(--slate-50); padding: 1.5rem; border-radius: var(--border-radius); border-left: 4px solid var(--primary);">
                                <h4 style="color: var(--slate-900); margin-bottom: 1rem;">Quick Actions</h4>
                                <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                                    <button class="btn btn-primary" onclick="showSection('profile', document.querySelector('[onclick*=profile]'))">
                                        <i class="fas fa-edit"></i> Edit Profile
                                    </button>
                                    <button class="btn btn-secondary" onclick="showSection('messages', document.querySelector('[onclick*=messages]'))">
                                        <i class="fas fa-envelope"></i> View Messages
                                    </button>
                                    <button class="btn btn-secondary" onclick="showSection('applications', document.querySelector('[onclick*=applications]'))">
                                        <i class="fas fa-file-alt"></i> My Applications
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Overview Stats -->
                    <div class="overview-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-label">Total Messages</div>
                                <div class="stat-icon messages">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $total_messages; ?></div>
                            <div class="stat-description"><?php echo $unread_count; ?> unread messages</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-label">Applications</div>
                                <div class="stat-icon applications">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?php echo $total_applications; ?></div>
                            <div class="stat-description">Course applications submitted</div>
                        </div>

                       
                        <div class="stat-card">
                            <div class="stat-header">
                                <div class="stat-label">Last Activity</div>
                                <div class="stat-icon feedback">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="stat-value">Today</div>
                            <div class="stat-description">Recent platform activity</div>
                        </div>
                    </div>
                </div>

<div id="profile" class="content-section <?php echo ($page === 'profile') ? 'active' : ''; ?>">
    <div class="content-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-user"></i>
                Personal Information
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error_message) && $page === 'profile'): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php elseif (!empty($success_message) && $page === 'profile'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Profile Photo Section -->
            <div class="profile-photo-section">
                <div class="current-photo-container">
                    <div class="profile-photo-display" id="currentPhotoDisplay">
                        <?php if (!empty($current_photo) && file_exists($current_photo)): ?>
                            <img src="<?php echo htmlspecialchars($current_photo); ?>" alt="Profile Photo">
                        <?php else: ?>
                            <?php echo strtoupper(substr($first_name, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: center;">
                        <h4 style="color: var(--slate-900); margin-bottom: 0.25rem;">Profile Photo</h4>
                        <p style="color: var(--slate-500); font-size: 0.875rem;">JPG, PNG or GIF (Max 5MB)</p>
                    </div>
                </div>

                <div class="photo-upload-area" id="photoUploadArea" onclick="document.getElementById('profilePhotoInput').click()">
                    <div class="upload-icon" id="uploadIcon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="upload-text" id="uploadText">
                        <h4>Upload New Profile Photo</h4>
                        <p>Drag and drop your photo here, or click to browse</p>
                        <div class="file-input-wrapper">
                            <button type="button" class="file-select-btn">
                                <i class="fas fa-image"></i>
                                Choose Photo
                            </button>
                        </div>
                    </div>
                    
                    <div class="file-info" id="fileInfo">
                        <div class="file-details">
                            <span class="file-name" id="fileName"></span>
                            <span class="file-size" id="fileSize"></span>
                            <button type="button" class="remove-file-btn" id="removeFileBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="photo-preview" id="photoPreview">
                        <img class="preview-image" id="previewImage" alt="Preview">
                    </div>
                </div>
            </div>

            <!-- Resume Upload Section - ONLY IN PROFILE -->
            <div class="resume-section">
                <div class="current-resume-container">
                    <div class="resume-display <?php echo !empty($current_resume) ? 'has-resume' : ''; ?>" id="currentResumeDisplay">
                        <?php if (!empty($current_resume) && file_exists($current_resume)): ?>
                            <i class="fas fa-file-pdf"></i>
                            <div style="font-size: 0.7rem; margin-top: 0.5rem; font-weight: 600;">RESUME</div>
                        <?php else: ?>
                            <i class="fas fa-file-upload"></i>
                            <div style="font-size: 0.7rem; margin-top: 0.5rem;">NO FILE</div>
                        <?php endif; ?>
                    </div>
                    <div class="resume-info" style="text-align: center;">
                        <h4>Resume/CV</h4>
                        <p><?php echo !empty($current_resume) ? 'PDF, DOC, DOCX (Max 10MB)' : 'Upload your resume'; ?></p>
                        
                        <?php if (!empty($current_resume) && file_exists($current_resume)): ?>
                            <div class="resume-file-actions">
                                <a href="<?php echo htmlspecialchars($current_resume); ?>" target="_blank" class="download-resume-btn">
                                    <i class="fas fa-download"></i>
                                    Download
                                </a>
                                <button type="button" class="remove-resume-btn" onclick="removeResume()">
                                    <i class="fas fa-trash"></i>
                                    Remove
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="resume-upload-area" id="resumeUploadArea" onclick="document.getElementById('resumeFileInput').click()">
                    <div class="upload-icon" id="resumeUploadIcon">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div class="upload-text" id="resumeUploadText">
                        <h4>Upload Resume/CV</h4>
                        <p>Drag and drop your resume here, or click to browse</p>
                        <div class="file-input-wrapper">
                            <button type="button" class="file-select-btn">
                                <i class="fas fa-file-text"></i>
                                Choose File
                            </button>
                        </div>
                    </div>
                    
                    <div class="file-info" id="resumeFileInfo">
                        <div class="file-details">
                            <span class="file-name" id="resumeFileName"></span>
                            <span class="file-size" id="resumeFileSize"></span>
                            <button type="button" class="remove-file-btn" id="removeResumeFileBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photo Upload Form -->
            <form id="photo-upload-form" action="student_dashboard.php" method="post" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*">
                <button type="submit" id="photoSubmitBtn">Upload Photo</button>
            </form>

            <!-- Resume Upload Form -->
            <form id="resume-upload-form" action="student_dashboard.php" method="post" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="resumeFileInput" name="resume_file" accept=".pdf,.doc,.docx">
                <button type="submit" id="resumeSubmitBtn">Upload Resume</button>
            </form>

            <!-- Regular Profile Form -->
            <form id="profile-form" action="student_dashboard.php" method="post">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Student ID</label>
                        <input type="text" class="form-input" value="<?php echo $student_id; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-input" name="first_name" value="<?php echo $first_name; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-input" name="last_name" value="<?php echo $last_name; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" value="<?php echo $email; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="text" class="form-input" name="contact" value="<?php echo $contact; ?>" placeholder="Enter your phone number">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select class="form-input" name="gender">
                            <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" class="form-input" name="dob" value="<?php echo $dob; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Educational Qualifications</label>
                        <select class="form-input" name="qualifications">
                            <option value="">Select Qualification</option>
                        <!--    <option value="High School" <?php echo ($qualifications === 'High School') ? 'selected' : ''; ?>>High School</option>-->
                            <option value="Undergraduate" <?php echo ($qualifications === 'Undergraduate') ? 'selected' : ''; ?>>Undergraduate</option>
                            <option value="Graduate" <?php echo ($qualifications === 'Graduate') ? 'selected' : ''; ?>>Graduate</option>
                            <option value="Postgraduate" <?php echo ($qualifications === 'Postgraduate') ? 'selected' : ''; ?>>Postgraduate</option>
                            <option value="PhD" <?php echo ($qualifications === 'PhD') ? 'selected' : ''; ?>>PhD</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

              <!-- Messages Section -->
<div id="messages" class="content-section <?php echo ($page === 'messages') ? 'active' : ''; ?>">
    <div class="content-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-envelope"></i>
                My Messages
            </div>
        </div>
        <div class="card-body">
            <div class="messages-header">
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
            </div>

            <?php if ($total_messages > 0): ?>
                <div class="messages-container">
                    <?php
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
                                        <h4><?php echo $sender_display; ?></h4>
                                        <div class="sender-type"><?php echo ucfirst($message['sender_type']); ?></div>
                                    </div>
                                </div>
                                <div class="message-time"><?php echo $time_ago; ?></div>
                            </div>
                            
                            <div class="message-subject">
                                <?php echo htmlspecialchars($message['subject']); ?>
                            </div>
                            
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                            </div>
                            
                            <div class="message-actions">
                                <div style="color: var(--slate-500); font-size: 0.875rem; font-weight: 500;">
                                    <i class="fas fa-info-circle"></i> 
                                    <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-envelope-open"></i>
                    <h3>No Messages Yet</h3>
                    <p>Your messages from administrators and companies will appear here. Check back later for updates!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
           <!-- Applications Section with Course Notifications -->
        <div id="applications" class="content-section <?php echo ($page === 'applications') ? 'active' : ''; ?>">
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-file-alt"></i>
                        My Applications
                        
                        <?php if ($course_notifications_count > 0): ?>
                            <span class="notification-badge">
                                <i class="fas fa-video"></i>
                                <?php echo $course_notifications_count; ?> Course Updates
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message) && $page === 'applications'): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php elseif (!empty($success_message) && $page === 'applications'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Course Notifications Section -->
                    <?php if ($course_notifications_count > 0): ?>
                        <div style="margin-bottom: 3rem;">
                            <h3 style="color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-video"></i>
                                Online Course Meeting Updates

                            </h3>
                            <h5> Offline / Hybrid mode courses notification will be received via registered email </h5>
                            <?php while ($notification = $course_notifications_result->fetch_assoc()): 
                                $meeting_datetime = date('F j, Y \a\t g:i A', strtotime($notification['meeting_datetime']));
                                $notification_date = date('M j, Y \a\t g:i A', strtotime($notification['created_at']));
                                $is_upcoming = strtotime($notification['meeting_datetime']) > time();
                            ?>
                                <div class="course-notification-item">
                                    <div class="notification-icon">
                                        <i class="fas fa-video"></i>
                                    </div>
                                    
                                    <div class="notification-header">
                                        <div class="notification-course-info">
                                            <h4>
                                                <i class="fas fa-graduation-cap"></i>
                                                <?php echo htmlspecialchars($notification['course_title']); ?>
                                            </h4>
                                            <div class="company-name">
                                                <i class="fas fa-building"></i>
                                                <?php echo htmlspecialchars($notification['company_name']); ?>
                                            </div>
                                        </div>
                                        <div class="notification-meta">
                                            <div class="notification-date">
                                                Sent: <?php echo $notification_date; ?>
                                            </div>
                                            <?php echo getStatusBadge($notification['application_status']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="meeting-details">
                                        <h5 style="color: var(--primary-dark); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-calendar-alt"></i>
                                            Online Meeting Details
                                            <?php if ($is_upcoming): ?>
                                                <span style="background: var(--success); color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                                    <i class="fas fa-clock"></i> UPCOMING
                                                </span>
                                            <?php else: ?>
                                                <span style="background: var(--slate-500); color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">
                                                    <i class="fas fa-history"></i> PAST
                                                </span>
                                            <?php endif; ?>
                                        </h5>
                                        
                                        <div class="meeting-info">
                                            <div class="meeting-detail">
                                                <i class="fas fa-calendar"></i>
                                                <strong>Date:</strong> <?php echo date('F j, Y', strtotime($notification['meeting_datetime'])); ?>
                                            </div>
                                            <div class="meeting-detail">
                                                <i class="fas fa-clock"></i>
                                                <strong>Time:</strong> <?php echo date('g:i A', strtotime($notification['meeting_datetime'])); ?>
                                            </div>
                                            <div class="meeting-detail">
                                                <i class="fas fa-users"></i>
                                                <strong>Notified:</strong> <?php echo ucfirst($notification['notify_status']); ?> students
                                            </div>
                                            <div class="meeting-detail">
                                                <i class="fas fa-paper-plane"></i>
                                                <strong>Recipients:</strong> <?php echo $notification['sent_count']; ?> student(s)
                                            </div>
                                        </div>
                                        
                                        <a href="<?php echo htmlspecialchars($notification['meeting_link']); ?>" target="_blank" class="meeting-link">
                                            <i class="fas fa-external-link-alt"></i>
                                            Join Meeting
                                        </a>
                                        
                                        <?php if (!empty($notification['additional_notes'])): ?>
                                            <div class="additional-notes">
                                                <h6 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                                    <i class="fas fa-sticky-note"></i>
                                                    Additional Information:
                                                </h6>
                                                <p style="margin: 0; color: var(--slate-700); line-height: 1.5;">
                                                    <?php echo nl2br(htmlspecialchars($notification['additional_notes'])); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Original Applications List -->
                    <?php if ($total_applications > 0): ?>
                        <h3 style="color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-list"></i>
                            Application History
                            
                        </h3>
                        
                        <?php
                        // Reset the result pointer to iterate through applications again
                        $student_apps_stmt->execute();
                        $student_apps_result = $student_apps_stmt->get_result();
                        
                        while ($app = $student_apps_result->fetch_assoc()): 
                            $formatted_date = date('M j, Y g:i A', strtotime($app['created_at']));
                            $learning_objective_display = str_replace('_', ' ', ucwords($app['learning_objective'], '_'));
                        ?>
                            <div class="application-item">
                                <div class="application-header">
                                    <div class="application-course">
                                        <h4>
                                            <?php echo htmlspecialchars($app['course_title']); ?>
                                            <?php if (strtolower($app['mode']) === 'online'): ?>
                                                <span class="notification-badge" style="font-size: 0.7rem; padding: 0.25rem 0.5rem;">
                                                    <i class="fas fa-video"></i>
                                                    ONLINE
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        
                                        <!-- Company Information -->
                                        <?php if (!empty($app['company_name'])): ?>
                                        <div style="margin-top: 0.5rem; display: flex; align-items: center; color: var(--slate-600);">
                                            <i class="fas fa-building" style="margin-right: 0.5rem; color: var(--primary);"></i>
                                            <span style="font-weight: 500;"><?php echo htmlspecialchars($app['company_name']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="application-meta">
                                        <div class="application-date">Applied: <?php echo $formatted_date; ?></div>
                                        <?php echo getStatusBadge($app['application_status']); ?>
                                    </div>
                                </div>

                                <!-- Course Description -->
                                <?php if (!empty($app['course_description'])): ?>
                                    <div style="margin-bottom: 1rem; padding: 1rem; background: var(--slate-50); border-radius: var(--border-radius); border-left: 4px solid var(--primary);">
                                        <h5 style="color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-info-circle"></i>
                                            Course Description
                                        </h5>
                                        <p style="color: var(--slate-600); line-height: 1.6; margin: 0;">
                                            <?php echo nl2br(htmlspecialchars($app['course_description'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div class="message-content">
                                    <strong>Learning Objective:</strong> <?php echo htmlspecialchars($learning_objective_display); ?>
                                </div>

                                <?php if (!empty($app['cover_letter'])): ?>
                                    <div class="message-content">
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
                            <p>You haven't submitted any course applications yet. Your application history will appear here once you start applying for courses!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


<!-- Saved Courses Section -->
<div id="saved_courses" class="content-section <?php echo ($page === 'saved_courses') ? 'active' : ''; ?>">
    <div class="content-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-bookmark"></i>
                My Saved Courses
            </div>
            <button class="btn btn-secondary" onclick="refreshSavedCourses()" id="refreshBtn">
                <i class="fas fa-sync-alt"></i>
                Refresh
            </button>
        </div>
        <div class="card-body">
            <div id="savedCoursesContainer">
                <div class="empty-state" id="savedCoursesEmpty">
                    <i class="fas fa-bookmark"></i>
                    <h3>No Saved Courses Yet</h3>
                    <p>Start exploring courses and bookmark your favorites to see them here!</p>
                </div>
                
                <div id="savedCoursesList" style="display: none;">
                    <!-- Saved courses will be loaded here dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>
               <div id="stories" class="content-section <?php echo ($page === 'stories') ? 'active' : ''; ?>">
    <!-- Submit New Story Form -->
    <div class="content-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-plus-circle"></i>
                Share Your Success Story
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error_message) && $page === 'stories'): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php elseif (!empty($success_message) && $page === 'stories'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <div style="background: var(--slate-50); padding: 1.5rem; border-radius: var(--border-radius); border-left: 4px solid var(--success); margin-bottom: 2rem;">
                <h4 style="color: var(--success); margin-bottom: 0.5rem;">Share Your Success Story</h4>
                <p style="color: var(--slate-600); line-height: 1.6;">Inspire others by sharing your achievements, milestones, and learning experiences. Your story could motivate fellow students and help showcase the impact of our programs.</p>
            </div>

            <form id="story-form" action="student_dashboard.php" method="post">
                <input type="hidden" name="submit_story" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Story Title</label>
                        <input type="text" class="form-input" name="story_title" placeholder="Enter a compelling title for your success story" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select class="form-input" name="story_category" required>
                            <option value="">Select Category</option>
                            <option value="Academic Achievement">Academic Achievement</option>
                            <option value="Career Breakthrough">Career Breakthrough</option>
                            <option value="Skill Development">Skill Development</option>
                            <option value="Personal Growth">Personal Growth</option>
                            <option value="Project Success">Project Success</option>
                            <option value="Internship Experience">Internship Experience</option>
                            <option value="Leadership">Leadership</option>
                            <option value="Community Impact">Community Impact</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">Your Success Story</label>
                        <textarea class="form-input form-textarea" name="story_content" placeholder="Tell us about your success story. Include details about your challenges, how you overcame them, what you learned, and the impact it had on your life or career." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Overall Rating (1-5 Stars)</label>
                        <div class="rating-stars">
                            <input type="radio" name="feedback_rating" value="5" id="star5" required>
                            <label for="star5">★</label>
                            <input type="radio" name="feedback_rating" value="4" id="star4">
                            <label for="star4">★</label>
                            <input type="radio" name="feedback_rating" value="3" id="star3">
                            <label for="star3">★</label>
                            <input type="radio" name="feedback_rating" value="2" id="star2">
                            <label for="star2">★</label>
                            <input type="radio" name="feedback_rating" value="1" id="star1">
                            <label for="star1">★</label>
                        </div>
                        <small style="color: var(--slate-500); font-size: 0.8rem;">Rate your overall experience (1-5 stars)</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Submit Success Story
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- My Submitted Stories -->
    <div class="content-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-list"></i>
                My Submitted Stories
            </div>
        </div>
        <div class="card-body">
            <?php if ($total_stories > 0): ?>
                <?php while ($story = $student_stories_result->fetch_assoc()): 
                    $formatted_date = date('M j, Y g:i A', strtotime($story['submission_date']));
                    $status_badge = '';
                    switch($story['status']) {
                        case 'pending':
                            $status_badge = '<span class="status-badge pending"><i class="fas fa-clock"></i> Under Review</span>';
                            break;
                        case 'approved':
                            $status_badge = '<span class="status-badge approved"><i class="fas fa-check"></i> Published</span>';
                            break;
                        case 'rejected':
                            $status_badge = '<span class="status-badge rejected"><i class="fas fa-times"></i> Not Approved</span>';
                            break;
                    }
                ?>
                    <div class="application-item" style="margin-bottom: 1.5rem;">
                        <div class="application-header">
                            <div class="application-course">
                                <h4><?php echo htmlspecialchars($story['story_title']); ?></h4>
                                <div class="application-date">Submitted: <?php echo $formatted_date; ?></div>
                                <div style="margin-top: 0.5rem;">
                                    <span style="background: var(--slate-100); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; color: var(--slate-700);">
                                        <?php echo htmlspecialchars($story['story_category']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="application-meta">
                                <?php echo $status_badge; ?>
                                <?php if ($story['feedback_rating']): ?>
                                    <div style="margin-top: 0.5rem;">
                                        <span style="color: var(--warning); font-size: 0.9rem;">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <?php echo ($i <= $story['feedback_rating']) ? '★' : '☆'; ?>
                                            <?php endfor; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars(substr($story['story_content'], 0, 300))); ?>
                            <?php if (strlen($story['story_content']) > 300): ?>
                                <span style="color: var(--slate-500);">...</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-star"></i>
                    <h3>No Stories Submitted Yet</h3>
                    <p>You haven't shared any success stories yet. Use the form above to inspire others with your achievements and experiences!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

            </div>
        </main>
    </div>
    
    <script>
// Initialize user data from PHP - Add this at the very top of your script section
const userData = {
    id: '<?php echo $student_id; ?>',
    role: 'student'
};

// Enhanced showSection function that handles page refresh
function showSection(sectionId, linkElement) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });

    // Remove active class from all navigation links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    // Show the selected section and add active class to the link
    document.getElementById(sectionId).classList.add('active');
    linkElement.classList.add('active');
    
    // Update page title in header
    const titles = {
        'dashboard': 'Dashboard',
        'profile': 'My Profile', 
        'messages': 'Messages',
        'applications': 'Applications',
        'stories': 'Success Stories',
        'saved_courses': 'My Saved Courses'
    };
    document.querySelector('.header-title').textContent = titles[sectionId];
    
    // Load saved courses when section is accessed (always refresh data)
    if (sectionId === 'saved_courses') {
        setTimeout(() => {
            loadSavedCourses();
        }, 100); // Small delay to ensure DOM is ready
    }
    
    // Update URL without reloading
    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?page=" + sectionId;
    window.history.pushState({ path: newUrl }, '', newUrl);

    // Close mobile sidebar if open
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    }
}

// Enhanced function specifically for saved courses
function enhancedShowSection(sectionId, linkElement) {
    showSection(sectionId, linkElement);
}

// Fixed loadSavedCourses function with better error handling
function loadSavedCourses() {
    const savedCoursesList = document.getElementById('savedCoursesList');
    const savedCoursesEmpty = document.getElementById('savedCoursesEmpty');
    const savedCoursesCount = document.getElementById('savedCoursesCount');
    
    // Check if elements exist
    if (!savedCoursesList || !savedCoursesEmpty || !savedCoursesCount) {
        console.error('Required DOM elements not found for saved courses');
        return;
    }
    
    // Show loading state
    savedCoursesList.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading saved courses...</div>';
    savedCoursesList.style.display = 'block';
    savedCoursesEmpty.style.display = 'none';
    
    // Use the student_id from PHP session
    const userId = userData.id;
    const userType = userData.role;
    
    console.log('Loading saved courses for user:', userId, 'type:', userType);
    
    // Add timestamp to prevent caching
    const timestamp = new Date().getTime();
    const url = `get_saved_courses_detailed.php?user_id=${encodeURIComponent(userId)}&user_type=${encodeURIComponent(userType)}&t=${timestamp}`;
    
    fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        },
        cache: 'no-store' // Force no caching
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text(); // Get as text first to debug
    })
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            console.log('Parsed data:', data);
            
            if (data.success) {
                if (data.saved_courses && data.saved_courses.length > 0) {
                    displaySavedCourses(data.saved_courses);
                    
                    // Update badge count
                    savedCoursesCount.textContent = data.saved_courses.length;
                    savedCoursesCount.style.display = 'inline-block';
                    
                    savedCoursesEmpty.style.display = 'none';
                    savedCoursesList.style.display = 'block';
                } else {
                    // No saved courses found
                    console.log('No saved courses found');
                    savedCoursesEmpty.style.display = 'block';
                    savedCoursesList.style.display = 'none';
                    savedCoursesCount.style.display = 'none';
                }
            } else {
                throw new Error(data.message || 'Unknown error from server');
            }
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            throw new Error('Invalid JSON response from server');
        }
    })
    .catch(error => {
        console.error('Error loading saved courses:', error);
        savedCoursesList.innerHTML = `
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> 
                Error loading saved courses: ${error.message}
                <button onclick="loadSavedCourses()" class="btn btn-secondary" style="margin-left: 1rem;">
                    <i class="fas fa-retry"></i> Try Again
                </button>
            </div>`;
        savedCoursesEmpty.style.display = 'none';
        savedCoursesList.style.display = 'block';
    });
}

function displaySavedCourses(courses) {
    const savedCoursesList = document.getElementById('savedCoursesList');
    
    if (!courses || courses.length === 0) {
        document.getElementById('savedCoursesEmpty').style.display = 'block';
        savedCoursesList.style.display = 'none';
        return;
    }
    
    savedCoursesList.innerHTML = courses.map(course => `
        <div class="saved-course-item" data-course-id="${course.id}">
            <div class="course-header">
                <div class="course-info">
                    <h4 class="course-title">${course.course_title}</h4>
                    <p class="course-category">${course.course_category}</p>
                    <div class="course-meta">
                        <span class="meta-tag duration">
                            <i class="fas fa-clock"></i> ${course.duration}
                        </span>
                        <span class="meta-tag difficulty ${course.difficulty_level.toLowerCase()}">
                            <i class="fas fa-signal"></i> ${course.difficulty_level}
                        </span>
                        <span class="meta-tag price">
                            <i class="fas fa-tag"></i> ${course.course_price_type === 'free' || course.price_amount == 0 ? 'Free' : '₹' + parseInt(course.price_amount).toLocaleString()}
                        </span>
                    </div>
                </div>
                <div class="course-actions">
                    <button class="btn btn-secondary" onclick="viewCourseDetail(${course.id})">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn" onclick="unsaveCourse(${course.id})" style="background: var(--danger); color: white;">
                        <i class="fas fa-bookmark"></i> Remove
                    </button>
                </div>
            </div>
            
            <div class="course-description">
                <p>${course.course_description}</p>
            </div>
            
            ${course.skills_taught ? `
                <div class="course-skills">
                    ${course.skills_taught.split(',').slice(0, 5).map(skill => 
                        `<span class="skill-tag">${skill.trim()}</span>`
                    ).join('')}
                    ${course.skills_taught.split(',').length > 5 ? 
                        `<span class="skill-tag">+${course.skills_taught.split(',').length - 5} more</span>` : ''
                    }
                </div>
            ` : ''}
            
            <div class="course-footer">
                <div class="saved-info">
                    <small style="color: var(--slate-500);">
                        <i class="fas fa-bookmark"></i> 
                        Saved on ${new Date(course.saved_at).toLocaleDateString()}
                    </small>
                </div>
                ${course.certificate_provided ? 
                    '<div class="certificate-badge"><i class="fas fa-certificate"></i> Certificate Included</div>' : ''
                }
            </div>
        </div>
    `).join('');
}

function refreshSavedCourses() {
    const refreshBtn = document.getElementById('refreshBtn');
    if (!refreshBtn) return;
    
    const originalHtml = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    // Force a fresh load
    loadSavedCourses();
    
    setTimeout(() => {
        refreshBtn.innerHTML = originalHtml;
        refreshBtn.disabled = false;
    }, 1000);
}

function unsaveCourse(courseId) {
    if (!confirm('Remove this course from your saved list?')) return;
    
    const formData = new FormData();
    formData.append('action', 'unsave');
    formData.append('course_id', courseId);
    formData.append('user_id', userData.id);
    formData.append('user_type', userData.role);
    
    fetch('save_course.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the course item with animation
            const courseItem = document.querySelector(`[data-course-id="${courseId}"]`);
            if (courseItem) {
                courseItem.style.transform = 'translateX(-100%)';
                courseItem.style.opacity = '0';
                setTimeout(() => {
                    courseItem.remove();
                    
                    // Check if any courses left
                    const remainingCourses = document.querySelectorAll('.saved-course-item');
                    if (remainingCourses.length === 0) {
                        document.getElementById('savedCoursesEmpty').style.display = 'block';
                        document.getElementById('savedCoursesList').style.display = 'none';
                        document.getElementById('savedCoursesCount').style.display = 'none';
                    } else {
                        document.getElementById('savedCoursesCount').textContent = remainingCourses.length;
                    }
                }, 300);
            }
        } else {
            console.error('Failed to remove course:', data.message || 'Unknown error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function viewCourseDetail(courseId) {
    window.open('internship_detail.php?id=' + courseId, '_blank');
}

// Initialize saved courses count and handle page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, user data:', userData);
    
    // Check current page and load saved courses if needed
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get('page');
    
    if (currentPage === 'saved_courses') {
        // If we're on saved courses page, load them immediately
        setTimeout(() => {
            loadSavedCourses();
        }, 500);
    }
    
    // Load initial saved courses count
    if (userData && userData.id) {
        const timestamp = new Date().getTime();
        fetch(`get_saved_courses.php?user_id=${encodeURIComponent(userData.id)}&user_type=${encodeURIComponent(userData.role)}&t=${timestamp}`, {
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.saved_courses.length > 0) {
                const savedCoursesCount = document.getElementById('savedCoursesCount');
                if (savedCoursesCount) {
                    savedCoursesCount.textContent = data.saved_courses.length;
                    savedCoursesCount.style.display = 'inline-block';
                }
            }
        })
        .catch(error => console.error('Error loading saved courses count:', error));
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page') || 'dashboard';
        const linkElement = document.querySelector(`.nav-link[onclick*="${page}"]`);
        if (linkElement) {
            showSection(page, linkElement);
        }
    });
});
// Add notification system for better user feedback
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(n => n.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `notification alert alert-${type === 'error' ? 'error' : 'success'}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    `;
    
    const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
    notification.innerHTML = `
        <i class="fas ${icon}"></i>
        ${message}
        <button onclick="this.parentElement.remove()" style="float: right; background: none; border: none; color: inherit; cursor: pointer;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}
        // Enhanced form validation
        function validateForm(formElement) {
            let isValid = true;
            const requiredFields = formElement.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--danger)';
                    field.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
                    isValid = false;
                    
                    // Remove error styling when user starts typing
                    field.addEventListener('input', function() {
                        this.style.borderColor = '';
                        this.style.boxShadow = '';
                    }, { once: true });
                } else {
                    field.style.borderColor = 'var(--success)';
                    field.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
                }
            });
            
            return isValid;
        }

        // Apply form validation to all forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
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
        });

     // Fixed rating stars functionality
document.querySelectorAll('.rating-stars').forEach(ratingContainer => {
    const labels = ratingContainer.querySelectorAll('label');
    const inputs = ratingContainer.querySelectorAll('input[type="radio"]');
    
    // Add click handlers
    inputs.forEach((input, index) => {
        input.addEventListener('change', function() {
            updateStarDisplay(ratingContainer, this.value);
        });
    });
    
    // Add hover handlers
    labels.forEach((label) => {
        label.addEventListener('mouseenter', function() {
            const value = this.previousElementSibling.value;
            highlightStars(ratingContainer, value);
        });
    });
    
    // Reset on mouse leave
    ratingContainer.addEventListener('mouseleave', function() {
        const checkedInput = this.querySelector('input[type="radio"]:checked');
        if (checkedInput) {
            updateStarDisplay(ratingContainer, checkedInput.value);
        } else {
            highlightStars(ratingContainer, 0);
        }
    });
});

function highlightStars(container, rating) {
    const labels = container.querySelectorAll('label');
    labels.forEach((label) => {
        const input = label.previousElementSibling;
        if (parseInt(input.value) <= parseInt(rating)) {
            label.style.color = 'var(--warning)';
        } else {
            label.style.color = 'var(--slate-300)';
        }
    });
}

function updateStarDisplay(container, rating) {
    highlightStars(container, rating);
}
        // File upload functionality
        function setupFileUpload(uploadAreaId, inputId) {
            const uploadArea = document.getElementById(uploadAreaId);
            const fileInput = document.getElementById(inputId);
            
            if (!uploadArea || !fileInput) return;

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover'), false);
            });

            uploadArea.addEventListener('drop', handleDrop, false);

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                updateFileDisplay(files);
            }

            fileInput.addEventListener('change', function(e) {
                updateFileDisplay(e.target.files);
            });

            function updateFileDisplay(files) {
                const uploadText = uploadArea.querySelector('.upload-text');
                if (files.length > 0) {
                    uploadText.innerHTML = `
                        <h4>${files.length} file(s) selected</h4>
                        <p>${Array.from(files).map(f => f.name).join(', ')}</p>
                    `;
                }
            }
        }

        // Initialize file uploads
        setupFileUpload('story-upload', 'story-images');
        setupFileUpload('feedback-upload', 'feedback-files');

        // Auto-refresh functionality
        setInterval(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page');
            if (currentPage === 'messages') {
                const lastInteraction = Date.now() - (window.lastUserInteraction || 0);
                if (lastInteraction > 10000) {
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
            if (e.key >= '1' && e.key <= '6') {
                const navLinks = document.querySelectorAll('.nav-link');
                const linkIndex = parseInt(e.key) - 1;
                if (navLinks[linkIndex]) {
                    navLinks[linkIndex].click();
                }
            }
        });

        // Add loading states to form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    const originalHtml = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    
                    // Re-enable after 5 seconds as fallback
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                    }, 5000);
                }
            });
        });

        // Handle window resize for responsive behavior
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('mobile-overlay');
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            }
        });



// Profile photo upload functionality
document.addEventListener('DOMContentLoaded', function() {
    const photoUploadArea = document.getElementById('photoUploadArea');
    const photoInput = document.getElementById('profilePhotoInput');
    const photoForm = document.getElementById('photo-upload-form');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFileBtn = document.getElementById('removeFileBtn');
    const photoPreview = document.getElementById('photoPreview');
    const previewImage = document.getElementById('previewImage');
    const uploadIcon = document.getElementById('uploadIcon');
    const uploadText = document.getElementById('uploadText');
    const photoSubmitBtn = document.getElementById('photoSubmitBtn');

    // Drag and drop functionality
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        photoUploadArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        photoUploadArea.addEventListener(eventName, () => {
            photoUploadArea.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        photoUploadArea.addEventListener(eventName, () => {
            photoUploadArea.classList.remove('dragover');
        }, false);
    });

    photoUploadArea.addEventListener('drop', handleDrop, false);

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            photoInput.files = files;
            handleFileSelect(files[0]);
        }
    }

    // File input change
    photoInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });

    // Handle file selection
    function handleFileSelect(file) {
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid image file (JPG, PNG, or GIF)');
            return;
        }

        // Validate file size (5MB limit)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('File size must be less than 5MB');
            return;
        }

        // Show file info
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileInfo.classList.add('show');
        photoUploadArea.classList.add('has-file');

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            photoPreview.classList.add('show');
            
            // Update upload area appearance
            uploadIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
            uploadText.querySelector('h4').textContent = 'Photo Ready to Upload';
            uploadText.querySelector('p').innerHTML = 'Click "Upload Now" to save your new profile photo<br><button type="button" class="btn btn-primary" onclick="document.getElementById(\'photoSubmitBtn\').click()" style="margin-top: 1rem;"><i class="fas fa-upload"></i> Upload Now</button>';
        };
        reader.readAsDataURL(file);
    }

    // Remove file
    removeFileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        clearFileSelection();
    });

    function clearFileSelection() {
        photoInput.value = '';
        fileInfo.classList.remove('show');
        photoPreview.classList.remove('show');
        photoUploadArea.classList.remove('has-file');
        
        // Reset upload area
        uploadIcon.innerHTML = '<i class="fas fa-cloud-upload-alt"></i>';
        uploadText.querySelector('h4').textContent = 'Upload New Profile Photo';
        uploadText.querySelector('p').innerHTML = 'Drag and drop your photo here, or click to browse<br><div class="file-input-wrapper"><button type="button" class="file-select-btn"><i class="fas fa-image"></i> Choose Photo</button></div>';
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Auto-submit form when file is selected
    photoInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            // Small delay to show preview
            setTimeout(() => {
                photoForm.submit();
            }, 1000);
        }
    });

    // Update user avatar in sidebar when photo changes
    function updateSidebarAvatar(photoUrl) {
        const userAvatar = document.querySelector('.user-avatar');
        if (userAvatar && photoUrl) {
            userAvatar.style.backgroundImage = `url(${photoUrl})`;
            userAvatar.style.backgroundSize = 'cover';
            userAvatar.style.backgroundPosition = 'center';
            userAvatar.textContent = '';
        }
    }
});
document.addEventListener('DOMContentLoaded', function() {
    // Resume upload functionality
    const resumeUploadArea = document.getElementById('resumeUploadArea');
    const resumeInput = document.getElementById('resumeFileInput');
    const resumeForm = document.getElementById('resume-upload-form');
    const resumeFileInfo = document.getElementById('resumeFileInfo');
    const resumeFileName = document.getElementById('resumeFileName');
    const resumeFileSize = document.getElementById('resumeFileSize');
    const removeResumeFileBtn = document.getElementById('removeResumeFileBtn');
    const resumeUploadIcon = document.getElementById('resumeUploadIcon');
    const resumeUploadText = document.getElementById('resumeUploadText');
    const resumeSubmitBtn = document.getElementById('resumeSubmitBtn');

    if (resumeUploadArea && resumeInput) {
        // Drag and drop functionality for resume
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            resumeUploadArea.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            resumeUploadArea.addEventListener(eventName, () => {
                resumeUploadArea.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            resumeUploadArea.addEventListener(eventName, () => {
                resumeUploadArea.classList.remove('dragover');
            }, false);
        });

        resumeUploadArea.addEventListener('drop', handleResumeDrop, false);

        function handleResumeDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                resumeInput.files = files;
                handleResumeFileSelect(files[0]);
            }
        }

        // Resume file input change
        resumeInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleResumeFileSelect(e.target.files[0]);
            }
        });

        // Handle resume file selection
        function handleResumeFileSelect(file) {
            // Validate file type
            const allowedExtensions = ['.pdf', '.doc', '.docx'];
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(fileExtension)) {
                alert('Please select a valid resume file (PDF, DOC, or DOCX)');
                return;
            }

            // Validate file size (10MB limit)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File size must be less than 10MB');
                return;
            }

            // Show file info
            resumeFileName.textContent = file.name;
            resumeFileSize.textContent = formatFileSize(file.size);
            resumeFileInfo.classList.add('show');
            resumeUploadArea.classList.add('has-file');

            // Update upload area appearance
            resumeUploadIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
            resumeUploadText.querySelector('h4').textContent = 'Resume Ready to Upload';
            resumeUploadText.querySelector('p').innerHTML = 'Click "Upload Now" to save your resume<br><button type="button" class="btn btn-primary" onclick="document.getElementById(\'resumeSubmitBtn\').click()" style="margin-top: 1rem;"><i class="fas fa-upload"></i> Upload Now</button>';
        }

        // Remove resume file
        if (removeResumeFileBtn) {
            removeResumeFileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                clearResumeFileSelection();
            });
        }

        function clearResumeFileSelection() {
            resumeInput.value = '';
            resumeFileInfo.classList.remove('show');
            resumeUploadArea.classList.remove('has-file');
            
            // Reset upload area
            resumeUploadIcon.innerHTML = '<i class="fas fa-file-upload"></i>';
            resumeUploadText.querySelector('h4').textContent = 'Upload Resume/CV';
            resumeUploadText.querySelector('p').innerHTML = 'Drag and drop your resume here, or click to browse<br><div class="file-input-wrapper"><button type="button" class="file-select-btn"><i class="fas fa-file-text"></i> Choose File</button></div>';
        }

        // Auto-submit form when resume file is selected
        resumeInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                setTimeout(() => {
                    resumeForm.submit();
                }, 1000);
            }
        });
    }
});

// Remove resume function
function removeResume() {
    if (confirm('Are you sure you want to remove your current resume? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'student_dashboard.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remove_resume';
        input.value = '1';
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
// JavaScript functions for Read More functionality
function toggleDescription(button) {
    const applicationItem = button.closest('.application-item');
    const shortDesc = applicationItem.querySelector('.description-short');
    const fullDesc = applicationItem.querySelector('.description-full');
    
    if (shortDesc.style.display === 'none') {
        // Currently showing full, switch to short
        shortDesc.style.display = 'inline';
        fullDesc.style.display = 'none';
        button.textContent = 'Read More';
    } else {
        // Currently showing short, switch to full
        shortDesc.style.display = 'none';
        fullDesc.style.display = 'inline';
        button.textContent = 'Read Less';
    }
}

function toggleCoverLetter(button) {
    const applicationItem = button.closest('.application-item');
    const shortCover = applicationItem.querySelector('.cover-short');
    const fullCover = applicationItem.querySelector('.cover-full');
    
    if (shortCover.style.display === 'none') {
        // Currently showing full, switch to short
        shortCover.style.display = 'block';
        fullCover.style.display = 'none';
        button.textContent = 'Read More';
    } else {
        // Currently showing short, switch to full
        shortCover.style.display = 'none';
        fullCover.style.display = 'block';
        button.textContent = 'Read Less';
    }
}
// Saved Courses Functionality
function loadSavedCourses() {
    const savedCoursesList = document.getElementById('savedCoursesList');
    const savedCoursesEmpty = document.getElementById('savedCoursesEmpty');
    const savedCoursesCount = document.getElementById('savedCoursesCount');
    
    // Show loading state
    savedCoursesList.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading saved courses...</div>';
    savedCoursesList.style.display = 'block';
    savedCoursesEmpty.style.display = 'none';
    
    fetch('get_saved_courses_detailed.php?user_id=' + userData.id + '&user_type=' + userData.role)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.saved_courses.length > 0) {
                displaySavedCourses(data.saved_courses);
                
                // Update badge count
                savedCoursesCount.textContent = data.saved_courses.length;
                savedCoursesCount.style.display = 'inline-block';
                
                savedCoursesEmpty.style.display = 'none';
                savedCoursesList.style.display = 'block';
            } else {
                savedCoursesEmpty.style.display = 'block';
                savedCoursesList.style.display = 'none';
                savedCoursesCount.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading saved courses:', error);
            savedCoursesList.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error loading saved courses. Please try again.</div>';
        });
}

function displaySavedCourses(courses) {
    const savedCoursesList = document.getElementById('savedCoursesList');
    
    savedCoursesList.innerHTML = courses.map(course => `
        <div class="saved-course-item" data-course-id="${course.id}">
            <div class="course-header">
                <div class="course-info">
                    <h4 class="course-title">${course.course_title}</h4>
                    <p class="course-category">${course.course_category}</p>
                    <div class="course-meta">
                        <span class="meta-tag duration">
                            <i class="fas fa-clock"></i> ${course.duration}
                        </span>
                        <span class="meta-tag difficulty ${course.difficulty_level.toLowerCase()}">
                            <i class="fas fa-signal"></i> ${course.difficulty_level}
                        </span>
                        <span class="meta-tag price">
                            <i class="fas fa-tag"></i> ${course.course_price_type === 'free' || course.price_amount == 0 ? 'Free' : '₹' + parseInt(course.price_amount).toLocaleString()}
                        </span>
                    </div>
                </div>
                <div class="course-actions">
                    <button class="btn btn-secondary" onclick="viewCourseDetail(${course.id})">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn" onclick="unsaveCourse(${course.id})" style="background: var(--danger); color: white;">
                        <i class="fas fa-bookmark"></i> Remove
                    </button>
                </div>
            </div>
            
            <div class="course-description">
                <p>${course.course_description}</p>
            </div>
            
            ${course.skills_taught ? `
                <div class="course-skills">
                    ${course.skills_taught.split(',').slice(0, 5).map(skill => 
                        `<span class="skill-tag">${skill.trim()}</span>`
                    ).join('')}
                    ${course.skills_taught.split(',').length > 5 ? 
                        `<span class="skill-tag">+${course.skills_taught.split(',').length - 5} more</span>` : ''
                    }
                </div>
            ` : ''}
            
            <div class="course-footer">
                <div class="saved-info">
                    <small style="color: var(--slate-500);">
                        <i class="fas fa-bookmark"></i> 
                        Saved on ${new Date(course.saved_at).toLocaleDateString()}
                    </small>
                </div>
                ${course.certificate_provided ? 
                    '<div class="certificate-badge"><i class="fas fa-certificate"></i> Certificate Included</div>' : ''
                }
            </div>
        </div>
    `).join('');
}

function refreshSavedCourses() {
    const refreshBtn = document.getElementById('refreshBtn');
    const originalHtml = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    loadSavedCourses();
    
    setTimeout(() => {
        refreshBtn.innerHTML = originalHtml;
        refreshBtn.disabled = false;
    }, 1000);
}

function unsaveCourse(courseId) {
    if (!confirm('Remove this course from your saved list?')) return;
    
    const formData = new FormData();
    formData.append('action', 'unsave');
    formData.append('course_id', courseId);
    formData.append('user_id', userData.id);
    formData.append('user_type', userData.role);
    
    fetch('save_course.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the course item with animation
            const courseItem = document.querySelector(`[data-course-id="${courseId}"]`);
            if (courseItem) {
                courseItem.style.transform = 'translateX(-100%)';
                courseItem.style.opacity = '0';
                setTimeout(() => {
                    courseItem.remove();
                    
                    // Check if any courses left
                    const remainingCourses = document.querySelectorAll('.saved-course-item');
                    if (remainingCourses.length === 0) {
                        document.getElementById('savedCoursesEmpty').style.display = 'block';
                        document.getElementById('savedCoursesList').style.display = 'none';
                        document.getElementById('savedCoursesCount').style.display = 'none';
                    } else {
                        document.getElementById('savedCoursesCount').textContent = remainingCourses.length;
                    }
                }, 300);
            }
            
            showNotification('Course removed from saved list', 'success');
        } else {
            showNotification('Failed to remove course', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error occurred', 'error');
    });
}

function viewCourseDetail(courseId) {
    window.open('course_detail.php?id=' + courseId, '_blank');
}

// Load saved courses when the section becomes active
document.addEventListener('DOMContentLoaded', function() {
    // Override the existing showSection function to include saved courses loading
    const originalShowSection = window.showSection;
    window.showSection = function(sectionId, linkElement) {
        originalShowSection(sectionId, linkElement);
        
        if (sectionId === 'saved_courses') {
            loadSavedCourses();
        }
    };
    
    // Load saved courses count on initial page load
    if (userData && userData.id) {
        fetch('get_saved_courses.php?user_id=' + userData.id + '&user_type=' + userData.role)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.saved_courses.length > 0) {
                    const savedCoursesCount = document.getElementById('savedCoursesCount');
                    savedCoursesCount.textContent = data.saved_courses.length;
                    savedCoursesCount.style.display = 'inline-block';
                }
            })
            .catch(error => console.error('Error loading saved courses count:', error));
    }
});
// Updated JavaScript functions - remove arrow toggle functionality

// Only hamburger toggle from header (for both desktop and mobile)
function toggleSidebarFromHeader() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    
    // For mobile screens (768px and below)
    if (window.innerWidth <= 768) {
        // Toggle mobile sidebar
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
    } else {
        // For desktop screens - toggle collapse/expand
        sidebar.classList.toggle('collapsed');
    }
}

// Remove the old toggleSidebar() function - no longer needed

// Mobile sidebar toggle (for overlay clicks)
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
}

// Enhanced showSection function
function showSection(sectionId, linkElement) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });

    // Remove active class from all navigation links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    // Show the selected section and add active class to the link
    document.getElementById(sectionId).classList.add('active');
    linkElement.classList.add('active');
    
    // Update page title in header
    const titles = {
        'dashboard': 'Dashboard',
        'profile': 'My Profile', 
        'messages': 'Messages',
        'applications': 'Applications',
        'stories': 'Success Stories',
        'saved_courses': 'My Saved Courses'
    };
    document.querySelector('.header-title').textContent = titles[sectionId];
    
    // Load saved courses when section is accessed
    if (sectionId === 'saved_courses') {
        setTimeout(() => {
            loadSavedCourses();
        }, 100);
    }
    
    // Update URL without reloading
    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?page=" + sectionId;
    window.history.pushState({ path: newUrl }, '', newUrl);

    // Close mobile sidebar if open
    if (window.innerWidth <= 768) {
        toggleMobileSidebar();
    }
}

// Enhanced function for saved courses
function enhancedShowSection(sectionId, linkElement) {
    showSection(sectionId, linkElement);
}

// Handle window resize for responsive behavior
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    
    if (window.innerWidth > 768) {
        // Desktop mode - remove mobile classes
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        
        // Auto-collapse sidebar on tablet screens
        if (window.innerWidth <= 1024) {
            sidebar.classList.add('collapsed');
        }
    } else {
        // Mobile mode - ensure sidebar is hidden and remove collapsed state
        sidebar.classList.remove('mobile-open', 'collapsed');
        overlay.classList.remove('active');
    }
});

// Initialize responsive behavior on page load
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    
    // Auto-collapse on tablet/smaller desktop screens only
    if (window.innerWidth > 768 && window.innerWidth <= 1024) {
        sidebar.classList.add('collapsed');
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page') || 'dashboard';
        const linkElement = document.querySelector(`.nav-link[onclick*="${page}"]`);
        if (linkElement) {
            showSection(page, linkElement);
        }
    });
    
    // Initialize current page
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get('page') || 'dashboard';
    const linkElement = document.querySelector(`.nav-link[onclick*="${currentPage}"]`);
    if (linkElement) {
        showSection(currentPage, linkElement);
    }
});

// Keyboard shortcut for sidebar toggle (Ctrl/Cmd + B)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        toggleSidebarFromHeader();
    }
});

// Touch gesture support for mobile sidebar
let touchStartX = null;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.touches[0].clientX;
});

document.addEventListener('touchend', function(e) {
    if (touchStartX === null) return;
    
    const touchEndX = e.changedTouches[0].clientX;
    const diffX = touchEndX - touchStartX;
    
    // Swipe right from left edge to open sidebar
    if (touchStartX < 50 && diffX > 100 && window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        if (!sidebar.classList.contains('mobile-open')) {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('active');
        }
    }
    
    touchStartX = null;
});
// Enhanced showSection function that handles page refresh
        function showSection(sectionId, linkElement) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all navigation links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });

            // Show the selected section and add active class to the link
            document.getElementById(sectionId).classList.add('active');
            linkElement.classList.add('active');
            
            // Update page title in header
            const titles = {
                'dashboard': 'Dashboard',
                'profile': 'My Profile', 
                'messages': 'Messages',
                'applications': 'Applications',
                'stories': 'Success Stories',
                'saved_courses': 'My Saved Courses'
            };
            document.querySelector('.header-title').textContent = titles[sectionId];
            
            // Update URL without reloading
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?page=" + sectionId;
            window.history.pushState({ path: newUrl }, '', newUrl);

            // Close mobile sidebar if open
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('mobile-overlay');
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            }
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 'dashboard';
            const linkElement = document.querySelector(`.nav-link[onclick*="${page}"]`);
            if (linkElement) {
                showSection(page, linkElement);
            }
        });

        // Initialize current page on load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page') || 'dashboard';
            const linkElement = document.querySelector(`.nav-link[onclick*="${currentPage}"]`);
            if (linkElement) {
                showSection(currentPage, linkElement);
            }

            // Add animation to notification items
            const notifications = document.querySelectorAll('.course-notification-item');
            notifications.forEach((notification, index) => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    notification.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    notification.style.opacity = '1';
                    notification.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add click animation to meeting links
            document.querySelectorAll('.meeting-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255,255,255,0.3);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        pointer-events: none;
                    `;
                    
                    this.style.position = 'relative';
                    this.appendChild(ripple);
                    
                    setTimeout(() => ripple.remove(), 600);
                });
            });
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
    </script>
</body>
</html>