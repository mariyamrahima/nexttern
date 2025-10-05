<?php
// course_applications.php - View and manage course applications with online meeting notifications
// session_start() is already called in the main dashboard file, so we don't need it here

// Check if company is logged in
if (!isset($_SESSION['company_id'])) {
    header('Location: logincompany.html');
    exit();
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "nexttern_db";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$company_id = $_SESSION['company_id'];
$company_name = $_SESSION['company_name'] ?? 'Company';

// Get course_id from URL parameter
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Handle application status updates
$success_message = '';
$error_message = '';

// Handle single application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_application_status'])) {
    $application_id = (int)$_POST['application_id'];
    $new_status = $_POST['application_status'];
    
    // Verify that this application belongs to a course owned by the current company
    $verify_stmt = $conn->prepare("SELECT ca.id FROM course_applications ca 
                                   INNER JOIN course c ON ca.course_id = c.id 
                                   WHERE ca.id = ? AND c.company_id = ?");
    $verify_stmt->bind_param("is", $application_id, $company_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        $update_stmt = $conn->prepare("UPDATE course_applications SET application_status = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $application_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Application status updated successfully!";
        } else {
            $error_message = "Error updating application status: " . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        $error_message = "Unauthorized action.";
    }
    $verify_stmt->close();
}

// Handle bulk approve all applications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_all_applications'])) {
    $bulk_update_stmt = $conn->prepare("UPDATE course_applications ca 
                                        INNER JOIN course c ON ca.course_id = c.id 
                                        SET ca.application_status = 'approved', ca.updated_at = NOW() 
                                        WHERE ca.course_id = ? AND c.company_id = ? AND ca.application_status = 'pending'");
    $bulk_update_stmt->bind_param("is", $course_id, $company_id);
    
    if ($bulk_update_stmt->execute()) {
        $affected_rows = $bulk_update_stmt->affected_rows;
        $success_message = "Successfully approved {$affected_rows} pending applications!";
    } else {
        $error_message = "Error updating applications: " . $bulk_update_stmt->error;
    }
    $bulk_update_stmt->close();
}

// Handle sending online course notification to all applicants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_online_notification'])) {
    $meeting_datetime = $_POST['meeting_datetime'];
    $meeting_link = $_POST['meeting_link'];
    $additional_notes = $_POST['additional_notes'];
    $notify_status = $_POST['notify_status'] ?? 'all'; // all, approved, pending
    
    // Validate meeting datetime
    if (strtotime($meeting_datetime) <= time()) {
        $error_message = "Meeting date and time must be in the future.";
    } else {
        // Get applicants based on status filter
        $status_condition = "";
        $params = [$course_id, $company_id];
        $types = "is";
        
        if ($notify_status === 'approved') {
            $status_condition = "AND ca.application_status = 'approved'";
        } elseif ($notify_status === 'pending') {
            $status_condition = "AND ca.application_status = 'pending'";
        }
        
        $applicants_stmt = $conn->prepare("SELECT ca.applicant_name, ca.email, s.id as student_id 
                                          FROM course_applications ca 
                                          INNER JOIN course c ON ca.course_id = c.id 
                                          LEFT JOIN students s ON ca.email = s.email
                                          WHERE ca.course_id = ? AND c.company_id = ? {$status_condition}");
        $applicants_stmt->bind_param($types, ...$params);
        $applicants_stmt->execute();
        $applicants_result = $applicants_stmt->get_result();
        
        $notification_count = 0;
        $meeting_subject = "Online Course Meeting Details - " . htmlspecialchars($course['course_title'] ?? 'Course');
        
        // Format meeting message
        $meeting_message = "Dear Student,\n\n";
        $meeting_message .= "We are excited to inform you about the upcoming online session for the course: " . htmlspecialchars($course['course_title'] ?? 'Course') . "\n\n";
        $meeting_message .= "Meeting Details:\n";
        $meeting_message .= "Date & Time: " . date('F j, Y \a\t g:i A', strtotime($meeting_datetime)) . "\n";
        $meeting_message .= "Meeting Link: " . htmlspecialchars($meeting_link) . "\n\n";
        
        if (!empty($additional_notes)) {
            $meeting_message .= "Additional Information:\n";
            $meeting_message .= htmlspecialchars($additional_notes) . "\n\n";
        }
        
        $meeting_message .= "Please make sure to join the meeting on time. We look forward to seeing you there!\n\n";
        $meeting_message .= "Best regards,\n" . htmlspecialchars($company_name);
        
        // Send notifications to all matching applicants
        while ($applicant = $applicants_result->fetch_assoc()) {
            if ($applicant['student_id']) {
                $message_stmt = $conn->prepare("INSERT INTO student_messages (sender_type, receiver_type, receiver_id, subject, message, is_read, created_at) 
                                               VALUES ('company', 'student', ?, ?, ?, 0, NOW())");
                $message_stmt->bind_param("sss", $applicant['student_id'], $meeting_subject, $meeting_message);
                
                if ($message_stmt->execute()) {
                    $notification_count++;
                }
                $message_stmt->close();
            }
        }
        
        // Store notification in the database for tracking
        $store_notification_stmt = $conn->prepare("INSERT INTO course_notifications (course_id, company_id, notification_type, meeting_datetime, meeting_link, additional_notes, notify_status, sent_count, created_at) 
                                                  VALUES (?, ?, 'online_meeting', ?, ?, ?, ?, ?, NOW())");
        $store_notification_stmt->bind_param("isssssi", $course_id, $company_id, $meeting_datetime, $meeting_link, $additional_notes, $notify_status, $notification_count);
        $store_notification_stmt->execute();
        $store_notification_stmt->close();
        
        $applicants_stmt->close();
        
        if ($notification_count > 0) {
            $success_message = "Online meeting notification sent successfully to {$notification_count} student(s)!";
        } else {
            $error_message = "No students found to notify or error sending notifications.";
        }
    }
}
// Handle sending message to individual student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $student_email = $_POST['student_email'];
    $message_subject = $_POST['message_subject'];
    $message_content = $_POST['message_content'];
    
    // Get student ID (student_id column, not id) from email
    $student_stmt = $conn->prepare("SELECT student_id FROM students WHERE email = ?");
    $student_stmt->bind_param("s", $student_email);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    
    if ($student_result->num_rows > 0) {
        $student = $student_result->fetch_assoc();
        $student_id = $student['student_id']; // This should be something like ST4050, ST1907
        
        // Insert message into student_messages table
        $message_stmt = $conn->prepare("INSERT INTO student_messages (sender_type, receiver_type, receiver_id, subject, message, is_read, created_at) 
                                       VALUES ('company', 'student', ?, ?, ?, 0, NOW())");
        $message_stmt->bind_param("sss", $student_id, $message_subject, $message_content);
        
        if ($message_stmt->execute()) {
            $success_message = "Message sent successfully to student!";
        } else {
            $error_message = "Error sending message: " . $message_stmt->error;
        }
        $message_stmt->close();
    } else {
        $error_message = "Student account not found. The student may not be registered in the system yet.";
    }
    $student_stmt->close();
}
// Fetch course details if course_id is provided
$course = null;
if ($course_id > 0) {
    $course_stmt = $conn->prepare("SELECT * FROM course WHERE id = ? AND company_id = ?");
    $course_stmt->bind_param("is", $course_id, $company_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    $course = $course_result->fetch_assoc();
    $course_stmt->close();
    
    if (!$course) {
        $error_message = "Course not found or access denied.";
        $course_id = 0;
    }
}

// Redirect if no valid course is selected
if ($course_id <= 0 || !$course) {
    header('Location: ?page=manage-internships');
    exit();
}

// Fetch applications for the specific course
$applications = [];
$stmt = $conn->prepare("SELECT ca.*, c.course_title, c.course_category, s.id as student_id
                       FROM course_applications ca 
                       INNER JOIN course c ON ca.course_id = c.id 
                       LEFT JOIN students s ON ca.email = s.email
                       WHERE ca.course_id = ? AND c.company_id = ? 
                       ORDER BY ca.created_at DESC");
$stmt->bind_param("is", $course_id, $company_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}
$stmt->close();

// Get summary statistics
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'waitlisted' => 0
];

foreach ($applications as $app) {
    $stats['total']++;
    $stats[$app['application_status']]++;
}

// Fetch notification history for this course
$notifications = [];
$notification_stmt = $conn->prepare("SELECT * FROM course_notifications WHERE course_id = ? AND company_id = ? ORDER BY created_at DESC LIMIT 10");
$notification_stmt->bind_param("is", $course_id, $company_id);
$notification_stmt->execute();
$notification_result = $notification_stmt->get_result();

while ($row = $notification_result->fetch_assoc()) {
    $notifications[] = $row;
}
$notification_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications for <?php echo htmlspecialchars($course['course_title']); ?> - <?php echo htmlspecialchars($company_name); ?> | Nexttern</title>
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
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: #f5fbfa;
            color: #333;
            line-height: 1.6;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: var(--glass-bg);
            backdrop-filter: blur(14px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 2rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .course-details {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-light);
            border-left: 5px solid var(--primary);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .course-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .course-category {
            background: var(--accent);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .online-badge {
            background: linear-gradient(135deg, var(--info) 0%, #5dade2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 1rem;
        }

        .course-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--bg-light);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .info-content h4 {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 0.25rem;
        }

        .info-content p {
            font-weight: 600;
            color: var(--primary-dark);
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--shadow-light);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-total { color: var(--primary); }
        .stat-pending { color: var(--warning); }
        .stat-approved { color: var(--success); }
        .stat-rejected { color: var(--danger); }
        .stat-waitlisted { color: var(--info); }

        .stat-label {
            font-size: 0.8rem;
            color: var(--secondary);
            font-weight: 500;
        }

        /* Online Meeting Notification Section */
        .online-notification-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid var(--info);
            position: relative;
            overflow: hidden;
        }

        .online-notification-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--info) 0%, var(--accent) 100%);
        }

        .notification-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .notification-icon {
            width: 60px;
            height: 60px;
            background: var(--info);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .notification-title {
            flex: 1;
        }

        .notification-title h3 {
            font-size: 1.5rem;
            color: var(--primary-dark);
            margin-bottom: 0.25rem;
        }

        .notification-title p {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .notification-form {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow-light);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
            margin-bottom: 2rem;
        }

        .table-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .table-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-info:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .applications-table {
            width: 100%;
            border-collapse: collapse;
        }

        .applications-table th,
        .applications-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .applications-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .applications-table tr:hover {
            background: #f8f9fa;
        }

        .applicant-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .applicant-name {
            font-weight: 600;
            color: var(--primary-dark);
        }

        .applicant-email {
            font-size: 0.85rem;
            color: var(--secondary);
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-waitlisted { background: #d1ecf1; color: #0c5460; }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .quick-status {
            display: flex;
            gap: 0.25rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            border-color: var(--success);
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border-color: var(--danger);
            color: #721c24;
        }

      .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 3% auto;
    padding: 2.5rem;
    border-radius: 15px;
    width: 90%;
    max-width: 700px;
    box-shadow: var(--shadow-medium);
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.modal-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--primary);
}

        .close {
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            color: var(--secondary);
        }

        .close:hover {
            color: var(--danger);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--secondary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(3, 89, 70, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .notification-history {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 2rem;
        }

        .history-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .history-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--info);
        }

        .history-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .history-date {
            font-size: 0.8rem;
            color: var(--secondary);
        }

        .history-count {
            background: var(--info);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .history-details {
            font-size: 0.9rem;
            color: var(--secondary);
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 1rem;
            }
            
            .course-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .course-info-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .applications-table {
                font-size: 0.8rem;
            }
            
            .applications-table th,
            .applications-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }

            .online-badge {
                margin-left: 0;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div class="breadcrumb">
                <a href="?page= manage-courses"><i class="fas fa-arrow-left"></i> Back to Courses</a>
                <span>/</span>
                <span>Applications</span>
            </div>
            
            <h1 class="page-title">
                <i class="fas fa-user-graduate"></i>
                Course Applications
            </h1>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="course-details">
            <div class="course-header">
                <div>
                    <h2 class="course-title">
                        <?php echo htmlspecialchars($course['course_title']); ?>
                        <?php if (strtolower($course['mode']) === 'online'): ?>
                            <span class="online-badge">
                                <i class="fas fa-video"></i>
                                Online Course
                            </span>
                        <?php endif; ?>
                    </h2>
                    <span class="course-category"><?php echo htmlspecialchars($course['course_category']); ?></span>
                </div>
            </div>
            
            <div class="course-info-grid">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="info-content">
                        <h4>Start Date</h4>
                        <p><?php echo date('M d, Y', strtotime($course['start_date'])); ?></p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <h4>Duration</h4>
                        <p><?php echo htmlspecialchars($course['duration']); ?></p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div class="info-content">
                        <h4>Mode</h4>
                        <p><?php echo ucfirst(htmlspecialchars($course['mode'])); ?></p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="info-content">
                        <h4>Total Applicants</h4>
                        <p><?php echo $stats['total']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (strtolower($course['mode']) === 'online'): ?>
        <!-- Online Meeting Notification Section -->
        <div class="online-notification-section">
            <div class="notification-header">
                <div class="notification-icon">
                    <i class="fas fa-video"></i>
                </div>
                <div class="notification-title">
                    <h3>Send Online Meeting Details</h3>
                    <p>Notify students about upcoming online sessions with meeting links and details</p>
                </div>
            </div>
            
            <form method="POST" class="notification-form">
                <input type="hidden" name="send_online_notification" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Course Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($course['course_title']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Meeting Date & Time <span style="color: var(--danger);">*</span></label>
                        <input type="datetime-local" name="meeting_datetime" class="form-control" required 
                               min="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Meeting Link (Google Meet/Zoom/Teams) <span style="color: var(--danger);">*</span></label>
                        <input type="url" name="meeting_link" class="form-control" 
                               placeholder="https://meet.google.com/xxx-xxxx-xxx" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notify Students</label>
                        <select name="notify_status" class="form-select">
                            <option value="all">All Applicants (<?php echo $stats['total']; ?>)</option>
                            <option value="approved" <?php echo $stats['approved'] == 0 ? 'disabled' : ''; ?>>
                                Approved Only (<?php echo $stats['approved']; ?>)
                            </option>
                            <option value="pending" <?php echo $stats['pending'] == 0 ? 'disabled' : ''; ?>>
                                Pending Only (<?php echo $stats['pending']; ?>)
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Additional Notes (Optional)</label>
                    <textarea name="additional_notes" class="form-control" rows="4" 
                              placeholder="Any additional information for students (requirements, preparation, etc.)"></textarea>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-info" style="padding: 1rem 2rem; font-size: 1rem;">
                        <i class="fas fa-paper-plane"></i> Send Meeting Notification
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($notifications) && strtolower($course['mode']) === 'online'): ?>
        <!-- Notification History -->
        <div class="notification-history">
            <div class="history-header">
                <i class="fas fa-history" style="color: var(--info); font-size: 1.2rem;"></i>
                <h3 style="color: var(--primary-dark);">Recent Notifications</h3>
            </div>
            
            <?php foreach ($notifications as $notification): ?>
                <div class="history-item">
                    <div class="history-meta">
                        <span class="history-date">
                            <i class="fas fa-clock"></i>
                            Sent on <?php echo date('M d, Y \a\t g:i A', strtotime($notification['created_at'])); ?>
                        </span>
                        <span class="history-count"><?php echo $notification['sent_count']; ?> recipients</span>
                    </div>
                    <div class="history-details">
                        <strong>Meeting:</strong> <?php echo date('M d, Y \a\t g:i A', strtotime($notification['meeting_datetime'])); ?><br>
                        <strong>Link:</strong> <a href="<?php echo htmlspecialchars($notification['meeting_link']); ?>" target="_blank" style="color: var(--info);"><?php echo htmlspecialchars($notification['meeting_link']); ?></a><br>
                        <strong>Notified:</strong> <?php echo ucfirst($notification['notify_status']); ?> students
                        <?php if ($notification['additional_notes']): ?>
                            <br><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($notification['additional_notes'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-number stat-total"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-pending"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-approved"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-rejected"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-waitlisted"><?php echo $stats['waitlisted']; ?></div>
                <div class="stat-label">Waitlisted</div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Applications List</h3>
                <div class="table-actions">
                    <?php if ($stats['pending'] > 0): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve all pending applications?')">
                            <input type="hidden" name="approve_all_applications" value="1">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check-double"></i> Approve All Pending (<?php echo $stats['pending']; ?>)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Applications Yet</h3>
                    <p>This course hasn't received any applications yet.</p>
                </div>
            <?php else: ?>
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Learning Objective</th>
                            <th>Applied Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $application): ?>
                            <tr>
                                <td>
                                    <div class="applicant-info">
                                        <span class="applicant-name"><?php echo htmlspecialchars($application['applicant_name']); ?></span>
                                        <span class="applicant-email"><?php echo htmlspecialchars($application['email']); ?></span>
                                        <?php if ($application['phone']): ?>
                                            <span class="applicant-email"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($application['phone']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $objectives = [
                                        'job_preparation' => 'Job Preparation',
                                        'interview_skills' => 'Interview Skills',
                                        'certification' => 'Certification',
                                        'skill_enhancement' => 'Skill Enhancement',
                                        'career_switch' => 'Career Switch',
                                        'academic_project' => 'Academic Project',
                                        'personal_interest' => 'Personal Interest',
                                        'startup_preparation' => 'Startup Preparation'
                                    ];
                                    echo $objectives[$application['learning_objective']] ?? ucfirst(str_replace('_', ' ', $application['learning_objective']));
                                    ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($application['created_at'])); ?>
                                    <br>
                                    <small><?php echo date('h:i A', strtotime($application['created_at'])); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($application['application_status']); ?>">
                                        <?php echo ucfirst($application['application_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <div class="quick-status">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                                <input type="hidden" name="application_status" value="approved">
                                                <input type="hidden" name="update_application_status" value="1">
                                                <button type="submit" class="btn btn-success btn-sm" title="Approve" 
                                                        <?php echo $application['application_status'] === 'approved' ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                                <input type="hidden" name="application_status" value="rejected">
                                                <input type="hidden" name="update_application_status" value="1">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Reject"
                                                        <?php echo $application['application_status'] === 'rejected' ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <button type="button" class="btn btn-primary btn-sm" 
                                                onclick="openMessageModal('<?php echo htmlspecialchars($application['applicant_name']); ?>', '<?php echo htmlspecialchars($application['email']); ?>', '<?php echo $application['student_id']; ?>')">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                        
                                        <?php if ($application['cover_letter']): ?>
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                    onclick="openCoverLetterModal('<?php echo htmlspecialchars($application['applicant_name']); ?>', <?php echo htmlspecialchars(json_encode($application['cover_letter'])); ?>)">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Send Message</h3>
                <span class="close" onclick="closeModal('messageModal')">&times;</span>
            </div>
            <form id="messageForm" method="POST">
                <input type="hidden" name="send_message" value="1">
                <input type="hidden" id="studentId" name="student_id">
                <div class="form-group">
                    <label class="form-label">To:</label>
                    <input type="text" id="recipientName" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Email:</label>
                    <input type="email" id="recipientEmail" name="student_email" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Subject:</label>
                    <input type="text" id="messageSubject" name="message_subject" class="form-control" placeholder="Enter subject" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Message:</label>
                    <textarea id="messageContent" name="message_content" class="form-control" placeholder="Enter your message" required></textarea>
                </div>
                <div style="text-align: right; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeModal('messageModal')" style="background: #6c757d; color: white;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cover Letter Modal -->
    <div id="coverLetterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cover Letter</h3>
                <span class="close" onclick="closeModal('coverLetterModal')">&times;</span>
            </div>
            <div id="coverLetterContent" style="line-height: 1.6; color: var(--secondary);"></div>
        </div>
    </div>

    <script>
        function openMessageModal(name, email, studentId) {
            document.getElementById('recipientName').value = name;
            document.getElementById('recipientEmail').value = email;
            document.getElementById('studentId').value = studentId || '';
            document.getElementById('messageSubject').value = 'Regarding your application for <?php echo htmlspecialchars($course['course_title']); ?>';
            document.getElementById('messageContent').value = '';
            document.getElementById('messageModal').style.display = 'block';
        }

        function openCoverLetterModal(name, coverLetter) {
            document.querySelector('#coverLetterModal .modal-title').textContent = name + "'s Cover Letter";
            document.getElementById('coverLetterContent').innerHTML = coverLetter.replace(/\n/g, '<br>');
            document.getElementById('coverLetterModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const messageModal = document.getElementById('messageModal');
            const coverLetterModal = document.getElementById('coverLetterModal');
            
            if (event.target === messageModal) {
                closeModal('messageModal');
            }
            if (event.target === coverLetterModal) {
                closeModal('coverLetterModal');
            }
        }

        // Add loading animation for form submissions
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const button = form.querySelector('button[type="submit"]');
                    if (button && !button.disabled) {
                        button.disabled = true;
                        const originalText = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                        
                        // For message form, close modal after short delay
                        if (form.id === 'messageForm') {
                            setTimeout(() => {
                                closeModal('messageModal');
                            }, 500);
                        }
                        
                        // Re-enable after 3 seconds as fallback
                        setTimeout(() => {
                            button.disabled = false;
                            button.innerHTML = originalText;
                        }, 3000);
                    }
                });
            });

            // Add animation to table rows
            const rows = document.querySelectorAll('.applications-table tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Set minimum datetime to current time + 1 hour
            const datetimeInput = document.querySelector('input[name="meeting_datetime"]');
            if (datetimeInput) {
                const now = new Date();
                now.setMinutes(now.getMinutes() + 60); // Add 1 hour
                const minDateTime = now.toISOString().slice(0, 16);
                datetimeInput.setAttribute('min', minDateTime);
            }

            // Form validation for online notification
            const notificationForm = document.querySelector('form[name="send_online_notification"]');
            if (notificationForm) {
                notificationForm.addEventListener('submit', function(e) {
                    const meetingDateTime = document.querySelector('input[name="meeting_datetime"]').value;
                    const meetingLink = document.querySelector('input[name="meeting_link"]').value;
                    
                    if (!meetingDateTime || !meetingLink) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                        return false;
                    }
                    
                    const selectedTime = new Date(meetingDateTime);
                    const now = new Date();
                    
                    if (selectedTime <= now) {
                        e.preventDefault();
                        alert('Meeting date and time must be in the future.');
                        return false;
                    }
                    
                    // Confirm before sending
                    const notifyStatus = document.querySelector('select[name="notify_status"]').value;
                    const statusText = notifyStatus === 'all' ? 'all applicants' : 
                                     notifyStatus === 'approved' ? 'approved applicants' : 
                                     'pending applicants';
                    
                    if (!confirm(`Are you sure you want to send meeting details to ${statusText}? This action cannot be undone.`)) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });

        // Confirm bulk actions
        function confirmBulkAction(action, count) {
            return confirm(`Are you sure you want to ${action} all ${count} pending applications? This action cannot be undone.`);
        }

        // Auto-resize textarea
        document.addEventListener('input', function(e) {
            if (e.target.tagName === 'TEXTAREA') {
                e.target.style.height = 'auto';
                e.target.style.height = e.target.scrollHeight + 'px';
            }
        });
    </script>
</body>
</html>