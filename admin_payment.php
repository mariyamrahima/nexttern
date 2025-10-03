<?php

// To this:
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.html');
    exit();
}

// Database connection
$conn = getDatabaseConnection();
if (!$conn) {
    echo '<div class="error-message">Database connection failed. Please try again later.</div>';
    return;
}

// Handle payment status updates - REMOVED (Status is now read-only)

// Pagination and filtering
$page_num = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$records_per_page = 15;
$offset = ($page_num - 1) * $records_per_page;

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(cp.company_name LIKE ? OR cp.razorpay_payment_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "cp.payment_status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(cp.payment_date) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Count total records
$count_query = "SELECT COUNT(*) as total FROM company_payment cp {$where_clause}";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $records_per_page);

// Updated query to fetch contact information directly from company_payment table
$query = "SELECT 
    cp.id,
    cp.company_id,
    cp.company_name,
    cp.payment_status,
    cp.razorpay_payment_id,
    cp.payment_date,
    cp.amount,
    cp.created_at,
    cp.updated_at,
    cp.company_email,
    cp.contact_name,
    cp.contact_phone
FROM company_payment cp
{$where_clause}
ORDER BY cp.created_at DESC
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$params[] = $records_per_page;
$params[] = $offset;
$param_types .= 'ii';

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get payment statistics (only completed and pending)
$stats_query = "SELECT 
    payment_status,
    COUNT(*) as count,
    COALESCE(SUM(amount), 0) as total_amount
FROM company_payment 
WHERE payment_status IN ('completed', 'pending')
GROUP BY payment_status";
$stats_result = $conn->query($stats_query);
$payment_stats = [];
if ($stats_result) {
    while ($row = $stats_result->fetch_assoc()) {
        $payment_stats[$row['payment_status']] = $row;
    }
}

$conn->close();
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-credit-card"></i>
            Company Payments
        </h1>
        <p class="page-subtitle">
            Monitor and manage all company payment transactions, including Razorpay payments and billing status.
        </p>
    </div>

    <!-- Payment Statistics -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <?php
        $status_configs = [
            'completed' => ['icon' => 'fa-check-circle', 'color' => 'success', 'label' => 'Completed'],
            'pending' => ['icon' => 'fa-clock', 'color' => 'warning', 'label' => 'Pending']
        ];
        
        foreach ($status_configs as $status => $config): 
            $count = $payment_stats[$status]['count'] ?? 0;
            $amount = $payment_stats[$status]['total_amount'] ?? 0;
        ?>
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= number_format($count) ?></div>
                    <div class="stat-label"><?= $config['label'] ?> Payments</div>
                    <div style="font-size: 0.8rem; color: var(--secondary); margin-top: 0.25rem;">
                        ₹<?= number_format($amount, 2) ?>
                    </div>
                </div>
                <div class="stat-icon <?= $config['color'] ?>">
                    <i class="fas <?= $config['icon'] ?>"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters and Search -->
    <div class="form-container" style="margin-bottom: 1.5rem;">
        <form method="GET" class="form-grid" id="filterForm">
            <input type="hidden" name="page" value="payments">
            
            <div class="form-group">
                <label for="search">
                    <i class="fas fa-search"></i>
                    Search Payments
                </label>
                <input type="text" 
                       id="search" 
                       name="search" 
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="Company name or Payment ID...">
            </div>
            
            <div class="form-group">
                <label for="status">
                    <i class="fas fa-filter"></i>
                    Payment Status
                </label>
                <select id="status" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="date">
                    <i class="fas fa-calendar"></i>
                    Payment Date
                </label>
                <input type="date" 
                       id="date" 
                       name="date" 
                       value="<?= htmlspecialchars($date_filter) ?>">
            </div>
            
            <div class="form-group">
                <label>&nbsp;</label>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                    <a href="?page=payments" class="btn" style="background: #6c757d; color: white;">
                        <i class="fas fa-refresh"></i>
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Payment Table -->
    <div class="table-container">
        <?php if (empty($payments)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--secondary);">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <h3>No Payment Records Found</h3>
                <p>No payment records match your current filters.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Company</th>
                        <th>Amount</th>
                        <th>Payment ID</th>
                        <th>Status</th>
                        <th>Payment Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td>
                            <span style="font-family: monospace; background: #f8f9fa; padding: 0.2rem 0.5rem; border-radius: 4px;">
                                #<?= $payment['id'] ?>
                            </span>
                        </td>
                        <td>
                            <div>
                                <strong><?= htmlspecialchars($payment['company_name']) ?></strong>
                            </div>
                        </td>
                        <td>
                            <strong style="color: var(--primary);">
                                <?php if ($payment['amount']): ?>
                                    ₹<?= number_format($payment['amount'], 2) ?>
                                <?php else: ?>
                                    <span style="color: var(--secondary);">N/A</span>
                                <?php endif; ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($payment['razorpay_payment_id']): ?>
                                <code style="font-size: 0.8rem; background: #f8f9fa; padding: 0.2rem 0.4rem; border-radius: 3px;">
                                    <?= htmlspecialchars($payment['razorpay_payment_id']) ?>
                                </code>
                            <?php else: ?>
                                <span style="color: var(--secondary);">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $status = $payment['payment_status'];
                            $status_color = getStatusColor($status);
                            $status_icon = $status === 'completed' ? 'check-circle' : 'clock';
                            ?>
                            <span style="background: <?= $status_color ?>; color: white; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap: 0.3rem;">
                                <i class="fas fa-<?= $status_icon ?>"></i>
                                <?= ucfirst($status) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($payment['payment_date']): ?>
                                <div>
                                    <?= date('M j, Y', strtotime($payment['payment_date'])) ?>
                                    <br><small style="color: var(--secondary);">
                                        <?= date('g:i A', strtotime($payment['payment_date'])) ?>
                                    </small>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--secondary);">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.25rem;">
                                <button class="btn" 
                                        onclick="viewPaymentDetails(<?= htmlspecialchars(json_encode($payment)) ?>)"
                                        style="background: var(--info); color: white; padding: 0.3rem 0.5rem; font-size: 0.7rem; border-radius: 4px; min-width: auto;"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($payment['razorpay_payment_id']): ?>
                                <button class="btn" 
                                        onclick="copyPaymentId('<?= htmlspecialchars($payment['razorpay_payment_id']) ?>')"
                                        style="background: var(--secondary); color: white; padding: 0.3rem 0.5rem; font-size: 0.7rem; border-radius: 4px; min-width: auto;"
                                        title="Copy Payment ID">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 2rem;">
        <div style="color: var(--secondary);">
            Showing <?= $offset + 1 ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> payments
        </div>
        
        <div style="display: flex; gap: 0.5rem;">
            <?php if ($page_num > 1): ?>
                <a href="?page=payments&<?= http_build_query(array_merge($_GET, ['page' => $page_num - 1])) ?>" 
                   class="btn" style="background: var(--primary); color: white; padding: 0.5rem 1rem;">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page_num - 2);
            $end = min($total_pages, $page_num + 2);
            
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="?page=payments&<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="btn <?= $i === $page_num ? 'btn-primary' : '' ?>"
                   style="<?= $i === $page_num ? '' : 'background: #f8f9fa; color: var(--secondary);' ?> padding: 0.5rem 0.8rem;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page_num < $total_pages): ?>
                <a href="?page=payments&<?= http_build_query(array_merge($_GET, ['page' => $page_num + 1])) ?>" 
                   class="btn" style="background: var(--primary); color: white; padding: 0.5rem 1rem;">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Payment Details Modal -->
<div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
     background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="color: var(--primary);">Payment Details</h3>
            <button onclick="closePaymentModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
        </div>
        <div id="paymentDetails">
            <!-- Payment details will be loaded here -->
        </div>
    </div>
</div>

<script>
// Status color helper
function getStatusColor(status) {
    const colors = {
        'pending': '#f39c12',
        'completed': '#27ae60'
    };
    return colors[status] || '#6c757d';
}

// Copy payment ID to clipboard
function copyPaymentId(paymentId) {
    navigator.clipboard.writeText(paymentId).then(() => {
        showNotification('Payment ID copied to clipboard', 'success');
    }).catch(() => {
        showNotification('Failed to copy payment ID', 'error');
    });
}

// View payment details with real data (fetching contact info from company_payment table)
function viewPaymentDetails(paymentData) {
    const formatCurrency = (amount) => {
        return amount ? `₹${parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : 'N/A';
    };
    
    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-IN', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };
    
    const getStatusBadge = (status) => {
        const statusConfig = {
            'completed': { color: '#27ae60', icon: 'check-circle', label: 'Completed' },
            'pending': { color: '#f39c12', icon: 'clock', label: 'Pending' }
        };
        const config = statusConfig[status] || { color: '#6c757d', icon: 'question', label: 'Unknown' };
        return `<span style="background: ${config.color}; color: white; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem; font-weight: 500;">
                    <i class="fas fa-${config.icon}"></i> ${config.label}
                </span>`;
    };
    
    document.getElementById('paymentDetails').innerHTML = `
        <div style="display: grid; gap: 1.5rem;">
            <!-- Basic Information -->
            <div style="background: #f8f9fa; padding: 1.25rem; border-radius: 10px; border-left: 4px solid var(--primary);">
                <h4 style="color: var(--primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Basic Information
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong style="color: var(--secondary);">Payment ID:</strong>
                        <div style="font-family: monospace; background: white; padding: 0.5rem; border-radius: 5px; margin-top: 0.25rem;">#${paymentData.id}</div>
                    </div>
                    <div>
                        <strong style="color: var(--secondary);">Company:</strong>
                        <div style="margin-top: 0.25rem;">${paymentData.company_name || 'N/A'}</div>
                    </div>
                    <div>
                        <strong style="color: var(--secondary);">Amount:</strong>
                        <div style="font-size: 1.2rem; font-weight: 600; color: var(--primary); margin-top: 0.25rem;">${formatCurrency(paymentData.amount)}</div>
                    </div>
                    <div>
                        <strong style="color: var(--secondary);">Status:</strong>
                        <div style="margin-top: 0.5rem;">${getStatusBadge(paymentData.payment_status)}</div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div style="background: #f0f8ff; padding: 1.25rem; border-radius: 10px; border-left: 4px solid var(--info);">
                <h4 style="color: var(--info); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-address-book"></i> Contact Information
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong style="color: var(--secondary);">Contact Name:</strong>
                        <div style="margin-top: 0.25rem;">${paymentData.contact_name || 'Not available'}</div>
                    </div>
                    <div>
                        <strong style="color: var(--secondary);">Phone Number:</strong>
                        <div style="margin-top: 0.25rem;">
                            ${paymentData.contact_phone ? 
                                `<a href="tel:${paymentData.contact_phone}" style="color: var(--info); text-decoration: none;">
                                    <i class="fas fa-phone"></i> ${paymentData.contact_phone}
                                </a>` : 
                                'Not available'
                            }
                        </div>
                    </div>
                    <div>
                        <strong style="color: var(--secondary);">Email:</strong>
                        <div style="margin-top: 0.25rem;">
                            ${paymentData.company_email ? 
                                `<a href="mailto:${paymentData.company_email}" style="color: var(--info); text-decoration: none;">
                                    <i class="fas fa-envelope"></i> ${paymentData.company_email}
                                </a>` : 
                                'Not available'
                            }
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div style="background: #f0fff0; padding: 1.25rem; border-radius: 10px; border-left: 4px solid var(--success);">
                <h4 style="color: var(--success); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-credit-card"></i> Payment Information
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong style="color: var(--secondary);">Razorpay Payment ID:</strong>
                        <div style="margin-top: 0.25rem;">
                            ${paymentData.razorpay_payment_id ? 
                                `<code style="background: white; padding: 0.5rem; border-radius: 5px; font-size: 0.85rem; display: block; word-break: break-all;">${paymentData.razorpay_payment_id}</code>
                                 <button onclick="copyPaymentId('${paymentData.razorpay_payment_id}')" style="background: var(--secondary); color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 5px; font-size: 0.8rem; margin-top: 0.5rem; cursor: pointer;">
                                    <i class="fas fa-copy"></i> Copy ID
                                 </button>` : 
                                'Not available'
                            }
                        </div>
                    </div>
                    <div>
                        <strong style="color: var(--secondary);">Payment Date:</strong>
                        <div style="margin-top: 0.25rem;">${formatDate(paymentData.payment_date)}</div>
                    </div>
                </div>
            </div>
            
            <!-- Timestamps -->
            <div style="background: #fff5f5; padding: 1.25rem; border-radius: 10px; border-left: 4px solid var(--danger);">
                <h4 style="color: var(--danger); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-clock"></i> Record Timestamps
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong style="color: var(--secondary);">Created:</strong>
                        <div style="margin-top: 0.25rem;">${formatDate(paymentData.created_at)}</div>
                    </div>
                    <div>
                        <strong style="color: var(--secondary);">Last Updated:</strong>
                        <div style="margin-top: 0.25rem;">${formatDate(paymentData.updated_at)}</div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

// Notification system
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 300px;
    `;
    
    if (type === 'success') {
        notification.style.background = '#27ae60';
    } else if (type === 'error') {
        notification.style.background = '#e74c3c';
    } else {
        notification.style.background = '#3498db';
    }
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Auto-submit form on input change
document.getElementById('search').addEventListener('input', debounce(() => {
    document.getElementById('filterForm').submit();
}, 500));

document.getElementById('status').addEventListener('change', () => {
    document.getElementById('filterForm').submit();
});

document.getElementById('date').addEventListener('change', () => {
    document.getElementById('filterForm').submit();
});

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Close modal on outside click
document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePaymentModal();
    }
});

// Keyboard shortcuts for modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('paymentModal').style.display === 'flex') {
        closePaymentModal();
    }
});

// Initialize tooltips and enhance UX
document.addEventListener('DOMContentLoaded', function() {
    // Add tooltips for truncated payment IDs
    document.querySelectorAll('[title]').forEach(element => {
        element.addEventListener('mouseenter', function() {
            if (this.scrollWidth > this.clientWidth) {
                this.title = this.textContent;
            }
        });
    });
    
    // Add loading states to buttons
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.type === 'submit' || this.href) {
                this.style.opacity = '0.7';
                this.style.pointerEvents = 'none';
                
                setTimeout(() => {
                    this.style.opacity = '';
                    this.style.pointerEvents = '';
                }, 2000);
            }
        });
    });
    
    // Auto-refresh page data every 2 minutes
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            // Silent refresh of statistics
            fetch(window.location.href + '&refresh=stats')
                .then(response => {
                    if (response.ok) {
                        console.log('Statistics refreshed silently');
                    }
                })
                .catch(() => {
                    // Silent fail - no user notification needed
                });
        }
    }, 120000); // 2 minutes
});

// Enhanced search functionality
let searchTimeout;
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const searchValue = this.value.trim();
    
    if (searchValue.length > 0) {
        // Show loading indicator
        this.style.background = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'20\' height=\'20\' viewBox=\'0 0 24 24\'%3E%3Cpath fill=\'%23999\' d=\'M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z\' opacity=\'.25\'/%3E%3Cpath fill=\'%23999\' d=\'M12,4a8,8,0,0,1,7.89,6.7A1.53,1.53,0,0,0,21.38,12h0a1.5,1.5,0,0,0,1.48-1.75,11,11,0,0,0-21.72,0A1.5,1.5,0,0,0,2.62,12h0a1.53,1.53,0,0,0,1.49-1.3A8,8,0,0,1,12,4Z\'%3E%3CanimateTransform attributeName=\'transform\' dur=\'0.75s\' repeatCount=\'indefinite\' type=\'rotate\' values=\'0 12 12;360 12 12\'/%3E%3C/path%3E%3C/svg%3E") no-repeat right 10px center';
        this.style.backgroundSize = '20px 20px';
        this.style.paddingRight = '40px';
    }
    
    searchTimeout = setTimeout(() => {
        // Reset background
        this.style.background = '';
        this.style.paddingRight = '';
        
        // Submit form
        document.getElementById('filterForm').submit();
    }, 800);
});

// Payment ID validation and formatting
document.addEventListener('input', function(e) {
    if (e.target.name === 'search' && e.target.value.startsWith('pay_')) {
        // Format Razorpay payment ID for better readability
        const value = e.target.value.replace(/[^a-zA-Z0-9_]/g, '');
        if (value !== e.target.value) {
            e.target.value = value;
        }
    }
});

// Export functionality (if needed)
function exportPaymentData(format = 'csv') {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    params.set('ajax', '1');
    
    fetch('?' + params.toString())
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `payments_${new Date().toISOString().split('T')[0]}.${format}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        })
        .catch(() => {
            showNotification('Export failed. Please try again.', 'error');
        });
}

// Print functionality
function printPaymentTable() {
    const printWindow = window.open('', '_blank');
    const tableContent = document.querySelector('.table-container').innerHTML;
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Payment Records - ${new Date().toLocaleDateString()}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f5f5f5; font-weight: bold; }
                    .btn { display: none; }
                    @media print { .btn { display: none !important; } }
                </style>
            </head>
            <body>
                <h2>Company Payment Records</h2>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                ${tableContent}
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php
// Helper function for status colors (updated for only pending/completed)
function getStatusColor($status) {
    $colors = [
        'pending' => '#f39c12',
        'completed' => '#27ae60'
    ];
    return $colors[$status] ?? '#6c757d';
}

// Handle export functionality (optional) - Updated to include contact information from company_payment table
if (isset($_GET['export']) && isset($_GET['ajax'])) {
    $export_format = $_GET['export'];
    
    if ($export_format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payments_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers - Updated to include contact information from company_payment table
        fputcsv($output, [
            'ID', 'Company Name', 'Company Email', 'Contact Name', 'Contact Phone', 
            'Amount', 'Razorpay Payment ID', 'Status', 'Payment Date', 
            'Created At', 'Updated At'
        ]);
        
        // CSV data - Updated to include contact information from company_payment table
        foreach ($payments as $payment) {
            fputcsv($output, [
                $payment['id'],
                $payment['company_name'],
                $payment['company_email'] ?? '',
                $payment['contact_name'] ?? '',
                $payment['contact_phone'] ?? '',
                $payment['amount'],
                $payment['razorpay_payment_id'] ?? '',
                $payment['payment_status'],
                $payment['payment_date'],
                $payment['created_at'],
                $payment['updated_at']
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// Handle silent statistics refresh
if (isset($_GET['refresh']) && $_GET['refresh'] === 'stats') {
    header('Content-Type: application/json');
    
    // Re-fetch statistics
    $conn = getDatabaseConnection();
    if ($conn) {
        $stats_query = "SELECT 
            payment_status,
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total_amount
        FROM company_payment 
        WHERE payment_status IN ('completed', 'pending')
        GROUP BY payment_status";
        
        $stats_result = $conn->query($stats_query);
        $fresh_stats = [];
        
        if ($stats_result) {
            while ($row = $stats_result->fetch_assoc()) {
                $fresh_stats[$row['payment_status']] = $row;
            }
        }
        
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'stats' => $fresh_stats,
            'timestamp' => time()
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}
?>