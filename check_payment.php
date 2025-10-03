<?php
// check_payment.php - Include this at the top of every protected company page
// This middleware checks if payment is completed before allowing access

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if company is logged in
if (!isset($_SESSION['company_id'])) {
    header('Location: logincompany.html');
    exit();
}

// Store original requested page in session if not set
if (!isset($_SESSION['payment_redirect_attempted'])) {
    $_SESSION['payment_redirect_attempted'] = false;
}

// Function to check payment status
function hasCompletedPayment($company_id) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    try {
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        if ($conn->connect_error) {
            error_log("Payment check DB connection failed: " . $conn->connect_error);
            return false;
        }
        
        // Check if payment exists and is completed
        $stmt = $conn->prepare("SELECT payment_status FROM company_payment WHERE company_id = ? AND payment_status = 'completed' LIMIT 1");
        
        if ($stmt) {
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $hasPayment = $result->num_rows > 0;
            $stmt->close();
            $conn->close();
            return $hasPayment;
        }
        
        $conn->close();
        return false;
        
    } catch (Exception $e) {
        error_log("Payment check error: " . $e->getMessage());
        return false;
    }
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Pages that don't require payment check (whitelist)
$exempt_pages = [
    'company_payments.php',  // The payment page itself
    'logoutcompany.php',     // Logout page
    'validate_session.php'   // Session validation
];

// Check if current page is exempt
$is_exempt = in_array($current_page, $exempt_pages);

// If not exempt and using query parameter system (company_dashboard.php?page=X)
if (!$is_exempt && isset($_GET['page'])) {
    $page = $_GET['page'];
    // Allow access to payments page via query parameter
    if ($page === 'payments') {
        $is_exempt = true;
    }
}

// If page is not exempt, check payment status
if (!$is_exempt) {
    $company_id = $_SESSION['company_id'];
    
    if (!hasCompletedPayment($company_id)) {
        // Payment not completed - redirect to payment page
        
        // For query parameter system
        if (strpos($current_page, 'company_dashboard.php') !== false) {
            header('Location: company_dashboard.php?page=payments');
            exit();
        } else {
            // For separate file system
            header('Location: company_payments.php');
            exit();
        }
    }
}
?>