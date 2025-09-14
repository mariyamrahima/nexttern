<?php
// Database connection and operations
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create about_content table if it doesn't exist and insert default content
$conn->query("CREATE TABLE IF NOT EXISTS about_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(50) NOT NULL UNIQUE,
    content TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(100) DEFAULT 'Admin'
)");

// Insert default content if it doesn't exist
$default_content = [
    'mission' => 'To empower students with real-world experience and help companies build the next generation of professionals through structured, meaningful internship programs.',
    'vision' => 'To become the global leader in bridging the education-industry gap, creating a world where every student has access to quality practical experience.',
    'values' => 'Excellence, Innovation, Integrity, Collaboration, Growth, Impact',
    'privacy_policy' => 'We are committed to protecting your privacy and ensuring the security of your personal information. This Privacy Policy explains how we collect, use, and safeguard your data when you use our internship platform.',
    'terms_of_service' => 'By accessing and using our platform, you agree to be bound by these Terms of Service. These terms govern your use of our internship matching service and define the rights and responsibilities of all users.'
];

foreach ($default_content as $key => $value) {
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM about_content WHERE section_key = ?");
    $check_stmt->bind_param("s", $key);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count = $check_result->fetch_row()[0];
    $check_stmt->close();
    
    if ($count == 0) {
        $insert_stmt = $conn->prepare("INSERT INTO about_content (section_key, content) VALUES (?, ?)");
        $insert_stmt->bind_param("ss", $key, $value);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated_by = 'Admin'; // Can be modified to use actual admin name from session
    $success = true;
    $error_message = '';

    if (isset($_POST['action']) && $_POST['action'] === 'update_about') {
        $sections = ['mission', 'vision', 'values'];
        foreach ($sections as $section) {
            if (isset($_POST[$section])) {
                $update_stmt = $conn->prepare("UPDATE about_content SET content=?, updated_by=? WHERE section_key=?");
                $update_stmt->bind_param("sss", $_POST[$section], $updated_by, $section);
                if (!$update_stmt->execute()) { 
                    $success = false; 
                    $error_message = $update_stmt->error; 
                }
                $update_stmt->close();
            }
        }
        
        if ($success) {
            $success_message = "About Us content updated successfully!";
        } else {
            $error_message = "Error updating About Us content: " . $error_message;
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_privacy') {
        $update_stmt = $conn->prepare("UPDATE about_content SET content=?, updated_by=? WHERE section_key='privacy_policy'");
        $update_stmt->bind_param("ss", $_POST['privacy_policy'], $updated_by);
        if ($update_stmt->execute()) {
            $success_message = "Privacy Policy updated successfully!";
        } else {
            $error_message = "Error updating Privacy Policy: " . $update_stmt->error;
        }
        $update_stmt->close();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_terms') {
        $update_stmt = $conn->prepare("UPDATE about_content SET content=?, updated_by=? WHERE section_key='terms_of_service'");
        $update_stmt->bind_param("ss", $_POST['terms_of_service'], $updated_by);
        if ($update_stmt->execute()) {
            $success_message = "Terms of Service updated successfully!";
        } else {
            $error_message = "Error updating Terms of Service: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

// Fetch current data for display
$about_data = [];
$result = $conn->query("SELECT section_key, content, updated_at, updated_by FROM about_content");
while ($row = $result->fetch_assoc()) {
    $about_data[$row['section_key']] = $row['content'];
    // Store the latest update info
    $updated_at = $row['updated_at'];
    $updated_by = $row['updated_by'];
}
?>

<style>
/* CSS code remains mostly unchanged with minor modifications */
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
    --bg-light: #f8fcfb;
    --glass-bg: rgba(255, 255, 255, 0.25);
    --glass-border: rgba(255, 255, 255, 0.3);
    --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
    --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
    --blur: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --border-radius: 16px;
}
.page-header { background: var(--glass-bg); backdrop-filter: blur(var(--blur)); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow-light); margin-bottom: 2rem; position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%); }
.page-title { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
.page-title i { font-size: 1.75rem; color: var(--accent); }
.page-description { font-family: 'Roboto', sans-serif; color: var(--secondary); opacity: 0.8; font-size: 1.1rem; }
.tab-navigation { display: flex; gap: 0.5rem; margin-bottom: 2rem; background: var(--glass-bg); backdrop-filter: blur(var(--blur)); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 0.5rem; box-shadow: var(--shadow-light); flex-wrap: wrap; }
.tab-btn { flex: 1; min-width: 150px; padding: 1rem 1.5rem; border: none; background: transparent; color: var(--secondary); font-weight: 600; border-radius: calc(var(--border-radius) - 4px); cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.tab-btn:hover { background: rgba(255, 255, 255, 0.3); color: var(--primary); }
.tab-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25); }
.tab-content { display: none; }
.tab-content.active { display: block; animation: fadeInUp 0.5s ease-out; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.content-form { background: var(--glass-bg); backdrop-filter: blur(var(--blur)); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow-light); margin-bottom: 2rem; }
.form-section { margin-bottom: 2rem; }
.section-title { font-family: 'Poppins', sans-serif; font-size: 1.2rem; font-weight: 600; color: var(--primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(78, 205, 196, 0.2); }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
.form-group { margin-bottom: 1.5rem; }
.form-label { display: block; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem; font-size: 0.9rem; }
.form-input, .form-textarea { width: 100%; padding: 1rem; border: 1px solid var(--glass-border); border-radius: 12px; background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); font-size: 0.95rem; color: var(--secondary); transition: var(--transition); font-family: inherit; }
.form-input:focus, .form-textarea:focus { outline: none; border-color: var(--accent); background: rgba(255, 255, 255, 0.9); box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1); transform: translateY(-1px); }
.form-textarea { min-height: 120px; resize: vertical; }
.form-textarea.large { min-height: 300px; }
.form-textarea.extra-large { min-height: 400px; }
.form-actions { display: flex; gap: 1rem; justify-content: flex-end; align-items: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--glass-border); }
.btn { padding: 0.75rem 2rem; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: var(--transition); display: flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.95rem; }
.btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25); }
.btn-primary:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(3, 89, 70, 0.35); }
.btn-secondary { background: rgba(255, 255, 255, 0.8); color: var(--secondary); border: 1px solid var(--glass-border); }
.btn-secondary:hover { background: rgba(255, 255, 255, 0.95); transform: translateY(-1px); }
.alert { padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-weight: 500; }
.alert-success { background: rgba(39, 174, 96, 0.1); color: var(--success); border: 1px solid rgba(39, 174, 96, 0.2); }
.alert-error { background: rgba(231, 76, 60, 0.1); color: var(--danger); border: 1px solid rgba(231, 76, 60, 0.2); }
.update-info { background: rgba(255, 255, 255, 0.5); padding: 1rem 1.5rem; border-radius: 12px; margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--glass-border); font-size: 0.9rem; color: var(--secondary); opacity: 0.8; }
.loading { opacity: 0; animation: fadeIn 0.5s ease-out 0.1s forwards; }
@keyframes fadeIn { to { opacity: 1; } }
@media (max-width: 768px) { .page-header { padding: 1.5rem; } .page-title { font-size: 1.5rem; } .tab-navigation { flex-direction: column; } .tab-btn { min-width: unset; } .form-grid { grid-template-columns: 1fr; } .form-actions { flex-direction: column; align-items: stretch; } .btn { justify-content: center; } .update-info { flex-direction: column; gap: 0.5rem; text-align: center; } }
@media (max-width: 480px) { .content-form { padding: 1.5rem; } }
</style>

<div class="page-header loading">
    <h1 class="page-title">
        <i class="fas fa-cogs"></i>
        Content Management
    </h1>
    <p class="page-description">Manage your organization's About Us, Privacy Policy, and Terms of Service content.</p>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($success_message) ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?= htmlspecialchars($error_message) ?>
    </div>
<?php endif; ?>

<div class="tab-navigation loading">
    <button class="tab-btn active" onclick="switchTab('about')">
        <i class="fas fa-info-circle"></i>
        About Us
    </button>
    <button class="tab-btn" onclick="switchTab('privacy')">
        <i class="fas fa-shield-alt"></i>
        Privacy Policy
    </button>
    <button class="tab-btn" onclick="switchTab('terms')">
        <i class="fas fa-file-contract"></i>
        Terms of Service
    </button>
</div>

<div id="about-tab" class="tab-content active">
    <form method="post" class="content-form loading">
        <input type="hidden" name="action" value="update_about">
        
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-bullseye"></i>
                Mission, Vision & Values
            </h3>
            <div class="form-group">
                <label class="form-label" for="mission">Our Mission</label>
                <textarea id="mission" name="mission" class="form-textarea large" 
                          placeholder="Describe your organization's mission and purpose..."><?= htmlspecialchars($about_data['mission'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="vision">Our Vision</label>
                <textarea id="vision" name="vision" class="form-textarea large"
                          placeholder="Describe your organization's vision for the future..."><?= htmlspecialchars($about_data['vision'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="values">Our Values</label>
                <textarea id="values" name="values" class="form-textarea" 
                          placeholder="List your core values, separated by commas or line breaks..."><?= htmlspecialchars($about_data['values'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="resetForm('about')">
                <i class="fas fa-undo"></i>
                Reset
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save Changes
            </button>
        </div>
    </form>

    <div class="update-info">
        <span><i class="fas fa-clock"></i> Last updated: <?= htmlspecialchars(date('M j, Y g:i A', strtotime($updated_at ?? 'now'))) ?></span>
        <span><i class="fas fa-user"></i> Updated by: <?= htmlspecialchars($updated_by ?? 'Admin') ?></span>
    </div>
</div>

<div id="privacy-tab" class="tab-content">
    <form method="post" class="content-form loading">
        <input type="hidden" name="action" value="update_privacy">
        
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-shield-alt"></i>
                Privacy Policy Content
            </h3>
            <div class="form-group">
                <label class="form-label" for="privacy_policy">Privacy Policy</label>
                <textarea id="privacy_policy" name="privacy_policy" class="form-textarea extra-large" 
                          placeholder="Enter your complete privacy policy content here..."><?= htmlspecialchars($about_data['privacy_policy'] ?? '') ?></textarea>
                <small style="color: var(--secondary); opacity: 0.7; display: block; margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Include information about data collection, usage, storage, and user rights.
                </small>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="resetForm('privacy')">
                <i class="fas fa-undo"></i>
                Reset
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save Privacy Policy
            </button>
        </div>
    </form>
</div>

<div id="terms-tab" class="tab-content">
    <form method="post" class="content-form loading">
        <input type="hidden" name="action" value="update_terms">
        
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-file-contract"></i>
                Terms of Service Content
            </h3>
            <div class="form-group">
                <label class="form-label" for="terms_of_service">Terms of Service</label>
                <textarea id="terms_of_service" name="terms_of_service" class="form-textarea extra-large" 
                          placeholder="Enter your complete terms of service content here..."><?= htmlspecialchars($about_data['terms_of_service'] ?? '') ?></textarea>
                <small style="color: var(--secondary); opacity: 0.7; display: block; margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Include user responsibilities, service terms, limitations, and legal agreements.
                </small>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="resetForm('terms')">
                <i class="fas fa-undo"></i>
                Reset
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save Terms of Service
            </button>
        </div>
    </form>
</div>

<script>
// Tab switching functionality
function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
    
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(`${tabName}-tab`).classList.add('active');
}

// Reset form to original values
function resetForm(formType) {
    if (confirm('Are you sure you want to reset all changes? This will restore the form to its last saved state.')) {
        location.reload();
    }
}

// Auto-resize textareas
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Remove loading class
    const elements = document.querySelectorAll('.loading');
    elements.forEach(el => el.classList.remove('loading'));
    
    // Add auto-resize to all textareas
    document.querySelectorAll('textarea').forEach(textarea => {
        textarea.addEventListener('input', () => autoResize(textarea));
        autoResize(textarea); // Initial resize
    });
});

// Form submission with loading state
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
        
        // Re-enable after 3 seconds in case of issues
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 3000);
    });
});
</script>