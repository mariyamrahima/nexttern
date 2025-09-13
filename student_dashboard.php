<?php
$page = $_GET['page'] ?? 'dashboard';
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
// Updated SELECT query to fetch 'qualifications' as well
$stmt = $conn->prepare("SELECT student_id, first_name, last_name, contact, gender, dob,qualifications FROM students WHERE email = ?");
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
$dob = htmlspecialchars(date('Y-m-d', strtotime($row['dob'])));

// 'qualifications' data is now being fetched.
// Using null coalescing operator to avoid "Deprecated" warning
$qualifications = htmlspecialchars($row['qualifications'] ?? '');

$error_message = '';
$success_message = '';
$update_success = false;
    // Updated query with the 'qualifications' column
    if ($update_success) {
        $stmt_update = $conn->prepare("UPDATE students SET first_name = ?, last_name = ?, contact = ?, gender = ?, dob = ?, qualifications = ? WHERE student_id = ?");
        $stmt_update->bind_param("sssssss", $new_first_name, $new_last_name, $new_contact, $new_gender, $new_dob, $new_qualifications, $student_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['email'] = $email;
            $success_message = "Profile updated successfully!";
            // Redirect to prevent form resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . "?page=profile&success=1");
            exit;
        } else {
            $error_message = "Error updating record: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
    $upload_dir = 'uploads/profile_photos/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
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
            // Update database with new photo path
            $stmt_photo = $conn->prepare("UPDATE students SET profile_photo = ? WHERE student_id = ?");
            $stmt_photo->bind_param("ss", $upload_path, $student_id);
            
            if ($stmt_photo->execute()) {
                // Delete old photo if exists and not default
                $old_photo_stmt = $conn->prepare("SELECT profile_photo FROM students WHERE student_id = ?");
                $old_photo_stmt->bind_param("s", $student_id);
                $old_photo_stmt->execute();
                $old_photo_result = $old_photo_stmt->get_result();
                $old_photo_data = $old_photo_result->fetch_assoc();
                
                if ($old_photo_data['profile_photo'] && $old_photo_data['profile_photo'] !== $upload_path && file_exists($old_photo_data['profile_photo'])) {
                    unlink($old_photo_data['profile_photo']);
                }
                
                $success_message = "Profile photo updated successfully!";
                // Refresh page to show new photo
                header("Location: " . $_SERVER['PHP_SELF'] . "?page=profile&success=1");
                exit;
            } else {
                $error_message = "Error updating profile photo in database.";
                unlink($upload_path); // Remove uploaded file on database error
            }
            $stmt_photo->close();
        } else {
            $error_message = "Error uploading file. Please try again.";
        }
    }
}

// Get current profile photo
$photo_stmt = $conn->prepare("SELECT profile_photo FROM students WHERE student_id = ?");
$photo_stmt->bind_param("s", $student_id);
$photo_stmt->execute();
$photo_result = $photo_stmt->get_result();
$photo_data = $photo_result->fetch_assoc();
$current_photo = $photo_data['profile_photo'] ?? '';


// Handle course application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $course_id = $_POST['course_id'];
    $learning_objective = $_POST['learning_objective'];
    $cover_letter = $_POST['cover_letter'];
    $applicant_name = $first_name . ' ' . $last_name;
    
    // Check if student already applied for this course
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
            header("Location: " . $_SERVER['PHP_SELF'] . "?page=applications&success=1");
            exit;
        } else {
            $error_message = "Error submitting application: " . $app_stmt->error;
        }
        $app_stmt->close();
    }
    $check_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_story'])) {
    $story_title = $_POST['story_title'];
    $story_content = $_POST['story_content'];
    $story_category = $_POST['story_category'];
    $feedback_rating = $_POST['feedback_rating'] ?? null;
    
    // Insert into stories table including student details for storage
    $story_stmt = $conn->prepare("INSERT INTO stories (story_title, story_category, story_content, feedback_rating, student_id, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $story_stmt->bind_param("sssssss", $story_title, $story_category, $story_content, $feedback_rating, $student_id, $first_name, $last_name);
    
    if ($story_stmt->execute()) {
        $success_message = "Success story submitted for review!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=stories&success=1");
        exit;
    } else {
        $error_message = "Error submitting story: " . $story_stmt->error;
    }
    $story_stmt->close();
}

// Get student's submitted stories count
$stories_count_stmt = $conn->prepare("SELECT COUNT(*) as stories_count FROM stories WHERE student_id = ?");
$stories_count_stmt->bind_param("i", $student_id);
$stories_count_stmt->execute();
$stories_count_result = $stories_count_stmt->get_result();
$total_stories = $stories_count_result->fetch_assoc()['stories_count'];

// Fetch student's submitted stories with status
$student_stories_stmt = $conn->prepare("
    SELECT story_id, story_title, story_category, story_content, feedback_rating, status, submission_date, updated_date
    FROM stories 
    WHERE student_id = ? 
    ORDER BY submission_date DESC
");
$student_stories_stmt->bind_param("i", $student_id);
$student_stories_stmt->execute();
$student_stories_result = $student_stories_stmt->get_result();

// Handle mark as read functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $message_id = $_POST['message_id'];
    $stmt = $conn->prepare("UPDATE student_messages SET is_read = TRUE WHERE id = ? AND receiver_id = ?");
    $stmt->bind_param("is", $message_id, $student_id);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $_SERVER['PHP_SELF'] . "?page=messages");
    exit;
}

// Handle mark all as read functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE student_messages SET is_read = TRUE WHERE receiver_type = 'student' AND receiver_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $_SERVER['PHP_SELF'] . "?page=messages");
    exit;
}

// Fetch messages for the current student with improved query
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
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM student_messages WHERE receiver_type = 'student' AND receiver_id = ? AND is_read = FALSE");
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
    SELECT ca.*, c.course_title, c.course_description as course_description
    FROM course_applications ca 
    JOIN course c ON ca.course_id = c.id 
    WHERE ca.student_id = ? 
    ORDER BY ca.created_at DESC
");
$student_apps_stmt->bind_param("s", $student_id);
$student_apps_stmt->execute();
$student_apps_result = $student_apps_stmt->get_result();

// Function to get sender display name
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

// Function to get sender icon
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

// Function to get time ago format
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return date('M j, Y', strtotime($datetime));
}

// Function to get application status badge
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="status-badge pending"><i class="fas fa-clock"></i> Pending</span>',
        'approved' => '<span class="status-badge approved"><i class="fas fa-check"></i> Approved</span>',
        'rejected' => '<span class="status-badge rejected"><i class="fas fa-times"></i> Rejected</span>',
        'waitlisted' => '<span class="status-badge waitlisted"><i class="fas fa-hourglass-half"></i> Waitlisted</span>'
    ];
    return $badges[$status] ?? '<span class="status-badge">' . ucfirst($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #035946;
            --primary-light: #0a7058;
            --primary-dark: #023d32;
            --secondary: #64748b;
            --accent: #4ecdc4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            
            /* Modern neutrals */
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 70px;
            --border-radius: 12px;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
.profile-photo-section {
    display: flex;
    align-items: flex-start;
    gap: 2rem;
    margin-bottom: 2rem;
    padding: 2rem;
    background: var(--slate-50);
    border-radius: var(--border-radius);
    border: 1px solid var(--slate-200);
}

.current-photo-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.profile-photo-display {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid white;
    box-shadow: var(--shadow-lg);
    object-fit: cover;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2.5rem;
    font-weight: 700;
    position: relative;
    overflow: hidden;
}

.profile-photo-display img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.photo-upload-area {
    flex: 1;
    border: 2px dashed var(--slate-300);
    border-radius: var(--border-radius);
    padding: 2rem;
    text-align: center;
    background: white;
    transition: var(--transition);
    cursor: pointer;
    position: relative;
}

.photo-upload-area:hover,
.photo-upload-area.dragover {
    border-color: var(--primary);
    background: rgba(3, 89, 70, 0.05);
}

.photo-upload-area.has-file {
    border-color: var(--success);
    background: rgba(16, 185, 129, 0.05);
}

.upload-icon {
    font-size: 2.5rem;
    color: var(--slate-400);
    margin-bottom: 1rem;
}

.photo-upload-area.has-file .upload-icon {
    color: var(--success);
}

.upload-text h4 {
    color: var(--slate-700);
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.upload-text p {
    color: var(--slate-500);
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.file-input-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
}

.file-input-wrapper input[type=file] {
    position: absolute;
    left: -9999px;
    opacity: 0;
    pointer-events: none;
}

.file-select-btn {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--border-radius);
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.925rem;
}

.file-select-btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

.file-info {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--slate-100);
    border-radius: var(--border-radius);
    display: none;
}

.file-info.show {
    display: block;
}

.file-details {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.file-name {
    font-weight: 600;
    color: var(--slate-900);
    flex: 1;
    text-align: left;
}

.file-size {
    color: var(--slate-500);
    font-size: 0.875rem;
}

.remove-file-btn {
    background: var(--danger);
    color: white;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: var(--transition);
}

.remove-file-btn:hover {
    background: #dc2626;
}

.photo-preview {
    margin-top: 1rem;
    display: none;
}

.photo-preview.show {
    display: block;
}

.preview-image {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 12px;
    border: 2px solid var(--slate-200);
    box-shadow: var(--shadow);
}

@media (max-width: 768px) {
    .profile-photo-section {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .profile-photo-display {
        width: 100px;
        height: 100px;
        font-size: 2rem;
    }
}
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--slate-50);
            color: var(--slate-700);
            line-height: 1.6;
        }

        /* Layout Structure */
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--slate-200);
            box-shadow: var(--shadow);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 50;
            transition: var(--transition);
            overflow: hidden;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--header-height);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
            transition: var(--transition);
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .logo-text {
            transition: var(--transition);
            white-space: nowrap;
        }

        .sidebar.collapsed .logo-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            color: var(--slate-500);
            transition: var(--transition);
        }

        .sidebar-toggle:hover {
            background-color: var(--slate-100);
            color: var(--primary);
        }

        .sidebar.collapsed .sidebar-toggle {
            transform: rotate(180deg);
        }

        /* User Profile in Sidebar */
        .sidebar-user {
            padding: 1.5rem;
            border-bottom: 1px solid var(--slate-200);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .user-details {
            flex: 1;
            min-width: 0;
            transition: var(--transition);
        }

        .user-name {
            font-weight: 600;
            color: var(--slate-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-id {
            font-size: 0.875rem;
            color: var(--slate-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar.collapsed .user-details {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        /* Navigation Menu */
        .sidebar-nav {
            flex: 1;
            padding: 1.5rem 0;
            overflow-y: auto;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            color: var(--slate-600);
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            font-weight: 500;
            cursor: pointer;
        }

        .nav-link:hover {
            background-color: var(--slate-50);
            color: var(--primary);
        }

        .nav-link.active {
            background-color: rgba(3, 89, 70, 0.1);
            color: var(--primary);
            border-right: 3px solid var(--primary);
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.1rem;
        }

        .nav-text {
            white-space: nowrap;
            transition: var(--transition);
        }

        .nav-badge {
            margin-left: auto;
            background-color: var(--danger);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
            transition: var(--transition);
        }

        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .nav-badge {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 0.875rem;
        }

        /* Logout Button */
        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--slate-200);
        }

        .logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .sidebar.collapsed .logout-btn .logout-text {
            display: none;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Top Header */
        .top-header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--slate-200);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow);
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--slate-900);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: var(--slate-100);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Content Container */
        .content-container {
            flex: 1;
            padding: 2rem;
            max-width: 1400px;
            width: 100%;
        }

        /* Content Sections */
        .content-section {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        .content-section.active {
            display: block;
        }

        /* Cards */
        .content-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--slate-200);
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: between;
            gap: 1rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--slate-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Dashboard Overview Cards */
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--slate-200);
            transition: var(--transition);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--slate-600);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.messages { background: linear-gradient(135deg, var(--info), #2563eb); }
        .stat-icon.applications { background: linear-gradient(135deg, var(--warning), #d97706); }
        .stat-icon.stories { background: linear-gradient(135deg, var(--success), #059669); }
        .stat-icon.feedback { background: linear-gradient(135deg, var(--accent), #06b6d4); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--slate-900);
            margin-bottom: 0.25rem;
        }

        .stat-description {
            font-size: 0.875rem;
            color: var(--slate-500);
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            color: var(--slate-700);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-input {
            padding: 0.75rem 1rem;
            border: 1px solid var(--slate-300);
            border-radius: var(--border-radius);
            background-color: white;
            transition: var(--transition);
            font-size: 0.925rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(3, 89, 70, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.925rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background-color: var(--slate-200);
            color: var(--slate-700);
        }

        .btn-secondary:hover {
            background-color: var(--slate-300);
        }

        /* Messages */
        .messages-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .messages-stats {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .message-stat {
            text-align: center;
        }

        .message-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .message-stat-label {
            font-size: 0.75rem;
            color: var(--slate-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .message-item {
            background: white;
            border: 1px solid var(--slate-200);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .message-item:hover {
            box-shadow: var(--shadow);
        }

        .message-item.unread {
            border-left: 4px solid var(--primary);
            background-color: rgba(3, 89, 70, 0.02);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sender-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
            font-weight: 600;
        }

        .sender-avatar.admin { background: linear-gradient(135deg, var(--danger), #dc2626); }
        .sender-avatar.company { background: linear-gradient(135deg, var(--info), #2563eb); }
        .sender-avatar.student { background: linear-gradient(135deg, var(--success), #059669); }

        .sender-details h4 {
            font-weight: 600;
            color: var(--slate-900);
            margin-bottom: 0.25rem;
            font-size: 0.925rem;
        }

        .sender-type {
            font-size: 0.8rem;
            color: var(--slate-500);
        }

        .message-time {
            font-size: 0.8rem;
            color: var(--slate-500);
        }

        .message-subject {
            font-weight: 600;
            color: var(--slate-900);
            margin-bottom: 0.75rem;
            font-size: 1.05rem;
        }

        .message-content {
            color: var(--slate-600);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .message-actions {
            display: flex;
            justify-content: flex-end;
        }

        .mark-read-btn {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-size: 0.825rem;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .mark-read-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-badge.approved {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .status-badge.waitlisted {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        /* Application Items */
        .application-item {
            background: white;
            border: 1px solid var(--slate-200);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .application-item:hover {
            box-shadow: var(--shadow);
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .application-course h4 {
            font-weight: 600;
            color: var(--slate-900);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .application-date {
            font-size: 0.8rem;
            color: var(--slate-500);
            margin-bottom: 0.5rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--slate-500);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--slate-300);
        }

        .empty-state h3 {
            margin-bottom: 0.75rem;
            font-size: 1.25rem;
            color: var(--slate-700);
        }

        .empty-state p {
            font-size: 1rem;
            line-height: 1.5;
            max-width: 500px;
            margin: 0 auto;
        }

        /* Rating Stars */
        .rating-stars {
            display: flex;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }

        .rating-stars input[type="radio"] {
            display: none;
        }

        .rating-stars label {
            font-size: 1.5rem;
            color: var(--slate-300);
            cursor: pointer;
            transition: var(--transition);
            margin: 0;
        }

        .rating-stars label:hover,
        .rating-stars input[type="radio"]:checked ~ label,
        .rating-stars label:hover ~ label {
            color: var(--warning);
        }

        .rating-stars input[type="radio"]:checked + label {
            color: var(--warning);
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed var(--slate-300);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            background: var(--slate-50);
            transition: var(--transition);
            cursor: pointer;
        }

        .upload-area:hover,
        .upload-area.dragover {
            border-color: var(--primary);
            background: rgba(3, 89, 70, 0.05);
        }

        .upload-icon {
            font-size: 2.5rem;
            color: var(--slate-400);
            margin-bottom: 1rem;
        }

        .upload-text h4 {
            color: var(--slate-700);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .upload-text p {
            color: var(--slate-500);
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: var(--sidebar-collapsed-width);
            }
            
            .sidebar .logo-text,
            .sidebar .nav-text,
            .sidebar .nav-badge,
            .sidebar .user-details {
                opacity: 0;
                width: 0;
                overflow: hidden;
            }

            .sidebar .nav-link {
                justify-content: center;
                padding: 0.875rem;
            }

            .main-content {
                margin-left: var(--sidebar-collapsed-width);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 100;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .content-container {
                padding: 1rem;
            }

            .overview-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .messages-header,
            .application-header {
                flex-direction: column;
                gap: 1rem;
            }

            .messages-stats {
                justify-content: space-around;
                width: 100%;
            }
        }

        /* Mobile Overlay */
        .mobile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
        }

        @media (max-width: 768px) {
            .mobile-overlay.active {
                display: block;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Scrollbar Styling */
        .sidebar-nav::-webkit-scrollbar,
        .messages-container::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-nav::-webkit-scrollbar-track,
        .messages-container::-webkit-scrollbar-track {
            background: var(--slate-100);
        }

        .sidebar-nav::-webkit-scrollbar-thumb,
        .messages-container::-webkit-scrollbar-thumb {
            background: var(--slate-300);
            border-radius: 2px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover,
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: var(--slate-400);
        }

        /* Focus Styles */
        .nav-link:focus,
        .btn:focus,
        .form-input:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobile-overlay" onclick="toggleMobileSidebar()"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <span class="logo-text">Nexttern</span>
                </div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-chevron-left"></i>
                </button>
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
<!-- Replace your existing profile section form with this updated version -->
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

            <!-- Photo Upload Form -->
            <form id="photo-upload-form" action="student_dashboard.php" method="post" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*">
                <button type="submit" id="photoSubmitBtn">Upload Photo</button>
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
                            <option value="High School" <?php echo ($qualifications === 'High School') ? 'selected' : ''; ?>>High School</option>
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
                            <?php if ($unread_count > 0): ?>
                            <form method="post" style="margin: 0;">
                                <button type="submit" name="mark_all_read" class="btn btn-secondary">
                                    <i class="fas fa-check-double"></i>
                                    Mark All Read
                                </button>
                            </form>
                            <?php endif; ?>
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
                                                <?php if (!$message['is_read']): ?>
                                                    <form method="post" style="margin: 0;">
                                                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                        <button type="submit" name="mark_read" class="mark-read-btn">
                                                            <i class="fas fa-check"></i>
                                                            Mark as Read
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <div style="color: var(--success); font-size: 0.875rem; font-weight: 500;">
                                                        <i class="fas fa-check-circle"></i> Read
                                                    </div>
                                                <?php endif; ?>
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

                <!-- Applications Section -->
                <div id="applications" class="content-section <?php echo ($page === 'applications') ? 'active' : ''; ?>">
                    <div class="content-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-file-alt"></i>
                                My Applications
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

                            <?php if ($total_applications > 0): ?>
                                <?php while ($app = $student_apps_result->fetch_assoc()): 
                                    $formatted_date = date('M j, Y g:i A', strtotime($app['created_at']));
                                    $learning_objective_display = str_replace('_', ' ', ucwords($app['learning_objective'], '_'));
                                ?>
                                    <div class="application-item">
                                        <div class="application-header">
                                            <div class="application-course">
                                                <h4><?php echo htmlspecialchars($app['course_title']); ?></h4>
                                            </div>
                                            <div class="application-meta">
                                                <div class="application-date">Applied: <?php echo $formatted_date; ?></div>
                                                <?php echo getStatusBadge($app['application_status']); ?>
                                            </div>
                                        </div>

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
        // Toggle sidebar from header (for both desktop and mobile)
        function toggleSidebarFromHeader() {
            const sidebar = document.getElementById('sidebar');
            
            // For mobile screens (768px and below)
            if (window.innerWidth <= 768) {
                toggleMobileSidebar();
            } else {
                // For desktop screens - toggle collapse/expand
                const isCollapsed = sidebar.classList.contains('collapsed');
                
                if (isCollapsed) {
                    // Expand the sidebar
                    sidebar.classList.remove('collapsed');
                } else {
                    // Collapse the sidebar
                    sidebar.classList.add('collapsed');
                }
            }
        }

        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = sidebar.classList.contains('collapsed');
            
            if (isCollapsed) {
                // Expand the sidebar
                sidebar.classList.remove('collapsed');
            } else {
                // Collapse the sidebar
                sidebar.classList.add('collapsed');
            }
        }

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        }

        // Function to handle section navigation
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
                'stories': 'Success Stories'
            };
            document.querySelector('.header-title').textContent = titles[sectionId];
            
            // Update URL without reloading
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?page=" + sectionId;
            window.history.pushState({ path: newUrl }, '', newUrl);

            // Close mobile sidebar if open
            if (window.innerWidth <= 768) {
                toggleMobileSidebar();
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

        // Initial setup on page load
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 'dashboard';
            const linkElement = document.querySelector(`.nav-link[onclick*="${page}"]`);
            if (linkElement) {
                showSection(page, linkElement);
            }
        });

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

       // Add this JavaScript for the rating stars functionality
document.querySelectorAll('.rating-stars').forEach(ratingContainer => {
    const stars = ratingContainer.querySelectorAll('label');
    const inputs = ratingContainer.querySelectorAll('input[type="radio"]');
    
    stars.forEach((star, index) => {
        star.addEventListener('mouseover', () => {
            stars.forEach((s, i) => {
                if (i <= index) {
                    s.style.color = 'var(--warning)';
                } else {
                    s.style.color = 'var(--slate-300)';
                }
            });
        });
        
        star.addEventListener('click', () => {
            inputs[index].checked = true;
            updateStarDisplay(ratingContainer, index);
        });
    });
    
    ratingContainer.addEventListener('mouseleave', () => {
        const checkedInput = ratingContainer.querySelector('input[type="radio"]:checked');
        if (checkedInput) {
            const checkedIndex = Array.from(inputs).indexOf(checkedInput);
            updateStarDisplay(ratingContainer, checkedIndex);
        } else {
            stars.forEach(s => s.style.color = 'var(--slate-300)');
        }
    });
});

function updateStarDisplay(container, selectedIndex) {
    const stars = container.querySelectorAll('label');
    stars.forEach((star, index) => {
        if (index <= selectedIndex) {
            star.style.color = 'var(--warning)';
        } else {
            star.style.color = 'var(--slate-300)';
        }
    });
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

    </script>
</body>
</html>