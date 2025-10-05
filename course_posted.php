<?php
// course_posted.php - Manage Company Courses
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

// Handle course status updates and edits
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $course_id = $_POST['course_id'];
        $new_status = $_POST['course_status'];
        
        $update_stmt = $conn->prepare("UPDATE course SET course_status = ?, updated_at = NOW() WHERE id = ? AND company_id = ?");
        $update_stmt->bind_param("sis", $new_status, $course_id, $company_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Course status updated successfully!";
        } else {
            $error_message = "Error updating course status: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
    
    if (isset($_POST['delete_course'])) {
        $course_id = $_POST['course_id'];
        
        $delete_stmt = $conn->prepare("DELETE FROM course WHERE id = ? AND company_id = ?");
        $delete_stmt->bind_param("is", $course_id, $company_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Course deleted successfully!";
        } else {
            $error_message = "Error deleting course: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    }
    
    // Handle course editing
    if (isset($_POST['edit_course'])) {
        $course_id = $_POST['course_id'];
        $course_title = $_POST['course_title'];
        $course_category = $_POST['course_category'];
        $duration = $_POST['duration'];
        $difficulty_level = $_POST['difficulty_level'];
        $mode = $_POST['mode'];
        $course_description = $_POST['course_description'];
        $max_students = $_POST['max_students'];
        $course_price_type = $_POST['course_price_type'];
        $price_amount = $_POST['price_amount'];
        $start_date = $_POST['start_date'];
        $enrollment_deadline = $_POST['enrollment_deadline'];
        
        $update_stmt = $conn->prepare("UPDATE course SET 
            course_title = ?, course_category = ?, duration = ?, difficulty_level = ?, 
            mode = ?, course_description = ?, max_students = ?, course_price_type = ?, 
            price_amount = ?, start_date = ?, enrollment_deadline = ?, updated_at = NOW() 
            WHERE id = ? AND company_id = ?");
        
        $update_stmt->bind_param("ssssssissssis", 
            $course_title, $course_category, $duration, $difficulty_level,
            $mode, $course_description, $max_students, $course_price_type,
            $price_amount, $start_date, $enrollment_deadline, $course_id, $company_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Course updated successfully!";
        } else {
            $error_message = "Error updating course: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

// Fetch company's courses
$courses = [];
$stmt = $conn->prepare("SELECT * FROM course WHERE company_id = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $company_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

// Get total application count for all company courses
$total_apps_stmt = $conn->prepare("SELECT COUNT(*) as total FROM course_applications ca 
                                 INNER JOIN course c ON ca.course_id = c.id 
                                 WHERE c.company_id = ?");
$total_apps_stmt->bind_param("s", $company_id);
$total_apps_stmt->execute();
$total_apps_result = $total_apps_stmt->get_result();
$total_apps = $total_apps_result->fetch_assoc();
$total_apps_stmt->close();

$conn->close();

// Count courses by status
$active_count = 0;
$inactive_count = 0;
$draft_count = 0;

foreach ($courses as $course) {
    switch ($course['course_status']) {
        case 'Active': $active_count++; break;
        case 'Inactive': $inactive_count++; break;
        case 'Draft': $draft_count++; break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - <?php echo htmlspecialchars($company_name); ?> | Nexttern</title>
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
    max-width: 1200px;
    margin: 0 auto;
    padding: 0;
}
.page-header {
    background: var(--glass-bg);
    backdrop-filter: blur(14px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: var(--shadow-light);
    margin-bottom: 2rem;
    margin-left: 0;
    margin-right: 0;
    text-align: center;
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
    background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: 20px 20px 0 0;
}
        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--secondary);
            opacity: 0.9;
        }

  .stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    margin-left: 0;
    margin-right: 0;
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
            margin-bottom: 0.5rem;
        }

        .stat-active { color: var(--success); }
        .stat-inactive { color: var(--warning); }
        .stat-draft { color: var(--info); }
        .stat-total { color: var(--primary); }
        .stat-applications { color: #9b59b6; }

        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary);
            font-weight: 500;
        }

        .actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    margin-left: 0;
    margin-right: 0;
    flex-wrap: wrap;
    gap: 1rem;
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
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

        .filter-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            font-size: 0.9rem;
        }

      .courses-vertical {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    margin-left: 0;
    margin-right: 0;
}

        .course-card-vertical {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
        }

        .course-card-vertical:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .course-header-vertical {
            padding: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .course-title-vertical {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.75rem;
            line-height: 1.3;
        }

        .course-meta-vertical {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .meta-tag {
            background: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--secondary);
            border: 1px solid #e0e0e0;
        }

        .course-status {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-inactive { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-draft { background: #d1ecf1; color: #0c5460; border: 1px solid #b8daff; }

        .course-body-vertical {
            padding: 1.5rem;
            flex: 1;
        }

        .course-description {
            color: var(--secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .course-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            color: var(--secondary);
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .detail-item i {
            color: var(--primary);
            width: 16px;
            font-size: 0.9rem;
        }

        .course-footer-vertical {
            padding: 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }

        .course-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-start;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .notification-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            margin-left: 0.5rem;
            font-weight: 600;
        }

       .empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--secondary);
    margin-left: 0;
    margin-right: 0;
}

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--secondary);
        }
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    margin-left: 0;
    margin-right: 0;
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

        /* Modal Styles */
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
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-medium);
        }

        .modal-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
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
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(3, 89, 70, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .course-details-grid {
                grid-template-columns: 1fr;
            }
            
            .course-actions {
                justify-content: center;
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-controls {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-tasks"></i>
                Manage Courses
            </h1>
            <p class="page-subtitle">View, edit, and manage your published courses and programs</p>
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

        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-number stat-total"><?php echo count($courses); ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-active"><?php echo $active_count; ?></div>
                <div class="stat-label">Active Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-inactive"><?php echo $inactive_count; ?></div>
                <div class="stat-label">Inactive Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-draft"><?php echo $draft_count; ?></div>
                <div class="stat-label">Draft Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-applications"><?php echo $total_apps['total']; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
        </div>

        <div class="actions-bar">
            <a href="?page=post-internship" class="btn btn-primary">
                <i class="fas fa-plus"></i> Post New Course
            </a>
            
            <div class="filter-controls">
                <select class="filter-select" onchange="filterCourses(this.value)">
                    <option value="all">All Courses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                    <option value="draft">Drafts Only</option>
                </select>
            </div>
        </div>

        <?php if (empty($courses)): ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h3>No Courses Posted Yet</h3>
                <p>Start by creating your first course to attract talented students.</p>
                <a href="?page=post-internship" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Create Your First Course
                </a>
            </div>
        <?php else: ?>
            <div class="courses-vertical" id="courses-container">
                <?php 
                // Re-establish connection for application counts
                $conn = new mysqli($host, $username, $password, $database);
                foreach ($courses as $course): 
                    // Get application count for this course
                    $app_count_stmt = $conn->prepare("SELECT COUNT(*) as total, 
                                                    SUM(CASE WHEN application_status = 'pending' THEN 1 ELSE 0 END) as pending 
                                                    FROM course_applications WHERE course_id = ?");
                    $app_count_stmt->bind_param("i", $course['id']);
                    $app_count_stmt->execute();
                    $app_count_result = $app_count_stmt->get_result();
                    $app_counts = $app_count_result->fetch_assoc();
                    $app_count_stmt->close();
                ?>
                    <div class="course-card-vertical" data-status="<?php echo strtolower($course['course_status']); ?>">
                        <div class="course-header-vertical">
                            <h3 class="course-title-vertical"><?php echo htmlspecialchars($course['course_title']); ?></h3>
                            <div class="course-meta-vertical">
                                <span class="meta-tag">
                                    <i class="fas fa-layer-group"></i>
                                    <?php echo htmlspecialchars($course['course_category']); ?>
                                </span>
                                <span class="meta-tag">
                                    <i class="fas fa-signal"></i>
                                    <?php echo htmlspecialchars($course['difficulty_level']); ?>
                                </span>
                                <span class="meta-tag">
                                    <i class="fas fa-clock"></i>
                                    <?php echo htmlspecialchars($course['duration']); ?>
                                </span>
                                <span class="course-status status-<?php echo strtolower($course['course_status']); ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo $course['course_status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="course-body-vertical">
                            <p class="course-description">
                                <?php echo htmlspecialchars($course['course_description'] ?? 'No description available'); ?>
                            </p>
                            
                            <div class="course-details-grid">
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <div>
                                        <strong>Start Date:</strong><br>
                                        <?php echo $course['start_date'] ? date('M d, Y', strtotime($course['start_date'])) : 'Not set'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-user-graduate"></i>
                                    <div>
                                        <strong>Applications:</strong><br>
                                        <?php 
                                        echo $app_counts['total'] . ' total';
                                        if ($app_counts['pending'] > 0) {
                                            echo ' (' . $app_counts['pending'] . ' pending)';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <div>
                                        <strong>Max Students:</strong><br>
                                        <?php echo $course['max_students'] ?? 'Unlimited'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div>
                                        <strong>Mode:</strong><br>
                                        <?php echo htmlspecialchars($course['mode'] ?? 'Not specified'); ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-dollar-sign"></i>
                                    <div>
                                        <strong>Price:</strong><br>
                                        <?php 
                                        if ($course['course_price_type'] === 'free') {
                                            echo 'Free';
                                        } else {
                                            echo '$' . number_format($course['price_amount'] ?? 0, 2);
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php if ($course['enrollment_deadline']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-hourglass-end"></i>
                                    <div>
                                        <strong>Enrollment Deadline:</strong><br>
                                        <?php echo date('M d, Y', strtotime($course['enrollment_deadline'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($course['students_trained']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-user-graduate"></i>
                                    <div>
                                        <strong>Students Trained:</strong><br>
                                        <?php echo $course['students_trained']; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="course-footer-vertical">
                            <div class="course-actions">
                               <a href="?page=course-applications&course_id=<?php echo $course['id']; ?>" class="action-btn btn-info">
            <i class="fas fa-users"></i> View Applications
            <?php if ($app_counts['pending'] > 0): ?>
                <span class="notification-badge"><?php echo $app_counts['pending']; ?></span>
                                    <?php endif; ?>
                                </a>
                                
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($course)); ?>)" class="action-btn btn-info">
                                    <i class="fas fa-edit"></i> Edit Course
                                </button>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <select name="course_status" onchange="this.form.submit()" class="action-btn" style="border: 1px solid #ddd; background: white; color: #333; cursor: pointer;">
                                        <option value="Draft" <?php echo $course['course_status'] === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="Active" <?php echo $course['course_status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo $course['course_status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                                
                                <button onclick="showDeleteModal(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_title']); ?>')" class="action-btn btn-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; 
                $conn->close();
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-edit"></i>
                    Edit Course
                </h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="course_id" id="editCourseId">
                <input type="hidden" name="edit_course" value="1">
                
                <div class="form-group">
                    <label class="form-label" for="edit_course_title">Course Title</label>
                    <input type="text" class="form-control" id="edit_course_title" name="course_title" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_course_category">Category</label>
                        <input type="text" class="form-control" id="edit_course_category" name="course_category" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_difficulty_level">Difficulty Level</label>
                        <select class="form-control" id="edit_difficulty_level" name="difficulty_level" required>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_duration">Duration</label>
                        <input type="text" class="form-control" id="edit_duration" name="duration" placeholder="e.g., 12 weeks" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_mode">Mode</label>
                        <select class="form-control" id="edit_mode" name="mode" required>
                            <option value="Online">Online</option>
                            <option value="Offline">Offline</option>
                            <option value="Hybrid">Hybrid</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_max_students">Max Students</label>
                        <input type="number" class="form-control" id="edit_max_students" name="max_students" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_course_price_type">Price Type</label>
                        <select class="form-control" id="edit_course_price_type" name="course_price_type" required>
                            <option value="free">Free</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" id="priceAmountGroup" style="display: none;">
                    <label class="form-label" for="edit_price_amount">Price Amount ($)</label>
                    <input type="number" class="form-control" id="edit_price_amount" name="price_amount" step="0.01" min="0">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_start_date">Start Date</label>
                        <input type="date" class="form-control" id="edit_start_date" name="start_date">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_enrollment_deadline">Enrollment Deadline</label>
                        <input type="date" class="form-control" id="edit_enrollment_deadline" name="enrollment_deadline">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_course_description">Course Description</label>
                    <textarea class="form-control" id="edit_course_description" name="course_description" rows="4" required></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Confirm Deletion
                </h3>
                <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
            </div>
            <p>Are you sure you want to delete the course "<strong id="courseTitle"></strong>"? This action cannot be undone.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="course_id" id="deleteCourseId">
                <input type="hidden" name="delete_course" value="1">
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Course</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function filterCourses(status) {
            const courses = document.querySelectorAll('.course-card-vertical');
            courses.forEach(course => {
                if (status === 'all' || course.dataset.status === status) {
                    course.style.display = 'flex';
                } else {
                    course.style.display = 'none';
                }
            });
        }

        function openEditModal(course) {
            document.getElementById('editCourseId').value = course.id;
            document.getElementById('edit_course_title').value = course.course_title || '';
            document.getElementById('edit_course_category').value = course.course_category || '';
            document.getElementById('edit_difficulty_level').value = course.difficulty_level || 'Beginner';
            document.getElementById('edit_duration').value = course.duration || '';
            document.getElementById('edit_mode').value = course.mode || 'Online';
            document.getElementById('edit_max_students').value = course.max_students || '';
            document.getElementById('edit_course_price_type').value = course.course_price_type || 'free';
            document.getElementById('edit_price_amount').value = course.price_amount || '';
            document.getElementById('edit_start_date').value = course.start_date || '';
            document.getElementById('edit_enrollment_deadline').value = course.enrollment_deadline || '';
            document.getElementById('edit_course_description').value = course.course_description || '';
            
            // Show/hide price amount based on price type
            togglePriceAmountField();
            
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function showDeleteModal(courseId, courseTitle) {
            document.getElementById('courseTitle').textContent = courseTitle;
            document.getElementById('deleteCourseId').value = courseId;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Toggle price amount field based on price type
        function togglePriceAmountField() {
            const priceType = document.getElementById('edit_course_price_type').value;
            const priceAmountGroup = document.getElementById('priceAmountGroup');
            
            if (priceType === 'paid') {
                priceAmountGroup.style.display = 'block';
            } else {
                priceAmountGroup.style.display = 'none';
            }
        }

        // Event listeners
        document.getElementById('edit_course_price_type').addEventListener('change', togglePriceAmountField);

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.course-card-vertical');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>