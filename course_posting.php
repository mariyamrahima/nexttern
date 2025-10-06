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
            $course_type = $_POST['course_type'] ?? 'self_paced'; 
            $duration = trim($_POST['duration'] ?? '');
            $difficulty_level = $_POST['difficulty_level'] ?? '';
            $course_description = trim($_POST['course_description'] ?? '');
            $what_you_will_learn = trim($_POST['what_you_will_learn'] ?? '');
            $program_structure = trim($_POST['program_structure'] ?? '');
            $skills_taught = trim($_POST['skills_taught'] ?? '');
            $prerequisites = trim($_POST['prerequisites'] ?? '');
            $students_trained = (int)($_POST['students_trained'] ?? 0);
            $student_rating = (float)($_POST['student_rating'] ?? 0.00);
            $enrollment_deadline = $_POST['enrollment_deadline'] ?? null;
            $start_date = $_POST['start_date'] ?? null;
            $certificate_provided = isset($_POST['certificate_provided']) ? 1 : 0;
            $course_format = trim($_POST['course_format'] ?? '');
            $meeting_link = trim($_POST['meeting_link'] ?? '');
            $course_link = trim($_POST['course_link'] ?? '');
            $course_status = 'Active';
            $featured = isset($_POST['featured']) ? 1 : 0;
            $max_students = (int)($_POST['max_students'] ?? 0);
            
            // Basic validation
            if (empty($course_title)) throw new Exception('Course title is required');
            if (empty($course_description)) throw new Exception('Course description is required');
            
            // Validate course type specific fields
            if ($course_type === 'live') {
                if (empty($meeting_link)) {
                    throw new Exception('Meeting link is required for live courses');
                }
                if (empty($enrollment_deadline)) {
                    throw new Exception('Application deadline is required for live courses');
                }
                if (empty($start_date)) {
                    throw new Exception('Start date is required for live courses');
                }
                
                // Validate dates for live courses
                if (strtotime($enrollment_deadline) <= time()) {
                    throw new Exception('Application deadline must be in the future');
                }
                if (strtotime($start_date) <= strtotime($enrollment_deadline)) {
                    throw new Exception('Start date must be after application deadline');
                }
           // Clear self-paced fields
                $course_link = '';
            } else {
                // Self-paced: clear live fields and validate course link
                $meeting_link = '';
                $enrollment_deadline = null;
                $start_date = null;
                
                if (empty($course_link)) {
                    throw new Exception('Course content link (playlist/notes) is required for self-paced courses');
                }
            }
            
          // Insert into database - UPDATED SQL
            $sql = "INSERT INTO course (
                company_id, company_name, course_title, course_category, course_type, duration, 
                difficulty_level, course_description, what_you_will_learn, 
                program_structure, skills_taught, prerequisites, students_trained, 
                student_rating, enrollment_deadline, start_date, 
                certificate_provided, course_format, meeting_link, course_link, course_status, 
                featured, max_students
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('Database preparation failed: ' . $conn->error);
            
            $stmt->bind_param(
                "sssssssssssssisssssssii",
                $_SESSION['company_id'], $_SESSION['company_name'], $course_title,
                $course_category, $course_type, $duration, $difficulty_level, $course_description,
                $what_you_will_learn, $program_structure, $skills_taught, $prerequisites,
                $students_trained, $student_rating,
                $enrollment_deadline, $start_date, $certificate_provided,
                $course_format, $meeting_link, $course_link, $course_status,
                $featured, $max_students
            );
            
            if ($stmt->execute()) {
                if ($course_type === 'self_paced') {
                    $success_message = 'Self-paced course posted successfully! Students can enroll instantly.';
                } else {
                    $success_message = 'Live course posted successfully! Students can apply and you will review their applications.';
                }
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
    --info: #3498db;
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
}

.page-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: transparent;
    min-height: 100vh;
}

.page-header {
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

.free-badge {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: var(--white);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: 1rem;
    transition: var(--transition);
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
}

.form-actions {
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

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
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
    .page-header { padding: 1.5rem; }
    .page-title { font-size: 1.8rem; }
    .form-section { padding: 1.5rem; }
    .form-row { grid-template-columns: 1fr; gap: 0; }
    .form-actions { flex-direction: column; padding: 1.5rem; }
    .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
}
</style>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-plus-circle"></i> Post New Course
        </h1>
        <p class="page-subtitle">Create and publish free online learning opportunities for students worldwide.</p>
        <div>
            <span class="company-badge">
                <i class="fas fa-building"></i>
                <strong><?php echo htmlspecialchars($_SESSION['company_name']); ?></strong>
                (ID: <?php echo htmlspecialchars($_SESSION['company_id']); ?>)
            </span>
            <span class="free-badge" id="courseTypeBadge">
                <i class="fas fa-gift"></i> Free Self-Paced Course
            </span>
        </div>
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

            <div class="form-group">
                <label for="course_type" class="form-label required">Course Type</label>
                <select id="course_type" name="course_type" class="form-select" required onchange="toggleCourseTypeFields()">
                    <option value="self_paced" <?php echo ($_POST['course_type'] ?? 'self_paced') === 'self_paced' ? 'selected' : ''; ?>>
                        Self-Paced (Auto-Enroll)
                    </option>
                    <option value="live" <?php echo ($_POST['course_type'] ?? '') === 'live' ? 'selected' : ''; ?>>
                        Live Sessions (Approval Required)
                    </option>
                </select>
                <small class="form-help">
                    <strong>Self-paced:</strong> Students enroll instantly, learn at their own pace | <strong>Live:</strong> Students apply, you review and approve
                </small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="duration" class="form-label">Duration</label>
                    <input type="text" id="duration" name="duration" class="form-input" 
                           placeholder="e.g., 12 weeks, Self-paced" value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>">
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

            <div class="form-group">
                <label for="course_format" class="form-label">Format</label>
                <input type="text" id="course_format" name="course_format" class="form-input" 
                       placeholder="e.g., Video Lectures, Interactive Coding, Quizzes" value="<?php echo htmlspecialchars($_POST['course_format'] ?? ''); ?>">
            </div>

<div class="form-group" id="courseLinkGroup" style="display: block;">
    <label for="course_link" class="form-label required" id="courseLinkLabel">Course Content Link (Playlist/Notes)</label>
    <input type="url" id="course_link" name="course_link" class="form-input" 
           placeholder="https://youtube.com/playlist?list=... or your course link"
           value="<?php echo htmlspecialchars($_POST['course_link'] ?? ''); ?>">
    <small class="form-help">
        Provide YouTube playlist, Google Drive folder, or any link to your course materials. This will be sent to enrolled students.
    </small>
</div>

            <div class="form-group" id="meetingLinkGroup" style="display: none;">
                <label for="meeting_link" class="form-label" id="meetingLinkLabel">Meeting Link (for Live Courses)</label>
                <input type="url" id="meeting_link" name="meeting_link" class="form-input" 
                       placeholder="https://meet.google.com/xxx-xxxx-xxx or https://zoom.us/j/xxxxx"
                       value="<?php echo htmlspecialchars($_POST['meeting_link'] ?? ''); ?>">
                <small class="form-help">
                    Provide Google Meet, Zoom, or Teams link. This will be sent to approved students before the course starts.
                </small>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-file-alt"></i> Course Details
            </h3>
            
            <div class="form-group">
                <label for="course_description" class="form-label required">Description</label>
                <textarea id="course_description" name="course_description" class="form-textarea" 
                          placeholder="Provide a comprehensive overview of the course..." rows="5" required><?php echo htmlspecialchars($_POST['course_description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="what_you_will_learn" class="form-label">What You'll Learn</label>
                <textarea id="what_you_will_learn" name="what_you_will_learn" class="form-textarea" rows="4"
                          placeholder="Topic 1: Description|Topic 2: Description|Topic 3: Description|"><?php echo htmlspecialchars($_POST['what_you_will_learn'] ?? ''); ?></textarea>
                <small class="form-help">Format: "Title: Description|Title: Description|" (separated by | pipe character)</small>
            </div>

            <div class="form-group">
                <label for="program_structure" class="form-label">Program Structure</label>
                <textarea id="program_structure" name="program_structure" class="form-textarea" rows="4"
                          placeholder="Week 1-3: Topics covered|Week 4-6: Topics covered|Week 7-9: Project work|"><?php echo htmlspecialchars($_POST['program_structure'] ?? ''); ?></textarea>
                <small class="form-help">Outline the course timeline and modules</small>
            </div>

            <div class="form-group">
                <label for="skills_taught" class="form-label">Skills Taught</label>
                <textarea id="skills_taught" name="skills_taught" class="form-textarea" rows="3"
                          placeholder="HTML, CSS, JavaScript, React, Node.js, MySQL, Git"><?php echo htmlspecialchars($_POST['skills_taught'] ?? ''); ?></textarea>
                <small class="form-help">Enter skills separated by commas</small>
            </div>

            <div class="form-group">
                <label for="prerequisites" class="form-label">Prerequisites</label>
                <textarea id="prerequisites" name="prerequisites" class="form-textarea" rows="3"
                          placeholder="Basic Programming Knowledge: Understanding of variables and loops|Computer Setup: Access to a computer with internet|"><?php echo htmlspecialchars($_POST['prerequisites'] ?? ''); ?></textarea>
                <small class="form-help">What students should know before starting</small>
            </div>
        </div>

        <div class="form-section" id="scheduleSection">
            <h3 class="section-title">
                <i class="fas fa-chart-bar"></i> Statistics & <span id="scheduleTitle">Course Info</span>
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="students_trained" class="form-label">Students Trained</label>
                    <input type="number" id="students_trained" name="students_trained" class="form-input" 
                           min="0" placeholder="500" value="<?php echo $_POST['students_trained'] ?? ''; ?>">
                    <small class="form-help">Total students you've trained (optional)</small>
                </div>
                
                <div class="form-group">
                    <label for="student_rating" class="form-label">Rating (1-5)</label>
                    <input type="number" id="student_rating" name="student_rating" class="form-input" 
                           min="1" max="5" step="0.1" placeholder="4.5" value="<?php echo $_POST['student_rating'] ?? ''; ?>">
                    <small class="form-help">Average course rating (optional)</small>
                </div>
            </div>

            <div class="form-group">
                <label for="max_students" class="form-label">Max Students</label>
                <input type="number" id="max_students" name="max_students" class="form-input" 
                       min="1" placeholder="25 for live, unlimited for self-paced" value="<?php echo $_POST['max_students'] ?? ''; ?>">
                <small class="form-help">Maximum number of students (leave blank for unlimited)</small>
            </div>

            <!-- Live Course Schedule Fields (Hidden by default) -->
            <div id="liveScheduleFields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="enrollment_deadline" class="form-label required">Application Deadline</label>
                        <input type="date" id="enrollment_deadline" name="enrollment_deadline" class="form-input"
                               value="<?php echo $_POST['enrollment_deadline'] ?? ''; ?>">
                        <small class="form-help">Last date for students to apply</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date" class="form-label required">Course Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-input"
                               value="<?php echo $_POST['start_date'] ?? ''; ?>">
                        <small class="form-help">When live sessions begin</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-star"></i> Additional Features
            </h3>

            <div class="form-row">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="certificate_provided" name="certificate_provided" 
                               <?php echo isset($_POST['certificate_provided']) ? 'checked' : ''; ?>>
                        Certificate Provided
                    </label>
                    <small class="form-help">Students receive a certificate upon completion</small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="featured" name="featured"
                               <?php echo isset($_POST['featured']) ? 'checked' : ''; ?>>
                        Feature this course
                    </label>
                    <small class="form-help">Display prominently on course listings</small>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary" id="submitBtn">
                <i class="fas fa-plus-circle"></i>
                Post Free Course
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
    const courseTypeSelect = document.getElementById('course_type');
    const today = new Date().toISOString().split('T')[0];
    
    // Set minimum dates
    document.getElementById('enrollment_deadline').min = today;
    document.getElementById('start_date').min = today;
    
    // Date validation for live courses
    document.getElementById('enrollment_deadline').addEventListener('change', function() {
        if (this.value) {
            const nextDay = new Date(this.value);
            nextDay.setDate(nextDay.getDate() + 1);
            document.getElementById('start_date').min = nextDay.toISOString().split('T')[0];
        }
    });
    
    // Initialize course type display on page load
    toggleCourseTypeFields();
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        const courseType = courseTypeSelect.value;
        const meetingLink = document.getElementById('meeting_link').value.trim();
        const enrollmentDeadline = document.getElementById('enrollment_deadline').value;
        const startDate = document.getElementById('start_date').value;
        
        // Validate live course required fields
        if (courseType === 'live') {
            if (!meetingLink) {
                e.preventDefault();
                alert('Meeting link is required for live courses');
                document.getElementById('meeting_link').focus();
                return false;
            }
            if (!enrollmentDeadline || !startDate) {
                e.preventDefault();
                alert('Application deadline and start date are required for live courses');
                return false;
            }
        }
        
        // Disable submit button and show loading state
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
    });
});
function toggleCourseTypeFields() {
    const courseType = document.getElementById('course_type').value;
    const meetingLinkGroup = document.getElementById('meetingLinkGroup');
    const courseLinkGroup = document.getElementById('courseLinkGroup');
    const courseLinkInput = document.getElementById('course_link');
    const courseLinkLabel = document.getElementById('courseLinkLabel');
    const meetingLinkInput = document.getElementById('meeting_link');
    const meetingLinkLabel = document.getElementById('meetingLinkLabel');
    
    // ADD THESE LINES - Get schedule section elements
    const liveScheduleFields = document.getElementById('liveScheduleFields');
    const enrollmentDeadlineInput = document.getElementById('enrollment_deadline');
    const startDateInput = document.getElementById('start_date');
    const courseTypeBadge = document.getElementById('courseTypeBadge');
    const scheduleTitle = document.getElementById('scheduleTitle');
    
    if (courseType === 'live') {
        // Show live course fields
        meetingLinkGroup.style.display = 'block';
        courseLinkGroup.style.display = 'none';
        liveScheduleFields.style.display = 'block'; // SHOW SCHEDULE
        
        // Set required fields
        meetingLinkInput.required = true;
        courseLinkInput.required = false;
        enrollmentDeadlineInput.required = true;
        startDateInput.required = true;
        
        // Clear self-paced fields
        courseLinkInput.value = '';
        
        // Update labels
        meetingLinkLabel.classList.add('required');
        courseLinkLabel.classList.remove('required');
        
        // Update badge and title
        courseTypeBadge.innerHTML = '<i class="fas fa-video"></i> Free Live Course';
        scheduleTitle.textContent = 'Schedule & Statistics';
        
    } else {
        // Show self-paced fields
        meetingLinkGroup.style.display = 'none';
        courseLinkGroup.style.display = 'block';
        liveScheduleFields.style.display = 'none'; // HIDE SCHEDULE
        
        // Set required fields
        meetingLinkInput.required = false;
        courseLinkInput.required = true;
        enrollmentDeadlineInput.required = false;
        startDateInput.required = false;
        
        // Clear live fields
        meetingLinkInput.value = '';
        enrollmentDeadlineInput.value = '';
        startDateInput.value = '';
        
        // Update labels
        meetingLinkLabel.classList.remove('required');
        courseLinkLabel.classList.add('required');
        
        // Update badge and title
        courseTypeBadge.innerHTML = '<i class="fas fa-gift"></i> Free Self-Paced Course';
        scheduleTitle.textContent = 'Course Info';
    }
}
</script>
</body>
</html>