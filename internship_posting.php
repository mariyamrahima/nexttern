<?php

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

// Handle AJAX submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['is_ajax'])) {

    // Set content type to application/json
    header('Content-Type: application/json');

    // Create a new mysqli connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check if the connection was successful
    if ($conn->connect_error) {
        echo json_encode(['status' => 'error', 'message' => "Connection failed: " . $conn->connect_error]);
        exit();
    }
    
    // Retrieve and trim form data, including the new 'one_line_description'
    $course_title = trim($_POST['course_title']);
    $one_line_description = trim($_POST['one_line_description']); // NEW FIELD
    $course_category = trim($_POST['course_category']);
    $duration = trim($_POST['duration']);
    $difficulty_level = trim($_POST['difficulty_level']);
    $course_description = trim($_POST['course_description']);
    $what_you_will_learn = trim($_POST['what_you_will_learn']);
    $enrollment_deadline = trim($_POST['enrollment_deadline']);
    $max_students = (int)trim($_POST['max_students']);
    $course_price_type = trim($_POST['course_price']);
    
    // Handle the price amount
    $price_amount = ($course_price_type === 'paid' || $course_price_type === 'freemium') && isset($_POST['price_amount']) ? (float)trim($_POST['price_amount']) : 0.00;
    
    $contact_email = trim($_POST['contact_email']);
    $enrollment_instructions = trim($_POST['enrollment_instructions']);

    // SQL query using prepared statements.
    // NOTE: The 'one_line_description' column is added to the INSERT query.
    $sql = "INSERT INTO courses (
        course_title, 
        one_line_description,
        course_category, 
        duration, 
        difficulty_level, 
        course_description, 
        what_you_will_learn, 
        enrollment_deadline, 
        max_students, 
        course_price_type, 
        price_amount, 
        contact_email, 
        enrollment_instructions
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Prepare the SQL statement
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => "Error preparing statement: " . $conn->error]);
        $conn->close();
        exit();
    }

    // Bind parameters, including the new 'one_line_description'
    // NOTE: The bind_param type string is updated to reflect the new string parameter.
    $stmt->bind_param(
        "ssssssssidiss",
        $course_title,
        $one_line_description, // NEW BIND PARAM
        $course_category,
        $duration,
        $difficulty_level,
        $course_description,
        $what_you_will_learn,
        $enrollment_deadline,
        $max_students,
        $course_price_type,
        $price_amount,
        $contact_email,
        $enrollment_instructions
    );

    // Execute the statement
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => "New course record created successfully! ✅"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Error: " . $stmt->error]);
    }

    // Close statement and connection
    $stmt->close();
    $conn->close();

    // End script execution for AJAX request
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Internship | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Your CSS code */
        :root {
            --primary: #035946;
            --primary-light: #0a7058;
            --primary-dark: #023d32;
            --secondary: #2e3944;
            --accent: #4ecdc4;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --bg-light: #f8fcfb;
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.3);
            --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
            --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
            --blur: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --border-radius: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #ffffff 100%);
            color: var(--secondary);
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background Blobs */
        .blob {
            position: fixed;
            border-radius: 50%;
            z-index: 0;
            animation: moveBlob 20s infinite alternate ease-in-out;
        }

        .blob1 { 
            width: 600px; 
            height: 600px; 
            background: rgba(3, 89, 70, 0.12); 
            top: -150px; 
            right: -200px; 
        }

        .blob2 { 
            width: 400px; 
            height: 400px; 
            background: rgba(78, 205, 196, 0.15); 
            top: 200px; 
            right: -150px; 
            animation-delay: 2s; 
        }

        .blob3 { 
            width: 350px; 
            height: 350px; 
            background: rgba(3, 89, 70, 0.08); 
            bottom: 100px; 
            left: -180px; 
            animation-delay: 4s; 
        }

        .blob4 { 
            width: 250px; 
            height: 250px; 
            background: rgba(78, 205, 196, 0.12); 
            bottom: -100px; 
            left: 150px; 
            animation-delay: 1s; 
        }

        .blob5 { 
            width: 300px; 
            height: 300px; 
            background: rgba(3, 89, 70, 0.06); 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            animation-delay: 3s; 
        }

        /* Blob Movement Animation */
        @keyframes moveBlob {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.1); }
            100% { transform: translate(-30px, 30px) scale(0.9); }
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        /* Header Section */
        .page-header {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
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
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            position: relative;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: var(--secondary);
            opacity: 0.8;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Form Container */
        .form-container {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--shadow-medium);
            animation: fadeInUp 0.6s ease-out 0.2s both;
            position: relative;
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 2.5rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            border-left: 4px solid var(--accent);
            transition: var(--transition);
        }

        .form-section:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(3, 89, 70, 0.1);
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--accent);
            font-size: 1.1rem;
        }

        /* Form Groups */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--accent);
            font-size: 0.8rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 1rem;
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            font-size: 0.95rem;
            color: var(--secondary);
            transition: var(--transition);
            font-family: inherit;
            outline: none;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
            transform: translateY(-1px);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* Skills Input */
        .skills-container {
            position: relative;
        }

        .skills-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .skill-tag {
            background: var(--primary);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease-out;
        }

        .skill-tag .remove-skill {
            cursor: pointer;
            font-size: 0.7rem;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .skill-tag .remove-skill:hover {
            opacity: 1;
        }

        /* Action Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .btn {
            padding: 0.8rem 2rem;
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
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(3, 89, 70, 0.35);
        }

        .btn-secondary {
            background: transparent;
            color: var(--secondary);
            border: 1px solid var(--glass-border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        /* Alert message styling */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 12px;
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

        /* Preview Card */
        .preview-section {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-top: 2rem;
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }

        .preview-card {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid var(--success);
        }

        .preview-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .preview-content {
            color: var(--secondary);
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .preview-description {
            font-size: 1rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        /* Animations */
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

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Loading State */
        .loading {
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-title {
                font-size: 2.2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .blob1, .blob2, .blob3, .blob4, .blob5 {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>
    <div class="blob blob3"></div>
    <div class="blob blob4"></div>
    <div class="blob blob5"></div>

    <div class="container">
        <header class="page-header">
            <h1 class="page-title">
                <i class="fas fa-graduation-cap" style="color: var(--accent); margin-right: 0.5rem;"></i>
                Post New Course
            </h1>
            <p class="page-subtitle">
                Share educational courses and help students learn new skills to advance their careers.
            </p>
        </header>
        
        <!-- This div will be used to display messages dynamically via JavaScript -->
        <div id="message_area"></div>

        <div class="form-container">
            <form id="courseForm" method="post" action="">
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-book-open"></i>
                        Course Details
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="course_title">
                                <i class="fas fa-heading"></i>
                                Course Title
                            </label>
                            <input type="text" id="course_title" name="course_title" class="form-input" placeholder="e.g., Complete Web Development Bootcamp" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="one_line_description">
                                <i class="fas fa-edit"></i>
                                One-Line Description
                            </label>
                            <input type="text" id="one_line_description" name="one_line_description" class="form-input" placeholder="e.g., Learn to build and deploy modern web applications." required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="course_category">
                                <i class="fas fa-tags"></i>
                                Category
                            </label>
                            <select id="course_category" name="course_category" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="programming">Programming & Development</option>
                                <option value="design">Design & Creative</option>
                                <option value="business">Business & Finance</option>
                                <option value="marketing">Digital Marketing</option>
                                <option value="data_science">Data Science & Analytics</option>
                                <option value="ai_ml">AI & Machine Learning</option>
                                <option value="cybersecurity">Cybersecurity</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="duration">
                                <i class="fas fa-clock"></i>
                                Course Duration
                            </label>
                            <select id="duration" name="duration" class="form-select" required>
                                <option value="">Select Duration</option>
                                <option value="1_week">1 Week</option>
                                <option value="2_weeks">2 Weeks</option>
                                <option value="1_month">1 Month</option>
                                <option value="2_months">2 Months</option>
                                <option value="3_months">3 Months</option>
                                <option value="6_months">6 Months</option>
                                <option value="self_paced">Self-Paced</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="difficulty_level">
                            <i class="fas fa-chart-line"></i>
                            Difficulty Level
                        </label>
                        <select id="difficulty_level" name="difficulty_level" class="form-select" required>
                            <option value="">Select Level</option>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="all_levels">All Levels</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="course_description">
                            <i class="fas fa-file-alt"></i>
                            Course Description
                        </label>
                        <textarea id="course_description" name="course_description" class="form-textarea" placeholder="Describe what this course covers, teaching methodology, and learning approach..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="what_you_will_learn">
                            <i class="fas fa-lightbulb"></i>
                            What You Will Learn
                        </label>
                        <textarea id="what_you_will_learn" name="what_you_will_learn" class="form-textarea" placeholder="List the key skills, concepts, and knowledge students will gain from this course..." required></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-plus"></i>
                        Enrollment Details
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="enrollment_deadline">
                                <i class="fas fa-calendar-alt"></i>
                                Enrollment Deadline
                            </label>
                            <input type="date" id="enrollment_deadline" name="enrollment_deadline" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="max_students">
                                <i class="fas fa-users"></i>
                                Maximum Students
                            </label>
                            <input type="number" id="max_students" name="max_students" class="form-input" min="1" value="50" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="course_price">
                                <i class="fas fa-tag"></i>
                                Course Price
                            </label>
                            <select id="course_price" name="course_price" class="form-select" required>
                                <option value="">Select Price Type</option>
                                <option value="free">Free</option>
                                <option value="paid">Paid</option>
                                <option value="freemium">Freemium</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="price_amount_group" style="display: none;">
                            <label class="form-label" for="price_amount">
                                <i class="fas fa-dollar-sign"></i>
                                Price Amount</label>
                            <input type="number" id="price_amount" name="price_amount" class="form-input" placeholder="0" min="0">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="contact_email">
                            <i class="fas fa-at"></i>
                            Contact Email
                        </label>
                        <input type="email" id="contact_email" name="contact_email" class="form-input" placeholder="education@company.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="enrollment_instructions">
                            <i class="fas fa-file-text"></i>
                            Enrollment Instructions
                        </label>
                        <textarea id="enrollment_instructions" name="enrollment_instructions" class="form-textarea" placeholder="Any specific instructions for students enrolling in this course..."></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="previewCourse()">
                        <i class="fas fa-eye"></i>
                        Preview
                    </button>
                    <button type="submit" id="submit_button" class="btn btn-primary">
                        <i class="fas fa-rocket"></i>
                        Publish Course
                    </button>
                </div>
            </form>
        </div>

        <div class="preview-section" id="preview_section" style="display: none;">
            <h3 class="section-title">
                <i class="fas fa-eye"></i>
                Preview
            </h3>
            <div class="preview-card" id="preview_content">
                </div>
        </div>
    </div>

    <script>
        // DOM elements
        const courseForm = document.getElementById('courseForm');
        const submitButton = document.getElementById('submit_button');
        const messageArea = document.getElementById('message_area');
        const priceAmountGroup = document.getElementById('price_amount_group');
        const priceAmountInput = document.getElementById('price_amount');

        // Show/hide price amount field based on course price type
        document.getElementById('course_price').addEventListener('change', function() {
            if (this.value === 'paid' || this.value === 'freemium') {
                priceAmountGroup.style.display = 'block';
                priceAmountInput.setAttribute('required', 'required');
            } else {
                priceAmountGroup.style.display = 'none';
                priceAmountInput.removeAttribute('required');
            }
        });

        // Handle form submission with AJAX
        courseForm.addEventListener('submit', function(event) {
            // Prevent the default form submission that causes a page reload
            event.preventDefault();
            
            // Disable the button and show a loading state
            submitButton.disabled = true;
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Publishing...`;
            
            // Clear any previous messages
            messageArea.innerHTML = '';
            
            // Create a FormData object from the form
            const formData = new FormData(this);
            // Add a flag to indicate this is an AJAX request
            formData.append('is_ajax', '1');

            // Use fetch API to send the data to the same PHP file
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json()) // Parse the JSON response from PHP
            .then(data => {
                // Check the status from the JSON response
                if (data.status === 'success') {
                    // Display success message
                    messageArea.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            ${data.message}
                        </div>
                    `;
                    // Optional: Reset the form
                    courseForm.reset();
                } else {
                    // Display error message
                    messageArea.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                // Handle network errors or issues with the fetch request
                console.error('Error:', error);
                messageArea.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        A network error occurred. Please try again.
                    </div>
                `;
            })
            .finally(() => {
                // Re-enable the button and reset its text after the request is complete
                submitButton.disabled = false;
                submitButton.innerHTML = `<i class="fas fa-rocket"></i> Publish Course`;
            });
        });

        // Preview functionality
        function previewCourse() {
            const previewSection = document.getElementById('preview_section');
            const previewContent = document.getElementById('preview_content');
            
            const formData = new FormData(document.getElementById('courseForm'));
            
            const oneLineDescription = formData.get('one_line_description'); // NEW
            
            let priceText = formData.get('course_price');
            if (priceText === 'paid' || priceText === 'freemium') {
                priceText += ': ' + formData.get('price_amount');
            }

            let previewHTML = `
                <div class="preview-title">
                    <i class="fas fa-book-open" style="color: var(--primary); margin-right: 0.5rem;"></i>
                    ${formData.get('course_title') || 'Course Title'}
                </div>
                <div class="preview-description">
                     ${oneLineDescription || 'Not specified'}
                </div>
                <div class="preview-content">
                    <strong>Category:</strong> ${formData.get('course_category') || 'Not specified'}<br>
                    <strong>Duration:</strong> ${formData.get('duration') || 'Not specified'}<br>
                    <strong>Level:</strong> ${formData.get('difficulty_level') || 'Not specified'}<br>
                    <strong>Price:</strong> ${priceText || 'Not specified'}<br>
                    <strong>Enrollment Deadline:</strong> ${formData.get('enrollment_deadline') || 'Not specified'}<br>
                    <strong>Max Students:</strong> ${formData.get('max_students') || 'Not specified'}
                </div>
            `;
            
            previewContent.innerHTML = previewHTML;
            previewSection.style.display = 'block';
            previewSection.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
