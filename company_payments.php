<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Razorpay Test API Keys
$razorpay_key_id = "rzp_test_RIAHTn2vkReIdc";
$razorpay_key_secret = "xuYRxMU7q9I8QBxPT6n8ejuH";

// Registration fee
$registration_fee = 99900; // 999 INR in paise
$registration_fee_inr = $registration_fee / 100;

// Get company details from session
$company_id = $_SESSION['company_id'] ?? '';
$company_name = $_SESSION['company_name'] ?? 'Company';

// Check if payment already exists
$stmt = $pdo->prepare("SELECT * FROM company_payment WHERE company_id = ?");
$stmt->execute([$company_id]);
$existing_payment = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle payment verification
if (isset($_POST['razorpay_payment_id']) && isset($_POST['razorpay_order_id']) && isset($_POST['razorpay_signature'])) {
    $payment_id = $_POST['razorpay_payment_id'];
    $order_id = $_POST['razorpay_order_id'];
    $signature = $_POST['razorpay_signature'];
    
    // Verify signature
    $generated_signature = hash_hmac('sha256', $order_id . "|" . $payment_id, $razorpay_key_secret);
    
    if ($generated_signature === $signature) {
        // Payment successful - update database with amount
        $stmt = $pdo->prepare("
            INSERT INTO company_payment (
                company_id, company_name, payment_status, amount,
                razorpay_payment_id, payment_date, created_at
            ) VALUES (?, ?, 'completed', ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                payment_status = 'completed',
                amount = ?,
                razorpay_payment_id = ?,
                payment_date = NOW(),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $company_id, 
            $company_name, 
            $registration_fee_inr,
            $payment_id, 
            $registration_fee_inr,
            $payment_id
        ]);
        
        $payment_success = true;
        
        // Fetch the updated payment record
        $stmt = $pdo->prepare("SELECT * FROM company_payment WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $existing_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $payment_error = "Payment verification failed. Please try again.";
    }
}

// Create Razorpay order if needed
$razorpay_order_id = null;
if (!$existing_payment || $existing_payment['payment_status'] !== 'completed') {
    $order_data = [
        'receipt' => 'company_reg_' . $company_id,
        'amount' => $registration_fee,
        'currency' => 'INR'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ':' . $razorpay_key_secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $order = json_decode($result, true);
        if (isset($order['id'])) {
            $razorpay_order_id = $order['id'];
        }
    } else {
        error_log("Razorpay API Error: " . $result);
    }
}
?>

<style>
:root {
    --primary: #035946;
    --primary-light: #047857;
    --primary-dark: #023d33;
    --accent: #10b981;
    --success: #27ae60;
    --warning: #f1c40f;
    --danger: #e74c3c;
    --secondary: #2e3944;
    --text-light: #6b7280;
    --glass-bg: rgba(255, 255, 255, 0.95);
    --glass-border: rgba(3, 89, 70, 0.1);
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.12);
}

.payment-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.payment-header {
    text-align: center;
    margin-bottom: 3rem;
    animation: fadeInDown 0.6s ease-out;
}

.payment-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.5rem;
    letter-spacing: -0.5px;
}

.payment-header p {
    font-size: 1.1rem;
    color: var(--text-light);
    font-weight: 400;
}

.alert {
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-weight: 500;
    animation: slideInDown 0.5s ease-out;
    border-left: 4px solid;
}

.alert i {
    font-size: 1.5rem;
}

.alert-success {
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05));
    color: var(--success);
    border-color: var(--success);
}

.alert-error {
    background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.05));
    color: var(--danger);
    border-color: var(--danger);
}

.payment-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.payment-card {
    background: white;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--glass-border);
    transition: all 0.3s ease;
    animation: fadeInUp 0.6s ease-out;
    position: relative;
    overflow: hidden;
}

.payment-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--primary), var(--accent));
}

.payment-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.status-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid rgba(3, 89, 70, 0.1);
}

.status-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    box-shadow: var(--shadow-md);
    position: relative;
}

.status-icon::after {
    content: '';
    position: absolute;
    inset: -5px;
    border-radius: 50%;
    background: inherit;
    opacity: 0.2;
    z-index: -1;
}

.status-icon.paid {
    background: linear-gradient(135deg, var(--success), #66bb6a);
}

.status-icon.pending {
    background: linear-gradient(135deg, var(--warning), #ffb74d);
}

.status-content h3 {
    font-size: 1.4rem;
    color: var(--primary);
    margin-bottom: 0.75rem;
    font-weight: 700;
}

.status-badge {
    display: inline-block;
    padding: 0.6rem 1.2rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.paid {
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.15), rgba(39, 174, 96, 0.1));
    color: var(--success);
    border: 2px solid rgba(39, 174, 96, 0.3);
}

.status-badge.pending {
    background: linear-gradient(135deg, rgba(241, 196, 15, 0.15), rgba(241, 196, 15, 0.1));
    color: var(--warning);
    border: 2px solid rgba(241, 196, 15, 0.3);
}

.payment-details {
    background: linear-gradient(135deg, rgba(3, 89, 70, 0.03), rgba(3, 89, 70, 0.01));
    border-radius: 16px;
    padding: 1.5rem;
    border: 1px solid var(--glass-border);
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid rgba(3, 89, 70, 0.08);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    color: var(--text-light);
    font-weight: 500;
    font-size: 0.95rem;
}

.detail-value {
    color: var(--primary);
    font-weight: 700;
    font-size: 1rem;
}

.amount-display {
    text-align: center;
    margin-bottom: 2.5rem;
}

.amount-label {
    color: var(--text-light);
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.amount-value {
    font-size: 4rem;
    font-weight: 900;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.company-info {
    display: grid;
    gap: 1rem;
    margin-bottom: 2rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: linear-gradient(135deg, rgba(3, 89, 70, 0.03), rgba(3, 89, 70, 0.01));
    border-radius: 12px;
    border: 1px solid var(--glass-border);
    transition: all 0.3s ease;
}

.info-item:hover {
    background: linear-gradient(135deg, rgba(3, 89, 70, 0.05), rgba(3, 89, 70, 0.02));
    transform: translateX(5px);
}

.info-item i {
    font-size: 1.5rem;
    color: var(--accent);
}

.info-item strong {
    color: var(--primary);
    font-weight: 700;
}

.info-item span {
    color: var(--text-light);
}

.btn {
    width: 100%;
    padding: 1.5rem 2rem;
    border: none;
    border-radius: 14px;
    font-weight: 700;
    font-size: 1.15rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.btn:hover::before {
    opacity: 1;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: 0 10px 30px rgba(3, 89, 70, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(3, 89, 70, 0.4);
}

.btn-primary:active {
    transform: translateY(-1px);
}

.btn-secondary {
    background: white;
    color: var(--primary);
    border: 2px solid var(--glass-border);
}

.btn-secondary:hover {
    background: rgba(3, 89, 70, 0.05);
    border-color: var(--primary);
    transform: translateY(-3px);
}

.payment-methods {
    text-align: center;
    padding: 2rem 0 0;
    border-top: 2px solid rgba(3, 89, 70, 0.1);
    margin-top: 2rem;
}

.payment-methods p {
    color: var(--text-light);
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.payment-methods i.fas.fa-shield-alt {
    color: var(--success);
}

.payment-icons {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
}

.payment-icons i {
    font-size: 2.5rem;
    color: var(--text-light);
    opacity: 0.5;
    transition: all 0.3s ease;
}

.payment-icons i:hover {
    opacity: 1;
    transform: scale(1.1);
}

.error-state {
    text-align: center;
    padding: 3rem 2rem;
}

.error-state i {
    font-size: 4rem;
    color: var(--danger);
    margin-bottom: 1.5rem;
}

.error-state p {
    color: var(--text-light);
    margin-bottom: 2rem;
    font-size: 1.05rem;
}

.success-state {
    text-align: center;
    padding: 2rem;
}

.success-state i {
    font-size: 5rem;
    color: var(--success);
    margin-bottom: 1.5rem;
    animation: scaleIn 0.6s ease-out;
}

.success-state h4 {
    font-size: 1.5rem;
    color: var(--primary);
    font-weight: 700;
    margin-bottom: 1rem;
}

.success-state p {
    color: var(--text-light);
    margin-bottom: 2.5rem;
    font-size: 1.05rem;
}

.features-list {
    display: grid;
    gap: 1rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, rgba(3, 89, 70, 0.03), rgba(3, 89, 70, 0.01));
    border-radius: 12px;
    border: 1px solid var(--glass-border);
    transition: all 0.3s ease;
}

.feature-item:hover {
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.05), rgba(39, 174, 96, 0.02));
    border-color: rgba(39, 174, 96, 0.2);
    transform: translateX(5px);
}

.feature-item i {
    font-size: 1.25rem;
    color: var(--success);
}

.feature-item span {
    color: var(--secondary);
    font-weight: 500;
}

.transaction-history {
    margin-top: 3rem;
    animation: fadeInUp 0.8s ease-out;
}

.history-card {
    background: white;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--glass-border);
    position: relative;
    overflow: hidden;
}

.history-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--primary), var(--accent));
}

.history-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid rgba(3, 89, 70, 0.1);
}

.history-header h3 {
    font-size: 1.5rem;
    color: var(--primary);
    font-weight: 700;
}

.transaction-table {
    background: linear-gradient(135deg, rgba(3, 89, 70, 0.02), transparent);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--glass-border);
}

.table-header {
    display: grid;
    grid-template-columns: 1.2fr 2fr 2.5fr 1.2fr 1.3fr;
    gap: 1.5rem;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-row {
    display: grid;
    grid-template-columns: 1.2fr 2fr 2.5fr 1.2fr 1.3fr;
    gap: 1.5rem;
    padding: 1.75rem 2rem;
    align-items: center;
    background: white;
    transition: all 0.3s ease;
}

.table-row:hover {
    background: rgba(3, 89, 70, 0.02);
}

.table-row > div {
    overflow: hidden;
    text-overflow: ellipsis;
}

.transaction-date {
    color: var(--secondary);
    font-weight: 600;
}

.transaction-desc {
    color: var(--text-light);
    font-weight: 500;
}

.transaction-id {
    color: var(--text-light);
    font-family: monospace;
    font-size: 0.9rem;
}

.transaction-amount {
    color: var(--primary);
    font-weight: 700;
    font-size: 1.1rem;
}

.transaction-status {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.15), rgba(39, 174, 96, 0.1));
    color: var(--success);
    border: 2px solid rgba(39, 174, 96, 0.3);
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

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

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.5);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@media (max-width: 1024px) {
    .payment-grid {
        grid-template-columns: 1fr;
    }
    
    .table-header, .table-row {
        grid-template-columns: 1fr 1.5fr 2fr 1fr 1fr;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
    }
}

@media (max-width: 768px) {
    .payment-container {
        padding: 1rem;
    }
    
    .payment-header h1 {
        font-size: 2rem;
    }
    
    .payment-card {
        padding: 1.5rem;
    }
    
    .amount-value {
        font-size: 3rem;
    }
    
    .status-header {
        flex-direction: column;
        text-align: center;
    }
    
    .table-header {
        display: none;
    }
    
    .table-row {
        grid-template-columns: 1fr;
        gap: 0.75rem;
        padding: 1.5rem;
        border-bottom: 1px solid var(--glass-border);
    }
    
    .table-row > div::before {
        content: attr(data-label);
        font-weight: 700;
        color: var(--primary);
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.85rem;
        text-transform: uppercase;
    }
}
</style>

<div class="payment-container">
    <div class="payment-header">
        <h1><i class="fas fa-credit-card"></i> Payment Management</h1>
        <p>Secure company registration fee payment portal</p>
    </div>

    <?php if (isset($payment_success) && $payment_success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>Payment completed successfully! Your company registration is now active.</span>
        </div>
    <?php endif; ?>

    <?php if (isset($payment_error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($payment_error) ?></span>
        </div>
    <?php endif; ?>

    <div class="payment-grid">
        <!-- Payment Status Card -->
        <div class="payment-card">
            <div class="status-header">
                <div class="status-icon <?= ($existing_payment && $existing_payment['payment_status'] === 'completed') ? 'paid' : 'pending' ?>">
                    <i class="fas <?= ($existing_payment && $existing_payment['payment_status'] === 'completed') ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                </div>
                <div class="status-content">
                    <h3>Registration Status</h3>
                    <span class="status-badge <?= ($existing_payment && $existing_payment['payment_status'] === 'completed') ? 'paid' : 'pending' ?>">
                        <?= ($existing_payment && $existing_payment['payment_status'] === 'completed') ? 'Paid' : 'Payment Required' ?>
                    </span>
                </div>
            </div>
            
            <?php if ($existing_payment && $existing_payment['payment_status'] === 'completed'): ?>
                <div class="payment-details">
                    <div class="detail-row">
                        <span class="detail-label">Payment ID</span>
                        <span class="detail-value"><?= htmlspecialchars($existing_payment['razorpay_payment_id']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Amount Paid</span>
                        <span class="detail-value">₹<?= isset($existing_payment['amount']) && $existing_payment['amount'] ? number_format($existing_payment['amount'], 2) : '999.00' ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Date</span>
                        <span class="detail-value">
                            <?php 
                            if (isset($existing_payment['payment_date']) && $existing_payment['payment_date']) {
                                echo date('d M Y, h:i A', strtotime($existing_payment['payment_date']));
                            } else if (isset($existing_payment['created_at']) && $existing_payment['created_at']) {
                                echo date('d M Y, h:i A', strtotime($existing_payment['created_at']));
                            } else {
                                echo date('d M Y, h:i A');
                            }
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Action Card -->
        <div class="payment-card">
            <div class="amount-display">
                <div class="amount-label">Registration Fee</div>
                <div class="amount-value">₹<?= number_format($registration_fee_inr) ?></div>
            </div>
            
            <?php if (!$existing_payment || $existing_payment['payment_status'] !== 'completed'): ?>
                <div class="company-info">
                    <div class="info-item">
                        <i class="fas fa-building"></i>
                        <div>
                            <strong>Company:</strong>
                            <span><?= htmlspecialchars($company_name) ?></span>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-id-card"></i>
                        <div>
                            <strong>Company ID:</strong>
                            <span><?= htmlspecialchars($company_id) ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($razorpay_order_id): ?>
                    <button id="pay-button" class="btn btn-primary">
                        <i class="fas fa-lock"></i>
                        Proceed to Secure Payment
                    </button>
                    
                    <div class="payment-methods">
                        <p>
                            <i class="fas fa-shield-alt"></i>
                            100% Secure Payment powered by Razorpay
                        </p>
                        <div class="payment-icons">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fas fa-university"></i>
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Unable to initialize payment gateway. Please check your connection and try again.</p>
                        <button onclick="window.location.reload()" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Retry Payment
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="success-state">
                    <i class="fas fa-check-circle"></i>
                    <h4>Payment Completed</h4>
                    <p>Your company registration is active with full access to all features</p>
                    
                    <div class="features-list">
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Access all applications</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Premium support</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Analytics dashboard</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaction History -->
    <?php if ($existing_payment && $existing_payment['payment_status'] === 'completed'): ?>
        <div class="transaction-history">
            <div class="history-card">
                <div class="history-header">
                    <i class="fas fa-history"></i>
                    <h3>Transaction History</h3>
                </div>
                <div class="transaction-table">
                    <div class="table-header">
                        <div>Date</div>
                        <div>Description</div>
                        <div>Payment ID</div>
                        <div>Amount</div>
                        <div>Status</div>
                    </div>
                    <div class="table-row">
                        <div class="transaction-date" data-label="Date">
                            <?php 
                            if (isset($existing_payment['payment_date']) && $existing_payment['payment_date']) {
                                echo date('d M Y', strtotime($existing_payment['payment_date']));
                            } else if (isset($existing_payment['created_at']) && $existing_payment['created_at']) {
                                echo date('d M Y', strtotime($existing_payment['created_at']));
                            } else {
                                echo date('d M Y');
                            }
                            ?>
                        </div>
                        <div class="transaction-desc" data-label="Description">Company Registration Fee</div>
                        <div class="transaction-id" data-label="Payment ID"><?= htmlspecialchars($existing_payment['razorpay_payment_id']) ?></div>
                        <div class="transaction-amount" data-label="Amount">
                            ₹<?= isset($existing_payment['amount']) && $existing_payment['amount'] ? number_format($existing_payment['amount'], 2) : '999.00' ?>
                        </div>
                        <div data-label="Status">
                            <span class="transaction-status">Completed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Hidden form for payment -->
<form id="payment-form" method="POST" style="display: none;">
    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
    <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
    <input type="hidden" name="razorpay_signature" id="razorpay_signature">
</form>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($razorpay_order_id && (!$existing_payment || $existing_payment['payment_status'] !== 'completed')): ?>
    
    const payButton = document.getElementById('pay-button');
    if (payButton) {
        payButton.onclick = function(e) {
            e.preventDefault();
            
            // Show loading state
            payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            payButton.disabled = true;
            
            var options = {
                "key": "<?= $razorpay_key_id ?>",
                "amount": "<?= $registration_fee ?>",
                "currency": "INR",
                "name": "Nexttern",
                "description": "Company Registration Fee",
                "order_id": "<?= $razorpay_order_id ?>",
                "prefill": {
                    "name": "<?= htmlspecialchars($company_name) ?>",
                    "contact": ""
                },
                "theme": {
                    "color": "#035946"
                },
                "handler": function (response) {
                    document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                    document.getElementById('razorpay_order_id').value = response.razorpay_order_id;
                    document.getElementById('razorpay_signature').value = response.razorpay_signature;
                    document.getElementById('payment-form').submit();
                },
                "modal": {
                    "ondismiss": function() {
                        // Restore button state
                        payButton.innerHTML = '<i class="fas fa-lock"></i> Proceed to Secure Payment';
                        payButton.disabled = false;
                    }
                }
            };
            
            try {
                var rzp = new Razorpay(options);
                rzp.on('payment.failed', function (response) {
                    alert('Payment failed: ' + response.error.description);
                    // Restore button state
                    payButton.innerHTML = '<i class="fas fa-lock"></i> Proceed to Secure Payment';
                    payButton.disabled = false;
                });
                rzp.open();
            } catch (error) {
                alert('Unable to open payment gateway. Please try again.');
                // Restore button state
                payButton.innerHTML = '<i class="fas fa-lock"></i> Proceed to Secure Payment';
                payButton.disabled = false;
            }
        };
    }
    
    <?php endif; ?>
});
</script>