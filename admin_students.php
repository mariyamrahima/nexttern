<?php
// PHP logic to connect to the database and handle POST requests
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Create log tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS recent_deleted_students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(50),
  name VARCHAR(100),
  deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS blocked_students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(50),
  name VARCHAR(100),
  blocked_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$message_status = '';
$operation_status = '';
$redirect_url = "admin_dashboard.php?page=students";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the request is for sending a message
    if (isset($_POST['send_message'])) {
        $receiver_id = $_POST['receiver_id'] ?? '';
        $subject = $_POST['message_subject'] ?? '';
        $message = $_POST['message_content'] ?? '';
        
        if (!empty($receiver_id) && !empty($subject) && !empty($message)) {
            // Create messages table if it doesn't exist
            $conn->query("CREATE TABLE IF NOT EXISTS student_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_type VARCHAR(20),
                receiver_type VARCHAR(20),
                receiver_id VARCHAR(50),
                subject VARCHAR(200),
                message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            $stmt = $conn->prepare("INSERT INTO student_messages (sender_type, receiver_type, receiver_id, subject, message) VALUES ('admin', 'student', ?, ?, ?)");
            $stmt->bind_param("sss", $receiver_id, $subject, $message);
            
            if ($stmt->execute()) {
                $message_status = 'success';
            } else {
                $message_status = 'error';
            }
            $stmt->close();
        } else {
            $message_status = 'error';
        }
        
        // Use JavaScript redirect to maintain the page structure
        echo '<script>window.location.href = "' . $redirect_url . '&msg_status=' . $message_status . '";</script>';
        exit;
    }
    
    // Check if the request is for an action (delete/block)
    if (isset($_POST['confirm_action'])) {
        $student_id = $_POST['student_id'] ?? '';
        $student_name = $_POST['student_name'] ?? '';
        $action = $_POST['confirm_action'];

        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            if ($stmt->execute()) {
                $stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO recent_deleted_students (student_id, name) VALUES (?, ?)");
                $stmt->bind_param("ss", $student_id, $student_name);
                $stmt->execute();
                $stmt->close();
                $operation_status = 'deleted';
            } else {
                $operation_status = 'error';
            }
        } elseif ($action === 'block') {
            $stmt = $conn->prepare("INSERT INTO blocked_students (student_id, name) VALUES (?, ?)");
            $stmt->bind_param("ss", $student_id, $student_name);
            if ($stmt->execute()) {
                $stmt->close();
                $operation_status = 'blocked';
            } else {
                $operation_status = 'error';
            }
        }
        
        // Use JavaScript redirect to maintain the page structure
        echo '<script>window.location.href = "' . $redirect_url . '&op_status=' . $operation_status . '";</script>';
        exit;
    }
}

// Check for message status or operation status from URL parameters
if (isset($_GET['msg_status'])) {
    $message_status = $_GET['msg_status'];
}
if (isset($_GET['op_status'])) {
    $operation_status = $_GET['op_status'];
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

/* Status Messages */
.status-message {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    opacity: 0;
    transform: translateY(-20px);
    animation: slideInDown 0.5s ease-out forwards;
}

.status-message.success {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.2);
}

.status-message.error {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger);
    border: 1px solid rgba(231, 76, 60, 0.2);
}

.status-message.info {
    background: rgba(52, 152, 219, 0.1);
    color: var(--info);
    border: 1px solid rgba(52, 152, 219, 0.2);
}

@keyframes slideInDown {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Page Header and Description */
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

/* Main Content Layout */
.content-container {
    display: grid;
    gap: 2rem;
}

.students-section {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    overflow: hidden;
}

.section-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.1);
}

.section-title {
    font-family: 'Poppins', sans-serif;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Table Styling */
.table-container {
    overflow-x: auto;
}

.students-table {
    width: 100%;
    border-collapse: collapse;
    background: transparent;
}

.students-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--primary);
    background: rgba(3, 89, 70, 0.05);
    border-bottom: 2px solid var(--glass-border);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.students-table td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--glass-border);
    color: var(--secondary);
    vertical-align: middle;
}

.students-table tbody tr {
    transition: var(--transition);
}

.students-table tbody tr:hover {
    background: rgba(78, 205, 196, 0.05);
}

.student-id {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.875rem;
    background: rgba(3, 89, 70, 0.1);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    color: var(--primary);
    font-weight: 500;
}

.student-name {
    font-weight: 600;
    color: var(--primary-dark);
}

.student-email {
    color: var(--info);
    font-size: 0.9rem;
}

/* Action Buttons */
.actions-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.action-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.375rem;
    text-decoration: none;
    min-width: fit-content;
}

.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-message {
    background: var(--info);
    color: white;
}

.btn-message:hover {
    background: #2980b9;
}

.btn-block {
    background: var(--warning);
    color: white;
}

.btn-block:hover {
    background: #e67e22;
}

.btn-delete {
    background: var(--danger);
    color: white;
}

.btn-delete:hover {
    background: #c0392b;
}

.no-data {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--secondary);
    opacity: 0.6;
}

.no-data i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: var(--accent);
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(8px);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    opacity: 0;
    transition: var(--transition);
}

.modal-overlay.show {
    display: flex;
    opacity: 1;
}

.modal {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    padding: 2rem;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    max-width: 400px;
    width: 90%;
    transform: scale(0.9);
    transition: var(--transition);
}

.modal-overlay.show .modal {
    transform: scale(1);
}

.message-modal {
    max-width: 500px;
    text-align: left;
}

.modal-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 1.5rem;
    color: white;
}

.modal-icon.delete {
    background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
}

.modal-icon.block {
    background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
}

.modal-icon.message {
    background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
}

.modal h3 {
    font-family: 'Poppins', sans-serif;
    margin-bottom: 1rem;
    color: var(--primary);
    font-size: 1.25rem;
}

.modal p {
    color: var(--secondary);
    margin-bottom: 2rem;
    opacity: 0.8;
}

.form-group {
    margin-bottom: 1.5rem;
    text-align: left;
}

.form-label {
    display: block;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    font-size: 0.9rem;
    color: var(--secondary);
    transition: var(--transition);
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--accent);
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
}

.form-textarea {
    min-height: 100px;
    resize: vertical;
    font-family: inherit;
}

.modal-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

.modal-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    min-width: 100px;
}

.btn-confirm {
    background: var(--primary);
    color: white;
}

.btn-confirm:hover {
    background: var(--primary-light);
    transform: translateY(-1px);
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.8);
    color: var(--secondary);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.9);
}

.btn-send {
    background: var(--info);
    color: white;
}

.btn-send:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

/* Recent Activities */
.recent-activities {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.activity-card {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    overflow: hidden;
}

.activity-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
}

.activity-icon.deleted {
    background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
}

.activity-icon.blocked {
    background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
}

.activity-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    color: var(--primary);
    font-size: 1.1rem;
}

.activity-content {
    padding: 1.5rem 2rem;
}

.activity-table {
    width: 100%;
    border-collapse: collapse;
}

.activity-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--primary);
    background: rgba(3, 89, 70, 0.05);
    border-bottom: 1px solid var(--glass-border);
    font-size: 0.875rem;
}

.activity-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--secondary);
    font-size: 0.9rem;
}

.activity-table tr:last-child td {
    border-bottom: none;
}

.timestamp {
    color: var(--secondary);
    opacity: 0.7;
    font-size: 0.85rem;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--secondary);
    opacity: 0.6;
}

.empty-state i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: var(--accent);
}

/* Loading States */
.loading {
    opacity: 0;
    animation: fadeIn 0.5s ease-out 0.1s forwards;
}

@keyframes fadeIn {
    to {
        opacity: 1;
    }
}

.page-header.loading,
.students-section.loading,
.activity-card.loading {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.students-section.loading { animation-delay: 0.2s; }
.activity-card.loading:nth-child(1) { animation-delay: 0.4s; }
.activity-card.loading:nth-child(2) { animation-delay: 0.6s; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .students-table th,
    .students-table td {
        padding: 0.75rem;
        font-size: 0.875rem;
    }
    
    .actions-group {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .recent-activities {
        grid-template-columns: 1fr;
    }
    
    .modal {
        margin: 1rem;
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .table-container {
        font-size: 0.8rem;
    }
    
    .students-table th,
    .students-table td {
        padding: 0.5rem;
    }
}
</style>

<?php if ($message_status === 'success'): ?>
    <div class="status-message success">
        <i class="fas fa-check-circle"></i>
        Message sent successfully!
    </div>
<?php elseif ($message_status === 'error'): ?>
    <div class="status-message error">
        <i class="fas fa-exclamation-circle"></i>
        Failed to send message. Please try again.
    </div>
<?php endif; ?>

<?php if ($operation_status === 'deleted'): ?>
    <div class="status-message info">
        <i class="fas fa-trash-alt"></i>
        Student has been successfully deleted.
    </div>
<?php elseif ($operation_status === 'blocked'): ?>
    <div class="status-message info">
        <i class="fas fa-ban"></i>
        Student has been successfully blocked.
    </div>
<?php elseif ($operation_status === 'error'): ?>
    <div class="status-message error">
        <i class="fas fa-exclamation-circle"></i>
        Operation failed. Please try again.
    </div>
<?php endif; ?>

<div class="page-header loading">
    <h1 class="page-title">
        <i class="fas fa-user-graduate"></i>
        Student Management
    </h1>
    <p class="page-description">Manage student accounts, monitor activities, and maintain platform integrity.</p>
</div>

<div class="content-container">
    <div class="students-section loading">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-users"></i>
                All Students
            </h2>
        </div>
        
        <div class="table-container">
            <?php
            // Fetch all students from the database - refresh data after operations
            $result = $conn->query("SELECT student_id, first_name, last_name, email, contact FROM students ORDER BY first_name, last_name");
            if ($result && $result->num_rows > 0): ?>
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = $result->fetch_assoc()):
                            $name = trim($row['first_name'] . " " . $row['last_name']); ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><span class="student-id"><?= htmlspecialchars($row['student_id']) ?></span></td>
                                <td><span class="student-name"><?= htmlspecialchars($name) ?></span></td>
                                <td><span class="student-email"><?= htmlspecialchars($row['email']) ?></span></td>
                                <td><?= htmlspecialchars($row['contact']) ?></td>
                                <td>
                                    <div class="actions-group">
                                        <button class="action-btn btn-message" onclick="openMessageModal('<?= htmlspecialchars($row['student_id']) ?>', '<?= htmlspecialchars($name) ?>')">
                                            <i class="fas fa-envelope"></i>
                                            Message
                                        </button>
                                        <button class="action-btn btn-block" onclick="openModal('<?= htmlspecialchars($row['student_id']) ?>', '<?= htmlspecialchars($name) ?>', 'block')">
                                            <i class="fas fa-ban"></i>
                                            Block
                                        </button>
                                        <button class="action-btn btn-delete" onclick="openModal('<?= htmlspecialchars($row['student_id']) ?>', '<?= htmlspecialchars($name) ?>', 'delete')">
                                            <i class="fas fa-trash"></i>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-user-slash"></i>
                    <h3>No Students Found</h3>
                    <p>There are currently no student records in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="recent-activities">
        <div class="activity-card loading">
            <div class="activity-header">
                <div class="activity-icon deleted">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h3 class="activity-title">Recently Deleted Students</h3>
            </div>
            <div class="activity-content">
                <?php
                $deleted_result = $conn->query("SELECT * FROM recent_deleted_students ORDER BY deleted_at DESC LIMIT 5");
                if ($deleted_result && $deleted_result->num_rows > 0): ?>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Deleted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $deleted_result->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="student-id"><?= htmlspecialchars($row['student_id']) ?></span></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><span class="timestamp"><?= date('M j, Y g:i A', strtotime($row['deleted_at'])) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No recent deletions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="activity-card loading">
            <div class="activity-header">
                <div class="activity-icon blocked">
                    <i class="fas fa-user-lock"></i>
                </div>
                <h3 class="activity-title">Blocked Students</h3>
            </div>
            <div class="activity-content">
                <?php
                $blocked_result = $conn->query("SELECT * FROM blocked_students ORDER BY blocked_at DESC LIMIT 5");
                if ($blocked_result && $blocked_result->num_rows > 0): ?>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Blocked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $blocked_result->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="student-id"><?= htmlspecialchars($row['student_id']) ?></span></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><span class="timestamp"><?= date('M j, Y g:i A', strtotime($row['blocked_at'])) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shield-alt"></i>
                        <p>No blocked students</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-icon" id="modalIcon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 id="modalTitle">Confirm Action</h3>
        <p id="modalMessage">Are you sure you want to perform this action?</p>
        
        <form method="post" id="modalForm">
            <input type="hidden" name="student_id" id="modalStudentId">
            <input type="hidden" name="student_name" id="modalStudentName">
            <input type="hidden" name="confirm_action" id="modalAction">
            
            <div class="modal-buttons">
                <button type="submit" class="modal-btn btn-confirm">Yes, Continue</button>
                <button type="button" class="modal-btn btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="messageModal">
    <div class="modal message-modal">
        <div class="modal-icon message">
            <i class="fas fa-envelope"></i>
        </div>
        <h3>Send Message</h3>
        <p>Send a message to <span id="messageStudentName"></span></p>
        
        <form method="post" id="messageForm">
            <input type="hidden" name="receiver_id" id="messageReceiverId">
            <input type="hidden" name="send_message" value="1">
            
            <div class="form-group">
                <label class="form-label" for="messageSubject">Subject</label>
                <input type="text" name="message_subject" id="messageSubject" class="form-input" placeholder="Enter message subject" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="messageContent">Message</label>
                <textarea name="message_content" id="messageContent" class="form-textarea" placeholder="Type your message here..." required></textarea>
            </div>
            
            <div class="modal-buttons">
                <button type="submit" class="modal-btn btn-send">
                    <i class="fas fa-paper-plane"></i>
                    Send Message
                </button>
                <button type="button" class="modal-btn btn-cancel" onclick="closeMessageModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// JavaScript for modal functionality
function openModal(studentId, studentName, action) {
    const modal = document.getElementById("confirmModal");
    const modalIcon = document.getElementById("modalIcon");
    const modalTitle = document.getElementById("modalTitle");
    const modalMessage = document.getElementById("modalMessage");
    
    document.getElementById("modalStudentId").value = studentId;
    document.getElementById("modalStudentName").value = studentName;
    document.getElementById("modalAction").value = action;
    
    // Set modal content based on action
    if (action === 'delete') {
        modalIcon.className = "modal-icon delete";
        modalIcon.innerHTML = '<i class="fas fa-trash-alt"></i>';
        modalTitle.textContent = "Delete Student";
        modalMessage.textContent = `Are you sure you want to permanently delete ${studentName}? This action cannot be undone.`;
    } else if (action === 'block') {
        modalIcon.className = "modal-icon block";
        modalIcon.innerHTML = '<i class="fas fa-ban"></i>';
        modalTitle.textContent = "Block Student";
        modalMessage.textContent = `Are you sure you want to block ${studentName}? They will no longer be able to access their account.`;
    }
    
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
}

function closeModal() {
    const modal = document.getElementById("confirmModal");
    modal.classList.remove("show");
    document.body.style.overflow = "auto";
}

function openMessageModal(studentId, studentName) {
    const modal = document.getElementById("messageModal");
    document.getElementById("messageStudentName").textContent = studentName;
    document.getElementById("messageReceiverId").value = studentId;
    
    // Clear form
    document.getElementById("messageSubject").value = "";
    document.getElementById("messageContent").value = "";
    
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    
    // Focus on subject field
    setTimeout(() => {
        document.getElementById("messageSubject").focus();
    }, 300);
}

function closeMessageModal() {
    const modal = document.getElementById("messageModal");
    modal.classList.remove("show");
    document.body.style.overflow = "auto";
}

// Close modal when clicking outside
document.getElementById("confirmModal").addEventListener("click", function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById("messageModal").addEventListener("click", function(e) {
    if (e.target === this) {
        closeMessageModal();
    }
});

// Close modal with Escape key
document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        closeModal();
        closeMessageModal();
    }
});

// Add loading animations on page load
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.loading');
    elements.forEach(el => {
        el.classList.remove('loading');
    });
    
    // Auto-hide status messages after 5 seconds
    const statusMessages = document.querySelectorAll('.status-message');
    statusMessages.forEach(msg => {
        setTimeout(() => {
            msg.style.animation = 'fadeOut 0.5s ease-out forwards';
            setTimeout(() => {
                msg.remove();
            }, 500);
        }, 5000);
    });
});

// Add fadeOut animation for status messages
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
`;
document.head.appendChild(style);

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    const url = new URL(window.location);
    url.searchParams.delete('msg_status');
    url.searchParams.delete('op_status');
    window.history.replaceState(null, null, url);
}
</script>

<?php
// Close the database connection
$conn->close();
?>