<?php
// registercompany.php - Backend API for Company Registration
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'nexttern_db';
    private $username = 'your_db_username';
    private $password = 'your_db_password';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

// Company Registration Class
class CompanyRegistration {
    private $conn;
    private $table_name = "companies";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Validate input data
    public function validateInput($data) {
        $errors = [];

        // Company Name validation
        if (empty($data['company_name']) || strlen(trim($data['company_name'])) < 2) {
            $errors['company_name'] = 'Company name must be at least 2 characters';
        }

        // Industry Type validation
        $valid_industries = ['IT', 'Finance', 'Healthcare', 'Education', 'Manufacturing', 'Retail', 'Consulting', 'Other'];
        if (empty($data['industry_type']) || !in_array($data['industry_type'], $valid_industries)) {
            $errors['industry_type'] = 'Please select a valid industry type';
        }

        // Email validation
        if (empty($data['company_email']) || !filter_var($data['company_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['company_email'] = 'Please enter a valid email address';
        }

        // Year established validation
        $current_year = date('Y');
        if (empty($data['year_established']) || !is_numeric($data['year_established']) || 
            $data['year_established'] < 1800 || $data['year_established'] > $current_year) {
            $errors['year_established'] = 'Please select a valid year';
        }

        // Contact Name validation
        if (empty($data['contact_name']) || strlen(trim($data['contact_name'])) < 2) {
            $errors['contact_name'] = 'Contact name must be at least 2 characters';
        }

        // Designation validation
        if (empty($data['designation']) || strlen(trim($data['designation'])) < 2) {
            $errors['designation'] = 'Designation must be at least 2 characters';
        }

        // Phone validation
        if (empty($data['contact_phone']) || !$this->validatePhone($data['contact_phone'])) {
            $errors['contact_phone'] = 'Please enter a valid phone number';
        }

        // Transaction ID validation (for payment confirmation)
        if (empty($data['transaction_id'])) {
            $errors['transaction_id'] = 'Transaction ID is required';
        }

        return $errors;
    }

    // Phone number validation
    private function validatePhone($phone) {
        $cleanPhone = preg_replace('/\D/', '', $phone);
        return strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 15;
    }

    // Check if email already exists
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE company_email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Verify payment with Razorpay
    public function verifyPayment($paymentId, $amount = 50000) {
        // Razorpay API credentials
        $razorpay_key_id = 'YOUR_RAZORPAY_KEY_ID';
        $razorpay_key_secret = 'YOUR_RAZORPAY_KEY_SECRET';

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/payments/$paymentId");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERPWD, "$razorpay_key_id:$razorpay_key_secret");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200) {
                $payment = json_decode($result, true);
                
                // Check if payment is captured and amount matches
                if ($payment['status'] == 'captured' && $payment['amount'] == $amount) {
                    return array('success' => true, 'payment' => $payment);
                } else {
                    return array('success' => false, 'message' => 'Payment verification failed');
                }
            } else {
                return array('success' => false, 'message' => 'Unable to verify payment');
            }
        } catch (Exception $e) {
            error_log("Payment verification error: " . $e->getMessage());
            return array('success' => false, 'message' => 'Payment verification failed');
        }
    }

    // Register company
    public function register($data) {
        try {
            // Start transaction
            $this->conn->beginTransaction();

            $query = "INSERT INTO " . $this->table_name . " 
                      (company_name, industry_type, company_email, year_established, 
                       contact_name, designation, contact_phone, transaction_id, 
                       payment_status, registration_date, status) 
                      VALUES 
                      (:company_name, :industry_type, :company_email, :year_established,
                       :contact_name, :designation, :contact_phone, :transaction_id,
                       :payment_status, NOW(), :status)";

            $stmt = $this->conn->prepare($query);

            // Sanitize data
            $company_name = htmlspecialchars(strip_tags(trim($data['company_name'])));
            $industry_type = htmlspecialchars(strip_tags($data['industry_type']));
            $company_email = filter_var($data['company_email'], FILTER_SANITIZE_EMAIL);
            $year_established = intval($data['year_established']);
            $contact_name = htmlspecialchars(strip_tags(trim($data['contact_name'])));
            $designation = htmlspecialchars(strip_tags(trim($data['designation'])));
            $contact_phone = htmlspecialchars(strip_tags(trim($data['contact_phone'])));
            $transaction_id = htmlspecialchars(strip_tags($data['transaction_id']));
            $payment_status = 'completed';
            $status = 'active';

            // Bind parameters
            $stmt->bindParam(':company_name', $company_name);
            $stmt->bindParam(':industry_type', $industry_type);
            $stmt->bindParam(':company_email', $company_email);
            $stmt->bindParam(':year_established', $year_established);
            $stmt->bindParam(':contact_name', $contact_name);
            $stmt->bindParam(':designation', $designation);
            $stmt->bindParam(':contact_phone', $contact_phone);
            $stmt->bindParam(':transaction_id', $transaction_id);
            $stmt->bindParam(':payment_status', $payment_status);
            $stmt->bindParam(':status', $status);

            if ($stmt->execute()) {
                $company_id = $this->conn->lastInsertId();
                
                // Send confirmation email
                $this->sendConfirmationEmail($data, $company_id);
                
                // Commit transaction
                $this->conn->commit();
                
                return array(
                    'success' => true,
                    'message' => 'Company registered successfully!',
                    'company_id' => $company_id
                );
            } else {
                $this->conn->rollback();
                return array('success' => false, 'message' => 'Registration failed. Please try again.');
            }

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Registration error: " . $e->getMessage());
            return array('success' => false, 'message' => 'Registration failed. Please try again.');
        }
    }

    // Send confirmation email
    private function sendConfirmationEmail($data, $company_id) {
        $to = $data['company_email'];
        $subject = "Company Registration Successful - Nexttern";
        
        $message = "
        <html>
        <head>
            <title>Registration Confirmation</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #035946; color: white; padding: 20px; text-align: center;'>
                    <h1>Welcome to Nexttern!</h1>
                </div>
                <div style='padding: 20px; background: #f9f9f9;'>
                    <h2>Registration Successful</h2>
                    <p>Dear " . htmlspecialchars($data['contact_name']) . ",</p>
                    <p>Thank you for registering your company with Nexttern. Your registration has been completed successfully.</p>
                    
                    <div style='background: white; padding: 15px; margin: 20px 0; border-left: 4px solid #035946;'>
                        <h3>Company Details:</h3>
                        <p><strong>Company ID:</strong> " . $company_id . "</p>
                        <p><strong>Company Name:</strong> " . htmlspecialchars($data['company_name']) . "</p>
                        <p><strong>Industry:</strong> " . htmlspecialchars($data['industry_type']) . "</p>
                        <p><strong>Email:</strong> " . htmlspecialchars($data['company_email']) . "</p>
                        <p><strong>Contact Person:</strong> " . htmlspecialchars($data['contact_name']) . "</p>
                        <p><strong>Transaction ID:</strong> " . htmlspecialchars($data['transaction_id']) . "</p>
                    </div>
                    
                    <p>You can now access your company dashboard and start posting internship opportunities.</p>
                    <p>If you have any questions, please don't hesitate to contact our support team.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='#' style='background: #035946; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>Access Dashboard</a>
                    </div>
                </div>
                <div style='text-align: center; padding: 20px; font-size: 12px; color: #666;'>
                    <p>&copy; 2025 Nexttern. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: noreply@nexttern.com' . "\r\n";

        mail($to, $subject, $message, $headers);
    }
}

// API Endpoint Handlers
class APIHandler {
    private $companyReg;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db === null) {
            $this->sendResponse(false, 'Database connection failed', 500);
            exit;
        }
        
        $this->companyReg = new CompanyRegistration($db);
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';

        switch ($method) {
            case 'POST':
                if ($action === 'register') {
                    $this->registerCompany();
                } elseif ($action === 'verify-payment') {
                    $this->verifyPayment();
                } else {
                    $this->sendResponse(false, 'Invalid action', 400);
                }
                break;
            case 'GET':
                if ($action === 'check-email') {
                    $this->checkEmailExists();
                } else {
                    $this->sendResponse(false, 'Invalid action', 400);
                }
                break;
            default:
                $this->sendResponse(false, 'Method not allowed', 405);
        }
    }

    private function registerCompany() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendResponse(false, 'Invalid JSON data', 400);
            return;
        }

        // Validate input
        $errors = $this->companyReg->validateInput($input);
        if (!empty($errors)) {
            $this->sendResponse(false, 'Validation failed', 400, ['errors' => $errors]);
            return;
        }

        // Check if email already exists
        if ($this->companyReg->emailExists($input['company_email'])) {
            $this->sendResponse(false, 'Email already registered', 409, [
                'errors' => ['company_email' => 'This email is already registered']
            ]);
            return;
        }

        // Verify payment
        $paymentVerification = $this->companyReg->verifyPayment($input['transaction_id']);
        if (!$paymentVerification['success']) {
            $this->sendResponse(false, $paymentVerification['message'], 400);
            return;
        }

        // Register company
        $result = $this->companyReg->register($input);
        
        if ($result['success']) {
            $this->sendResponse(true, $result['message'], 201, [
                'company_id' => $result['company_id']
            ]);
        } else {
            $this->sendResponse(false, $result['message'], 500);
        }
    }

    private function verifyPayment() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['payment_id'])) {
            $this->sendResponse(false, 'Payment ID required', 400);
            return;
        }

        $result = $this->companyReg->verifyPayment($input['payment_id']);
        
        if ($result['success']) {
            $this->sendResponse(true, 'Payment verified successfully', 200, [
                'payment' => $result['payment']
            ]);
        } else {
            $this->sendResponse(false, $result['message'], 400);
        }
    }

    private function checkEmailExists() {
        $email = $_GET['email'] ?? '';
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(false, 'Invalid email', 400);
            return;
        }

        $exists = $this->companyReg->emailExists($email);
        $this->sendResponse(true, '', 200, ['exists' => $exists]);
    }

    private function sendResponse($success, $message, $httpCode = 200, $data = []) {
        http_response_code($httpCode);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// Initialize and handle request
try {
    $api = new APIHandler();
    $api->handleRequest();
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>