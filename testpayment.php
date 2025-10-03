<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...<br>";

// Start session
session_start();
echo "Session started<br>";

// Test database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=nexttern_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connected successfully<br>";
} catch(PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "<br>";
    die();
}

// Check if table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'company_payment'");
    if ($stmt->rowCount() > 0) {
        echo "company_payment table exists<br>";
    } else {
        echo "company_payment table does NOT exist<br>";
        // Create table
        $create_table = "
        CREATE TABLE company_payment (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id VARCHAR(50) NOT NULL UNIQUE,
            company_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password VARCHAR(255) NULL,
            payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
            razorpay_payment_id VARCHAR(255) NULL,
            payment_date DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($create_table);
        echo "Table created successfully<br>";
    }
} catch(PDOException $e) {
    echo "Table check/creation failed: " . $e->getMessage() . "<br>";
}

// Set test session variables if they don't exist
if (!isset($_SESSION['company_id'])) {
    $_SESSION['company_id'] = 'TEST_COMP_001';
    $_SESSION['company_name'] = 'Test Company Ltd';
    $_SESSION['company_email'] = 'test@company.com';
    echo "Set test session variables<br>";
}

// Display session variables
echo "Session variables:<br>";
echo "Company ID: " . ($_SESSION['company_id'] ?? 'Not set') . "<br>";
echo "Company Name: " . ($_SESSION['company_name'] ?? 'Not set') . "<br>";
echo "Company Email: " . ($_SESSION['company_email'] ?? 'Not set') . "<br>";

// Test cURL (for Razorpay API)
if (function_exists('curl_init')) {
    echo "cURL is available<br>";
} else {
    echo "cURL is NOT available - this will cause Razorpay integration to fail<br>";
}

echo "<hr>";
echo "If you see this message, basic PHP is working. Now test the actual payment page.";
echo "<br><a href='company_dashboard.php?page=payments'>Go to Payment Page</a>";
?>