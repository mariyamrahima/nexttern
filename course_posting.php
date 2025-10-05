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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $course_status = 'Active';
            $featured = isset($_POST['featured']) ? 1 : 0;
            $max_students = (int)($_POST['max_students'] ?? 0);
            $course_price_type = $_POST['course_price_type'] ?? '';
            $price_amount = (float)($_POST['price_amount'] ?? 0.00);
            
            // Basic validation
            if (empty($course_title)) throw new Exception('Course title is required');
            if (empty($course_description)) throw new Exception('Course description is required');
            if (empty($enrollment_deadline)) throw new Exception('Enrollment deadline is required');
            if (empty($start_date)) throw new Exception('Start date is required');
            
            // Validate dates
            if (strtotime($enrollment_deadline) <= time()) {
                throw new Exception('Enrollment deadline must be in the future');
            }
            if (strtotime($start_date) <= strtotime($enrollment_deadline)) {
                throw new Exception('Start date must be after enrollment deadline');
            }
            
            // Insert into database
            $sql = "INSERT INTO course (
                company_id, company_name, course_title, course_category, duration, 
                difficulty_level, mode, course_description, what_you_will_learn, 
                program_structure, skills_taught, prerequisites, students_trained, 
                job_placement_rate, student_rating, enrollment_deadline, start_date, 
                certificate_provided, job_placement_support, course_format, course_status, 
                featured, max_students, course_price_type, price_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('Database preparation failed: ' . $conn->error);
            
            $stmt->bind_param(
                "ssssssssssssissssiissiisd",
                $_SESSION['company_id'], $_SESSION['company_name'], $course_title,
                $course_category, $duration, $difficulty_level, $mode, $course_description,
                $what_you_will_learn, $program_structure, $skills_taught, $prerequisites,
                $students_trained, $job_placement_rate, $student_rating,
                $enrollment_deadline, $start_date, $certificate_provided,
                $job_placement_support, $course_format, $course_status,
                $featured, $max_students, $course_price_type, $price_amount
            );
            
            if ($stmt->execute()) {
                $success_message = 'Course posted successfully! Students can now apply for your course.';
                $_POST = [];
            } else {
                throw new Exception('Failed to save course: ' . $stmt->error);
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        } finally {
            if (isset($stmt)) $stmt->close();
            $conn->close();
        }
    } else {
        $error_message = 'Database connection failed. Please try again.';
    }
}

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = 'Course posted successfully!';
}
?>

<style>
:root {
    --primary: #035946;
    --primary-light: #0a7058;
    --primary-dark: #023d32;
    --success: #27ae60;
    --danger: #e74c3c;
    --text-primary: #2c3e50;
    --text-secondary: #7f8c8d;
    --text-muted: #95a5a6;
    --white: #ffffff;
    --border: #e5e7eb;
    --border-focus: #bdc3c7;
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 12px rgba(3, 89, 70, 0.08);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}.page-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: transparent;
    min-height: 100vh;
}.page-header {
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    margin-left: 0;
    margin-right: 0;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.3);
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

.company-badge {
    background: var(--gradient-primary);
    color: var(--white);
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: var(--shadow-md);
}

.alert {
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-weight: 500;
    box-shadow: var(--shadow);
}

.alert-success {
    background: rgba(39, 174, 96, 0.15);
    color: var(--success);
    border-left: 4px solid var(--success);
}

.alert-error {
    background: rgba(231, 76, 60, 0.15);
    color: var(--danger);
    border-left: 4px solid var(--danger);
}

.alert i { font-size: 1.2rem; }
.alert-link { color: inherit; text-decoration: underline; font-weight: 600; }

.form-container {
    background: transparent;
    border-radius: 16px;
    overflow: hidden;
}
.form-section {
    padding: 2.5rem;
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    margin-bottom: 1.5rem;
    margin-left: 0;
    margin-right: 0;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.3);
}
.form-section:nth-child(even) {
    background: rgba(255, 255, 255, 0.5);
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
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
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(3, 89, 70, 0.1);
    transform: translateY(-1px);
}

.form-textarea {
    min-height: 120px;
    resize: vertical;
}

.form-select {
    background-image: url("data:image/svg+xml;charset=US-ASCII,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'><path fill='%23666' d='M2 0L0 2h4zm0 5L0 3h4z'/></svg>");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 12px;
    appearance: none;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 500;
    color: var(--text-primary);
}

.checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}.form-actions {
    padding: 2.5rem;
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    border-radius: 16px;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(255, 255, 255, 0.3);
    margin-left: 0;
    margin-right: 0;
}
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

.btn-secondary {
    background: var(--white);
    color: var(--text-primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
}

@media (max-width: 768px) {
    .page-container { padding: 1rem; }
    .form-row { grid-template-columns: 1fr; }
    .form-actions { flex-direction: column; }
    .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
}
</style>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-plus-circle"></i> Post New Course
        </h1>
        <p class="page-subtitle">Create and publish comprehensive learning opportunities for students worldwide.</p>
        <span class="company-badge">
            <i class="fas fa-building"></i>
            <strong><?php echo htmlspecialchars($_SESSION['company_name']); ?></strong>
            (ID: <?php echo htmlspecialchars($_SESSION['company_id']); ?>)
        </span>
    </div>

    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
        <a href="?page=manage-courses" class="alert-link">View courses</a>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="courseForm">
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i> Basic Information
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="course_title" class="form-label required">Course Title</label>
                    <input type="text" id="course_title" name="course_title" class="form-input" 
                           placeholder="e.g., Full Stack Web Development" maxlength="255" required
                           value="<?php echo htmlspecialchars($_POST['course_title'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="course_category" class="form-label">Category</label>
                    <select id="course_category" name="course_category" class="form-select">
                        <option value="">Select category</option>
                        <option value="Web Development">Web Development</option>
                        <option value="Mobile Development">Mobile Development</option>
                        <option value="Data Science">Data Science</option>
                        <option value="AI/Machine Learning">AI/ML</option>
                        <option value="Cybersecurity">Cybersecurity</option>
                        <option value="Digital Marketing">Digital Marketing</option>
                        <option value="Graphic Design">Graphic Design</option>
                        <option value="Cloud Computing">Cloud Computing</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="duration" class="form-label">Duration</label>
                    <input type="text" id="duration" name="duration" class="form-input" 
                           placeholder="e.g., 12 weeks" value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="difficulty_level" class="form-label">Level</label>
                    <select id="difficulty_level" name="difficulty_level" class="form-select">
                        <option value="">Select level</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="mode" class="form-label">Mode</label>
                    <select id="mode" name="mode" class="form-select">
                        <option value="">Select mode</option>
                        <option value="Online">Online</option>
                        <option value="Offline">Offline</option>
                        <option value="Hybrid">Hybrid</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="course_format" class="form-label">Format</label>
                    <input type="text" id="course_format" name="course_format" class="form-input" 
                           placeholder="e.g., Live + Recorded" value="<?php echo htmlspecialchars($_POST['course_format'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-file-alt"></i> Course Details
            </h3>
            
            <div class="form-group">
                <label for="course_description" class="form-label required">Description</label>
                <textarea id="course_description" name="course_description" class="form-textarea" 
                          placeholder="Comprehensive course overview..." rows="5" required><?php echo htmlspecialchars($_POST['course_description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="what_you_will_learn" class="form-label">What You'll Learn</label>
                <textarea id="what_you_will_learn" name="what_you_will_learn" class="form-textarea" rows="4"
                          placeholder="Topic 1: Description|Topic 2: Description|"><?php echo htmlspecialchars($_POST['what_you_will_learn'] ?? ''); ?></textarea>
                <small class="form-help">Format: "Title: Description|Title: Description|"</small>
            </div>

            <div class="form-group">
                <label for="program_structure" class="form-label">Program Structure</label>
                <textarea id="program_structure" name="program_structure" class="form-textarea" rows="4"
                          placeholder="Week 1-3: Topics|Week 4-6: Topics|"><?php echo htmlspecialchars($_POST['program_structure'] ?? ''); ?></textarea>
            </div>

           <div class="form-group">
    <label for="skills_taught" class="form-label">Skills Taught</label>
    <textarea id="skills_taught" name="skills_taught" class="form-textarea" rows="3"
              placeholder="HTML, CSS, JavaScript, React, Node.js, MySQL"><?php echo htmlspecialchars($_POST['skills_taught'] ?? ''); ?></textarea>
    <small class="form-help">Enter skills separated by commas (e.g., HTML, CSS, JavaScript)</small>
</div>

            <div class="form-group">
                <label for="prerequisites" class="form-label">Prerequisites</label>
                <textarea id="prerequisites" name="prerequisites" class="form-textarea" rows="3"
                          placeholder="Requirement: Description|Requirement: Description|"><?php echo htmlspecialchars($_POST['prerequisites'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-chart-bar"></i> Statistics & Schedule
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="students_trained" class="form-label">Students Trained</label>
                    <input type="number" id="students_trained" name="students_trained" class="form-input" 
                           min="0" placeholder="500" value="<?php echo $_POST['students_trained'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="job_placement_rate" class="form-label">Placement Rate (%)</label>
                    <input type="number" id="job_placement_rate" name="job_placement_rate" class="form-input" 
                           min="0" max="100" step="0.1" placeholder="85.5" value="<?php echo $_POST['job_placement_rate'] ?? ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="student_rating" class="form-label">Rating (1-5)</label>
                    <input type="number" id="student_rating" name="student_rating" class="form-input" 
                           min="1" max="5" step="0.1" placeholder="4.5" value="<?php echo $_POST['student_rating'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="max_students" class="form-label">Max Students</label>
                    <input type="number" id="max_students" name="max_students" class="form-input" 
                           min="1" placeholder="25" value="<?php echo $_POST['max_students'] ?? ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="enrollment_deadline" class="form-label required">Enrollment Deadline</label>
                    <input type="date" id="enrollment_deadline" name="enrollment_deadline" class="form-input" required
                           value="<?php echo $_POST['enrollment_deadline'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="start_date" class="form-label required">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-input" required
                           value="<?php echo $_POST['start_date'] ?? ''; ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-money-bill-wave"></i> Pricing & Features
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="course_price_type" class="form-label">Pricing</label>
                    <select id="course_price_type" name="course_price_type" class="form-select">
                        <option value="">Select pricing</option>
                        <option value="Free">Free</option>
                        <option value="Paid">Paid</option>
                        <option value="Subscription">Subscription</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="price_amount" class="form-label">Price (₹)</label>
                    <input type="number" id="price_amount" name="price_amount" class="form-input" 
                           min="0" step="0.01" placeholder="15000.00" value="<?php echo $_POST['price_amount'] ?? ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="certificate_provided" name="certificate_provided">
                        Certificate Provided
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="job_placement_support" name="job_placement_support">
                        Job Placement Support
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="featured" name="featured">
                    Feature this course
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i class="fas fa-plus-circle"></i>
                Post Course
            </button>
            <a href="?page=home" class="btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('courseForm');
    const today = new Date().toISOString().split('T')[0];
    
    document.getElementById('enrollment_deadline').min = today;
    document.getElementById('start_date').min = today;
    
    document.getElementById('enrollment_deadline').addEventListener('change', function() {
        if (this.value) {
            const nextDay = new Date(this.value);
            nextDay.setDate(nextDay.getDate() + 1);
            document.getElementById('start_date').min = nextDay.toISOString().split('T')[0];
        }
    });
    
    document.getElementById('course_price_type').addEventListener('change', function() {
        const priceAmount = document.getElementById('price_amount');
        if (this.value === 'Free') {
            priceAmount.value = '0';
            priceAmount.disabled = true;
        } else {
            priceAmount.disabled = false;
        }
    });
    
    form.addEventListener('submit', function() {
        const btn = form.querySelector('.btn-primary');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
    });
});
</script>
</body>
</html>