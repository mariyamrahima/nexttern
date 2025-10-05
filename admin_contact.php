<?php
// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

// Initialize variables
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if contact_content table exists, if not, create it and insert default data
$table_check_content = $conn->query("SHOW TABLES LIKE 'contact_content'");
if ($table_check_content->num_rows == 0) {
    $create_contact_content_table = "CREATE TABLE `contact_content` (
        `section_key` VARCHAR(50) NOT NULL PRIMARY KEY,
        `content` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_contact_content_table);

    // Insert default data
    $default_inserts = [
        "INSERT INTO `contact_content` (`section_key`, `content`) VALUES ('university_email', 'university.relations@nexttern.com')",
        "INSERT INTO `contact_content` (`section_key`, `content`) VALUES ('media_email', 'pr@nexttern.com')",
        "INSERT INTO `contact_content` (`section_key`, `content`) VALUES ('sponsorship_email', 'pr@nexttern.com')",
        "INSERT INTO `contact_content` (`section_key`, `content`) VALUES ('support_email', 'sarvesh@nexttern.com')",
        "INSERT INTO `contact_content` (`section_key`, `content`) VALUES ('address', 'Schollverse Educare Pvt. Ltd. 901A/B, Iris Tech Park, Sector 48, Gurugram, Haryana, India - 122018')",
        "INSERT INTO `contact_content` (`section_key`, `content`) VALUES ('working_hours', 'Monday to Friday, 10:00 AM - 6:00 PM')"
    ];

    foreach ($default_inserts as $insert_query) {
        $conn->query($insert_query);
    }
}

$success_message = '';
$error_message = '';
$active_tab = 'contact-info'; // Default tab

// Handle contact info updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_contact') {
    $fields = ['address', 'working_hours', 'university_email', 'media_email', 'sponsorship_email', 'support_email'];
    $updated = true;
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $update_stmt = $conn->prepare("UPDATE contact_content SET content=? WHERE section_key=?");
            if ($update_stmt) {
                $update_stmt->bind_param("ss", $_POST[$field], $field);
                if (!$update_stmt->execute()) {
                    $updated = false;
                }
                $update_stmt->close();
            }
        }
    }
    
    if ($updated) {
        $success_message = 'Contact information updated successfully!';
    } else {
        $error_message = 'Error updating contact information.';
    }
    $active_tab = 'contact-info';
}

// Fetch current contact data
$contact_data = [];
$result = $conn->query("SELECT section_key, content FROM contact_content");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $contact_data[$row['section_key']] = $row['content'];
    }
}

// Close the connection
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #035946;
        --primary-light: #0a7058;
        --primary-dark: #023d32;
        --secondary: #2e3944;
        --accent: #4ecdc4;
        --success: #27ae60;
        --danger: #e74c3c;
        --glass-bg: rgba(255, 255, 255, 0.25);
        --glass-border: rgba(255, 255, 255, 0.3);
        --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
        --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
        --blur: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --border-radius: 16px;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background: transparent;
        margin: 0;
        padding: 0;
        min-height: 100vh;
        color: var(--secondary);
        line-height: 1.6;
    }

    .page-header {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--blur));
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--shadow-light);
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
        border-radius: 20px 20px 0 0;
        z-index: 1;
    }

    .page-header::after {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, transparent 30%, rgba(78, 205, 196, 0.08) 50%, transparent 70%);
        animation: shimmer 8s infinite;
        z-index: 1;
    }

    @keyframes shimmer {
        0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
    }

    .page-title {
        font-family: 'Poppins', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        position: relative;
        z-index: 2;
    }

    .page-description {
        font-family: 'Roboto', sans-serif;
        color: var(--secondary);
        opacity: 0.85;
        font-size: 1.1rem;
        line-height: 1.6;
        position: relative;
        z-index: 2;
    }

    .page-title i {
        font-size: 1.75rem;
        color: var(--accent);
    }

    .alert {
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    .alert-success {
        background: rgba(39, 174, 96, 0.1);
        color: var(--success);
        border: 1px solid rgba(39, 174, 96, 0.2);
    }

    .alert-error {
        background: rgba(231, 76, 60, 0.1);
        color: var(--danger);
        border: 1px solid rgba(231, 76, 60, 0.2);
    }

    .tab-navigation {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        background: var(--glass-bg);
        backdrop-filter: blur(var(--blur));
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 0.5rem;
        box-shadow: var(--shadow-light);
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    .tab-btn {
        flex: 1;
        padding: 1rem 1.5rem;
        border: none;
        background: transparent;
        color: var(--secondary);
        font-weight: 600;
        border-radius: calc(var(--border-radius) - 4px);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .tab-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        color: var(--primary);
    }

    .tab-btn.active {
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeInUp 0.5s ease-out;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .contact-form, .contact-info-display {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--blur));
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--shadow-light);
        margin-bottom: 2rem;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    .form-section {
        margin-bottom: 2rem;
    }

    .section-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid rgba(78, 205, 196, 0.2);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-input, .form-textarea {
        width: 100%;
        padding: 1rem;
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        font-size: 0.95rem;
        color: var(--secondary);
        transition: var(--transition);
        font-family: inherit;
        box-sizing: border-box;
    }

    .form-input:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--accent);
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        transform: translateY(-1px);
    }

    .form-textarea {
        min-height: 120px;
        resize: vertical;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        align-items: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--glass-border);
    }

    .btn {
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        font-size: 0.95rem;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25);
    }

    .btn-primary:hover {
        background: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(3, 89, 70, 0.35);
    }

    .contact-item {
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: rgba(255, 255, 255, 0.3);
        border-left: 4px solid var(--accent);
        border-radius: 12px;
    }

    .contact-item h4 {
        font-family: 'Poppins', sans-serif;
        color: var(--primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .contact-item p {
        color: var(--secondary);
        line-height: 1.6;
        margin: 0;
        white-space: pre-line;
    }

    @media (max-width: 768px) {
        .page-header { 
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .page-title { font-size: 1.6rem; }
        .page-description { font-size: 1rem; }
        .tab-navigation { flex-direction: column; }
        .form-grid { grid-template-columns: 1fr; }
        .form-actions { flex-direction: column; align-items: stretch; }
        .btn { justify-content: center; }
        .contact-form { padding: 1.5rem; }
    }

    @media (max-width: 480px) {
        .page-header { padding: 1.5rem 1rem; }
        .page-title { font-size: 1.4rem; }
        .contact-form { 
            padding: 1.5rem 1rem;
        }
    }
    </style>
</head>
<body>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-address-book"></i>
        Contact Information
    </h1>
    <p class="page-description">Manage and preview contact information displayed on your website.</p>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?= $success_message ?>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i>
    <?= $error_message ?>
</div>
<?php endif; ?>

<div class="tab-navigation">
    <button class="tab-btn <?= $active_tab === 'contact-info' ? 'active' : '' ?>" onclick="switchTab('contact-info')">
        <i class="fas fa-edit"></i>
        Edit Information
    </button>
    <button class="tab-btn <?= $active_tab === 'preview' ? 'active' : '' ?>" onclick="switchTab('preview')">
        <i class="fas fa-eye"></i>
        Preview
    </button>
</div>

<div id="contact-info" class="tab-content <?= $active_tab === 'contact-info' ? 'active' : '' ?>">
    <form method="post" class="contact-form">
        <input type="hidden" name="action" value="update_contact">

        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-map-marker-alt"></i>
                Office Information
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="address">Office Address</label>
                    <textarea id="address" name="address" class="form-textarea"><?= htmlspecialchars($contact_data['address'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="working_hours">Working Hours</label>
                    <textarea id="working_hours" name="working_hours" class="form-textarea"><?= htmlspecialchars($contact_data['working_hours'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-envelope"></i>
                Email Addresses
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="university_email">University Relations Email</label>
                    <input type="email" id="university_email" name="university_email" class="form-input" value="<?= htmlspecialchars($contact_data['university_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="media_email">Media & PR Email</label>
                    <input type="email" id="media_email" name="media_email" class="form-input" value="<?= htmlspecialchars($contact_data['media_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="sponsorship_email">Sponsorship Email</label>
                    <input type="email" id="sponsorship_email" name="sponsorship_email" class="form-input" value="<?= htmlspecialchars($contact_data['sponsorship_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="support_email">Support Email</label>
                    <input type="email" id="support_email" name="support_email" class="form-input" value="<?= htmlspecialchars($contact_data['support_email'] ?? '') ?>">
                </div>
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

<div id="preview" class="tab-content <?= $active_tab === 'preview' ? 'active' : '' ?>">
    <div class="contact-info-display">
        <div class="contact-item">
            <h4><i class="fas fa-map-marker-alt"></i> Office Address</h4>
            <p><?= htmlspecialchars($contact_data['address'] ?? '') ?></p>
        </div>
        <div class="contact-item">
            <h4><i class="fas fa-clock"></i> Working Hours</h4>
            <p><?= htmlspecialchars($contact_data['working_hours'] ?? '') ?></p>
        </div>
        <div class="contact-item">
            <h4><i class="fas fa-envelope"></i> Email Addresses</h4>
            <p>
                <strong>University Relations:</strong> <?= htmlspecialchars($contact_data['university_email'] ?? '') ?><br>
                <strong>Media & PR:</strong> <?= htmlspecialchars($contact_data['media_email'] ?? '') ?><br>
                <strong>Sponsorship:</strong> <?= htmlspecialchars($contact_data['sponsorship_email'] ?? '') ?><br>
                <strong>Support:</strong> <?= htmlspecialchars($contact_data['support_email'] ?? '') ?>
            </p>
        </div>
    </div>
</div>

<script>
    function switchTab(tabName) {
        const allTabs = document.querySelectorAll('.tab-content');
        allTabs.forEach(tab => tab.classList.remove('active'));

        const allButtons = document.querySelectorAll('.tab-btn');
        allButtons.forEach(btn => btn.classList.remove('active'));

        const selectedTab = document.getElementById(tabName);
        if (selectedTab) {
            selectedTab.classList.add('active');
        }

        event.target.classList.add('active');
    }
</script>

</body>
</html>