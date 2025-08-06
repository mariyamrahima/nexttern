<?php
// Database connection and operations
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if contact_content table exists, if not create it with existing data structure
$table_check = $conn->query("SHOW TABLES LIKE 'contact_content'");
if ($table_check->num_rows == 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS `contact_content` (
        `section_key` VARCHAR(50) NOT NULL PRIMARY KEY,
        `content` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'archived') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    replied_at TIMESTAMP NULL,
    replied_by VARCHAR(100) NULL
)");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $updated_by = 'Admin'; // Can be modified to use actual admin name from session
        
        if ($_POST['action'] === 'update_contact') {
            $success = true;
            $error_message = '';

            $fields = ['address', 'working_hours', 'university_email', 'media_email', 'sponsorship_email', 'support_email'];
            
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $update_stmt = $conn->prepare("UPDATE contact_content SET content=? WHERE section_key=?");
                    $update_stmt->bind_param("ss", $_POST[$field], $field);
                    if (!$update_stmt->execute()) {
                        $success = false;
                        $error_message = $update_stmt->error;
                        break;
                    }
                    $update_stmt->close();
                }
            }
            
            if ($success) {
                $success_message = "Contact information updated successfully!";
            } else {
                $error_message = "Error updating contact information: " . $error_message;
            }
        }
        
        if ($_POST['action'] === 'update_message_status') {
            $message_id = (int)$_POST['message_id'];
            $status = $_POST['status'];
            $replied_by = ($status === 'replied') ? $updated_by : NULL;
            $replied_at = ($status === 'replied') ? date('Y-m-d H:i:s') : NULL;
            
            $update_status = $conn->prepare("UPDATE contact_messages SET status=?, replied_by=?, replied_at=? WHERE id=?");
            $update_status->bind_param("sssi", $status, $replied_by, $replied_at, $message_id);
            
            if ($update_status->execute()) {
                $success_message = "Message status updated successfully!";
            } else {
                $error_message = "Error updating message status.";
            }
            $update_status->close();
        }
        
        if ($_POST['action'] === 'delete_message') {
            $message_id = (int)$_POST['message_id'];
            
            $delete_stmt = $conn->prepare("DELETE FROM contact_messages WHERE id=?");
            $delete_stmt->bind_param("i", $message_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Message deleted successfully!";
            } else {
                $error_message = "Error deleting message.";
            }
            $delete_stmt->close();
        }
    }
}

// Fetch current contact data for display
$contact_data = [];
$result = $conn->query("SELECT section_key, content FROM contact_content");
while ($row = $result->fetch_assoc()) {
    $contact_data[$row['section_key']] = $row['content'];
}

// Fetch contact messages with pagination
$page = isset($_GET['msg_page']) ? (int)$_GET['msg_page'] : 1;
$messages_per_page = 10;
$offset = ($page - 1) * $messages_per_page;

$messages_query = "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT $messages_per_page OFFSET $offset";
$messages_result = $conn->query($messages_query);

// Get total message count for pagination
$total_messages_result = $conn->query("SELECT COUNT(*) as total FROM contact_messages");
$total_messages = $total_messages_result->fetch_assoc()['total'];
$total_pages = ceil($total_messages / $messages_per_page);

// Get message statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_messages,
    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_messages,
    SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_messages,
    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_messages
    FROM contact_messages";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

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

.page-header { background: var(--glass-bg); backdrop-filter: blur(var(--blur)); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow-light); margin-bottom: 2rem; position: relative; overflow: hidden; }
.page-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%); }
.page-title { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
.page-title i { font-size: 1.75rem; color: var(--accent); }
.page-description { font-family: 'Roboto', sans-serif; color: var(--secondary); opacity: 0.8; font-size: 1.1rem; }

.tab-navigation { display: flex; gap: 0.5rem; margin-bottom: 2rem; background: var(--glass-bg); backdrop-filter: blur(var(--blur)); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 0.5rem; box-shadow: var(--shadow-light); }
.tab-btn { flex: 1; padding: 1rem 1.5rem; border: none; background: transparent; color: var(--secondary); font-weight: 600; border-radius: calc(var(--border-radius) - 4px); cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.tab-btn:hover { background: rgba(255, 255, 255, 0.3); color: var(--primary); }
.tab-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25); }
.tab-content { display: none; }
.tab-content.active { display: block; animation: fadeInUp 0.5s ease-out; }

@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

.contact-form, .messages-container { background: var(--glass-bg); backdrop-filter: blur(var(--blur)); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow-light); margin-bottom: 2rem; }

.form-section { margin-bottom: 2rem; }
.section-title { font-family: 'Poppins', sans-serif; font-size: 1.2rem; font-weight: 600; color: var(--primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(78, 205, 196, 0.2); }

.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
.form-group { margin-bottom: 1.5rem; }
.form-label { display: block; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem; font-size: 0.9rem; }
.form-input, .form-textarea { width: 100%; padding: 1rem; border: 1px solid var(--glass-border); border-radius: 12px; background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); font-size: 0.95rem; color: var(--secondary); transition: var(--transition); font-family: inherit; }
.form-input:focus, .form-textarea:focus { outline: none; border-color: var(--accent); background: rgba(255, 255, 255, 0.9); box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1); transform: translateY(-1px); }
.form-textarea { min-height: 120px; resize: vertical; }

.form-actions { display: flex; gap: 1rem; justify-content: flex-end; align-items: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--glass-border); }
.btn { padding: 0.75rem 2rem; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: var(--transition); display: flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.95rem; }
.btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25); }
.btn-primary:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(3, 89, 70, 0.35); }
.btn-secondary { background: rgba(255, 255, 255, 0.8); color: var(--secondary); border: 1px solid var(--glass-border); }
.btn-secondary:hover { background: rgba(255, 255, 255, 0.95); transform: translateY(-1px); }
.btn-info { background: var(--info); color: white; box-shadow: 0 4px 15px rgba(52, 152, 219, 0.25); }
.btn-info:hover { background: #2980b9; transform: translateY(-2px); }
.btn-success { background: var(--success); color: white; box-shadow: 0 4px 15px rgba(39, 174, 96, 0.25); }
.btn-success:hover { background: #219a52; transform: translateY(-2px); }
.btn-warning { background: var(--warning); color: white; box-shadow: 0 4px 15px rgba(243, 156, 18, 0.25); }
.btn-warning:hover { background: #e67e22; transform: translateY(-2px); }
.btn-danger { background: var(--danger); color: white; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.25); }
.btn-danger:hover { background: #c0392b; transform: translateY(-2px); }
.btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { background: var(--glass-bg); backdrop-filter: blur(var(--blur)); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--shadow-light); transition: var(--transition); position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; border-radius: var(--border-radius) var(--border-radius) 0 0; }
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-medium); }
.stat-card.new::before { background: var(--info); }
.stat-card.read::before { background: var(--warning); }
.stat-card.replied::before { background: var(--success); }
.stat-card.archived::before { background: var(--secondary); }
.stat-number { font-size: 2rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem; }
.stat-label { color: var(--secondary); font-weight: 500; opacity: 0.8; }

.messages-table { width: 100%; border-collapse: collapse; background: rgba(255, 255, 255, 0.5); border-radius: 12px; overflow: hidden; }
.messages-table th, .messages-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--glass-border); }
.messages-table th { background: rgba(3, 89, 70, 0.1); font-weight: 600; color: var(--primary); }
.messages-table tr:hover { background: rgba(255, 255, 255, 0.7); }
.status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
.status-new { background: rgba(52, 152, 219, 0.2); color: var(--info); }
.status-read { background: rgba(243, 156, 18, 0.2); color: var(--warning); }
.status-replied { background: rgba(39, 174, 96, 0.2); color: var(--success); }
.status-archived { background: rgba(46, 57, 68, 0.2); color: var(--secondary); }

.pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem; }
.pagination a, .pagination span { padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; color: var(--primary); background: rgba(255, 255, 255, 0.7); border: 1px solid var(--glass-border); transition: var(--transition); }
.pagination a:hover { background: var(--accent); color: white; }
.pagination .current { background: var(--primary); color: white; }

.contact-info-display { background: var(--glass-bg); backdrop-filter: blur(var(--blur)); border: 1px solid var(--glass-border); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow-light); }
.contact-item { margin-bottom: 2rem; padding: 1.5rem; background: rgba(255, 255, 255, 0.3); border-radius: 12px; border-left: 4px solid var(--accent); }
.contact-item h4 { font-family: 'Poppins', sans-serif; color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
.contact-item p { color: var(--secondary); line-height: 1.6; margin: 0; white-space: pre-line; }

.alert { padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-weight: 500; }
.alert-success { background: rgba(39, 174, 96, 0.1); color: var(--success); border: 1px solid rgba(39, 174, 96, 0.2); }
.alert-error { background: rgba(231, 76, 60, 0.1); color: var(--danger); border: 1px solid rgba(231, 76, 60, 0.2); }

.update-info { background: rgba(255, 255, 255, 0.5); padding: 1rem 1.5rem; border-radius: 12px; margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--glass-border); font-size: 0.9rem; color: var(--secondary); opacity: 0.8; }

.loading { opacity: 0; animation: fadeIn 0.5s ease-out 0.1s forwards; }
@keyframes fadeIn { to { opacity: 1; } }

.message-details { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

@media (max-width: 768px) {
    .page-header { padding: 1.5rem; }
    .page-title { font-size: 1.5rem; }
    .tab-navigation { flex-direction: column; }
    .form-grid { grid-template-columns: 1fr; }
    .form-actions { flex-direction: column; align-items: stretch; }
    .btn { justify-content: center; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .update-info { flex-direction: column; gap: 0.5rem; text-align: center; }
    .messages-table { font-size: 0.9rem; }
    .messages-table th, .messages-table td { padding: 0.75rem; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .contact-form, .messages-container { padding: 1.5rem; }
    .messages-table th, .messages-table td { padding: 0.5rem; }
    .message-details { max-width: 150px; }
}
</style>

<div class="page-header loading">
    <h1 class="page-title">
        <i class="fas fa-envelope"></i>
        Contact Management
    </h1>
    <p class="page-description">Manage contact information, view messages, and handle customer inquiries.</p>
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
    <button class="tab-btn active" onclick="switchTab('contact-info')">
        <i class="fas fa-info-circle"></i>
        Contact Info
    </button>
    <button class="tab-btn" onclick="switchTab('messages')">
        <i class="fas fa-inbox"></i>
        Messages (<?= $stats['new_messages'] ?>)
    </button>
    <button class="tab-btn" onclick="switchTab('preview')">
        <i class="fas fa-eye"></i>
        Preview
    </button>
</div>

<div id="contact-info-tab" class="tab-content active">
    <form method="post" class="contact-form loading">
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
            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                <i class="fas fa-undo"></i>
                Reset
            </button>
            <button type="button" class="btn btn-info" onclick="switchTab('preview')">
                <i class="fas fa-eye"></i>
                Preview Changes
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save Changes
            </button>
        </div>
    </form>

    <?php /* Removed update info section since contact_content table doesn't have updated_at and updated_by fields */ ?>
</div>

<div id="messages-tab" class="tab-content">
    <div class="stats-grid loading">
        <div class="stat-card new">
            <div class="stat-number"><?= $stats['new_messages'] ?></div>
            <div class="stat-label">New Messages</div>
        </div>
        <div class="stat-card read">
            <div class="stat-number"><?= $stats['read_messages'] ?></div>
            <div class="stat-label">Read Messages</div>
        </div>
        <div class="stat-card replied">
            <div class="stat-number"><?= $stats['replied_messages'] ?></div>
            <div class="stat-label">Replied Messages</div>
        </div>
        <div class="stat-card archived">
            <div class="stat-number"><?= $stats['archived_messages'] ?></div>
            <div class="stat-label">Archived Messages</div>
        </div>
    </div>

    <div class="messages-container loading">
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
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="update_message_status">
                                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                    <input type="hidden" name="status" value="read">
                                    <button type="submit" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($message['status'] !== 'replied'): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="update_message_status">
                                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                    <input type="hidden" name="status" value="replied">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="update_message_status">
                                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                    <input type="hidden" name="status" value="archived">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="fas fa-archive"></i>
                                    </button>
                                </form>
                                
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this message? This action cannot be undone.')">
                                    <input type="hidden" name="action" value="delete_message">
                                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
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
            <?php if ($page > 1): ?>
                <a href="?page=contact&msg_page=<?= $page - 1 ?>">&laquo; Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=contact&msg_page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=contact&msg_page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div style="text-align: center; padding: 3rem; color: var(--secondary); opacity: 0.7;">
            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; color: var(--accent);"></i>
            <p style="font-size: 1.1rem;">No messages found.</p>
            <p>Contact form submissions will appear here.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="preview-tab" class="tab-content">
    <div class="contact-info-display loading">
        <h3 class="section-title">
            <i class="fas fa-eye"></i>
            Contact Information Preview
        </h3>
        <div id="preview-content">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<script>
// Tab switching functionality
function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
    
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(`${tabName}-tab`).classList.add('active');
    
    if (tabName === 'preview') {
        updatePreview();
    }
}

// Update preview content
function updatePreview() {
    const address = document.getElementById('address').value;
    const workingHours = document.getElementById('working_hours').value;
    const universityEmail = document.getElementById('university_email').value;
    const mediaEmail = document.getElementById('media_email').value;
    const sponsorshipEmail = document.getElementById('sponsorship_email').value;
    const supportEmail = document.getElementById('support_email').value;
    
    let previewHTML = '';
    
    if (address) {
        previewHTML += `
            <div class="contact-item">
                <h4><i class="fas fa-map-marker-alt"></i> Office Address</h4>
                <p>${escapeHtml(address)}</p>
            </div>
        `;
    }
    
    if (workingHours) {
        previewHTML += `
            <div class="contact-item">
                <h4><i class="fas fa-clock"></i> Working Hours</h4>
                <p>${escapeHtml(workingHours)}</p>
            </div>
        `;
    }
    
    if (universityEmail) {
        previewHTML += `
            <div class="contact-item">
                <h4><i class="fas fa-university"></i> University Relations</h4>
                <p><a href="mailto:${escapeHtml(universityEmail)}" style="color: var(--accent); text-decoration: none;">${escapeHtml(universityEmail)}</a></p>
            </div>
        `;
    }
    
    if (mediaEmail) {
        previewHTML += `
            <div class="contact-item">
                <h4><i class="fas fa-bullhorn"></i> Media & PR</h4>
                <p><a href="mailto:${escapeHtml(mediaEmail)}" style="color: var(--accent); text-decoration: none;">${escapeHtml(mediaEmail)}</a></p>
            </div>
        `;
    }
    
    if (sponsorshipEmail) {
        previewHTML += `
            <div class="contact-item">
                <h4><i class="fas fa-handshake"></i> Sponsorship</h4>
                <p><a href="mailto:${escapeHtml(sponsorshipEmail)}" style="color: var(--accent); text-decoration: none;">${escapeHtml(sponsorshipEmail)}</a></p>
            </div>
        `;
    }
    
    if (supportEmail) {
        previewHTML += `
            <div class="contact-item">
                <h4><i class="fas fa-life-ring"></i> Support</h4>
                <p><a href="mailto:${escapeHtml(supportEmail)}" style="color: var(--accent); text-decoration: none;">${escapeHtml(supportEmail)}</a></p>
            </div>
        `;
    }
    
    if (!previewHTML) {
        previewHTML = `
            <div style="text-align: center; padding: 3rem; color: var(--secondary); opacity: 0.7;">
                <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; color: var(--accent);"></i>
                <p style="font-size: 1.1rem;">No contact information to preview.</p>
                <p>Fill in the contact form to see the preview.</p>
            </div>
        `;
    }
    
    document.getElementById('preview-content').innerHTML = previewHTML;
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const map = { 
        '&': '&amp;', 
        '<': '&lt;', 
        '>': '&gt;', 
        '"': '&quot;', 
        "'": '&#039;' 
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Reset form to original values
function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will restore the form to its last saved state.')) {
        location.reload();
    }
}

// Auto-refresh messages tab every 30 seconds if it's active
function autoRefreshMessages() {
    const messagesTab = document.getElementById('messages-tab');
    if (messagesTab && messagesTab.classList.contains('active')) {
        // Only refresh if on messages tab and no forms are being submitted
        const forms = document.querySelectorAll('#messages-tab form');
        let hasActiveForms = false;
        forms.forEach(form => {
            if (form.classList.contains('submitting')) {
                hasActiveForms = true;
            }
        });
        
        if (!hasActiveForms) {
            // You can implement AJAX refresh here if needed
            // For now, we'll just update the timestamp
            console.log('Auto-refresh check at:', new Date().toLocaleTimeString());
        }
    }
}

// Initialize auto-refresh (optional)
// setInterval(autoRefreshMessages, 30000);

// Handle form submissions with loading states
document.addEventListener('DOMContentLoaded', function() {
    // Update preview on load
    updatePreview();
    
    // Remove loading classes
    const elements = document.querySelectorAll('.loading');
    elements.forEach(el => el.classList.remove('loading'));
    
    // Add loading states to form submissions
    const forms = document.querySelectorAll('form[method="post"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                // Re-enable after 5 seconds as fallback
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 5000);
            }
            form.classList.add('submitting');
        });
    });
    
    // Real-time preview updates
    const formInputs = document.querySelectorAll('#contact-info-tab input, #contact-info-tab textarea');
    formInputs.forEach(input => {
        input.addEventListener('input', updatePreview);
    });
});

// Message actions with confirmation
function confirmAction(action, messageId) {
    let message = '';
    switch(action) {
        case 'delete':
            message = 'Are you sure you want to delete this message? This action cannot be undone.';
            break;
        case 'archive':
            message = 'Archive this message?';
            break;
        case 'reply':
            message = 'Mark this message as replied?';
            break;
        default:
            return true;
    }
    
    return confirm(message);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save (prevent default browser save)
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const activeTab = document.querySelector('.tab-content.active');
        if (activeTab && activeTab.id === 'contact-info-tab') {
            const form = activeTab.querySelector('form');
            if (form) {
                form.submit();
            }
        }
    }
    
    // Tab navigation with keyboard
    if (e.altKey) {
        switch(e.key) {
            case '1':
                e.preventDefault();
                switchTab('contact-info');
                break;
            case '2':
                e.preventDefault();
                switchTab('messages');
                break;
            case '3':
                e.preventDefault();
                switchTab('preview');
                break;
        }
    }
});

// Utility function to format phone numbers (removed since no phone field in current structure)
// Add email validation enhancement
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Add email validation to email inputs
const emailInputs = document.querySelectorAll('input[type="email"]');
emailInputs.forEach(input => {
    input.addEventListener('blur', function() {
        if (this.value && !validateEmail(this.value)) {
            this.style.borderColor = 'var(--danger)';
            this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
        } else {
            this.style.borderColor = '';
            this.style.boxShadow = '';
        }
    });
    
    input.addEventListener('input', function() {
        this.style.borderColor = '';
        this.style.boxShadow = '';
        updatePreview();
    });
});
</script>