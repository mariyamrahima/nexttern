<?php
// Ensure this file is included within the dashboard context
if (!isset($_SESSION['company_id'])) {
    echo '<div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            Session expired. Please <a href="logincompany.html">login again</a>.
          </div>';
    return;
}

// Handle form submission
$success_message = '';
$error_message = '';
$form_submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_submitted = true;
    $conn = getDatabaseConnection();
    
    if ($conn) {
        try {
            // Sanitize and validate input data
            $course_title = trim($_POST['course_title'] ?? '');
            $course_category = trim($_POST['course_category'] ?? '');
            $duration = trim($_POST['duration'] ?? '');
            $difficulty_level = $_POST['difficulty_level'] ?? '';
            $mode = $_POST['mode'] ?? '';
            $course_description = trim($_POST['course_description'] ?? '');
            $what_you_will_learn = trim($_POST['what_you_will_learn'] ?? '');
            $program_structure = trim($_POST['program_structure'] ?? '');
            $skills_taught = trim($_POST['skills_taught'] ?? '');
            $prerequisites = trim($_POST['prerequisites'] ?? '');
            $students_trained = (int)($_POST['students_trained'] ?? 0);
            $job_placement_rate = (float)($_POST['job_placement_rate'] ?? 0.00);
            $student_rating = (float)($_POST['student_rating'] ?? 0.00);
            $enrollment_deadline = $_POST['enrollment_deadline'] ?? null;
            $start_date = $_POST['start_date'] ?? null;
            $certificate_provided = isset($_POST['certificate_provided']) ? 1 : 0;
            $job_placement_support = isset($_POST['job_placement_support']) ? 1 : 0;
            $course_format = trim($_POST['course_format'] ?? '');
            $course_status = 'Active'; // Default status
            $featured = isset($_POST['featured']) ? 1 : 0;
            $max_students = (int)($_POST['max_students'] ?? 0);
            $course_price_type = $_POST['course_price_type'] ?? '';
            $price_amount = (float)($_POST['price_amount'] ?? 0.00);
            
            // Basic validation
            if (empty($course_title)) {
                throw new Exception('Course title is required');
            }
            if (empty($course_description)) {
                throw new Exception('Course description is required');
            }
            if (empty($enrollment_deadline)) {
                throw new Exception('Enrollment deadline is required');
            }
            if (empty($start_date)) {
                throw new Exception('Start date is required');
            }
            
            // Validate dates
            if (strtotime($enrollment_deadline) <= time()) {
                throw new Exception('Enrollment deadline must be in the future');
            }
            if (strtotime($start_date) <= strtotime($enrollment_deadline)) {
                throw new Exception('Start date must be after enrollment deadline');
            }
            
            // Debug: Log the values being inserted
            error_log("=== COURSE INSERTION DEBUG ===");
            error_log("Company ID: " . $_SESSION['company_id']);
            error_log("Company Name: " . $_SESSION['company_name']);
            error_log("Course title: " . $course_title);
            error_log("Course description length: " . strlen($course_description));
            error_log("Enrollment deadline: " . $enrollment_deadline);
            error_log("Start date: " . $start_date);
            
            // Insert into database using session data - CORRECTED SQL
            $sql = "INSERT INTO course (
                company_id, company_name, course_title, course_category, duration, 
                difficulty_level, mode, course_description, what_you_will_learn, 
                program_structure, skills_taught, prerequisites, students_trained, 
                job_placement_rate, student_rating, enrollment_deadline, start_date, 
                certificate_provided, job_placement_support, course_format, course_status, 
                featured, max_students, course_price_type, price_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            error_log("SQL Query: " . $sql);
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database preparation failed: ' . $conn->error);
            }
            
            // CORRECTED BINDING STRING - 25 parameters with correct types
            $bind_result = $stmt->bind_param(
                "ssssssssssssissssiissiisd", // 25 characters for 25 parameters
                $_SESSION['company_id'],      // s - company_id (varchar)
                $_SESSION['company_name'],    // s - company_name (varchar)
                $course_title,               // s - course_title (varchar)
                $course_category,            // s - course_category (varchar)
                $duration,                   // s - duration (varchar)
                $difficulty_level,           // s - difficulty_level (varchar)
                $mode,                       // s - mode (varchar)
                $course_description,         // s - course_description (text)
                $what_you_will_learn,        // s - what_you_will_learn (text)
                $program_structure,          // s - program_structure (text)
                $skills_taught,              // s - skills_taught (text)
                $prerequisites,              // s - prerequisites (text)
                $students_trained,           // i - students_trained (int)
                $job_placement_rate,         // d - job_placement_rate (decimal)
                $student_rating,             // d - student_rating (decimal)
                $enrollment_deadline,        // s - enrollment_deadline (date)
                $start_date,                 // s - start_date (date)
                $certificate_provided,       // i - certificate_provided (tinyint)
                $job_placement_support,      // i - job_placement_support (tinyint)
                $course_format,              // s - course_format (varchar)
                $course_status,              // s - course_status (varchar)
                $featured,                   // i - featured (tinyint)
                $max_students,               // i - max_students (int)
                $course_price_type,          // s - course_price_type (varchar)
                $price_amount                // d - price_amount (decimal)
            );
            
            if (!$bind_result) {
                throw new Exception('Parameter binding failed: ' . $stmt->error);
            }
            
            error_log("Parameters bound successfully");
            
            if ($stmt->execute()) {
                $course_id = $conn->insert_id;
                error_log("Course inserted successfully with ID: " . $course_id);
                
                // Verify the insertion by querying back
                $verify_sql = "SELECT id, course_title, company_id FROM course WHERE id = ?";
                $verify_stmt = $conn->prepare($verify_sql);
                $verify_stmt->bind_param("i", $course_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows > 0) {
                    $row = $verify_result->fetch_assoc();
                    error_log("VERIFICATION SUCCESS: Course ID " . $row['id'] . " - " . $row['course_title'] . " for company " . $row['company_id']);
                    $success_message = 'Course posted successfully! Students can now apply for your course.';
                    
                    // Clear form data on success
                    $_POST = [];
                    
                } else {
                    throw new Exception('Course insertion could not be verified');
                }
                $verify_stmt->close();
                
            } else {
                error_log("Execute failed: " . $stmt->error);
                throw new Exception('Failed to save course: ' . $stmt->error);
            }
            
        } catch (Exception $e) {
            error_log("Course insertion error: " . $e->getMessage());
            $error_message = $e->getMessage();
        } finally {
            if (isset($stmt)) $stmt->close();
            $conn->close();
        }
    } else {
        $error_message = 'Database connection failed. Please try again.';
        error_log("Database connection failed in course posting");
    }
}

// Check for success parameter from URL
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = 'Course posted successfully! Students can now apply for your course.';
}
?>

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
    --info: #3498db;
    --text-primary: #2c3e50;
    --text-secondary: #7f8c8d;
    --text-muted: #95a5a6;
    --bg-light: #f8fafc;
    --white: #ffffff;
    --border: #e5e7eb;
    --border-focus: #bdc3c7;
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 12px rgba(3, 89, 70, 0.08);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    --gradient-secondary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --text-dark: #1f2937;
    --glass-bg: rgba(255, 255, 255, 0.95);
    --glass-border: rgba(255, 255, 255, 0.2);
}

/* Page Container */
.page-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}

/* Page Header */
.page-header {
    background: var(--white);
    border-radius: 16px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(3, 89, 70, 0.08);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-primary);
}

.page-title {
    font-family: 'Poppins', sans-serif;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-dark);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-title i {
    color: var(--primary);
    font-size: 2rem;
}

.page-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.company-info-display {
    display: flex;
    align-items: center;
    margin-top: 1rem;
}

.company-badge {
    background: var(--gradient-primary);
    color: var(--white);
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: var(--shadow-md);
}

/* Alert Messages */
.alert {
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-weight: 500;
    box-shadow: var(--shadow);
    border-left: 4px solid;
}

.alert-success {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success);
    border-left-color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.2);
}

.alert-error {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger);
    border-left-color: var(--danger);
    border: 1px solid rgba(231, 76, 60, 0.2);
}

.alert i {
    font-size: 1.2rem;
}

.alert-link {
    color: inherit;
    text-decoration: underline;
    font-weight: 600;
}

/* Form Container */
.form-container {
    background: var(--white);
    border-radius: 16px;
    box-shadow: var(--shadow-md);
    overflow: hidden;
    border: 1px solid rgba(3, 89, 70, 0.08);
}

.course-form {
    padding: 0;
}

/* Form Sections */
.form-section {
    padding: 2.5rem;
    border-bottom: 1px solid var(--border);
    position: relative;
}

.form-section:last-child {
    border-bottom: none;
}

.form-section:nth-child(even) {
    background: rgba(248, 250, 252, 0.5);
}

.section-title {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-dark);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid rgba(3, 89, 70, 0.1);
}

.section-title i {
    color: var(--primary);
    font-size: 1.25rem;
}

/* Form Layout */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 0;
}

.form-group {
    margin-bottom: 2rem;
}

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
    letter-spacing: 0.01em;
}

.form-label.required::after {
    content: ' *';
    color: var(--danger);
    font-weight: 700;
}

.form-help {
    display: block;
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
    font-style: italic;
}

.format-example {
    background: rgba(3, 89, 70, 0.05);
    border: 1px solid rgba(3, 89, 70, 0.1);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 0.5rem;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.8rem;
    color: var(--primary-dark);
    line-height: 1.4;
}

/* Enhanced Form Inputs */
.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: var(--white);
    transition: var(--transition);
    font-family: inherit;
    line-height: 1.5;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(3, 89, 70, 0.1);
    transform: translateY(-1px);
}

.form-input:hover, .form-select:hover, .form-textarea:hover {
    border-color: var(--border-focus);
}

.form-textarea {
    min-height: 120px;
    resize: vertical;
    font-family: inherit;
}

.form-select {
    background-image: url("data:image/svg+xml;charset=US-ASCII,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'><path fill='%23666' d='M2 0L0 2h4zm0 5L0 3h4z'/></svg>");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 12px;
    appearance: none;
}

/* Enhanced Placeholders */
.form-input::placeholder, .form-textarea::placeholder {
    color: var(--text-muted);
    font-style: italic;
}

/* Checkbox Styling */
.checkbox-group {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    cursor: pointer;
    font-weight: 500;
    color: var(--text-primary);
    line-height: 1.5;
    position: relative;
}

.checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    appearance: none;
    border: 2px solid var(--border);
    border-radius: 4px;
    background: var(--white);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    margin: 0;
    flex-shrink: 0;
}

.checkbox-label input[type="checkbox"]:checked {
    background: var(--primary);
    border-color: var(--primary);
}

.checkbox-label input[type="checkbox"]:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: var(--white);
    font-size: 12px;
    font-weight: bold;
}

.checkbox-label:hover input[type="checkbox"] {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(3, 89, 70, 0.1);
}

/* Form Actions */
.form-actions {
    padding: 2.5rem;
    background: rgba(248, 250, 252, 0.8);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    border-top: 1px solid var(--border);
}

/* Enhanced Buttons */
.btn-primary, .btn-secondary {
    padding: 1rem 2rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: var(--transition);
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    min-height: 48px;
    box-shadow: var(--shadow);
}

.btn-primary {
    background: var(--gradient-primary);
    color: var(--white);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-secondary {
    background: var(--white);
    color: var(--text-primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    background: var(--bg-light);
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-1px);
}

/* Pipeline/Timeline Display */
.pipeline-preview {
    background: var(--bg-light);
    border-radius: 12px;
    padding: 2rem;
    margin-top: 1.5rem;
    border: 1px solid var(--border);
    display: none;
}

.pipeline-preview.active {
    display: block;
}

.pipeline-title {
    font-family: 'Poppins', sans-serif;
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pipeline-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: relative;
}

.pipeline-container::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--primary);
    border-radius: 3px;
}

.pipeline-phase {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    position: relative;
}

.pipeline-marker {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
    z-index: 2;
    box-shadow: var(--shadow);
}

.pipeline-content {
    flex: 1;
    background: var(--white);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--primary);
}

.pipeline-phase-title {
    font-family: 'Poppins', sans-serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-dark);
    margin-bottom: 0.5rem;
}

.pipeline-phase-description {
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-container {
        padding: 1rem;
    }

    .page-header {
        padding: 2rem;
    }

    .page-title {
        font-size: 2rem;
    }

    .form-section {
        padding: 2rem;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .form-actions {
        flex-direction: column;
        padding: 2rem;
    }

    .btn-primary, .btn-secondary {
        width: 100%;
        justify-content: center;
    }
    
    .pipeline-container::before {
        left: 15px;
    }
    
    .pipeline-phase {
        gap: 1rem;
    }
    
    .pipeline-marker {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
    }
}
</style>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-plus-circle"></i> Post New Course
        </h1>
        <p class="page-subtitle">Create and publish comprehensive learning opportunities for students worldwide. Share your expertise and help students advance their careers through structured, professional courses.</p>
        
        <!-- Company Info Display -->
        <div class="company-info-display">
            <span class="company-badge">
                <i class="fas fa-building"></i>
                <strong><?php echo htmlspecialchars($_SESSION['company_name']); ?></strong>
                (ID: <?php echo htmlspecialchars($_SESSION['company_id']); ?>)
            </span>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
        <a href="?page=manage-courses" class="alert-link">View your courses</a>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" class="course-form" id="courseForm">
            
            <!-- Basic Information Section -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Basic Information
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_title" class="form-label required">Course Title</label>
                        <input type="text" id="course_title" name="course_title" class="form-input" 
                               placeholder="e.g., Full Stack Web Development Masterclass - From Beginner to Professional" maxlength="255" 
                               value="<?php echo htmlspecialchars($_POST['course_title'] ?? ''); ?>" required>
                        <small class="form-help">Create an engaging, descriptive title that clearly conveys what students will learn</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_category" class="form-label">Course Category</label>
                        <select id="course_category" name="course_category" class="form-select">
                            <option value="">Select the most relevant category</option>
                            <option value="Web Development" <?php echo (($_POST['course_category'] ?? '') === 'Web Development') ? 'selected' : ''; ?>>Web Development</option>
                            <option value="Mobile Development" <?php echo (($_POST['course_category'] ?? '') === 'Mobile Development') ? 'selected' : ''; ?>>Mobile Development</option>
                            <option value="Data Science" <?php echo (($_POST['course_category'] ?? '') === 'Data Science') ? 'selected' : ''; ?>>Data Science & Analytics</option>
                            <option value="AI/Machine Learning" <?php echo (($_POST['course_category'] ?? '') === 'AI/Machine Learning') ? 'selected' : ''; ?>>AI/Machine Learning</option>
                            <option value="Cybersecurity" <?php echo (($_POST['course_category'] ?? '') === 'Cybersecurity') ? 'selected' : ''; ?>>Cybersecurity</option>
                            <option value="Digital Marketing" <?php echo (($_POST['digital_marketing'] ?? '') === 'Digital Marketing') ? 'selected' : ''; ?>>Digital Marketing</option>
                            <option value="Graphic Design" <?php echo (($_POST['course_category'] ?? '') === 'Graphic Design') ? 'selected' : ''; ?>>Graphic Design & UI/UX</option>
                            <option value="Business Analytics" <?php echo (($_POST['course_category'] ?? '') === 'Business Analytics') ? 'selected' : ''; ?>>Business Analytics</option>
                            <option value="Cloud Computing" <?php echo (($_POST['course_category'] ?? '') === 'Cloud Computing') ? 'selected' : ''; ?>>Cloud Computing</option>
                            <option value="Other" <?php echo (($_POST['course_category'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other Technology</option>
                        </select>
                        <small class="form-help">Choose the primary field this course belongs to</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="duration" class="form-label">Course Duration</label>
                        <input type="text" id="duration" name="duration" class="form-input" 
                               placeholder="e.g., 12 weeks, 3 months, 6 weeks intensive" maxlength="50"
                               value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>">
                        <small class="form-help">Specify the total time commitment (e.g., "8 weeks", "3 months part-time")</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="difficulty_level" class="form-label">Difficulty Level</label>
                        <select id="difficulty_level" name="difficulty_level" class="form-select">
                            <option value="">Select appropriate level</option>
                            <option value="Beginner" <?php echo (($_POST['difficulty_level'] ?? '') === 'Beginner') ? 'selected' : ''; ?>>Beginner - No prior experience required</option>
                            <option value="Intermediate" <?php echo (($_POST['difficulty_level'] ?? '') === 'Intermediate') ? 'selected' : ''; ?>>Intermediate - Some background knowledge needed</option>
                            <option value="Advanced" <?php echo (($_POST['difficulty_level'] ?? '') === 'Advanced') ? 'selected' : ''; ?>>Advanced - Strong foundation required</option>
                            <option value="All Levels" <?php echo (($_POST['difficulty_level'] ?? '') === 'All Levels') ? 'selected' : ''; ?>>All Levels - Suitable for everyone</option>
                        </select>
                        <small class="form-help">Help students understand if this course is right for their skill level</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mode" class="form-label">Delivery Mode</label>
                        <select id="mode" name="mode" class="form-select">
                            <option value="">Select delivery method</option>
                            <option value="Online" <?php echo (($_POST['mode'] ?? '') === 'Online') ? 'selected' : ''; ?>>Online - Fully remote learning</option>
                            <option value="Offline" <?php echo (($_POST['mode'] ?? '') === 'Offline') ? 'selected' : ''; ?>>Offline - In-person classes</option>
                            <option value="Hybrid" <?php echo (($_POST['mode'] ?? '') === 'Hybrid') ? 'selected' : ''; ?>>Hybrid - Combination of online and offline</option>
                        </select>
                        <small class="form-help">How will students attend your course?</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_format" class="form-label">Course Format</label>
                        <input type="text" id="course_format" name="course_format" class="form-input" 
                               placeholder="e.g., Live Sessions + Recorded Videos, Interactive Workshops, Project-Based Learning" maxlength="100"
                               value="<?php echo htmlspecialchars($_POST['course_format'] ?? ''); ?>">
                        <small class="form-help">Describe the teaching methodology and structure</small>
                    </div>
                </div>
            </div>

            <!-- Course Details Section -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Course Content & Structure
                </h3>
                
                <div class="form-group">
                    <label for="course_description" class="form-label required">Course Description</label>
                    <textarea id="course_description" name="course_description" class="form-textarea" 
                              placeholder="Provide a comprehensive overview of your course. Explain what makes it unique, who it's designed for, and the key benefits students will gain. Include information about your teaching approach, industry relevance, and career outcomes. This description will be the first thing potential students see, so make it compelling and informative." rows="5" required><?php echo htmlspecialchars($_POST['course_description'] ?? ''); ?></textarea>
                    <small class="form-help">Write a detailed, engaging description that sells the value of your course</small>
                </div>

                <div class="form-group">
                    <label for="what_you_will_learn" class="form-label">What You'll Learn</label>
                    <textarea id="what_you_will_learn" name="what_you_will_learn" class="form-textarea" 
                              placeholder="Frontend Development Fundamentals: Master HTML5, CSS3, and JavaScript to create responsive and interactive user interfaces.|React.js and Component Architecture: Build dynamic single-page applications using React.js, understand component lifecycle, state management, and hooks.|Backend Development with Node.js: Create robust server-side applications, build RESTful APIs, handle authentication, and manage data flow.|Database Design and Management: Work with both SQL and NoSQL databases, design efficient schemas, and implement data relationships." rows="6"><?php echo htmlspecialchars($_POST['what_you_will_learn'] ?? ''); ?></textarea>
                    <small class="form-help">Format: "Title: Description|Next Title: Next Description|" - Each item separated by pipe (|)</small>
                    <div class="format-example">
                        Example Format:<br>
                        Frontend Basics: HTML5, CSS3, JavaScript fundamentals|<br>
                        React Development: Component-based architecture and state management|<br>
                        Backend APIs: Node.js and Express.js server development
                    </div>
                </div>

                <div class="form-group">
                    <label for="program_structure" class="form-label">Program Structure & Timeline</label>
                    <textarea id="program_structure" name="program_structure" class="form-textarea" 
                              placeholder="Week 1-3 Foundation Phase: HTML5, CSS3, JavaScript fundamentals, responsive design, and version control basics.|Week 4-6 Frontend Mastery: Advanced JavaScript, React.js, state management, and modern development tools.|Week 7-9 Backend Development: Node.js, Express.js, API development, database integration, and authentication.|Week 10-12 Full Stack Projects: Complete end-to-end applications, deployment, testing, and portfolio development." rows="6"><?php echo htmlspecialchars($_POST['program_structure'] ?? ''); ?></textarea>
                    <small class="form-help">Format: "Phase Title: Description|Next Phase: Description|" - Will display as structured timeline</small>
                    <div class="format-example">
                        Example Format:<br>
                        Week 1-3 Foundation: Basic concepts and fundamentals|<br>
                        Week 4-6 Advanced Topics: Complex implementations|<br>
                        Week 7-9 Projects: Real-world applications
                    </div>
                    
                    <!-- Pipeline Preview -->
                    <div class="pipeline-preview" id="pipelinePreview">
                        <h4 class="pipeline-title">
                            <i class="fas fa-project-diagram"></i>
                            Program Structure Preview
                        </h4>
                        <div class="pipeline-container" id="pipelineContainer">
                            <!-- Pipeline phases will be dynamically inserted here -->
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="skills_taught" class="form-label">Technical & Professional Skills</label>
                    <textarea id="skills_taught" name="skills_taught" class="form-textarea" 
                              placeholder="Technical Skills: JavaScript ES6+, React.js, Node.js, MongoDB, Git/GitHub, AWS deployment, RESTful APIs, HTML5/CSS3|Professional Skills: Project management, code review practices, debugging techniques, team collaboration, presentation skills, portfolio development, client communication" rows="4"><?php echo htmlspecialchars($_POST['skills_taught'] ?? ''); ?></textarea>
                    <small class="form-help">Format: "Technical Skills: skill1, skill2, skill3|Professional Skills: skill1, skill2, skill3"</small>
                    <div class="format-example">
                        Example Format:<br>
                        Technical Skills: Python, Django, PostgreSQL, Docker|<br>
                        Professional Skills: Agile methodology, Code review, Testing
                    </div>
                </div>

                <div class="form-group">
                    <label for="prerequisites" class="form-label">Prerequisites & Requirements</label>
                    <textarea id="prerequisites" name="prerequisites" class="form-textarea" 
                              placeholder="Basic Computer Skills: Comfortable with using computers and installing software|Logical Thinking: Ability to break down problems and think analytically|Time Commitment: 15-20 hours per week for assignments and projects|Education: High school diploma or equivalent (any background welcome)" rows="5"><?php echo htmlspecialchars($_POST['prerequisites'] ?? ''); ?></textarea>
                    <small class="form-help">Format: "Requirement Title: Description|Next Requirement: Description|" - Will display as organized cards</small>
                    <div class="format-example">
                        Example Format:<br>
                        Basic Programming: Understanding of variables and functions|<br>
                        Time Commitment: 10-15 hours per week|<br>
                        Hardware: Computer with internet connection
                    </div>
                </div>
            </div>

            <!-- Statistics and Dates Section -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Course Statistics & Schedule
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="students_trained" class="form-label">Students Previously Trained</label>
                        <input type="number" id="students_trained" name="students_trained" class="form-input" 
                               min="0" placeholder="500" max="999999" value="<?php echo $_POST['students_trained'] ?? ''; ?>">
                        <small class="form-help">Total number of students you've successfully trained (builds credibility)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="job_placement_rate" class="form-label">Job Placement Rate (%)</label>
                        <input type="number" id="job_placement_rate" name="job_placement_rate" class="form-input" 
                               min="0" max="100" step="0.1" placeholder="85.5" value="<?php echo $_POST['job_placement_rate'] ?? ''; ?>">
                        <small class="form-help">Percentage of graduates who found relevant jobs (if applicable)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="student_rating" class="form-label">Average Student Rating (1-5)</label>
                        <input type="number" id="student_rating" name="student_rating" class="form-input" 
                               min="1" max="5" step="0.1" placeholder="4.5" value="<?php echo $_POST['student_rating'] ?? ''; ?>">
                        <small class="form-help">Average rating from previous students (helps build trust)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_students" class="form-label">Maximum Students per Batch</label>
                        <input type="number" id="max_students" name="max_students" class="form-input" 
                               min="1" max="1000" placeholder="25" value="<?php echo $_POST['max_students'] ?? ''; ?>">
                        <small class="form-help">Class size limit to ensure quality learning experience</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="enrollment_deadline" class="form-label required">Enrollment Deadline</label>
                        <input type="date" id="enrollment_deadline" name="enrollment_deadline" class="form-input" 
                               value="<?php echo $_POST['enrollment_deadline'] ?? ''; ?>" required>
                        <small class="form-help">Last date for students to enroll (must be before start date)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date" class="form-label required">Course Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-input" 
                               value="<?php echo $_POST['start_date'] ?? ''; ?>" required>
                        <small class="form-help">When the course officially begins</small>
                    </div>
                </div>
            </div>

            <!-- Pricing Section -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Pricing & Additional Features
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_price_type" class="form-label">Pricing Model</label>
                        <select id="course_price_type" name="course_price_type" class="form-select">
                            <option value="">Select pricing structure</option>
                            <option value="Free" <?php echo (($_POST['course_price_type'] ?? '') === 'Free') ? 'selected' : ''; ?>>Free - No charge for students</option>
                            <option value="Paid" <?php echo (($_POST['course_price_type'] ?? '') === 'Paid') ? 'selected' : ''; ?>>One-time Payment - Single upfront fee</option>
                            <option value="Subscription" <?php echo (($_POST['course_price_type'] ?? '') === 'Subscription') ? 'selected' : ''; ?>>Subscription - Monthly/yearly payments</option>
                        </select>
                        <small class="form-help">Choose how students will pay for your course</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="price_amount" class="form-label">Price Amount (₹)</label>
                        <input type="number" id="price_amount" name="price_amount" class="form-input" 
                               min="0" step="0.01" placeholder="15000.00" value="<?php echo $_POST['price_amount'] ?? ''; ?>">
                        <small class="form-help">Enter 0 for free courses, or the full course fee in INR</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="certificate_provided" name="certificate_provided" 
                                   <?php echo isset($_POST['certificate_provided']) ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Certificate Provided
                        </label>
                        <small class="form-help">Will students receive a completion certificate?</small>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="job_placement_support" name="job_placement_support"
                                   <?php echo isset($_POST['job_placement_support']) ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Job Placement Support
                        </label>
                        <small class="form-help">Do you provide career assistance and job placement help?</small>
                    </div>
                </div>

                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="featured" name="featured"
                               <?php echo isset($_POST['featured']) ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        Feature this course (recommended for better visibility)
                    </label>
                    <small class="form-help">Featured courses appear prominently in search results and course listings</small>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-plus-circle"></i>
                    Post Course
                </button>
                <a href="?page=home" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Cancel
                </a>
                <?php if ($success_message): ?>
                <a href="?page=post-course" class="btn-secondary">
                    <i class="fas fa-plus"></i>
                    Post Another Course
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
// Form validation and enhancement
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('courseForm');
    const submitBtn = form.querySelector('.btn-primary');
    const programStructureTextarea = document.getElementById('program_structure');
    const pipelinePreview = document.getElementById('pipelinePreview');
    const pipelineContainer = document.getElementById('pipelineContainer');
    
    // Set minimum dates
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('enrollment_deadline').min = today;
    document.getElementById('start_date').min = today;
    
    // Update start date minimum when enrollment deadline changes
    document.getElementById('enrollment_deadline').addEventListener('change', function() {
        const enrollmentDate = this.value;
        if (enrollmentDate) {
            const nextDay = new Date(enrollmentDate);
            nextDay.setDate(nextDay.getDate() + 1);
            document.getElementById('start_date').min = nextDay.toISOString().split('T')[0];
        }
    });
    
    // Price field handling
    const priceType = document.getElementById('course_price_type');
    const priceAmount = document.getElementById('price_amount');
    
    priceType.addEventListener('change', function() {
        if (this.value === 'Free') {
            priceAmount.value = '0';
            priceAmount.disabled = true;
        } else {
            priceAmount.disabled = false;
            if (priceAmount.value === '0') {
                priceAmount.value = '';
            }
        }
    });
    
    // Form submission handling
    form.addEventListener('submit', function(e) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting Course...';
    });
    
    // Character count for textareas
    const textareas = form.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        if (maxLength) {
            const counter = document.createElement('small');
            counter.className = 'char-counter';
            counter.style.float = 'right';
            counter.style.color = 'var(--text-muted)';
            textarea.parentNode.appendChild(counter);
            
            function updateCounter() {
                const remaining = maxLength - textarea.value.length;
                counter.textContent = `${remaining} characters remaining`;
                counter.style.color = remaining < 50 ? 'var(--danger)' : 'var(--text-muted)';
            }
            
            textarea.addEventListener('input', updateCounter);
            updateCounter();
        }
    });
    
    // Pipeline preview functionality
    function updatePipelinePreview() {
        const text = programStructureTextarea.value.trim();
        pipelineContainer.innerHTML = '';
        
        if (!text) {
            pipelinePreview.classList.remove('active');
            return;
        }
        
        // Parse the pipeline data
        const phases = text.split('|').filter(phase => phase.trim() !== '');
        
        if (phases.length === 0) {
            pipelinePreview.classList.remove('active');
            return;
        }
        
        phases.forEach((phase, index) => {
            const [title, ...descriptionParts] = phase.split(':');
            const description = descriptionParts.join(':').trim();
            
            if (title && description) {
                const phaseElement = document.createElement('div');
                phaseElement.className = 'pipeline-phase';
                
                phaseElement.innerHTML = `
                    <div class="pipeline-marker">${index + 1}</div>
                    <div class="pipeline-content">
                        <div class="pipeline-phase-title">${title.trim()}</div>
                        <div class="pipeline-phase-description">${description}</div>
                    </div>
                `;
                
                pipelineContainer.appendChild(phaseElement);
            }
        });
        
        pipelinePreview.classList.add('active');
    }
    
    // Update pipeline preview when text changes
    programStructureTextarea.addEventListener('input', updatePipelinePreview);
    
    // Initial pipeline preview if there's existing content
    updatePipelinePreview();
});
</script>
</body>
</html>