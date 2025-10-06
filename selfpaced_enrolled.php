<?php
// selfpaced_enrolled.php - View Enrolled Students for Self-Paced Courses

// Check if company is logged in
if (!isset($_SESSION['company_id'])) {
    header('Location: logincompany.html');
    exit();
}

// Get course ID from URL
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id <= 0) {
    echo '<div class="alert alert-error">Invalid course ID.</div>';
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

// Verify course belongs to this company and is self-paced
$course_check = $conn->prepare("SELECT course_title, course_type FROM course WHERE id = ? AND company_id = ?");
$course_check->bind_param("is", $course_id, $company_id);
$course_check->execute();
$course_result = $course_check->get_result();

if ($course_result->num_rows === 0) {
    echo '<div class="alert alert-error">Course not found or you do not have permission to view it.</div>';
    $course_check->close();
    $conn->close();
    exit();
}

$course = $course_result->fetch_assoc();
$course_check->close();

if ($course['course_type'] !== 'self_paced') {
    echo '<div class="alert alert-error">This page is only for self-paced courses. Please use the Applications page for live courses.</div>';
    $conn->close();
    exit();
}

// Fetch enrolled students
$enrollments = [];
$stmt = $conn->prepare("SELECT 
    ca.id as enrollment_id,
    ca.student_id,
    ca.created_at as enrolled_at,
    ca.application_status,
    ca.applicant_name as student_name,
    ca.email,
    ca.phone as contact,
    ca.learning_objective,
    ca.cover_letter
FROM course_applications ca
WHERE ca.course_id = ?
ORDER BY ca.created_at DESC");

$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $enrollments[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrolled Students - <?php echo htmlspecialchars($course['course_title']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #035946;
            --primary-light: #0a7058;
            --success: #27ae60;
            --danger: #e74c3c;
            --info: #3498db;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --border: #e5e7eb;
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
            padding: 0;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--success) 100%);
            border-radius: 20px 20px 0 0;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .course-badge {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .course-info {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(39, 174, 96, 0.1);
            border-radius: 10px;
            border-left: 4px solid var(--success);
        }

        .course-info strong {
            color: var(--primary);
        }

        .course-link-display {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .course-link-display a {
            color: var(--info);
            text-decoration: none;
            font-weight: 600;
            word-break: break-all;
        }

        .course-link-display a:hover {
            text-decoration: underline;
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .students-table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
        }

        .students-table thead {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .students-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .students-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .students-table tbody tr:hover {
            background: rgba(3, 89, 70, 0.05);
        }

        .students-table tbody tr:last-child td {
            border-bottom: none;
        }

        .student-name {
            font-weight: 600;
            color: var(--primary);
        }

        .student-email {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-enrolled {
            background: #d4edda;
            color: #155724;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-error {
            background: #f8d7da;
            border-color: var(--danger);
            color: #721c24;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 1rem;
            }

            .students-table {
                display: block;
                overflow-x: auto;
            }

            .header-top {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div class="header-top">
                <h1 class="page-title">
                    <i class="fas fa-users-check"></i>
                    Enrolled Students
                </h1>
                <span class="course-badge">
                    <i class="fas fa-play-circle"></i>
                    Self-Paced Course
                </span>
            </div>
            
            <div class="course-info">
                <strong>Course:</strong> <?php echo htmlspecialchars($course['course_title']); ?>
                <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                    <i class="fas fa-info-circle"></i> Students enrolled in this self-paced course can access the course materials at any time.
                </p>
            </div>
        </div>

        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($enrollments); ?></div>
                <div class="stat-label">Total Enrolled Students</div>
            </div>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <a href="?page=manage-courses" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Courses
            </a>
        </div>

        <?php if (empty($enrollments)): ?>
            <div class="students-table-container">
                <div class="empty-state">
                    <i class="fas fa-user-graduate"></i>
                    <h3>No Students Enrolled Yet</h3>
                    <p>Students can enroll instantly in this self-paced course. They will appear here once they enroll.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="students-table-container">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Contact</th>
                            <th>Learning Objective</th>
                            <th>Enrolled Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $index => $enrollment): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="student-name"><?php echo htmlspecialchars($enrollment['student_name']); ?></div>
                                    <div class="student-email">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($enrollment['email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($enrollment['contact'])): ?>
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($enrollment['contact']); ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $objective = str_replace('_', ' ', $enrollment['learning_objective']);
                                    echo ucwords(htmlspecialchars($objective)); 
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?></td>
                                <td>
                                    <span class="badge badge-enrolled">
                                        <i class="fas fa-check-circle"></i> Enrolled
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>