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

// Create contact_messages table for storing contact form submissions
$table_check_messages = $conn->query("SHOW TABLES LIKE 'contact_messages'");
if ($table_check_messages->num_rows == 0) {
    $create_contact_messages_table = "CREATE TABLE `contact_messages` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('new', 'read', 'archived') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_contact_messages_table);
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

// Handle message status updates or deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_message'])) {
    $action = $_POST['action_message'];
    $message_id = (int)$_POST['message_id'];
    
    if ($action === 'read' || $action === 'archived') {
        $update_status = $conn->prepare("UPDATE contact_messages SET status=? WHERE id=?");
        if ($update_status) {
            $update_status->bind_param("si", $action, $message_id);
            if ($update_status->execute()) {
                $success_message = 'Message status updated successfully!';
            } else {
                $error_message = 'Error updating message status.';
            }
            $update_status->close();
        }
    } elseif ($action === 'delete') {
        $delete_stmt = $conn->prepare("DELETE FROM contact_messages WHERE id=?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("i", $message_id);
            if ($delete_stmt->execute()) {
                $success_message = 'Message deleted successfully!';
            } else {
                $error_message = 'Error deleting message.';
            }
            $delete_stmt->close();
        }
    }
    $active_tab = 'messages'; // Stay on messages tab
}

// Pagination setup
$messages_per_page = 10;
$page = isset($_GET['msg_page']) ? (int)$_GET['msg_page'] : 1;
$offset = ($page - 1) * $messages_per_page;

// Fetch current contact data
$contact_data = [];
$result = $conn->query("SELECT section_key, content FROM contact_content");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $contact_data[$row['section_key']] = $row['content'];
    }
}

// Fetch contact messages with pagination
$messages_query = "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT $messages_per_page OFFSET $offset";
$messages_result = $conn->query($messages_query);

// Get total message count for pagination
$total_messages = 0;
$total_messages_result = $conn->query("SELECT COUNT(*) as total FROM contact_messages");
if ($total_messages_result) {
    $total_messages = $total_messages_result->fetch_assoc()['total'];
}
$total_pages = ceil($total_messages / $messages_per_page);

// Get message statistics
$stats = ['total' => 0, 'new_messages' => 0, 'read_messages' => 0, 'archived_messages' => 0];
$stats_query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_messages,
    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_messages,
    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_messages
    FROM contact_messages";
$stats_result = $conn->query($stats_query);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
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
    /* Enhanced CSS matching the about us page style */
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

    body {
        font-family: 'Roboto', sans-serif;
        background: linear-gradient(135deg, var(--bg-light) 0%, #ffffff 100%);
        margin: 0;
        padding: 20px;
        min-height: 100vh;
        color: var(--secondary);
        line-height: 1.6;
    }

    .page-header {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--blur));
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--shadow-light);
        margin-bottom: 2rem;
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
        background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
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
    }

    .page-title i {
        font-size: 1.75rem;
        color: var(--accent);
    }

    .page-description {
        font-family: 'Roboto', sans-serif;
        color: var(--secondary);
        opacity: 0.8;
        font-size: 1.1rem;
    }

    .alert {
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
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

    .contact-form, .messages-container, .contact-info-display {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--blur));
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--shadow-light);
        margin-bottom: 2rem;
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

    .btn-info {
        background: var(--info);
        color: white;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.25);
    }
    .btn-info:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }

    .btn-warning {
        background: var(--warning);
        color: white;
        box-shadow: 0 4px 15px rgba(243, 156, 18, 0.25);
    }

    .btn-warning:hover {
        background: #e67e22;
        transform: translateY(-2px);
    }

    .btn-danger {
        background: var(--danger);
        color: white;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.25);
    }

    .btn-danger:hover {
        background: #c0392b;
        transform: translateY(-2px);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--blur));
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--shadow-light);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-medium);
    }

    .stat-card.new::before { background: var(--info); }
    .stat-card.read::before { background: var(--warning); }
    .stat-card.archived::before { background: var(--secondary); }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: var(--secondary);
        font-weight: 500;
        opacity: 0.8;
    }

    .messages-table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(255, 255, 255, 0.5);
        border-radius: 12px;
        overflow: hidden;
    }

    .messages-table th, .messages-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--glass-border);
    }

    .messages-table th {
        background: rgba(3, 89, 70, 0.1);
        font-weight: 600;
        color: var(--primary);
    }

    .messages-table tr:hover {
        background: rgba(255, 255, 255, 0.7);
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-new { background: rgba(52, 152, 219, 0.2); color: var(--info); }
    .status-read { background: rgba(243, 156, 18, 0.2); color: var(--warning); }
    .status-archived { background: rgba(46, 57, 68, 0.2); color: var(--secondary); }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .pagination a, .pagination span {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        text-decoration: none;
        color: var(--primary);
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid var(--glass-border);
        transition: var(--transition);
    }

    .pagination a:hover {
        background: var(--accent);
        color: white;
    }

    .pagination .current {
        background: var(--primary);
        color: white;
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

    .message-details {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        .page-header { padding: 1.5rem; }
        .page-title { font-size: 1.5rem; }
        .tab-navigation { flex-direction: column; }
        .form-grid { grid-template-columns: 1fr; }
        .form-actions { flex-direction: column; align-items: stretch; }
        .btn { justify-content: center; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .messages-table { font-size: 0.9rem; }
        .messages-table th, .messages-table td { padding: 0.75rem; }
        .message-details { max-width: 150px; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .contact-form, .messages-container { padding: 1.5rem; }
        .messages-table th, .messages-table td { padding: 0.5rem; }
    }
    </style>
</head>
<body>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-envelope"></i>
        Contact Management
    </h1>
    <p class="page-description">Manage contact information, view messages, and handle customer inquiries.</p>
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
        <i class="fas fa-info-circle"></i>
        Contact Info
    </button>
    <button class="tab-btn <?= $active_tab === 'messages' ? 'active' : '' ?>" onclick="switchTab('messages')">
        <i class="fas fa-inbox"></i>
        Messages (<?= $stats['new_messages'] ?>)
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

<div id="messages" class="tab-content <?= $active_tab === 'messages' ? 'active' : '' ?>">
    <div class="stats-grid">
        <div class="stat-card new">
            <div class="stat-number"><?= $stats['new_messages'] ?></div>
            <div class="stat-label">New Messages</div>
        </div>
        <div class="stat-card read">
            <div class="stat-number"><?= $stats['read_messages'] ?></div>
            <div class="stat-label">Read Messages</div>
        </div>
        <div class="stat-card archived">
            <div class="stat-number"><?= $stats['archived_messages'] ?></div>
            <div class="stat-label">Archived Messages</div>
        </div>
    </div>

    <div class="messages-container">
        <h3 class="section-title">
            <i class="fas fa-inbox"></i>
            Contact Messages
        </h3>

        <?php if ($messages_result && $messages_result->num_rows > 0): ?>
        <div style="overflow-x: auto;">
            <table class="messages-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($message = $messages_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($message['name']) ?></td>
                        <td><?= htmlspecialchars($message['email']) ?></td>
                        <td><?= htmlspecialchars($message['subject']) ?></td>
                        <td class="message-details" title="<?= htmlspecialchars($message['message']) ?>">
                            <?= htmlspecialchars(substr($message['message'], 0, 50)) ?>...
                        </td>
                        <td><?= date('M j, Y', strtotime($message['created_at'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= $message['status'] ?>">
                                <?= ucfirst($message['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <?php if ($message['status'] === 'new'): ?>
                                    <form style="display: inline;" method="post">
                                        <input type="hidden" name="action_message" value="read">
                                        <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                        <button type="submit" class="btn btn-info btn-sm" title="Mark as Read">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form style="display: inline;" method="post">
                                    <input type="hidden" name="action_message" value="archived">
                                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                    <button type="submit" class="btn btn-warning btn-sm" title="Archive">
                                        <i class="fas fa-archive"></i>
                                    </button>
                                </form>
                                
                                <form style="display: inline;" method="post" onsubmit="return confirm('Are you sure you want to delete this message?')">
                                    <input type="hidden" name="action_message" value="delete">
                                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?msg_page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: var(--secondary); opacity: 0.7;">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p>No messages found.</p>
            </div>
        <?php endif; ?>
    </div>
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
    // Simple tab switching function
    function switchTab(tabName) {
        // Hide all tab contents
        const allTabs = document.querySelectorAll('.tab-content');
        allTabs.forEach(tab => tab.classList.remove('active'));

        // Remove active class from all tab buttons
        const allButtons = document.querySelectorAll('.tab-btn');
        allButtons.forEach(btn => btn.classList.remove('active'));

        // Show selected tab content
        const selectedTab = document.getElementById(tabName);
        if (selectedTab) {
            selectedTab.classList.add('active');
        }

        // Add active class to clicked button
        event.target.classList.add('active');
    }
</script>

</body>
</html>