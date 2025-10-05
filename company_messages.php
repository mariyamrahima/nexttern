<?php
// This file is meant to be included from dashboard_company.php

if (!isset($_SESSION['company_id'])) {
    header('Location: logincompany.html');
    exit();
}

$company_id = $_SESSION['company_id'];

// Fetch messages for the company
function getCompanyMessages($company_id) {
    $conn = getDatabaseConnection();
    if (!$conn) return [];
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                id,
                sender_type,
                receiver_type,
                receiver_id,
                subject,
                message,
                created_at,
                CASE 
                    WHEN sender_type = 'admin' THEN 'System Admin'
                    WHEN sender_type = 'company' THEN 'Your Company'
                    ELSE sender_type
                END as sender_display
            FROM company_messages 
            WHERE receiver_id = ? AND receiver_type = 'company'
            ORDER BY created_at DESC
        ");
        
        if ($stmt) {
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $messages = [];
            
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            
            $stmt->close();
            $conn->close();
            return $messages;
        }
        
        $conn->close();
        return [];
        
    } catch (Exception $e) {
        error_log("Error fetching messages: " . $e->getMessage());
        if ($conn) $conn->close();
        return [];
    }
}

// Get unread count (you can enhance this by adding a 'read' column to your table)
function getUnreadCount($company_id) {
    $conn = getDatabaseConnection();
    if (!$conn) return 0;
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM company_messages 
            WHERE receiver_id = ? AND receiver_type = 'company'
        ");
        
        if ($stmt) {
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $count = (int)($row['count'] ?? 0);
            $stmt->close();
            $conn->close();
            return $count;
        }
        
        $conn->close();
        return 0;
        
    } catch (Exception $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        if ($conn) $conn->close();
        return 0;
    }
}

$messages = getCompanyMessages($company_id);
$total_messages = count($messages);
?>

<style>
.messages-container {
    max-width: 1200px;
    margin: 0 auto;
}

.messages-header {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: var(--shadow-light);
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.messages-header::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: 20px 20px 0 0;
}

.messages-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.messages-header h1 i {
    color: var(--accent);
}

.messages-stats {
    display: flex;
    gap: 1.5rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.stat-badge {
    background: rgba(78, 205, 196, 0.15);
    color: var(--primary);
    padding: 0.5rem 1rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    border: 1px solid rgba(78, 205, 196, 0.3);
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
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--shadow-light);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.message-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, var(--primary) 0%, var(--accent) 100%);
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
    flex-wrap: wrap;
}

.message-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex: 1;
}

.message-sender {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sender-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.sender-name {
    font-weight: 600;
    color: var(--primary);
    font-size: 1rem;
}

.message-date {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--secondary);
    opacity: 0.7;
    font-size: 0.85rem;
}

.message-date i {
    font-size: 0.75rem;
}

.message-subject {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 0.75rem;
    line-height: 1.4;
}

.message-body {
    color: var(--secondary);
    line-height: 1.6;
    font-size: 0.95rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
    border-left: 3px solid var(--accent);
}

.empty-state {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 3rem;
    text-align: center;
    box-shadow: var(--shadow-light);
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.empty-state h3 {
    color: var(--primary);
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: var(--secondary);
    opacity: 0.8;
    font-size: 1rem;
}

.message-type-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-admin {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

.badge-system {
    background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
    color: white;
}

@media (max-width: 768px) {
    .messages-header {
        padding: 1.5rem;
    }
    
    .messages-header h1 {
        font-size: 1.5rem;
    }
    
    .message-card {
        padding: 1.25rem;
    }
    
    .message-header {
        flex-direction: column;
    }
    
    .empty-state {
        padding: 2rem 1.5rem;
    }
}
</style>

<div class="messages-container">
    <div class="messages-header">
        <h1>
            <i class="fas fa-envelope"></i>
            Messages
        </h1>
        <p style="color: var(--secondary); opacity: 0.9; margin-top: 0.5rem;">
            View all messages and notifications sent to your company
        </p>
        <div class="messages-stats">
            <div class="stat-badge">
                <i class="fas fa-envelope"></i> Total Messages: <?= $total_messages ?>
            </div>
        </div>
    </div>

    <div class="messages-list">
        <?php if (empty($messages)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3>No Messages Yet</h3>
                <p>You haven't received any messages. Check back later for updates and notifications.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <div class="message-card">
                    <div class="message-header">
                        <div class="message-meta">
                            <div class="message-sender">
                                <div class="sender-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <span class="sender-name">
                                    <?= htmlspecialchars($message['sender_display']) ?>
                                </span>
                                <?php if ($message['sender_type'] === 'admin'): ?>
                                    <span class="message-type-badge badge-admin">Admin</span>
                                <?php else: ?>
                                    <span class="message-type-badge badge-system">System</span>
                                <?php endif; ?>
                            </div>
                            <div class="message-date">
                                <i class="far fa-clock"></i>
                                <?= date('M d, Y • h:i A', strtotime($message['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($message['subject'])): ?>
                        <div class="message-subject">
                            <?= htmlspecialchars($message['subject']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="message-body">
                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>