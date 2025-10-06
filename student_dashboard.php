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
$student_id = $row['student_id'];
$student_id_display = htmlspecialchars($row['student_id']);
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

// Handle FREE course application submission - SIMPLIFIED for free online courses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $course_id = $_POST['course_id'];
    $learning_objective = $_POST['learning_objective'];
    $cover_letter = $_POST['cover_letter'];
    $applicant_name = $first_name . ' ' . $last_name;
    
    // Check if already applied
    $check_stmt = $conn->prepare("SELECT id FROM course_applications WHERE course_id = ? AND student_id = ?");
    $check_stmt->bind_param("is", $course_id, $student_id);
    $check_stmt->execute();
    $existing_application = $check_stmt->get_result();
    
    if ($existing_application->num_rows > 0) {
        $error_message = "You have already applied for this free course!";
    } else {
        // Verify course exists and is free
        $verify_stmt = $conn->prepare("SELECT course_title, company_name FROM course WHERE id = ?");
        $verify_stmt->bind_param("i", $course_id);
        $verify_stmt->execute();
        $course_result = $verify_stmt->get_result();
        
        if ($course_result->num_rows > 0) {
            $app_stmt = $conn->prepare("INSERT INTO course_applications (course_id, student_id, applicant_name, email, phone, learning_objective, cover_letter, application_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $app_stmt->bind_param("issssss", $course_id, $student_id, $applicant_name, $email, $contact, $learning_objective, $cover_letter);
            
            if ($app_stmt->execute()) {
                $success_message = "Application submitted successfully for this free online course!";
                header("Location: student_dashboard.php?page=applications&success=1");
                exit;
            } else {
                $error_message = "Error submitting application: " . $app_stmt->error;
            }
            $app_stmt->close();
        } else {
            $error_message = "Course not found.";
        }
        $verify_stmt->close();
    }
    $check_stmt->close();
}// Handle course withdrawal/unenrollment - Enhanced for live sessions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_course'])) {
    $application_id = (int)$_POST['application_id'];
    
    // Verify the application belongs to this student
    $verify_stmt = $conn->prepare("SELECT ca.id, c.course_title, c.course_type FROM course_applications ca JOIN course c ON ca.course_id = c.id WHERE ca.id = ? AND ca.student_id = ?");
    $verify_stmt->bind_param("is", $application_id, $student_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        $course_data = $verify_result->fetch_assoc();
        
        // Special check for live courses - prevent withdrawal if sessions already started
        if ($course_data['course_type'] === 'live') {
            // Check if any sessions have already occurred
            $session_check = $conn->prepare("SELECT COUNT(*) as past_sessions FROM course_notifications WHERE course_id = (SELECT course_id FROM course_applications WHERE id = ?) AND meeting_datetime < NOW()");
            $session_check->bind_param("i", $application_id);
            $session_check->execute();
            $session_result = $session_check->get_result();
            $past_data = $session_result->fetch_assoc();
            
            if ($past_data['past_sessions'] > 0) {
                $error_message = "Cannot withdraw from this live course as sessions have already started. Please contact support for assistance.";
                header("Location: student_dashboard.php?page=applications&error=session_started");
                exit;
            }
            $session_check->close();
        }
        
        // Delete the application
        $withdraw_stmt = $conn->prepare("DELETE FROM course_applications WHERE id = ? AND student_id = ?");
        $withdraw_stmt->bind_param("is", $application_id, $student_id);
        
        if ($withdraw_stmt->execute()) {
            $success_message = "Successfully withdrawn from " . htmlspecialchars($course_data['course_title']);
            header("Location: student_dashboard.php?page=applications&success=withdrawn");
            exit;
        } else {
            $error_message = "Error processing withdrawal. Please try again.";
        }
        $withdraw_stmt->close();
    } else {
        $error_message = "Application not found or unauthorized.";
    }
    $verify_stmt->close();
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
// Add this AFTER line ~260 (after fetching saved courses)

// Get saved courses count - Make sure this runs BEFORE the HTML output
$saved_count_stmt = $conn->prepare("SELECT COUNT(*) as saved_count FROM saved_courses WHERE user_id = ? AND user_type = 'student'");
$saved_count_stmt->bind_param("s", $student_id);
$saved_count_stmt->execute();
$saved_count_result = $saved_count_stmt->get_result();
$total_saved_courses = $saved_count_result->fetch_assoc()['saved_count'] ?? 0;
$saved_count_stmt->close();

// Fetch saved courses with course details - Make sure columns match your course table
$saved_courses_stmt = $conn->prepare("
    SELECT sc.*, 
           c.id as course_id,
           c.course_title, 
           c.course_description, 
           c.company_name,
           c.course_format,
           c.course_type,
           c.course_category,
           c.duration,
           c.difficulty_level,
           c.start_date,
           c.enrollment_deadline,
           c.certificate_provided,
           c.skills_taught
    FROM saved_courses sc 
    JOIN course c ON sc.course_id = c.id 
    WHERE sc.user_id = ? AND sc.user_type = 'student'
    ORDER BY sc.saved_at DESC
");
$saved_courses_stmt->bind_param("s", $student_id);
$saved_courses_stmt->execute();
$saved_courses_result = $saved_courses_stmt->get_result();

// Store saved courses in array for later use
$saved_courses_array = [];
while ($row = $saved_courses_result->fetch_assoc()) {
    $saved_courses_array[] = $row;
}
$saved_courses_stmt->close();
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
// First get the messages - Direct match using student_id
$messages_stmt = $conn->prepare("
    SELECT id, sender_type, subject, message, is_read, created_at 
    FROM student_messages 
    WHERE receiver_type = 'student' AND receiver_id = ? 
    ORDER BY is_read ASC, created_at DESC
");
$messages_stmt->bind_param("s", $student_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();

// Filter out meeting link messages if student is not approved for that course
$filtered_messages = [];
$unread_count = 0;

while ($message = $messages_result->fetch_assoc()) {
    // Count unread messages
    if (!$message['is_read']) {
        $unread_count++;
    }
    
    // If message is about meeting details, check approval status
    if (strpos($message['subject'], 'Meeting Details') !== false) {
        // For now, skip meeting messages as they're shown in notifications section
        continue;
    }
    $filtered_messages[] = $message;
}

// Get total messages count
$total_messages = count($filtered_messages);
// Filter out meeting link messages if student is not approved for that course
$filtered_messages = [];
while ($message = $messages_result->fetch_assoc()) {
    // If message is about meeting details, check approval status
    if (strpos($message['subject'], 'Meeting Details') !== false) {
        // Extract course title and check if student is approved
        // For now, skip meeting messages unless explicitly approved
        continue; // Skip meeting link messages - they're shown in notifications section
    }
    $filtered_messages[] = $message;
}

// Get total messages count
$total_messages = $messages_result->num_rows;

// Get total applications count for FREE courses
$app_count_stmt = $conn->prepare("SELECT COUNT(*) as app_count FROM course_applications WHERE student_id = ?");
$app_count_stmt->bind_param("s", $student_id);
$app_count_stmt->execute();
$app_count_result = $app_count_stmt->get_result();
$total_applications = $app_count_result->fetch_assoc()['app_count'];
// Line ~290: Update the student applications query
$student_apps_stmt = $conn->prepare("
    SELECT ca.*, 
           c.course_title, 
           c.course_description, 
           c.company_name,
           c.course_format,
           c.course_type,  -- ADD THIS
           c.duration,
           c.difficulty_level,
           c.start_date,
           c.enrollment_deadline
    FROM course_applications ca 
    JOIN course c ON ca.course_id = c.id 
    WHERE ca.student_id = ? 
    ORDER BY ca.created_at DESC
");
$student_apps_stmt->bind_param("s", $student_id);
$student_apps_stmt->execute();
$student_apps_result = $student_apps_stmt->get_result();

// Fetch course notifications for FREE online courses
$course_notifications_stmt = $conn->prepare("
    SELECT 
        cn.*,
        c.course_title,
        c.company_name,
        c.course_format,
        c.course_type,
        ca.application_status
    FROM course_notifications cn
    JOIN course c ON cn.course_id = c.id
    JOIN course_applications ca ON ca.course_id = c.id
    WHERE ca.student_id = ? 
    AND c.course_type = 'live'
    AND cn.notification_type = 'online_meeting'
    AND ca.application_status = 'approved'  -- CRITICAL: Only show to approved students
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
    'applications' => 'Free Course Applications',
    'stories' => 'Success Stories',
    'saved_courses' => 'Saved Free Courses'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Nexttern - Free Online Courses</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="student_dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobile-overlay" onclick="toggleMobileSidebar()"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <span class="logo-text">Nexttern</span>
                </div>
            </div>
            
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
    </a>
</div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link <?php echo ($page === 'applications') ? 'active' : ''; ?>" onclick="showSection('applications', this)">
                        <div class="nav-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <span class="nav-text">Free Courses</span>
                    </a>
                </div>
                
                <div class="nav-item">
    <a href="#" class="nav-link <?php echo ($page === 'saved_courses') ? 'active' : ''; ?>" onclick="enhancedShowSection('saved_courses', this)">
        <div class="nav-icon">
            <i class="fas fa-bookmark"></i>
        </div>
        <span class="nav-text">Saved Courses</span>
        <?php if ($total_saved_courses > 0): ?>
            <span class="nav-badge has-courses"><?php echo $total_saved_courses; ?></span>
        <?php endif; ?>
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
                    <?php echo $titles[$page] ?? 'Dashboard'; ?>
                </h1>
                <div class="header-actions">
                    <div class="header-stat">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo $unread_count; ?> Unread</span>
                    </div>
                    <div class="header-stat">
                        <i class="fas fa-gift"></i>
                        <span><?php echo $total_applications; ?> Free Courses</span>
                    </div>
                </div>
            </header>

            <div class="content-container">
               <!-- Dashboard Section -->
<div id="dashboard" class="content-section <?php echo ($page === 'dashboard' || !isset($_GET['page'])) ? 'active' : ''; ?>">
    <div class="content-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-wave-square"></i>
                Welcome to Free Online Learning, <?php echo $first_name; ?>!
            </div>
        </div>
        <div class="card-body">
            <p style="color: var(--slate-600); margin-bottom: 1.5rem; line-height: 1.6;">
                Access 100% free online courses from top companies. Choose between self-paced learning (instant access) or live sessions (interactive classes). No hidden fees, no payment required - just quality education to advance your career.
            </p>
            <div style="background: var(--slate-50); padding: 1.5rem; border-radius: var(--border-radius); border-left: 4px solid var(--success);">
                <h4 style="color: var(--slate-900); margin-bottom: 1rem;">Quick Actions</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                    <button class="btn btn-primary" onclick="window.location.href='index.php#courses'">
                        <i class="fas fa-search"></i> Browse Free Courses
                    </button>
                    <button class="btn btn-secondary" onclick="showSection('applications', document.querySelector('[onclick*=applications]'))">
                        <i class="fas fa-graduation-cap"></i> My Enrollments
                    </button>
                    <button class="btn btn-secondary" onclick="showSection('profile', document.querySelector('[onclick*=profile]'))">
                        <i class="fas fa-user-edit"></i> Update Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="overview-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">Messages</div>
                <div class="stat-icon messages">
                    <i class="fas fa-envelope"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $total_messages; ?></div>
            <div class="stat-description"><?php echo $unread_count; ?> unread</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">Free Courses</div>
                <div class="stat-icon applications">
                    <i class="fas fa-gift"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $total_applications; ?></div>
            <div class="stat-description">Enrolled courses</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">Success Stories</div>
                <div class="stat-icon feedback">
                    <i class="fas fa-star"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $total_stories; ?></div>
            <div class="stat-description">Shared experiences</div>
        </div>
        
        <!-- Course Types Card - MOVED INSIDE overview-grid -->
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">Course Types</div>
                <div class="stat-icon" style="background: var(--accent);">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-value">
                <?php 
                // Count by type
                $type_count_stmt = $conn->prepare("
                    SELECT c.course_type, COUNT(*) as count 
                    FROM course_applications ca 
                    JOIN course c ON ca.course_id = c.id 
                    WHERE ca.student_id = ? 
                    GROUP BY c.course_type
                ");
                $type_count_stmt->bind_param("s", $student_id);
                $type_count_stmt->execute();
                $type_counts = $type_count_stmt->get_result();
                $self_paced = 0;
                $live = 0;
                while($tc = $type_counts->fetch_assoc()) {
                    if($tc['course_type'] === 'self_paced') $self_paced = $tc['count'];
                    if($tc['course_type'] === 'live') $live = $tc['count'];
                }
                echo $self_paced + $live;
                ?>
            </div>
            <div class="stat-description">
                <?php echo $self_paced; ?> Self-Paced • <?php echo $live; ?> Live
            </div>
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
                <!-- Messages Section (same as before) -->
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
            <i class="fas fa-envelope" style="color: var(--primary); font-size: 1.2rem;"></i>
            <span class="message-stat-value"><?php echo $total_messages; ?></span>
            <span class="message-stat-label">Messages Received</span>
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
                                    ?>
                                      <div class="message-item">
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
                                            
                                          
                                            
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
    <i class="fas fa-graduation-cap"></i>
    <h3>No Course Enrollments Yet</h3>
    <p>Start your learning journey! Choose self-paced courses for instant access or live sessions for interactive learning.</p>
    <button class="btn btn-primary" onclick="window.location.href='index.php#courses'" style="margin-top: 1rem;">
        <i class="fas fa-search"></i> Browse Free Courses
    </button>
</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

               <!-- Free Course Applications Section -->
<div id="applications" class="content-section <?php echo ($page === 'applications') ? 'active' : ''; ?>">
    <div class="content-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-graduation-cap"></i>
                My Free Course Enrollments
                <?php if ($course_notifications_count > 0): ?>
                    <span class="notification-badge">
                        <i class="fas fa-bell"></i>
                        <?php echo $course_notifications_count; ?> Updates
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Add Course Type Filter -->
            <div class="course-type-filter">
                <button class="filter-btn active" onclick="filterCoursesByType('all')" data-type="all">
                    <i class="fas fa-th"></i> All Courses
                </button>
                <button class="filter-btn" onclick="filterCoursesByType('self_paced')" data-type="self_paced">
                    <i class="fas fa-user-clock"></i> Self-Paced
                </button>
                <button class="filter-btn" onclick="filterCoursesByType('live')" data-type="live">
                    <i class="fas fa-video"></i> Live Sessions
                </button>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (!empty($success_message) && $page === 'applications'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Course Notifications for LIVE courses only -->
            <?php if ($course_notifications_count > 0): ?>
                <div style="margin-bottom: 3rem;">
                    <h3 style="color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-video"></i>
                        Live Session Notifications
                    </h3>
                    <?php while ($notification = $course_notifications_result->fetch_assoc()): 
                        $meeting_datetime = date('F j, Y \a\t g:i A', strtotime($notification['meeting_datetime']));
                        $is_upcoming = strtotime($notification['meeting_datetime']) > time();
                    ?>
                        <div class="course-notification-item">
                            <div class="notification-header">
                                <div class="notification-course-info">
                                    <h4>
                                        <i class="fas fa-graduation-cap"></i>
                                        <?php echo htmlspecialchars($notification['course_title']); ?>
                                        <span class="course-type-badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            LIVE
                                        </span>
                                    </h4>
                                    <div class="company-name">
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars($notification['company_name']); ?>
                                    </div>
                                </div>
                                <?php if ($is_upcoming): ?>
                                    <span style="background: var(--success); color: white; padding: 0.5rem 1rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                        <i class="fas fa-clock"></i> UPCOMING
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="meeting-details">
                                <div class="meeting-info">
                                    <div class="meeting-detail">
                                        <i class="fas fa-calendar"></i>
                                        <strong>Date:</strong> <?php echo date('F j, Y', strtotime($notification['meeting_datetime'])); ?>
                                    </div>
                                    <div class="meeting-detail">
                                        <i class="fas fa-clock"></i>
                                        <strong>Time:</strong> <?php echo date('g:i A', strtotime($notification['meeting_datetime'])); ?>
                                    </div>
                                </div>
                                
                                <a href="<?php echo htmlspecialchars($notification['meeting_link']); ?>" target="_blank" class="meeting-link">
                                    <i class="fas fa-external-link-alt"></i>
                                    Join Live Session
                                </a>
                                
                                <?php if (!empty($notification['additional_notes'])): ?>
                                    <div class="additional-notes">
                                        <h6><i class="fas fa-sticky-note"></i> Additional Information:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($notification['additional_notes'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Enrolled Free Courses List -->
            <?php if ($total_applications > 0): ?>
                <h3 style="color: var(--primary); margin-bottom: 1.5rem;">
                    <i class="fas fa-list"></i>
                    My Free Course Enrollments
                </h3>
                
                <div id="coursesContainer">
                    <?php
                    $student_apps_stmt->execute();
                    $student_apps_result = $student_apps_stmt->get_result();
                    
                    while ($app = $student_apps_result->fetch_assoc()): 
                        $formatted_date = date('M j, Y', strtotime($app['created_at']));
                        $learning_objective_display = str_replace('_', ' ', ucwords($app['learning_objective'], '_'));
                        $start_date = !empty($app['start_date']) ? date('M j, Y', strtotime($app['start_date'])) : 'TBA';
                        $enrollment_deadline = !empty($app['enrollment_deadline']) ? date('M j, Y', strtotime($app['enrollment_deadline'])) : 'N/A';
                        $course_type = $app['course_type'] ?? 'self_paced';
                    ?>
                        <div class="application-item" data-course-type="<?php echo $course_type; ?>">
                            <div class="application-header">
                                <div class="application-course">
                                    <h4>
                                        <i class="fas fa-<?php echo $course_type === 'self_paced' ? 'user-clock' : 'video'; ?>"></i>
                                        <?php echo htmlspecialchars($app['course_title']); ?>
                                        
                                        <!-- Enhanced Course Type Badge -->
                                         <!-- ADD THIS: Withdrawal Button -->
<div class="application-actions" style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--slate-200); display: flex; justify-content: flex-end; gap: 0.75rem;">
    <form method="POST" action="student_dashboard.php" onsubmit="return confirmWithdrawal('<?php echo htmlspecialchars($app['course_title']); ?>', '<?php echo $course_type; ?>');" style="margin: 0;">
        <input type="hidden" name="withdraw_course" value="1">
        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
        <button type="submit" class="btn btn-danger withdraw-btn">
            <i class="fas fa-sign-out-alt"></i> 
            Withdraw from Course
        </button>
    </form>
</div>
                                        <span class="course-type-badge" style="background: <?php echo $course_type === 'self_paced' ? 'linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%)' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; ?>; color: white; padding: 0.35rem 0.85rem; border-radius: 12px; font-size: 0.75rem; margin-left: 0.5rem; font-weight: 600;">
                                            <?php echo $course_type === 'self_paced' ? 'SELF-PACED' : 'LIVE SESSIONS'; ?>
                                        </span>
                                        
                                        <span class="free-badge" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); color: white; padding: 0.35rem 0.85rem; border-radius: 12px; font-size: 0.75rem; margin-left: 0.5rem; font-weight: 600;">
                                            100% FREE
                                        </span>
                                    </h4>
                                    
                                    <div style="margin-top: 0.75rem; display: flex; flex-wrap: wrap; gap: 1rem; color: var(--slate-600);">
                                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($app['company_name']); ?></span>
                                        <?php if (!empty($app['duration'])): ?>
                                            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($app['duration']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($app['difficulty_level'])): ?>
                                            <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($app['difficulty_level']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($app['course_format'])): ?>
                                            <span><i class="fas fa-desktop"></i> <?php echo htmlspecialchars($app['course_format']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="application-meta">
                                    <div class="application-date">Applied: <?php echo $formatted_date; ?></div>
                                    <?php echo getStatusBadge($app['application_status']); ?>
                                </div>
                            </div>

                            <?php if (!empty($app['course_description'])): ?>
                                <div class="course-description" style="margin: 1rem 0; padding: 1rem; background: var(--slate-50); border-radius: 8px; border-left: 4px solid var(--primary);">
                                    <h5 style="color: var(--primary); margin-bottom: 0.5rem;">
                                        <i class="fas fa-info-circle"></i> About This Free Course
                                    </h5>
                                    <p style="color: var(--slate-600); line-height: 1.6; margin: 0;">
                                        <?php echo nl2br(htmlspecialchars($app['course_description'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Course Type Specific Info -->
                            <?php if ($course_type === 'self_paced'): ?>
                                <div class="course-access-info" style="margin: 1rem 0; padding: 1rem; background: rgba(78, 205, 196, 0.1); border-radius: 8px; border-left: 4px solid #4ecdc4;">
                                    <h5 style="color: #44a08d; margin-bottom: 0.5rem;">
                                        <i class="fas fa-infinity"></i> Self-Paced Learning Access
                                    </h5>
                                    <p style="color: var(--slate-600); margin: 0;">
                                        <i class="fas fa-check-circle" style="color: #44a08d;"></i> Course materials sent to your email<br>
                                        <i class="fas fa-check-circle" style="color: #44a08d;"></i> Learn at your own pace, anytime<br>
                                        <i class="fas fa-check-circle" style="color: #44a08d;"></i> Lifetime access to content
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="course-timeline" style="display: flex; gap: 2rem; margin: 1rem 0; padding: 1rem; background: rgba(102, 126, 234, 0.1); border-radius: 8px; border-left: 4px solid #667eea;">
                                    <div>
                                        <strong style="color: #667eea;">Start Date:</strong>
                                        <span style="color: var(--slate-700);"><?php echo $start_date; ?></span>
                                    </div>
                                    <div>
                                        <strong style="color: #667eea;">Enrollment Deadline:</strong>
                                        <span style="color: var(--slate-700);"><?php echo $enrollment_deadline; ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="message-content">
                                <strong>Learning Objective:</strong> <?php echo htmlspecialchars($learning_objective_display); ?>
                            </div>

                            <?php if (!empty($app['cover_letter'])): ?>
                                <div class="message-content">
                                    <strong>Your Note:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>No Course Enrollments Yet</h3>
                    <p>Start your learning journey! Choose self-paced courses for instant access or live sessions for interactive learning.</p>
                    <button class="btn btn-primary" onclick="window.location.href='index.php#courses'" style="margin-top: 1rem;">
                        <i class="fas fa-search"></i> Browse Free Courses
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

              <!-- Saved Courses Section - FIXED -->
<div id="saved_courses" class="content-section <?php echo ($page === 'saved_courses') ? 'active' : ''; ?>">
    <div class="content-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-bookmark"></i>
                My Saved Free Courses
                <?php if ($total_saved_courses > 0): ?>
                    <span class="nav-badge"><?php echo $total_saved_courses; ?></span>
                <?php endif; ?>
            </div>
            <button class="btn btn-secondary" onclick="refreshSavedCourses()" id="refreshBtn">
                <i class="fas fa-sync-alt"></i>
                Refresh
            </button>
        </div>
        <div class="card-body">
            <div id="savedCoursesContainer">
                <?php if ($total_saved_courses > 0): ?>
                    <div id="savedCoursesList">
                        <?php foreach ($saved_courses_array as $course): ?>
                            <div class="saved-course-item" data-course-id="<?php echo $course['course_id']; ?>">
                                <div class="course-header">
                                    <div class="course-info">
                                        <h4 class="course-title">
                                            <?php echo htmlspecialchars($course['course_title']); ?>
                                            <span class="course-type-badge" style="background: <?php echo $course['course_type'] === 'self_paced' ? 'linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%)' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; ?>; color: white; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.7rem; margin-left: 0.5rem;">
                                                <?php echo $course['course_type'] === 'self_paced' ? 'SELF-PACED' : 'LIVE'; ?>
                                            </span>
                                            <span class="free-badge" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); color: white; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.7rem; margin-left: 0.5rem; font-weight: 600;">
                                                100% FREE
                                            </span>
                                        </h4>
                                        <p class="course-category"><?php echo htmlspecialchars($course['course_category'] ?? 'General'); ?></p>
                                        <div class="course-meta">
                                            <span class="meta-tag duration">
                                                <i class="fas fa-clock"></i> <?php echo htmlspecialchars($course['duration'] ?? 'Duration not specified'); ?>
                                            </span>
                                            <span class="meta-tag difficulty <?php echo strtolower($course['difficulty_level'] ?? 'beginner'); ?>">
                                                <i class="fas fa-signal"></i> <?php echo htmlspecialchars($course['difficulty_level'] ?? 'Beginner'); ?>
                                            </span>
                                            <span class="meta-tag company">
                                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($course['company_name'] ?? 'Unknown Company'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="course-actions">
                                        <button class="btn btn-secondary" onclick="viewCourseDetail(<?php echo $course['course_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn" onclick="unsaveCourse(<?php echo $course['course_id']; ?>)" style="background: var(--danger); color: white;">
                                            <i class="fas fa-bookmark-slash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="course-description">
                                    <p><?php echo htmlspecialchars($course['course_description'] ?? 'No description available'); ?></p>
                                </div>
                                
                                <?php if (!empty($course['skills_taught'])): ?>
                                    <div class="course-skills">
                                        <?php 
                                        $skills = explode(',', $course['skills_taught']);
                                        $displaySkills = array_slice($skills, 0, 5);
                                        foreach ($displaySkills as $skill): ?>
                                            <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($skills) > 5): ?>
                                            <span class="skill-tag">+<?php echo count($skills) - 5; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="course-footer">
                                    <div class="saved-info">
                                        <small style="color: var(--slate-500);">
                                            <i class="fas fa-bookmark"></i> 
                                            Saved on <?php echo date('M j, Y', strtotime($course['saved_at'])); ?>
                                        </small>
                                    </div>
                                    <?php if ($course['certificate_provided']): ?>
                                        <div class="certificate-badge"><i class="fas fa-certificate"></i> Certificate Included</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" id="savedCoursesEmpty">
                        <i class="fas fa-bookmark"></i>
                        <h3>No Saved Courses Yet</h3>
                        <p>Bookmark your favorite free courses to access them quickly!</p>
                        <button class="btn btn-primary" onclick="window.location.href='course.php#courses'" style="margin-top: 1rem;">
                            <i class="fas fa-search"></i> Browse Free Courses
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

                <!-- Stories Section (same as before) -->
                <div id="stories" class="content-section <?php echo ($page === 'stories') ? 'active' : ''; ?>">
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
                            <?php endif; ?>

                            <div style="background: var(--slate-50); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--success); margin-bottom: 2rem;">
                                <h4 style="color: var(--success); margin-bottom: 0.5rem;">Share Your Free Course Success</h4>
                                <p style="color: var(--slate-600); line-height: 1.6;">Tell others how free online courses helped you achieve your goals!</p>
                            </div>

                            <form id="story-form" action="student_dashboard.php" method="post">
                                <input type="hidden" name="submit_story" value="1">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Story Title</label>
                                        <input type="text" class="form-input" name="story_title" placeholder="e.g., How Free Web Development Course Changed My Career" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Category</label>
                                        <select class="form-input" name="story_category" required>
                                            <option value="">Select Category</option>
                                            <option value="Career Breakthrough">Career Breakthrough</option>
                                            <option value="Skill Development">Skill Development</option>
                                            <option value="Personal Growth">Personal Growth</option>
                                            <option value="Project Success">Project Success</option>
                                            <option value="Learning Experience">Learning Experience</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <label class="form-label">Your Success Story</label>
                                        <textarea class="form-input form-textarea" name="story_content" placeholder="Share your experience with free online courses..." required></textarea>
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
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i>
                                        Submit Story
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
                                    $formatted_date = date('M j, Y', strtotime($story['submission_date']));
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
                                            </div>
                                            <div class="application-meta">
                                                <?php echo $status_badge; ?>
                                                <?php if ($story['feedback_rating']): ?>
                                                    <div style="margin-top: 0.5rem; color: var(--warning);">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <?php echo ($i <= $story['feedback_rating']) ? '★' : '☆'; ?>
                                                        <?php endfor; ?>
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
                                    <h3>No Stories Submitted</h3>
                                    <p>Share your experience with free courses!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
    
    <script src="student_dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>