<?php
// This file should be included by the main company_dashboard.php
// It contains the profile page content

// Ensure we have a database connection
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() {
        $host = "localhost";
        $username = "root";
        $password = "";
        $database = "nexttern_db";
        
        $conn = new mysqli($host, $username, $password, $database);
        return $conn->connect_error ? null : $conn;
    }
}

// Get company details for editing
$company_details = [
    'company_name' => $_SESSION['company_name'] ?? '',
    'industry_type' => '',
    'company_email' => '',
    'year_established' => '',
    'contact_name' => '',
    'designation' => '',
    'contact_phone' => '',
    'status' => 'active',
    'company_id' => $_SESSION['company_id'] ?? '',
    'created_at' => '',
    'updated_at' => ''
];

$conn = getDatabaseConnection();
if ($conn && isset($_SESSION['company_id'])) {
    $stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['company_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $company_details = array_merge($company_details, $row);
        }
        $stmt->close();
    }
    $conn->close();
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $conn = getDatabaseConnection();
    if ($conn && isset($_SESSION['company_id'])) {
        try {
            $stmt = $conn->prepare("UPDATE companies SET 
                company_name = ?, 
                industry_type = ?, 
                company_email = ?, 
                year_established = ?, 
                contact_name = ?, 
                designation = ?, 
                contact_phone = ?,
                updated_at = NOW()
                WHERE company_id = ?");
            
            if ($stmt) {
                $stmt->bind_param("sssissss", 
                    $_POST['company_name'],
                    $_POST['industry_type'],
                    $_POST['company_email'],
                    $_POST['year_established'],
                    $_POST['contact_name'],
                    $_POST['designation'],
                    $_POST['contact_phone'],
                    $_SESSION['company_id']
                );
                
                if ($stmt->execute()) {
                    // Update session data
                    $_SESSION['company_name'] = $_POST['company_name'];
                    $_SESSION['industry_type'] = $_POST['industry_type'];
                    $success_message = 'Profile updated successfully!';
                    
                    // Refresh company details
                    $company_details = array_merge($company_details, $_POST);
                    
                    // Refresh updated_at timestamp
                    $company_details['updated_at'] = date('Y-m-d H:i:s');
                } else {
                    $error_message = 'Failed to update profile. Please try again.';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error_message = 'An error occurred while updating your profile.';
        }
        $conn->close();
    } else {
        $error_message = 'Database connection failed.';
    }
}
?>

<div class="page-container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-user-circle"></i> Company Profile</h1>
        <p class="page-subtitle">Update your company information and contact details.</p>
    </div>

    <?php if ($success_message): ?>
        <div class="success-message" style="background: rgba(39, 174, 96, 0.1); color: var(--success); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid var(--success); font-weight: 500;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="error-message" style="background: rgba(231, 76, 60, 0.1); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid var(--danger); font-weight: 500;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="form-container glass-card">
        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label for="company_name">
                        <i class="fas fa-building"></i>
                        Company Name
                    </label>
                    <input 
                        type="text" 
                        id="company_name" 
                        name="company_name" 
                        value="<?php echo htmlspecialchars($company_details['company_name']); ?>" 
                        required
                        placeholder="Enter your company name"
                        class="glass-input"
                    >
                </div>

                <div class="form-group">
                    <label for="industry_type">
                        <i class="fas fa-industry"></i>
                        Industry Type
                    </label>
                    <select id="industry_type" name="industry_type" required class="glass-input">
                        <option value="">Select Industry</option>
                        <option value="Technology" <?php echo ($company_details['industry_type'] === 'Technology') ? 'selected' : ''; ?>>Technology</option>
                        <option value="Healthcare" <?php echo ($company_details['industry_type'] === 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                        <option value="Finance" <?php echo ($company_details['industry_type'] === 'Finance') ? 'selected' : ''; ?>>Finance</option>
                        <option value="Education" <?php echo ($company_details['industry_type'] === 'Education') ? 'selected' : ''; ?>>Education</option>
                        <option value="Manufacturing" <?php echo ($company_details['industry_type'] === 'Manufacturing') ? 'selected' : ''; ?>>Manufacturing</option>
                        <option value="Retail" <?php echo ($company_details['industry_type'] === 'Retail') ? 'selected' : ''; ?>>Retail</option>
                        <option value="Consulting" <?php echo ($company_details['industry_type'] === 'Consulting') ? 'selected' : ''; ?>>Consulting</option>
                        <option value="Media" <?php echo ($company_details['industry_type'] === 'Media') ? 'selected' : ''; ?>>Media & Entertainment</option>
                        <option value="Automotive" <?php echo ($company_details['industry_type'] === 'Automotive') ? 'selected' : ''; ?>>Automotive</option>
                        <option value="Other" <?php echo ($company_details['industry_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="company_email">
                        <i class="fas fa-envelope"></i>
                        Company Email
                    </label>
                    <input 
                        type="email" 
                        id="company_email" 
                        name="company_email" 
                        value="<?php echo htmlspecialchars($company_details['company_email']); ?>" 
                        required
                        placeholder="company@example.com"
                        class="glass-input"
                    >
                </div>

                <div class="form-group">
                    <label for="year_established">
                        <i class="fas fa-calendar"></i>
                        Year Established
                    </label>
                    <input 
                        type="number" 
                        id="year_established" 
                        name="year_established" 
                        value="<?php echo htmlspecialchars($company_details['year_established']); ?>" 
                        min="1800" 
                        max="<?php echo date('Y'); ?>"
                        placeholder="e.g., 2010"
                        class="glass-input"
                    >
                </div>

                <div class="form-group">
                    <label for="contact_name">
                        <i class="fas fa-user"></i>
                        Contact Person Name
                    </label>
                    <input 
                        type="text" 
                        id="contact_name" 
                        name="contact_name" 
                        value="<?php echo htmlspecialchars($company_details['contact_name']); ?>" 
                        required
                        placeholder="Enter contact person name"
                        class="glass-input"
                    >
                </div>

                <div class="form-group">
                    <label for="designation">
                        <i class="fas fa-briefcase"></i>
                        Designation
                    </label>
                    <input 
                        type="text" 
                        id="designation" 
                        name="designation" 
                        value="<?php echo htmlspecialchars($company_details['designation']); ?>" 
                        required
                        placeholder="e.g., HR Manager, CEO"
                        class="glass-input"
                    >
                </div>

                <div class="form-group">
                    <label for="contact_phone">
                        <i class="fas fa-phone"></i>
                        Contact Phone
                    </label>
                    <input 
                        type="tel" 
                        id="contact_phone" 
                        name="contact_phone" 
                        value="<?php echo htmlspecialchars($company_details['contact_phone']); ?>" 
                        required
                        placeholder="+1 (555) 123-4567"
                        class="glass-input"
                    >
                </div>

                <div class="form-group">
                    <label for="status">
                        <i class="fas fa-toggle-on"></i>
                        Account Status
                    </label>
                    <select id="status" name="status" disabled class="glass-input">
                        <option value="active" <?php echo ($company_details['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($company_details['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="pending" <?php echo ($company_details['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    </select>
                    <small style="color: var(--secondary); opacity: 0.7; margin-top: 0.5rem; display: block;">
                        Account status can only be changed by administrators.
                    </small>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Update Profile
                </button>
                <a href="?page=home" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </form>
    </div>

    <!-- Additional Information Section -->
    <div class="form-container glass-card" style="margin-top: 2rem;">
        <h3 style="color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-info-circle"></i>
            Account Information
        </h3>
        
        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="form-group">
                <label>Company ID</label>
                <div class="info-display" style="background: rgba(78, 205, 196, 0.1); color: var(--primary);">
                    <?php echo htmlspecialchars($company_details['company_id']); ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Account Created</label>
                <div class="info-display" style="background: rgba(52, 152, 219, 0.1); color: var(--info);">
                    <?php echo isset($company_details['created_at']) ? date('M j, Y', strtotime($company_details['created_at'])) : 'Not available'; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Last Updated</label>
                <div class="info-display" style="background: rgba(241, 196, 15, 0.1); color: var(--warning);">
                    <?php echo isset($company_details['updated_at']) && $company_details['updated_at'] !== '0000-00-00 00:00:00' ? date('M j, Y g:i A', strtotime($company_details['updated_at'])) : 'Never'; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
/* Additional CSS for profile page to match dashboard */
.page-container {
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.page-header {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: var(--shadow-light);
    text-align: center;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent 30%, rgba(78, 205, 196, 0.08) 50%, transparent 70%);
    animation: shimmer 8s infinite;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 1rem;
    position: relative;
    z-index: 2;
}

.page-subtitle {
    font-size: 1.1rem;
    color: var(--secondary);
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
    position: relative;
    z-index: 2;
}

.glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    padding: 2rem;
    box-shadow: var(--shadow-light);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.glass-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: 18px 18px 0 0;
}

.glass-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
}

.form-container {
    width: 100%;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 600;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
}

.glass-input {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    font-size: 1rem;
    color: var(--secondary);
    transition: var(--transition);
    backdrop-filter: blur(10px);
}

.glass-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.2);
    background: rgba(255, 255, 255, 0.15);
}

.glass-input::placeholder {
    color: rgba(46, 57, 68, 0.5);
}

.info-display {
    padding: 1rem;
    border-radius: 12px;
    font-weight: 600;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

.btn-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: flex-start;
}

.btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
    min-width: 160px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(3, 89, 70, 0.3);
    text-decoration: none;
    color: white;
}

.btn-secondary {
    background: rgba(46, 57, 68, 0.1);
    color: var(--secondary);
    border: 1px solid var(--glass-border);
}

.btn-secondary:hover {
    background: rgba(46, 57, 68, 0.2);
    transform: translateY(-3px);
    text-decoration: none;
    color: var(--secondary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header {
        padding: 2rem 1.5rem;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}

@keyframes shimmer {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}
</style>

<script>
// Enhanced form validation for company profile
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const inputs = form.querySelectorAll('input, select');
    
    // Add real-time validation
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            // Clear error state when user starts typing
            this.style.borderColor = '';
            this.style.boxShadow = '';
        });
    });
    
    // Phone number formatting
    const phoneInput = document.getElementById('contact_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            }
            this.value = value;
        });
    }
    
    // Email validation
    const emailInput = document.getElementById('company_email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = 'var(--danger)';
                this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
            }
        });
    }
    
    // Year validation
    const yearInput = document.getElementById('year_established');
    if (yearInput) {
        yearInput.addEventListener('blur', function() {
            const year = parseInt(this.value);
            const currentYear = new Date().getFullYear();
            
            if (year && (year < 1800 || year > currentYear)) {
                this.style.borderColor = 'var(--danger)';
                this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
            }
        });
    }
});

function validateField(field) {
    const value = field.value.trim();
    const isRequired = field.hasAttribute('required');
    
    if (isRequired && !value) {
        field.style.borderColor = 'var(--danger)';
        field.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
        return false;
    } else if (value) {
        field.style.borderColor = 'var(--success)';
        field.style.boxShadow = '0 0 0 3px rgba(39, 174, 96, 0.1)';
        return true;
    }
    
    return true;
}

// Auto-save draft functionality
let saveTimer;
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const inputs = form.querySelectorAll('input:not([type="submit"]), select, textarea');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => {
                saveDraft();
            }, 2000); // Save draft after 2 seconds of inactivity
        });
    });
});

function saveDraft() {
    const formData = new FormData(document.querySelector('form'));
    const draftData = {};
    
    for (let [key, value] of formData.entries()) {
        if (key !== 'update_profile') {
            draftData[key] = value;
        }
    }
    
    // Save to localStorage for draft functionality
    localStorage.setItem('company_profile_draft', JSON.stringify(draftData));
}

function loadDraft() {
    const draft = localStorage.getItem('company_profile_draft');
    if (draft) {
        const draftData = JSON.parse(draft);
        Object.keys(draftData).forEach(key => {
            const field = document.querySelector(`[name="${key}"]`);
            if (field && !field.value) {
                field.value = draftData[key];
            }
        });
    }
}

// Load draft on page load
document.addEventListener('DOMContentLoaded', loadDraft);

// Clear draft on successful save
<?php if ($success_message): ?>
localStorage.removeItem('company_profile_draft');
<?php endif; ?>
</script>