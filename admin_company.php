<?php

// PHP logic to connect to the database and handle POST requests
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Create log tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS recent_deleted_companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id VARCHAR(50),
  company_name VARCHAR(100),
  company_email VARCHAR(100),
  deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS blocked_companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id VARCHAR(50),
  company_name VARCHAR(100),
  company_email VARCHAR(100),
  blocked_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS company_approvals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id VARCHAR(50),
  company_name VARCHAR(100),
  company_email VARCHAR(100),
  action VARCHAR(10),
  admin_notes TEXT,
  processed_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Create messages table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS company_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_type VARCHAR(20),
    receiver_type VARCHAR(20),
    receiver_id VARCHAR(50),
    subject VARCHAR(200),
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$message_status = '';
$operation_status = '';
$cleanup_status = '';
$redirect_url = "admin_dashboard.php?page=companies";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the request is for sending a broadcast message
    if (isset($_POST['send_broadcast'])) {
        $subject = $_POST['message_subject'] ?? '';
        $message = $_POST['message_content'] ?? '';
        $target_status = $_POST['target_status'] ?? 'all';
        
        if (!empty($subject) && !empty($message)) {
            // Build query based on target status
            $query = "SELECT company_id FROM companies";
            if ($target_status !== 'all') {
                if ($target_status === 'blocked') {
                    $query .= " WHERE status = 'inactive'";
                } else {
                    $query .= " WHERE status = '" . $conn->real_escape_string($target_status) . "'";
                }
            }
            
            $companies_result = $conn->query($query);
            $message_count = 0;
            
            if ($companies_result && $companies_result->num_rows > 0) {
                $stmt = $conn->prepare("INSERT INTO company_messages (sender_type, receiver_type, receiver_id, subject, message) VALUES ('admin', 'company', ?, ?, ?)");
                
                while ($company = $companies_result->fetch_assoc()) {
                    $company_id = $company['company_id'];
                    $stmt->bind_param("sss", $company_id, $subject, $message);
                    if ($stmt->execute()) {
                        $message_count++;
                    }
                }
                $stmt->close();
                
                if ($message_count > 0) {
                    $message_status = 'broadcast_success';
                   
                    $_SESSION['broadcast_count'] = $message_count;
                    $_SESSION['broadcast_target'] = $target_status;
                } else {
                    $message_status = 'error';
                }
            } else {
                $message_status = 'no_companies';
            }
        } else {
            $message_status = 'error';
        }
        
        echo '<script>window.location.href = "' . $redirect_url . '&msg_status=' . $message_status . '";</script>';
        exit;
    }
    
    // Check if the request is for cleanup
    if (isset($_POST['cleanup_action'])) {
        $cleanup_action = $_POST['cleanup_action'];
        
        if ($cleanup_action === 'cleanup_deleted') {
            $stmt = $conn->prepare("DELETE FROM recent_deleted_companies");
            if ($stmt->execute()) {
                $cleanup_status = 'deleted_cleared';
            } else {
                $cleanup_status = 'error';
            }
            $stmt->close();
        } elseif ($cleanup_action === 'cleanup_blocked') {
            $stmt = $conn->prepare("DELETE FROM blocked_companies");
            if ($stmt->execute()) {
                $cleanup_status = 'blocked_cleared';
            } else {
                $cleanup_status = 'error';
            }
            $stmt->close();
        }
        
        echo '<script>window.location.href = "' . $redirect_url . '&cleanup_status=' . $cleanup_status . '";</script>';
        exit;
    }
    
    // Check if the request is for sending a message
    if (isset($_POST['send_message'])) {
        $receiver_id = $_POST['receiver_id'] ?? '';
        $subject = $_POST['message_subject'] ?? '';
        $message = $_POST['message_content'] ?? '';
        
        if (!empty($receiver_id) && !empty($subject) && !empty($message)) {
            $stmt = $conn->prepare("INSERT INTO company_messages (sender_type, receiver_type, receiver_id, subject, message) VALUES ('admin', 'company', ?, ?, ?)");
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
        
        echo '<script>window.location.href = "' . $redirect_url . '&msg_status=' . $message_status . '";</script>';
        exit;
    }
    
    // Check if the request is for an action (delete/block/unblock/accept/reject)
    if (isset($_POST['confirm_action'])) {
        $company_id = $_POST['company_id'] ?? '';
        $company_name = $_POST['company_name'] ?? '';
        $company_email = $_POST['company_email'] ?? '';
        $admin_notes = $_POST['admin_notes'] ?? '';
        $action = $_POST['confirm_action'];

        if ($action === 'accept') {
            $stmt = $conn->prepare("UPDATE companies SET status = 'active' WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            if ($stmt->execute()) {
                $stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO company_approvals (company_id, company_name, company_email, action, admin_notes) VALUES (?, ?, ?, 'accept', ?)");
                $stmt->bind_param("ssss", $company_id, $company_name, $company_email, $admin_notes);
                $stmt->execute();
                $stmt->close();
                $operation_status = 'accepted';
            } else {
                $operation_status = 'error';
            }
        }

        if ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE companies SET status = 'reject' WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            if ($stmt->execute()) {
                $stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO company_approvals (company_id, company_name, company_email, action, admin_notes) VALUES (?, ?, ?, 'reject', ?)");
                $stmt->bind_param("ssss", $company_id, $company_name, $company_email, $admin_notes);
                $stmt->execute();
                $stmt->close();
                $operation_status = 'rejected';
            } else {
                $operation_status = 'error';
            }
        }

        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM companies WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            if ($stmt->execute()) {
                $stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO recent_deleted_companies (company_id, company_name, company_email) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $company_id, $company_name, $company_email);
                $stmt->execute();
                $stmt->close();
                $operation_status = 'deleted';
            } else {
                $operation_status = 'error';
            }
        }

        if ($action === 'block') {
            $check_stmt = $conn->prepare("SELECT id FROM blocked_companies WHERE company_id = ?");
            $check_stmt->bind_param("s", $company_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO blocked_companies (company_id, company_name, company_email) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $company_id, $company_name, $company_email);
                $stmt->execute();
                $stmt->close();
            }
            $check_stmt->close();

            $stmt = $conn->prepare("UPDATE companies SET status = 'inactive' WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            if ($stmt->execute()) {
                $stmt->close();
                $operation_status = 'blocked';
            } else {
                $operation_status = 'error';
            }
        }

        if ($action === 'unblock') {
            $stmt = $conn->prepare("DELETE FROM blocked_companies WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE companies SET status = 'active' WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            if ($stmt->execute()) {
                $stmt->close();
                $operation_status = 'unblocked';
            } else {
                $operation_status = 'error';
            }
        }
        
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
if (isset($_GET['cleanup_status'])) {
    $cleanup_status = $_GET['cleanup_status'];
}

// Get total company count for display
$company_count_result = $conn->query("SELECT COUNT(*) as total FROM companies");
$total_companies = 0;
if ($company_count_result) {
    $count_row = $company_count_result->fetch_assoc();
    $total_companies = $count_row['total'];
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
    --pending: #9b59b6;
    --rejected: #e67e22;
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

.status-message.broadcast {
    background: linear-gradient(135deg, rgba(78, 205, 196, 0.15) 0%, rgba(3, 89, 70, 0.1) 100%);
    color: var(--primary);
    border: 1px solid rgba(78, 205, 196, 0.3);
    font-size: 1.05rem;
}

.status-message .count-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
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

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.header-text {
    flex: 1;
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

.broadcast-btn-container {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.btn-broadcast-all {
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: var(--shadow-light);
}

.btn-broadcast-all:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
    background: linear-gradient(135deg, var(--primary-light) 0%, var(--accent) 100%);
}

.btn-broadcast-all i {
    font-size: 1.25rem;
}

.company-count-badge {
    background: rgba(3, 89, 70, 0.1);
    color: var(--primary);
    padding: 0.375rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Main Content Layout */
.content-container {
    display: grid;
    gap: 2rem;
}

.companies-section {
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
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.header-row-1 {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-row-2 {
  display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
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

.stats-summary {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
    flex-wrap: wrap;
    order: 2;
}

.controls-group {
     display: flex;
    gap: 1rem;
    align-items: center;
    order: 1;
}

.search-input {
    padding: 0.5rem 1rem;
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    font-size: 0.9rem;
    color: var(--secondary);
    transition: var(--transition);
    min-width: 250px;
}

.search-input:focus {
    outline: none;
    border-color: var(--accent);
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
}

.search-input::placeholder {
    color: var(--secondary);
    opacity: 0.6;
}

.status-filter {
    padding: 0.5rem 1rem;
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    font-size: 0.9rem;
    color: var(--secondary);
    cursor: pointer;
    transition: var(--transition);
    min-width: 140px;
}

.status-filter:focus {
    outline: none;
    border-color: var(--accent);
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: 500;
}

.stat-total {
    background: rgba(3, 89, 70, 0.1);
    color: var(--primary);
}

.stat-active {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success);
}

.stat-blocked {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger);
}

.stat-pending {
    background: rgba(155, 89, 182, 0.1);
    color: var(--pending);
}

.stat-rejected {
    background: rgba(230, 126, 34, 0.1);
    color: var(--rejected);
}

/* Table Styling */
.table-container {
    overflow-x: auto;
}

.companies-table, .activity-table {
    width: 100%;
    border-collapse: collapse;
    background: transparent;
}

.companies-table th, .activity-table th {
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

.companies-table td, .activity-table td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--glass-border);
    color: var(--secondary);
    vertical-align: middle;
}

.companies-table tbody tr, .activity-table tbody tr {
    transition: var(--transition);
}

.companies-table tbody tr:hover, .activity-table tbody tr:hover {
    background: rgba(78, 205, 196, 0.05);
}

.company-id {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.875rem;
    background: rgba(3, 89, 70, 0.1);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    color: var(--primary);
    font-weight: 500;
}

.company-name {
    font-weight: 600;
    color: var(--primary-dark);
    font-size: 1rem;
}

.company-email {
    color: var(--info);
    font-size: 0.9rem;
}

.industry-badge {
    display: inline-block;
    background: rgba(78, 205, 196, 0.1);
    color: var(--accent);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    border: 1px solid rgba(78, 205, 196, 0.3);
}

.contact-info {
    font-size: 0.9rem;
}

.contact-name {
    font-weight: 500;
    color: var(--primary-dark);
}

.contact-details {
    color: var(--secondary);
    opacity: 0.8;
    font-size: 0.85rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-active {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.3);
}

.status-blocked {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger);
    border: 1px solid rgba(231, 76, 60, 0.3);
}

.status-pending {
    background: rgba(155, 89, 182, 0.1);
    color: var(--pending);
    border: 1px solid rgba(155, 89, 182, 0.3);
}

.status-rejected,
.status-reject {
    background: rgba(230, 126, 34, 0.1);
    color: var(--rejected);
    border: 1px solid rgba(230, 126, 34, 0.3);
}

/* Action Buttons */
.actions-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
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

.btn-accept {
    background: var(--success);
    color: white;
}

.btn-accept:hover {
    background: #229954;
}

.btn-reject {
    background: var(--rejected);
    color: white;
}

.btn-reject:hover {
    background: #d35400;
}

.btn-block {
    background: var(--warning);
    color: white;
}

.btn-block:hover {
    background: #e67e22;
}

.btn-unblock {
    background: var(--success);
    color: white;
}

.btn-unblock:hover {
    background: #229954;
}

.btn-delete {
    background: var(--danger);
    color: white;
}

.btn-delete:hover {
    background: #c0392b;
}

.btn-cleanup {
    background: var(--warning);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-cleanup:hover {
    background: #e67e22;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-excel {
    background: #217346;
    color: white;
}

.btn-excel:hover {
    background: #1a5c37;
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
    overflow-y: auto; /* Add this */
    padding: 2rem 0; /* Add padding for breathing room */
}
.modal-overlay.show {
    display: flex;
    opacity: 1;
    align-items: flex-start; /* Change from center to flex-start */
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
    margin: auto; /* Add this for vertical centering when content is short */
    max-height: calc(100vh - 4rem); /* Prevent modal from exceeding viewport */
    overflow-y: auto; /* Make modal content scrollable */
}
.modal-overlay.show .modal {
    transform: scale(1);
}

.message-modal {
    max-width: 500px;
    text-align: left;
}

.approval-modal {
    max-width: 550px;
    text-align: left;
}

.broadcast-modal {
    max-width: 600px;
    text-align: left;
}

.export-modal {
    max-width: 600px;
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

.modal-icon.unblock {
    background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
}

.modal-icon.message {
    background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
}

.modal-icon.accept {
    background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
}

.modal-icon.reject {
    background: linear-gradient(135deg, var(--rejected) 0%, #d35400 100%);
}

.modal-icon.cleanup {
    background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
}

.modal-icon.broadcast {
    background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
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

.broadcast-info {
    background: rgba(78, 205, 196, 0.1);
    border: 1px solid rgba(78, 205, 196, 0.2);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.broadcast-info i {
    color: var(--accent);
    font-size: 1.25rem;
}

.broadcast-info-text {
    flex: 1;
}

.broadcast-info strong {
    color: var(--primary);
    display: block;
    margin-bottom: 0.25rem;
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
.form-textarea,
.form-select {
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
.form-textarea:focus,
.form-select:focus {
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

.target-status-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.target-option {
    position: relative;
}

.target-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.target-option label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.9rem;
    font-weight: 500;
}

.target-option input[type="radio"]:checked + label {
    border-color: var(--accent);
    background: rgba(78, 205, 196, 0.1);
    color: var(--primary);
}

.target-option label:hover {
    background: rgba(255, 255, 255, 0.7);
}

.target-option i {
    font-size: 1rem;
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

.btn-approve {
    background: var(--success);
    color: white;
}

.btn-approve:hover {
    background: #229954;
    transform: translateY(-1px);
}

.btn-reject-confirm {
    background: var(--rejected);
    color: white;
}

.btn-reject-confirm:hover {
    background: #d35400;
    transform: translateY(-1px);
}

.btn-cleanup-confirm {
    background: var(--warning);
    color: white;
}

.btn-cleanup-confirm:hover {
    background: #e67e22;
    transform: translateY(-1px);
}

.btn-broadcast {
    background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
    color: white;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-broadcast:hover {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    transform: translateY(-1px);
}

.filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-grid-full {
    grid-column: 1 / -1;
}

/* Recent Activities */
.recent-activities {
    display: flex;
    flex-direction: column;
    gap: 2rem;
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
    justify-content: space-between;
    align-items: center;
}

.activity-header-left {
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

.activity-icon.approvals {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
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

.approval-action {
    font-weight: 600;
    text-transform: capitalize;
}

.approval-action.accepted,
.approval-action.accept {
    color: var(--success);
}

.approval-action.rejected,
.approval-action.reject {
    color: var(--rejected);
}

.approval-notes {
    font-style: italic;
    color: var(--secondary);
    opacity: 0.8;
    max-width: 800px;
    overflow: hidden;
}

/* Animation Styles */
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
.companies-section.loading,
.activity-card.loading {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.companies-section.loading { animation-delay: 0.2s; }
.activity-card.loading:nth-child(1) { animation-delay: 0.4s; }
.activity-card.loading:nth-child(2) { animation-delay: 0.6s; }
.activity-card.loading:nth-child(3) { animation-delay: 0.8s; }

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
@media (max-width: 992px) {
    .header-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .broadcast-btn-container {
        align-items: stretch;
    }
    
    .btn-broadcast-all {
        width: 100%;
        justify-content: center;
    }
    
    .target-status-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .section-header {
        gap: 1.5rem;
    }
    
    .header-row-2 {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .stats-summary {
        flex-wrap: wrap;
        width: 100%;
    }
    
    .controls-group {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input,
    .status-filter {
        min-width: unset;
        width: 100%;
    }
    
    .companies-table th,
    .companies-table td,
    .activity-table th,
    .activity-table td {
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
    
    .activity-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .modal {
        margin: 1rem;
        padding: 1.5rem;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .table-container {
        font-size: 0.8rem;
    }
    
    .companies-table th,
    .companies-table td,
    .activity-table th,
    .activity-table td {
        padding: 0.5rem;
    }
    
    .stats-summary {
        gap: 0.5rem;
    }
    
    .stat-item {
        font-size: 0.8rem;
        padding: 0.2rem 0.6rem;
    }
}
</style>

<?php
// Fetch total companies count
$broadcast_count = isset($_SESSION['broadcast_count']) ? $_SESSION['broadcast_count'] : 0;
$broadcast_target = isset($_SESSION['broadcast_target']) ? $_SESSION['broadcast_target'] : 'all';
?>

<?php if ($message_status === 'success'): ?>
    <div class="status-message success">
        <i class="fas fa-check-circle"></i>
        Message sent successfully!
    </div>
<?php elseif ($message_status === 'broadcast_success'): ?>
    <div class="status-message broadcast">
        <i class="fas fa-bullhorn"></i>
        Broadcast message sent successfully to
        <span class="count-badge"><?= $broadcast_count ?> companies</span>
        <?php if ($broadcast_target !== 'all'): ?>
            (<?= ucfirst($broadcast_target === 'blocked' ? 'Blocked' : $broadcast_target) ?> companies)
        <?php endif; ?>
    </div>
    <?php 
    unset($_SESSION['broadcast_count']);
    unset($_SESSION['broadcast_target']);
    ?>
<?php elseif ($message_status === 'no_companies'): ?>
    <div class="status-message error">
        <i class="fas fa-exclamation-triangle"></i>
        No companies found matching the selected criteria.
    </div>
<?php elseif ($message_status === 'error'): ?>
    <div class="status-message error">
        <i class="fas fa-exclamation-circle"></i>
        Failed to send message. Please try again.
    </div>
<?php endif; ?>

<?php if ($operation_status === 'accepted'): ?>
    <div class="status-message info">
        <i class="fas fa-check-circle"></i>
        Company application has been accepted successfully.
    </div>
<?php elseif ($operation_status === 'rejected'): ?>
    <div class="status-message info">
        <i class="fas fa-times-circle"></i>
        Company application has been rejected successfully.
    </div>
<?php elseif ($operation_status === 'deleted'): ?>
    <div class="status-message info">
        <i class="fas fa-trash-alt"></i>
        Company has been successfully deleted.
    </div>
<?php elseif ($operation_status === 'blocked'): ?>
    <div class="status-message info">
        <i class="fas fa-ban"></i>
        Company has been successfully blocked.
    </div>
<?php elseif ($operation_status === 'unblocked'): ?>
    <div class="status-message info">
        <i class="fas fa-check-circle"></i>
        Company has been successfully unblocked.
    </div>
<?php elseif ($operation_status === 'error'): ?>
    <div class="status-message error">
        <i class="fas fa-exclamation-circle"></i>
        Operation failed. Please try again.
    </div>
<?php endif; ?>

<?php if ($cleanup_status === 'deleted_cleared'): ?>
    <div class="status-message success">
        <i class="fas fa-trash-alt"></i>
        All deleted company records have been cleared successfully.
    </div>
<?php elseif ($cleanup_status === 'blocked_cleared'): ?>
    <div class="status-message success">
        <i class="fas fa-broom"></i>
        All blocked company records have been cleared successfully.
    </div>
<?php elseif ($cleanup_status === 'error'): ?>
    <div class="status-message error">
        <i class="fas fa-exclamation-circle"></i>
        Cleanup operation failed. Please try again.
    </div>
<?php endif; ?>

<div class="page-header loading">
    <div class="header-content">
        <div class="header-text">
            <h1 class="page-title">
                <i class="fas fa-building"></i>
                Company Management
            </h1>
            <p class="page-description">Manage company accounts, review applications, and maintain platform integrity.</p>
        </div>
        <div class="broadcast-btn-container">
            <button class="btn-broadcast-all" onclick="openBroadcastModal()">
                <i class="fas fa-bullhorn"></i>
                Send Message to Companies
            </button>
            <div class="company-count-badge">
                <i class="fas fa-building"></i>
                <?= $total_companies ?> Total Companies
            </div>
        </div>
    </div>
</div>

<div class="content-container">
    <div class="companies-section loading">
        <div class="section-header">
            <div class="header-row-1">
                <h2 class="section-title">
                    <i class="fas fa-industry"></i>
                    All Companies
                </h2>
            </div>
            
            <?php
            // Get statistics
            $total_result = $conn->query("SELECT COUNT(*) as total FROM companies");
            $active_result = $conn->query("SELECT COUNT(*) as active FROM companies WHERE status = 'active'");
            $blocked_result = $conn->query("SELECT COUNT(*) as blocked FROM companies WHERE status = 'inactive'");
            $pending_result = $conn->query("SELECT COUNT(*) as pending FROM companies WHERE status = 'pending'");
            $rejected_result = $conn->query("SELECT COUNT(*) as rejected FROM companies WHERE status = 'reject'");
            
            $total = $total_result->fetch_assoc()['total'];
            $active = $active_result->fetch_assoc()['active'];
            $blocked = $blocked_result->fetch_assoc()['blocked'];
            $pending = $pending_result->fetch_assoc()['pending'];
            $rejected = $rejected_result->fetch_assoc()['rejected'];
            ?>
            
            <div class="header-row-2">
                <div class="stats-summary">
                    <div class="stat-item stat-total">
                        <i class="fas fa-building"></i>
                        Total: <?= $total ?>
                    </div>
                    <div class="stat-item stat-active">
                        <i class="fas fa-check-circle"></i>
                        Active: <?= $active ?>
                    </div>
                    <div class="stat-item stat-pending">
                        <i class="fas fa-clock"></i>
                        Pending: <?= $pending ?>
                    </div>
                    <div class="stat-item stat-blocked">
                        <i class="fas fa-ban"></i>
                        Blocked: <?= $blocked ?>
                    </div>
                    <div class="stat-item stat-rejected">
                        <i class="fas fa-times-circle"></i>
                        Rejected: <?= $rejected ?>
                    </div>
                </div>
                
                <div class="controls-group">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search companies...">
                    <select id="statusFilter" class="status-filter">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="inactive">Blocked</option>
                        <option value="reject">Rejected</option>
                    </select>
                    <button class="action-btn btn-excel" onclick="openExportModal()">
                        <i class="fas fa-file-excel"></i>
                        Export Excel
                    </button>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <?php
            $result = $conn->query("SELECT company_id, company_name, industry_type, company_email, year_established, contact_name, designation, contact_phone, status FROM companies ORDER BY 
    CASE 
        WHEN status = 'pending' THEN 1 
        WHEN status = 'active' THEN 2 
        WHEN status = 'inactive' THEN 3 
        ELSE 4 
    END, id DESC");
            if ($result && $result->num_rows > 0): ?>
                <table class="companies-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Company ID</th>
                            <th>Company Details</th>
                            <th>Industry</th>
                            <th>Contact Person</th>
                            <th>Year Est.</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><span class="company-id"><?= htmlspecialchars($row['company_id']) ?></span></td>
                                <td>
                                    <div class="company-name"><?= htmlspecialchars($row['company_name']) ?></div>
                                    <div class="company-email"><?= htmlspecialchars($row['company_email']) ?></div>
                                </td>
                                <td><span class="industry-badge"><?= htmlspecialchars($row['industry_type']) ?></span></td>
                                <td>
                                    <div class="contact-info">
                                        <div class="contact-name"><?= htmlspecialchars($row['contact_name']) ?></div>
                                        <div class="contact-details"><?= htmlspecialchars($row['designation']) ?></div>
                                        <div class="contact-details"><?= htmlspecialchars($row['contact_phone']) ?></div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['year_established']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $row['status'] === 'inactive' ? 'blocked' : $row['status'] ?>">
                                        <i class="fas fa-<?= 
                                            $row['status'] === 'active' ? 'check-circle' : 
                                            ($row['status'] === 'pending' ? 'clock' : 
                                            ($row['status'] === 'inactive' ? 'ban' : 'times-circle')) 
                                        ?>"></i>
                                        <?= $row['status'] === 'inactive' ? 'Blocked' : ($row['status'] === 'reject' ? 'Rejected' : ucfirst($row['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-group">
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <button class="action-btn btn-accept" onclick="openApprovalModal('<?= htmlspecialchars($row['company_id']) ?>', '<?= htmlspecialchars($row['company_name']) ?>', '<?= htmlspecialchars($row['company_email']) ?>', 'accept')">
                                                <i class="fas fa-check"></i>
                                                Accept
                                            </button>
                                            <button class="action-btn btn-reject" onclick="openApprovalModal('<?= htmlspecialchars($row['company_id']) ?>', '<?= htmlspecialchars($row['company_name']) ?>', '<?= htmlspecialchars($row['company_email']) ?>', 'reject')">
                                                <i class="fas fa-times"></i>
                                                Reject
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="action-btn btn-message" onclick="openMessageModal('<?= htmlspecialchars($row['company_id']) ?>', '<?= htmlspecialchars($row['company_name']) ?>')">
                                            <i class="fas fa-envelope"></i>
                                            Message
                                        </button>
                                        
                                        <?php if ($row['status'] === 'active'): ?>
                                            <button class="action-btn btn-block" onclick="openModal('<?= htmlspecialchars($row['company_id']) ?>', '<?= htmlspecialchars($row['company_name']) ?>', '<?= htmlspecialchars($row['company_email']) ?>', 'block')">
                                                <i class="fas fa-ban"></i>
                                                Block
                                            </button>
                                        <?php elseif ($row['status'] === 'inactive'): ?>
                                            <button class="action-btn btn-unblock" onclick="openModal('<?= htmlspecialchars($row['company_id']) ?>', '<?= htmlspecialchars($row['company_name']) ?>', '<?= htmlspecialchars($row['company_email']) ?>', 'unblock')">
                                                <i class="fas fa-check-circle"></i>
                                                Unblock
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="action-btn btn-delete" onclick="openModal('<?= htmlspecialchars($row['company_id']) ?>', '<?= htmlspecialchars($row['company_name']) ?>', '<?= htmlspecialchars($row['company_email']) ?>', 'delete')">
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
                    <i class="fas fa-building-slash"></i>
                    <h3>No Companies Found</h3>
                    <p>There are currently no company records in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="recent-activities">
        <!-- Recently Deleted Companies -->
        <div class="activity-card loading">
            <div class="activity-header">
                <div class="activity-header-left">
                    <div class="activity-icon deleted">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <h3 class="activity-title">Recently Deleted Companies</h3>
                </div>
                <?php
                $deleted_count_result = $conn->query("SELECT COUNT(*) as count FROM recent_deleted_companies");
                $deleted_count = $deleted_count_result->fetch_assoc()['count'];
                if ($deleted_count > 0): ?>
                    <button class="btn-cleanup" onclick="openCleanupModal('deleted')">
                        <i class="fas fa-broom"></i>
                        Clear Up
                    </button>
                <?php endif; ?>
            </div>
            <div class="activity-content">
                <div class="table-container">
                    <?php
                    $deleted_result = $conn->query("SELECT * FROM recent_deleted_companies ORDER BY deleted_at DESC");
                    if ($deleted_result && $deleted_result->num_rows > 0): ?>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Company ID</th>
                                    <th>Company Name</th>
                                    <th>Email</th>
                                    <th>Deleted At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; while ($row = $deleted_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><span class="company-id"><?= htmlspecialchars($row['company_id']) ?></span></td>
                                        <td class="company-name"><?= htmlspecialchars($row['company_name']) ?></td>
                                        <td class="company-email"><?= htmlspecialchars($row['company_email']) ?></td>
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
        </div>

        <!-- Blocked Companies -->
        <div class="activity-card loading">
            <div class="activity-header">
                <div class="activity-header-left">
                    <div class="activity-icon blocked">
                        <i class="fas fa-building-lock"></i>
                    </div>
                    <h3 class="activity-title">Blocked Companies</h3>
                </div>
                <?php
                $blocked_count_result = $conn->query("SELECT COUNT(*) as count FROM blocked_companies");
                $blocked_count = $blocked_count_result->fetch_assoc()['count'];
                if ($blocked_count > 0): ?>
                    <button class="btn-cleanup" onclick="openCleanupModal('blocked')">
                        <i class="fas fa-broom"></i>
                        Clear Up
                    </button>
                <?php endif; ?>
            </div>
            <div class="activity-content">
                <div class="table-container">
                    <?php
                    $blocked_companies_result = $conn->query("SELECT * FROM blocked_companies ORDER BY blocked_at DESC");
                    if ($blocked_companies_result && $blocked_companies_result->num_rows > 0): ?>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Company ID</th>
                                    <th>Company Name</th>
                                    <th>Email</th>
                                    <th>Blocked At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; while ($row = $blocked_companies_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><span class="company-id"><?= htmlspecialchars($row['company_id']) ?></span></td>
                                        <td class="company-name"><?= htmlspecialchars($row['company_name']) ?></td>
                                        <td class="company-email"><?= htmlspecialchars($row['company_email']) ?></td>
                                        <td><span class="timestamp"><?= date('M j, Y g:i A', strtotime($row['blocked_at'])) ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No blocked companies</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Approvals -->
        <div class="activity-card loading">
            <div class="activity-header">
                <div class="activity-header-left">
                    <div class="activity-icon approvals">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3 class="activity-title">Recent Approvals</h3>
                </div>
            </div>
            <div class="activity-content">
                <div class="table-container">
                    <?php
                    $approvals_result = $conn->query("SELECT * FROM company_approvals ORDER BY processed_at DESC");
                    if ($approvals_result && $approvals_result->num_rows > 0): ?>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Company ID</th>
                                    <th>Company Name</th>
                                    <th>Email</th>
                                    <th>Action</th>
                                    <th>Notes</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; while ($row = $approvals_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><span class="company-id"><?= htmlspecialchars($row['company_id']) ?></span></td>
                                        <td class="company-name"><?= htmlspecialchars($row['company_name']) ?></td>
                                        <td class="company-email"><?= htmlspecialchars($row['company_email']) ?></td>
                                        <td><span class="approval-action <?= $row['action'] ?>"><?= $row['action'] === 'accept' ? 'Accepted' : ($row['action'] === 'reject' ? 'Rejected' : ucfirst($row['action'])) ?></span></td>
                                        <td><span class="approval-notes" title="<?= htmlspecialchars($row['admin_notes']) ?>"><?= htmlspecialchars($row['admin_notes'] ? $row['admin_notes'] : 'No notes') ?></span></td>
                                        <td><span class="timestamp"><?= date('M j, Y g:i A', strtotime($row['processed_at'])) ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard"></i>
                            <p>No recent approvals</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal (Delete/Block/Unblock) -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-icon" id="modalIcon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 id="modalTitle">Confirm Action</h3>
        <p id="modalMessage">Are you sure you want to perform this action?</p>
        
        <form method="post" id="modalForm">
            <input type="hidden" name="company_id" id="modalCompanyId">
            <input type="hidden" name="company_name" id="modalCompanyName">
            <input type="hidden" name="company_email" id="modalCompanyEmail">
            <input type="hidden" name="confirm_action" id="modalAction">
            
            <div class="modal-buttons">
                <button type="submit" class="modal-btn btn-confirm">Yes, Continue</button>
                <button type="button" class="modal-btn btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Cleanup Modal -->
<div class="modal-overlay" id="cleanupModal">
    <div class="modal">
        <div class="modal-icon cleanup">
            <i class="fas fa-broom"></i>
        </div>
        <h3 id="cleanupModalTitle">Clear Up Records</h3>
        <p id="cleanupModalMessage">Are you sure you want to clear all records? This action cannot be undone.</p>
        
        <form method="post" id="cleanupForm">
            <input type="hidden" name="cleanup_action" id="cleanupAction">
            
            <div class="modal-buttons">
                <button type="submit" class="modal-btn btn-cleanup-confirm">Yes, Clear All</button>
                <button type="button" class="modal-btn btn-cancel" onclick="closeCleanupModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Approval Modal (Accept/Reject) -->
<div class="modal-overlay" id="approvalModal">
    <div class="modal approval-modal">
        <div class="modal-icon" id="approvalModalIcon">
            <i class="fas fa-check"></i>
        </div>
        <h3 id="approvalModalTitle">Review Company Application</h3>
        <p id="approvalModalMessage">Please review the company application and provide your decision.</p>
        
        <form method="post" id="approvalForm">
            <input type="hidden" name="company_id" id="approvalCompanyId">
            <input type="hidden" name="company_name" id="approvalCompanyName">
            <input type="hidden" name="company_email" id="approvalCompanyEmail">
            <input type="hidden" name="confirm_action" id="approvalAction">
            
            <div class="form-group">
                <label class="form-label" for="adminNotes">Admin Notes (Optional)</label>
                <textarea name="admin_notes" id="adminNotes" class="form-textarea" placeholder="Add any notes about this decision..."></textarea>
            </div>
            
            <div class="modal-buttons">
                <button type="submit" class="modal-btn" id="approvalSubmitBtn">Confirm Decision</button>
                <button type="button" class="modal-btn btn-cancel" onclick="closeApprovalModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Individual Message Modal -->
<div class="modal-overlay" id="messageModal">
    <div class="modal message-modal">
        <div class="modal-icon message">
            <i class="fas fa-envelope"></i>
        </div>
        <h3>Send Message</h3>
        <p>Send a message to <span id="messageCompanyName"></span></p>
        
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

<!-- Broadcast Message Modal -->
<div class="modal-overlay" id="broadcastModal">
    <div class="modal broadcast-modal">
        <div class="modal-icon broadcast">
            <i class="fas fa-bullhorn"></i>
        </div>
        <h3>Broadcast Message to Companies</h3>
        <p>Send a message to multiple companies based on their status</p>
        
        <div class="broadcast-info">
            <i class="fas fa-info-circle"></i>
            <div class="broadcast-info-text">
                <strong>Select Target Companies</strong>
                <span>Choose which companies should receive this message</span>
            </div>
        </div>
        
        <form method="post" id="broadcastForm">
            <input type="hidden" name="send_broadcast" value="1">
            
            <div class="form-group">
                <label class="form-label">Target Companies</label>
                <div class="target-status-grid">
                    <div class="target-option">
                        <input type="radio" name="target_status" id="target_all" value="all" checked>
                        <label for="target_all">
                            <i class="fas fa-building"></i>
                            All Companies (<?= $total ?>)
                        </label>
                    </div>
                    <div class="target-option">
                        <input type="radio" name="target_status" id="target_active" value="active">
                        <label for="target_active">
                            <i class="fas fa-check-circle"></i>
                            Active (<?= $active ?>)
                        </label>
                    </div>
                    <div class="target-option">
                        <input type="radio" name="target_status" id="target_pending" value="pending">
                        <label for="target_pending">
                            <i class="fas fa-clock"></i>
                            Pending (<?= $pending ?>)
                        </label>
                    </div>
                    <div class="target-option">
                        <input type="radio" name="target_status" id="target_blocked" value="blocked">
                        <label for="target_blocked">
                            <i class="fas fa-ban"></i>
                            Blocked (<?= $blocked ?>)
                        </label>
                    </div>
                    <div class="target-option">
                        <input type="radio" name="target_status" id="target_rejected" value="reject">
                        <label for="target_rejected">
                            <i class="fas fa-times-circle"></i>
                            Rejected (<?= $rejected ?>)
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="broadcastSubject">Subject</label>
                <input type="text" name="message_subject" id="broadcastSubject" class="form-input" placeholder="Enter broadcast subject" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="broadcastContent">Message</label>
                <textarea name="message_content" id="broadcastContent" class="form-textarea" placeholder="Type your broadcast message here..." required style="min-height: 150px;"></textarea>
            </div>
            
            <div class="modal-buttons">
                <button type="submit" class="modal-btn btn-broadcast">
                    <i class="fas fa-bullhorn"></i>
                    Send Broadcast
                </button>
                <button type="button" class="modal-btn btn-cancel" onclick="closeBroadcastModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Excel Export Modal -->
<div class="modal-overlay" id="exportModal">
    <div class="modal export-modal">
        <div class="modal-icon" style="background: linear-gradient(135deg, #217346 0%, #1a5c37 100%);">
            <i class="fas fa-file-excel"></i>
        </div>
        <h3>Export Companies to Excel</h3>
        <p>Configure your export settings and filters</p>
        
        <form id="exportForm" target="_blank">
            <div class="form-group">
                <label class="form-label" for="exportReportType">Report Type</label>
                <select name="report_type" id="exportReportType" class="form-input" onchange="toggleExportOptions()">
                    <option value="current">Current View (with active filters)</option>
                    <option value="monthly">Monthly Report</option>
                    <option value="quarterly">Quarterly Report</option>
                </select>
            </div>

            <div class="filter-grid" id="currentFilters">
                <div class="form-group filter-grid-full">
                    <label class="form-label" for="exportSearch">Search Term</label>
                    <input type="text" name="search" id="exportSearch" class="form-input" placeholder="Company name, email, or ID">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="exportStatus">Status</label>
                    <select name="status" id="exportStatus" class="form-input">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="blocked">Blocked</option>
                        <option value="reject">Rejected</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="exportIndustry">Industry</label>
                    <select name="industry" id="exportIndustry" class="form-input">
                        <option value="all">All Industries</option>
                        <?php
                        $industries_result = $conn->query("SELECT DISTINCT industry_type FROM companies WHERE industry_type IS NOT NULL ORDER BY industry_type");
                        while ($ind = $industries_result->fetch_assoc()):
                        ?>
                            <option value="<?= htmlspecialchars($ind['industry_type']) ?>">
                                <?= htmlspecialchars($ind['industry_type']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="exportDateFrom">Registration From</label>
                    <input type="date" name="date_from" id="exportDateFrom" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label" for="exportDateTo">Registration To</label>
                    <input type="date" name="date_to" id="exportDateTo" class="form-input">
                </div>
            </div>

            <div class="filter-grid" id="monthlyFilters" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-input">
                        <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-input">
                        <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="filter-grid" id="quarterlyFilters" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Quarter</label>
                    <select name="quarter" class="form-input">
                        <option value="1" <?= ceil(date('m') / 3) == 1 ? 'selected' : '' ?>>Q1 (Jan - Mar)</option>
                        <option value="2" <?= ceil(date('m') / 3) == 2 ? 'selected' : '' ?>>Q2 (Apr - Jun)</option>
                        <option value="3" <?= ceil(date('m') / 3) == 3 ? 'selected' : '' ?>>Q3 (Jul - Sep)</option>
                        <option value="4" <?= ceil(date('m') / 3) == 4 ? 'selected' : '' ?>>Q4 (Oct - Dec)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-input">
                        <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button type="submit" class="modal-btn btn-excel">
                    <i class="fas fa-download"></i>
                    Export to Excel
                </button>
                <button type="button" class="modal-btn btn-cancel" onclick="closeExportModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// JavaScript for modal functionality

function openModal(companyId, companyName, companyEmail, action) {
    const modal = document.getElementById("confirmModal");
    const modalIcon = document.getElementById("modalIcon");
    const modalTitle = document.getElementById("modalTitle");
    const modalMessage = document.getElementById("modalMessage");
    
    document.getElementById("modalCompanyId").value = companyId;
    document.getElementById("modalCompanyName").value = companyName;
    document.getElementById("modalCompanyEmail").value = companyEmail;
    document.getElementById("modalAction").value = action;
    
    if (action === 'delete') {
        modalIcon.className = "modal-icon delete";
        modalIcon.innerHTML = '<i class="fas fa-trash-alt"></i>';
        modalTitle.textContent = "Delete Company";
        modalMessage.textContent = `Are you sure you want to permanently delete ${companyName}? This action cannot be undone and will remove all associated data.`;
    } else if (action === 'block') {
        modalIcon.className = "modal-icon block";
        modalIcon.innerHTML = '<i class="fas fa-ban"></i>';
        modalTitle.textContent = "Block Company";
        modalMessage.textContent = `Are you sure you want to block ${companyName}? They will no longer be able to access their account or post internships.`;
    } else if (action === 'unblock') {
        modalIcon.className = "modal-icon unblock";
        modalIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
        modalTitle.textContent = "Unblock Company";
        modalMessage.textContent = `Are you sure you want to unblock ${companyName}? They will regain access to their account and be able to post internships.`;
    }
    
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
}

function closeModal() {
    const modal = document.getElementById("confirmModal");
    modal.classList.remove("show");
    document.body.style.overflow = "auto";
}

function openCleanupModal(type) {
    const modal = document.getElementById("cleanupModal");
    const modalTitle = document.getElementById("cleanupModalTitle");
    const modalMessage = document.getElementById("cleanupModalMessage");
    
    if (type === 'deleted') {
        document.getElementById("cleanupAction").value = "cleanup_deleted";
        modalTitle.textContent = "Clear Deleted Records";
        modalMessage.textContent = "Are you sure you want to clear all deleted company records? This action cannot be undone.";
    } else if (type === 'blocked') {
        document.getElementById("cleanupAction").value = "cleanup_blocked";
        modalTitle.textContent = "Clear Blocked Records";
        modalMessage.textContent = "Are you sure you want to clear all blocked company records? This action cannot be undone.";
    }
    
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
}

function closeCleanupModal() {
    const modal = document.getElementById("cleanupModal");
    modal.classList.remove("show");
    document.body.style.overflow = "auto";
}

function openApprovalModal(companyId, companyName, companyEmail, action) {
    const modal = document.getElementById("approvalModal");
    const modalIcon = document.getElementById("approvalModalIcon");
    const modalTitle = document.getElementById("approvalModalTitle");
    const modalMessage = document.getElementById("approvalModalMessage");
    const submitBtn = document.getElementById("approvalSubmitBtn");
    
    document.getElementById("approvalCompanyId").value = companyId;
    document.getElementById("approvalCompanyName").value = companyName;
    document.getElementById("approvalCompanyEmail").value = companyEmail;
    document.getElementById("approvalAction").value = action;
    
    document.getElementById("adminNotes").value = "";
    
    if (action === 'accept') {
        modalIcon.className = "modal-icon accept";
        modalIcon.innerHTML = '<i class="fas fa-check"></i>';
        modalTitle.textContent = "Accept Company Application";
        modalMessage.textContent = `You are about to accept ${companyName}'s application. They will gain full access to the platform and can start posting internships.`;
        submitBtn.className = "modal-btn btn-approve";
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Accept Application';
    } else if (action === 'reject') {
        modalIcon.className = "modal-icon reject";
        modalIcon.innerHTML = '<i class="fas fa-times"></i>';
        modalTitle.textContent = "Reject Company Application";
        modalMessage.textContent = `You are about to reject ${companyName}'s application. Please provide a reason for the rejection to help them understand the decision.`;
        submitBtn.className = "modal-btn btn-reject-confirm";
        submitBtn.innerHTML = '<i class="fas fa-times"></i> Reject Application';
    }
    
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    
    setTimeout(() => {
        document.getElementById("adminNotes").focus();
    }, 300);
}

function closeApprovalModal() {
    const modal = document.getElementById("approvalModal");
    modal.classList.remove("show");
    document.body.style.overflow = "auto";
}

function openMessageModal(companyId, companyName) {
    const modal = document.getElementById("messageModal");
    document.getElementById("messageCompanyName").textContent = companyName;
    document.getElementById("messageReceiverId").value = companyId;
    
    document.getElementById("messageSubject").value = "";
    document.getElementById("messageContent").value = "";
    
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    
    setTimeout(() => {
        document.getElementById("messageSubject").focus();
    }, 300);
}

function closeMessageModal() {
    const modal = document.getElementById("messageModal");
    modal.classList.remove("show");
    document.body.style.overflow = "auto";
}

function openBroadcastModal() {
    const modal = document.getElementById("broadcastModal");
    
    document.getElementById("broadcastSubject").value = "";
    document.getElementById("broadcastContent").value = "";
    document.getElementById("target_all").checked = true;
    
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    
    setTimeout(() => {
        document.getElementById("broadcastSubject").focus();
    }, 300);
}

function closeBroadcastModal() {
    const modal = document.getElementById("broadcastModal");
    modal.classList.remove("show");
    document.body.style.overflow = "auto";
}

function openExportModal() {
    const modal = document.getElementById("exportModal");
    
    const currentSearch = document.getElementById("searchInput").value;
    const currentStatus = document.getElementById("statusFilter").value;
    
    document.getElementById("exportSearch").value = currentSearch;
    document.getElementById("exportStatus").value = currentStatus;
    
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
}

function closeExportModal() {
    const modal = document.getElementById("exportModal");
    modal.classList.remove("show");
    document.body.style.overflow = "auto";
}

function toggleExportOptions() {
    const reportType = document.getElementById("exportReportType").value;
    const currentFilters = document.getElementById("currentFilters");
    const monthlyFilters = document.getElementById("monthlyFilters");
    const quarterlyFilters = document.getElementById("quarterlyFilters");
    
    currentFilters.style.display = 'none';
    monthlyFilters.style.display = 'none';
    quarterlyFilters.style.display = 'none';
    
    if (reportType === 'current') {
        currentFilters.style.display = 'grid';
    } else if (reportType === 'monthly') {
        monthlyFilters.style.display = 'grid';
    } else if (reportType === 'quarterly') {
        quarterlyFilters.style.display = 'grid';
    }
}

// Close modals when clicking outside
document.getElementById("confirmModal").addEventListener("click", function(e) {
    if (e.target === this) closeModal();
});

document.getElementById("cleanupModal").addEventListener("click", function(e) {
    if (e.target === this) closeCleanupModal();
});

document.getElementById("approvalModal").addEventListener("click", function(e) {
    if (e.target === this) closeApprovalModal();
});

document.getElementById("messageModal").addEventListener("click", function(e) {
    if (e.target === this) closeMessageModal();
});

document.getElementById("broadcastModal").addEventListener("click", function(e) {
    if (e.target === this) closeBroadcastModal();
});

document.getElementById("exportModal").addEventListener("click", function(e) {
    if (e.target === this) closeExportModal();
});

// Close modals with Escape key
document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        closeModal();
        closeCleanupModal();
        closeApprovalModal();
        closeMessageModal();
        closeBroadcastModal();
        closeExportModal();
    }
});

// Export form submission
document.getElementById("exportForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const params = new URLSearchParams(formData);
    window.open('export_companies_excel.php?' + params.toString(), '_blank');
    closeExportModal();
});

// Initialize search and filter functionality
function initializeSearchAndFilter() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput && statusFilter) {
        searchInput.addEventListener('input', function() {
            filterTable();
        });
        
        statusFilter.addEventListener('change', function() {
            filterTable();
        });
    }
}

// Combined filter function
function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const filterValue = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('.companies-table tbody tr');
    
    rows.forEach(row => {
        let showRow = true;
        
        if (searchTerm) {
            const text = row.textContent.toLowerCase();
            if (!text.includes(searchTerm)) {
                showRow = false;
            }
        }
        
        if (filterValue !== 'all' && showRow) {
            const statusBadge = row.querySelector('.status-badge');
            const status = statusBadge.textContent.toLowerCase().trim();
            if (status !== filterValue) {
                showRow = false;
            }
        }
        
        row.style.display = showRow ? '' : 'none';
    });
}

// Page load animations
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.loading');
    elements.forEach((el, index) => {
        setTimeout(() => {
            el.classList.remove('loading');
        }, index * 100);
    });
    
    initializeSearchAndFilter();
    
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

// Add fadeOut animation
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
    url.searchParams.delete('cleanup_status');
    window.history.replaceState(null, null, url);
}
</script>

<?php
// Close the database connection
$conn->close();
?>