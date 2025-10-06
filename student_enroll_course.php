<?php
// student_course_enroll.php - Complete enrollment system for self-paced courses
session_start();

if (!isset($_SESSION['student_id'])) {
    header('Location: loginstudent.html');
    exit();
}

$host = "localhost";
$username = "root";
$password = "";
$database = "nexttern_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$student_email = $_SESSION['email'] ?? '';

// Handle enrollment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course'])) {
    $course_id = intval($_POST['course_id']);
    
    try {
        $course_stmt = $conn->prepare("SELECT c.* FROM course c 
                                        WHERE c.id = ? 
                                        AND c.course_type = 'self_paced' 
                                        AND c.course_status = 'Active'");
        $course_stmt->bind_param("i", $course_id);
        $course_stmt->execute();
        $course_result = $course_stmt->get_result();
        $course = $course_result->fetch_assoc();
        $course_stmt->close();
        
        if (!$course) {
            throw new Exception("Course not found or not available for enrollment.");
        }
        
        if (empty($course['course_link'])) {
            throw new Exception("Course materials link not available yet. Please contact the course provider.");
        }
        
        $check_stmt = $conn->prepare("SELECT id FROM course_applications 
                                       WHERE student_id = ? AND course_id = ?");
        $check_stmt->bind_param("si", $student_id, $course_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception("You are already enrolled in this course.");
        }
        $check_stmt->close();
        
        if ($course['max_students'] > 0) {
            $count_stmt = $conn->prepare("SELECT COUNT(*) as enrolled 
                                          FROM course_applications 
                                          WHERE course_id = ? AND application_status = 'approved'");
            $count_stmt->bind_param("i", $course_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $enrolled_count = $count_result->fetch_assoc()['enrolled'];
            $count_stmt->close();
            
            if ($enrolled_count >= $course['max_students']) {
                throw new Exception("This course has reached its maximum capacity.");
            }
        }
        
        $conn->begin_transaction();
        
        $enroll_stmt = $conn->prepare("INSERT INTO course_applications 
                                        (student_id, student_name, student_email, course_id, 
                                         application_status, applied_at) 
                                        VALUES (?, ?, ?, ?, 'approved', NOW())");
        $enroll_stmt->bind_param("sssi", $student_id, $student_name, $student_email, $course_id);
        
        if (!$enroll_stmt->execute()) {
            throw new Exception("Failed to process enrollment. Please try again.");
        }
        $enroll_stmt->close();
       $subject = "Welcome to: " . $course['course_title'];

$message = "Congratulations! You have successfully enrolled in the course.\n\n";
$message .= "COURSE DETAILS\n";
$message .= "Course Title: " . $course['course_title'] . "\n";
$message .= "Provider: " . $course['company_name'] . "\n";
$message .= "Category: " . ($course['course_category'] ?? 'General') . "\n";

if (!empty($course['difficulty_level'])) {
    $message .= "Level: " . $course['difficulty_level'] . "\n";
}

if (!empty($course['duration'])) {
    $message .= "Duration: " . $course['duration'] . "\n";
}

$message .= "\n====================================\n";
$message .= "ACCESS YOUR COURSE MATERIALS\n";
$message .= "====================================\n\n";
$message .= "Click or copy the link below to access your course:\n\n";
$message .= $course['course_link'] . "\n\n";
$message .= "====================================\n\n";

$message .= "ABOUT THIS COURSE\n";
$message .= $course['course_description'] . "\n\n";

if (!empty($course['what_you_will_learn'])) {
    $message .= "What You'll Learn:\n";
    $message .= str_replace('|', "\n- ", "- " . $course['what_you_will_learn']) . "\n\n";
}

if (!empty($course['skills_taught'])) {
    $message .= "Skills Covered:\n";
    $message .= "- " . str_replace(',', "\n- ", $course['skills_taught']) . "\n\n";
}

if (!empty($course['prerequisites'])) {
    $message .= "Prerequisites:\n";
    $message .= str_replace('|', "\n- ", "- " . $course['prerequisites']) . "\n\n";
}

$message .= "LEARNING TIPS\n";
$message .= "- This is a self-paced course - learn at your own speed\n";
$message .= "- Set aside dedicated time for learning each day\n";
$message .= "- Take notes and practice what you learn\n";
$message .= "- Complete exercises and projects for hands-on experience\n";

if ($course['certificate_provided']) {
    $message .= "- Certificate will be provided upon course completion\n";
}

$message .= "\nNEED HELP?\n";
$message .= "If you have any questions, contact: " . $course['company_name'] . "\n\n";
$message .= "Happy Learning!\n";
$message .= "- Nexttern Team";

// Fix: Use correct table name - student_messages (plural)
$sender_type = 'company';
$receiver_type = 'student';

$msg_stmt = $conn->prepare("INSERT INTO student_messages 
                             (sender_type, receiver_type, receiver_id, subject, message, is_read, created_at) 
                             VALUES (?, ?, ?, ?, ?, 0, NOW())");
$msg_stmt->bind_param("sssss", $sender_type, $receiver_type, $student_id, $subject, $message);
        if (!$msg_stmt->execute()) {
            throw new Exception("Enrollment successful but failed to send course materials message.");
        }
        $msg_stmt->close();
        
        $conn->commit();
        
        $_SESSION['success'] = "Successfully enrolled! Check your messages for the course access link and materials.";
        header('Location: ?page=my-courses');
        exit();
        
    } catch (Exception $e) {
        if ($conn) {
            $conn->rollback();
        }
        $_SESSION['error'] = $e->getMessage();
        header('Location: ?page=courses');
        exit();
    }
}

// Get course details for display
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$course = null;
$already_enrolled = false;

if ($course_id > 0) {
    $course_stmt = $conn->prepare("SELECT c.* FROM course c 
                                    WHERE c.id = ? 
                                    AND c.course_type = 'self_paced' 
                                    AND c.course_status = 'Active'");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    $course = $course_result->fetch_assoc();
    $course_stmt->close();
    
    if ($course) {
        // Check if already enrolled
        $check_stmt = $conn->prepare("SELECT id FROM course_applications 
                                       WHERE student_id = ? AND course_id = ?");
        $check_stmt->bind_param("si", $student_id, $course_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $already_enrolled = $check_result->num_rows > 0;
        $check_stmt->close();
        
        // Get enrollment count
        $count_stmt = $conn->prepare("SELECT COUNT(*) as enrolled 
                                      FROM course_applications 
                                      WHERE course_id = ? AND application_status = 'approved'");
        $count_stmt->bind_param("i", $course_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $enrolled_count = $count_result->fetch_assoc()['enrolled'];
        $count_stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enroll in Course - Nexttern</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #035946;
            --primary-light: #0a7058;
            --success: #27ae60;
            --danger: #e74c3c;
            --info: #3498db;
            --warning: #f39c12;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --border: #e5e7eb;
            --shadow: 0 4px 12px rgba(3, 89, 70, 0.08);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: linear-gradient(135deg, #f5fbfa 0%, #e8f5f2 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .enrollment-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
        }

        .card-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .card-header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .card-body {
            padding: 2.5rem 2rem;
        }

        .course-info {
            margin-bottom: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }

        .info-item i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .course-description {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .course-description h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .course-description p {
            color: var(--text-secondary);
            line-height: 1.8;
        }

        .features-list {
            display: grid;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .feature-item i {
            color: var(--success);
            margin-top: 0.25rem;
            font-size: 1.2rem;
        }

        .feature-content h4 {
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .feature-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .enrollment-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            padding-top: 2rem;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid var(--warning);
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid var(--info);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }

        .badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .capacity-indicator {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .capacity-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .capacity-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success) 0%, var(--warning) 80%, var(--danger) 100%);
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .card-header {
                padding: 2rem 1.5rem;
            }

            .card-body {
                padding: 2rem 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .enrollment-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$course): ?>
            <div class="enrollment-card">
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Course not found or not available for enrollment.
                    </div>
                    <div style="text-align: center;">
                        <a href="?page=courses" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Courses
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="enrollment-card">
                <div class="card-header">
                    <h1><?php echo htmlspecialchars($course['course_title']); ?></h1>
                    <p>by <?php echo htmlspecialchars($course['company_name']); ?></p>
                </div>

                <div class="card-body">
                    <?php if ($already_enrolled): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-check-circle"></i>
                            You are already enrolled in this course. Check your messages for the course materials link.
                        </div>
                    <?php endif; ?>

                    <div class="info-grid">
                        <div class="info-item">
                            <i class="fas fa-layer-group"></i>
                            <div>
                                <div class="info-label">Category</div>
                                <div class="info-value"><?php echo htmlspecialchars($course['course_category'] ?? 'General'); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <i class="fas fa-signal"></i>
                            <div>
                                <div class="info-label">Difficulty</div>
                                <div class="info-value"><?php echo htmlspecialchars($course['difficulty_level'] ?? 'All Levels'); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <div class="info-label">Duration</div>
                                <div class="info-value"><?php echo htmlspecialchars($course['duration'] ?? 'Self-Paced'); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <i class="fas fa-gift"></i>
                            <div>
                                <div class="info-label">Price</div>
                                <div class="info-value" style="color: var(--success);">FREE</div>
                            </div>
                        </div>
                    </div>

                    <?php if ($course['max_students'] > 0): ?>
                        <div class="capacity-indicator">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <span style="font-weight: 600; color: var(--text-primary);">
                                    <i class="fas fa-users"></i> Enrollment Status
                                </span>
                                <span style="color: var(--text-secondary);">
                                    <?php echo $enrolled_count; ?> / <?php echo $course['max_students']; ?> students
                                </span>
                            </div>
                            <div class="capacity-bar">
                                <div class="capacity-fill" style="width: <?php echo ($enrolled_count / $course['max_students']) * 100; ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="course-description">
                        <h3><i class="fas fa-info-circle"></i> About This Course</h3>
                        <p><?php echo nl2br(htmlspecialchars($course['course_description'])); ?></p>
                    </div>

                    <div class="features-list">
                        <div class="feature-item">
                            <i class="fas fa-play-circle"></i>
                            <div class="feature-content">
                                <h4>Self-Paced Learning</h4>
                                <p>Learn at your own pace with instant access to all course materials</p>
                            </div>
                        </div>

                        <div class="feature-item">
                            <i class="fas fa-bolt"></i>
                            <div class="feature-content">
                                <h4>Instant Enrollment</h4>
                                <p>Get immediate access - no waiting for approval required</p>
                            </div>
                        </div>

                        <?php if ($course['certificate_provided']): ?>
                        <div class="feature-item">
                            <i class="fas fa-certificate"></i>
                            <div class="feature-content">
                                <h4>Certificate of Completion</h4>
                                <p>Receive a certificate upon successfully completing the course</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="feature-item">
                            <i class="fas fa-link"></i>
                            <div class="feature-content">
                                <h4>Course Materials Included</h4>
                                <p>Access link to all course content will be sent to your messages</p>
                            </div>
                        </div>
                    </div>

                    <div class="enrollment-actions">
                        <?php if (!$already_enrolled): ?>
                            <?php if ($course['max_students'] > 0 && $enrolled_count >= $course['max_students']): ?>
                                <div class="alert alert-warning" style="width: 100%; text-align: center;">
                                    <i class="fas fa-user-times"></i>
                                    This course has reached its maximum capacity
                                </div>
                            <?php else: ?>
                                <form method="POST" style="display: contents;">
                                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                    <button type="submit" name="enroll_course" class="btn btn-primary">
                                        <i class="fas fa-check-circle"></i>
                                        Enroll Now - It's Free!
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="?page=my-courses" class="btn btn-primary">
                                <i class="fas fa-book-reader"></i>
                                Go to My Courses
                            </a>
                            <a href="?page=messages" class="btn btn-secondary">
                                <i class="fas fa-envelope"></i>
                                View Course Link in Messages
                            </a>
                        <?php endif; ?>
                        
                        <a href="?page=courses" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Courses
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>