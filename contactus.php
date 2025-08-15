<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";
$conn = new mysqli($servername, $username, $password, $dbname);
$db_connection_error = '';
$success_message = '';
$form_error = '';
$email_error = '';
$name = '';
$email = '';
$subject = '';
$message = '';

if ($conn->connect_error) {
    $db_connection_error = "We're sorry, we can't connect to our database at the moment. Please try again later.";
} else {
    // --- Handle Contact Form Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_submitted'])) {
        $name = htmlspecialchars(trim($_POST['name']));
        $email = htmlspecialchars(trim($_POST['email']));
        $subject = htmlspecialchars(trim($_POST['subject']));
        $message = htmlspecialchars(trim($_POST['message']));

        $has_error = false;

        // Simple validation for required fields
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $form_error = "All fields are required. Please fill out the form completely.";
            $has_error = true;
        }

        // Specific email format validation
        if (!str_ends_with($email, '@gmail.com')) {
            $email_error = "Please use a valid email address ending in @gmail.com.";
            $has_error = true;
        }

        // Prepare and execute the INSERT statement only if there are no errors
        if (!$has_error) {
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssss", $name, $email, $subject, $message);
                if ($stmt->execute()) {
                    $success_message = "Thank you for your message! We'll get back to you as soon as possible.";
                    // Clear form data on success
                    $name = $email = $subject = $message = '';
                } else {
                    $form_error = "An error occurred while submitting your message. Please try again.";
                }
                $stmt->close();
            } else {
                $form_error = "Failed to prepare the database statement.";
            }
        }
    }

    // --- Fetch Contact Information for Display ---
    $contact_data = [];
    $result = $conn->query("SELECT section_key, content FROM contact_content");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $contact_data[$row['section_key']] = $row['content'];
        }
    }
}

// Close the database connection
if ($conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">  
    <style>
    :root {
        --primary: #035946;
        --primary-light: #0a7058;
        --primary-dark: #023d32;
        --secondary: #2e3944;
        --accent: #4ecdc4;
        --success: #27ae60;
        --danger: #e74c3c;
        --bg-light: #f8fcfb;
        --glass-bg: rgba(255, 255, 255, 0.25);
        --glass-border: rgba(255, 255, 255, 0.3);
        --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
        --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
        --blur: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --border-radius: 16px;
    }
    body {
        font-family: 'Roboto', sans-serif;
        background: linear-gradient(135deg, var(--bg-light) 0%, #ffffff 100%);
        color: var(--secondary);
        line-height: 1.6;
        padding: 2rem;
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        overflow-y: auto;
    }
    .contact-container {
        width: 100%;
        max-width: 900px;
        margin-top: 2rem;
    }
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
        text-align: center;
        animation: fadeInUp 0.5s ease-out;
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
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }

    .page-description {
        font-family: 'Roboto', sans-serif;
        color: var(--secondary);
        opacity: 0.8;
        font-size: 1.1rem;
        max-width: 600px;
        margin: 0 auto;
    }

    /* Container for contact information blocks */
    .contact-info-display, .contact-form {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--blur));
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--shadow-light);
        animation: fadeInUp 0.5s ease-out 0.2s both;
        margin-bottom: 2rem;
    }

    /* Styling for individual contact items */
    .contact-item {
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 12px;
        border-left: 4px solid var(--accent);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .contact-item:hover {
        background: rgba(255, 255, 255, 0.5);
        transform: translateY(-4px);
        box-shadow: 0 6px 20px rgba(3, 89, 70, 0.1);
    }
    
    .contact-item h4 {
        font-family: 'Poppins', sans-serif;
        color: var(--primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.1rem;
    }

    .contact-item h4 i {
        color: var(--accent);
    }

    .contact-item p {
        color: var(--secondary);
        line-height: 1.6;
        margin: 0;
        white-space: pre-line; /* Keeps line breaks from the database */
    }
    
    /* Specific styling for links */
    .contact-item p a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s ease;
    }

    .contact-item p a:hover {
        color: var(--accent);
        text-decoration: underline;
    }
    
    /* Form specific styles */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-input, .form-textarea {
        width: 100%;
        padding: 1rem;
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        font-size: 0.95rem;
        color: var(--secondary);
        transition: var(--transition);
        font-family: inherit;
        box-sizing: border-box;
    }

    .form-input:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--accent);
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        transform: translateY(-1px);
    }

    .form-textarea {
        min-height: 120px;
        resize: vertical;
    }
    
    .btn-primary {
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        font-size: 0.95rem;
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25);
    }

    .btn-primary:hover {
        background: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(3, 89, 70, 0.35);
    }
    
    /* Alert styling */
    .alert {
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
    }
    .alert-success {
        background: rgba(39, 174, 96, 0.1);
        color: var(--success);
        border: 1px solid rgba(39, 174, 96, 0.2);
    }
    .alert-error {
        background: rgba(231, 76, 60, 0.1);
        color: var(--danger);
        border: 1px solid rgba(231, 76, 60, 0.2);
    }
    
    /* Styling for inline error messages */
    .error-message {
        display: block;
        margin-top: 0.25rem;
        color: var(--danger);
        font-size: 0.8rem;
    }

    /* Animation keyframes */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @media (max-width: 768px) {
        body { padding: 1rem; }
        .contact-container { margin-top: 1rem; }
        .page-title { font-size: 2rem; }
        .page-header { padding: 1.5rem; }
        .contact-info-display, .contact-form { padding: 1.5rem; }
    }
    </style>
</head>
<body>

<div class="contact-container">
    <?php if ($db_connection_error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($db_connection_error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($form_error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($form_error) ?>
        </div>
    <?php endif; ?>

    <header class="page-header">
        <h1 class="page-title">Contact Us</h1>
        <p class="page-description">
            Have a question or need to get in touch? Find the right contact information or send us a message below.
        </p>
    </header>

    <?php if (!$db_connection_error): ?>
        <section class="contact-info-display">
            <div class="contact-item">
                <h4><i class="fas fa-map-marker-alt"></i> Our Address</h4>
                <p><?= nl2br(htmlspecialchars($contact_data['address'] ?? '')) ?></p>
            </div>
            
            <div class="contact-item">
                <h4><i class="fas fa-clock"></i> Working Hours</h4>
                <p><?= nl2br(htmlspecialchars($contact_data['working_hours'] ?? '')) ?></p>
            </div>
            
            <div class="contact-item">
                <h4><i class="fas fa-envelope"></i> Email Inquiries</h4>
                <p>
                    <a href="mailto:<?= htmlspecialchars($contact_data['university_email'] ?? '') ?>">University Relations: <?= htmlspecialchars($contact_data['university_email'] ?? '') ?></a><br>
                    <a href="mailto:<?= htmlspecialchars($contact_data['media_email'] ?? '') ?>">Media & PR: <?= htmlspecialchars($contact_data['media_email'] ?? '') ?></a><br>
                    <a href="mailto:<?= htmlspecialchars($contact_data['sponsorship_email'] ?? '') ?>">Sponsorship: <?= htmlspecialchars($contact_data['sponsorship_email'] ?? '') ?></a><br>
                    <a href="mailto:<?= htmlspecialchars($contact_data['support_email'] ?? '') ?>">General Support: <?= htmlspecialchars($contact_data['support_email'] ?? '') ?></a>
                </p>
            </div>
        </section>

        <!-- New Contact Form Section -->
        <section class="contact-form">
            <form method="post" action="">
                <input type="hidden" name="form_submitted" value="1">
                
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; color: var(--primary); margin-bottom: 2rem; text-align: center;">Send Us a Message</h3>

                <div class="form-group">
                    <label class="form-label" for="name">Your Name</label>
                    <input type="text" id="name" name="name" class="form-input" required value="<?= htmlspecialchars($name) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Your Email</label>
                    <input type="email" id="email" name="email" class="form-input" required value="<?= htmlspecialchars($email) ?>">
                    <?php if ($email_error): ?>
                        <span class="error-message"><?= htmlspecialchars($email_error) ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" class="form-input" required value="<?= htmlspecialchars($subject) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="message">Your Message</label>
                    <textarea id="message" name="message" class="form-textarea" required><?= htmlspecialchars($message) ?></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i>
                    Send Message
                </button>
            </form>
        </section>
    <?php endif; ?>
</div>

</body>
</html>
