<?php
// Database connection and operations
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle message status updates
$operation_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $message_id = $_POST['message_id'] ?? 0;
        $action = $_POST['action'];
        
        if ($action === 'mark_read') {
            $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
            $stmt->bind_param("i", $message_id);
            if ($stmt->execute()) {
                $operation_status = 'marked_read';
            }
            $stmt->close();
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->bind_param("i", $message_id);
            if ($stmt->execute()) {
                $operation_status = 'deleted';
            }
            $stmt->close();
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status_filter'] ?? 'all';
$search_term = $_GET['search'] ?? '';

// Build query based on filters
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search_term)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $search_param = "%$search_term%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch messages
$query = "SELECT * FROM contact_messages $where_clause ORDER BY created_at DESC";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$messages = $stmt->get_result();
$stmt->close();

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM contact_messages")->fetch_assoc()['count'],
    'new' => $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'new'")->fetch_assoc()['count'],
    'read' => $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'read'")->fetch_assoc()['count']
];
?>

<style>
:root {
    --primary: #035946;
    --primary-light: #0a7058;
    --secondary: #2e3944;
    --accent: #4ecdc4;
    --success: #27ae60;
    --warning: #f39c12;
    --danger: #e74c3c;
    --info: #3498db;
    --glass-bg: rgba(255, 255, 255, 0.25);
    --glass-border: rgba(255, 255, 255, 0.3);
    --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
    --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
    --blur: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.messages-container {
    max-width: 1400px;
    margin: 0 auto;
}

.page-header {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 16px;
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
    color: var(--accent);
}

.status-message {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    animation: slideDown 0.3s ease-out;
}

.status-message.success {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.2);
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-medium);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--secondary);
    opacity: 0.8;
}

.filters-bar {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.search-input, .status-select {
    padding: 0.75rem 1rem;
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    transition: var(--transition);
}

.search-input {
    flex: 1;
    min-width: 250px;
}

.search-input:focus, .status-select:focus {
    outline: none;
    border-color: var(--accent);
    background: white;
}

.messages-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.message-card {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1.5rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.message-card.new {
    border-left: 4px solid var(--info);
    background: rgba(52, 152, 219, 0.05);
}

.message-card.read {
    border-left: 4px solid var(--warning);
}

.message-card:hover {
    transform: translateX(5px);
    box-shadow: var(--shadow-medium);
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    gap: 1rem;
}

.sender-info {
    flex: 1;
}

.sender-name {
    font-weight: 600;
    font-size: 1.1rem;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.sender-email {
    font-size: 0.9rem;
    color: var(--info);
}

.message-meta {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-new { background: rgba(52, 152, 219, 0.2); color: var(--info); }
.status-read { background: rgba(243, 156, 18, 0.2); color: var(--warning); }

.message-date {
    font-size: 0.85rem;
    color: var(--secondary);
    opacity: 0.7;
}

.message-subject {
    font-weight: 600;
    font-size: 1rem;
    color: var(--primary-dark);
    margin-bottom: 0.75rem;
}

.message-content {
    color: var(--secondary);
    line-height: 1.6;
    margin-bottom: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 8px;
}

.message-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.action-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.btn-read { background: var(--warning); color: white; }
.btn-delete { background: var(--danger); color: white; }

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.no-messages {
    text-align: center;
    padding: 3rem;
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border-radius: 12px;
}

.no-messages i {
    font-size: 3rem;
    color: var(--accent);
    margin-bottom: 1rem;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
}

.modal {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    max-width: 450px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    text-align: center;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal-overlay.show .modal {
    transform: scale(1);
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

.modal h3 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.modal p {
    color: var(--secondary);
    line-height: 1.6;
    margin-bottom: 2rem;
}

.modal-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.modal-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    min-width: 120px;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-confirm-delete {
    background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
}

.btn-confirm-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
}

.btn-cancel {
    background: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .filters-bar {
        flex-direction: column;
    }
    
    .search-input {
        min-width: unset;
    }
    
    .message-header {
        flex-direction: column;
    }
    
    .message-actions {
        flex-direction: column;
    }
    
    .action-btn {
        justify-content: center;
    }
}
</style>

<div class="messages-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-envelope-open-text"></i>
            Contact Messages
        </h1>
        <p class="page-description">View and manage messages received from the contact form.</p>
    </div>

    <?php if ($operation_status === 'marked_read'): ?>
        <div class="status-message success">
            <i class="fas fa-check-circle"></i>
            Message marked as read successfully.
        </div>
    <?php elseif ($operation_status === 'deleted'): ?>
        <div class="status-message success">
            <i class="fas fa-check-circle"></i>
            Message deleted successfully.
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Messages</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['new'] ?></div>
            <div class="stat-label">New Messages</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['read'] ?></div>
            <div class="stat-label">Read Messages</div>
        </div>
    </div>

    <form method="GET" class="filters-bar">
        <input type="hidden" name="page" value="messages">
        <input type="text" name="search" class="search-input" placeholder="Search messages..." value="<?= htmlspecialchars($search_term) ?>">
        <select name="status_filter" class="status-select" onchange="this.form.submit()">
            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
            <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
            <option value="read" <?= $status_filter === 'read' ? 'selected' : '' ?>>Read</option>
        </select>
    </form>

    <div class="messages-list">
        <?php if ($messages->num_rows > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
                <div class="message-card <?= htmlspecialchars($msg['status']) ?>">
                    <div class="message-header">
                        <div class="sender-info">
                            <div class="sender-name"><?= htmlspecialchars($msg['name']) ?></div>
                            <div class="sender-email"><?= htmlspecialchars($msg['email']) ?></div>
                        </div>
                        <div class="message-meta">
                            <span class="status-badge status-<?= $msg['status'] ?>"><?= ucfirst($msg['status']) ?></span>
                            <span class="message-date">
                                <i class="far fa-clock"></i>
                                <?= date('M d, Y g:i A', strtotime($msg['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="message-subject">
                        <i class="fas fa-tag"></i>
                        <?= htmlspecialchars($msg['subject']) ?>
                    </div>
                    
                    <div class="message-content">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                    </div>
                    
                    <div class="message-actions">
                        <?php if ($msg['status'] === 'new'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                <input type="hidden" name="action" value="mark_read">
                                <button type="submit" class="action-btn btn-read">
                                    <i class="fas fa-eye"></i>
                                    Mark as Read
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <button type="button" class="action-btn btn-delete" onclick="openDeleteModal(<?= $msg['id'] ?>, '<?= htmlspecialchars(addslashes($msg['name'])) ?>')">
                            <i class="fas fa-trash"></i>
                            Delete
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-messages">
                <i class="fas fa-inbox"></i>
                <h3>No messages found</h3>
                <p>There are no messages matching your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-icon delete">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3>Delete Message</h3>
        <p id="deleteModalMessage">Are you sure you want to delete this message? This action cannot be undone.</p>
        
        <form method="POST" id="deleteForm">
            <input type="hidden" name="message_id" id="deleteMessageId">
            <input type="hidden" name="action" value="delete">
            
            <div class="modal-buttons">
                <button type="submit" class="modal-btn btn-confirm-delete">
                    <i class="fas fa-trash"></i>
                    Delete Message
                </button>
                <button type="button" class="modal-btn btn-cancel" onclick="closeDeleteModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteModal(messageId, senderName) {
    const modal = document.getElementById('deleteModal');
    const modalMessage = document.getElementById('deleteModalMessage');
    const deleteMessageId = document.getElementById('deleteMessageId');
    
    modalMessage.textContent = `Are you sure you want to delete the message from ${senderName}? This action cannot be undone.`;
    deleteMessageId.value = messageId;
    
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }, 300);
}

// Close modal on outside click
document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

// Add loading state to delete button
document.getElementById('deleteForm')?.addEventListener('submit', function() {
    const submitBtn = this.querySelector('.btn-confirm-delete');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    submitBtn.disabled = true;
});
</script>

<?php $conn->close(); ?>