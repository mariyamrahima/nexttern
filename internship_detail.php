<?php
// Sample internship data (in a real application, this would come from a database)
$internships = [
    1 => [
        'id' => 1,
        'title' => 'Web Development Internship',
        'company' => 'TechCorp Solutions',
        'domain' => 'Technology',
        'location' => 'Remote',
        'duration' => '3 months',
        'stipend' => '₹15,000/month',
        'description' => 'Work on cutting-edge web applications using modern frameworks and technologies. You will be part of our dynamic development team and work on real-world projects that impact thousands of users.',
        'requirements' => 'HTML, CSS, JavaScript, React/Vue.js knowledge preferred',
        'responsibilities' => [
            'Develop responsive web applications using modern frameworks',
            'Collaborate with UI/UX designers to implement pixel-perfect designs',
            'Write clean, maintainable, and well-documented code',
            'Participate in code reviews and team meetings',
            'Debug and fix issues in existing applications',
            'Learn and implement new technologies as needed'
        ],
        'qualifications' => [
            'Currently pursuing or recently completed degree in Computer Science or related field',
            'Strong foundation in HTML, CSS, and JavaScript',
            'Familiarity with React.js or Vue.js',
            'Understanding of responsive web design principles',
            'Good problem-solving skills',
            'Excellent communication skills'
        ],
        'skills' => ['HTML', 'CSS', 'JavaScript', 'React'],
        'posted_date' => '2024-08-10',
        'application_deadline' => '2024-09-10',
        'start_date' => '2024-09-15',
        'company_description' => 'TechCorp Solutions is a leading technology company specializing in web and mobile application development. We work with clients across various industries to build innovative digital solutions.',
        'perks' => [
            'Flexible working hours',
            'Mentorship from senior developers',
            'Certificate of completion',
            'Opportunity for full-time conversion',
            'Access to premium learning resources'
        ]
    ],
    2 => [
        'id' => 2,
        'title' => 'Digital Marketing Internship',
        'company' => 'Creative Agency',
        'domain' => 'Marketing',
        'location' => 'Bangalore',
        'duration' => '4 months',
        'stipend' => '₹12,000/month',
        'description' => 'Learn and implement digital marketing strategies across various platforms including social media, email marketing, and content creation.',
        'requirements' => 'Basic understanding of social media, Google Analytics knowledge is a plus',
        'responsibilities' => [
            'Create engaging content for social media platforms',
            'Assist in email marketing campaigns',
            'Analyze marketing metrics and prepare reports',
            'Support SEO and content marketing efforts',
            'Collaborate with design team for visual content',
            'Research market trends and competitor analysis'
        ],
        'qualifications' => [
            'Currently pursuing degree in Marketing, Communications, or related field',
            'Strong written and verbal communication skills',
            'Creative thinking and attention to detail',
            'Basic understanding of social media platforms',
            'Familiarity with Google Analytics (preferred)',
            'Ability to work in a fast-paced environment'
        ],
        'skills' => ['Social Media', 'Analytics', 'Content Creation', 'SEO'],
        'posted_date' => '2024-08-12',
        'application_deadline' => '2024-09-12',
        'start_date' => '2024-09-20',
        'company_description' => 'Creative Agency is a full-service digital marketing agency helping brands connect with their audience through innovative marketing strategies.',
        'perks' => [
            'Hands-on experience with real campaigns',
            'Training on premium marketing tools',
            'Networking opportunities',
            'Performance-based incentives',
            'Career guidance and mentorship'
        ]
    ],
    3 => [
        'id' => 3,
        'title' => 'Data Science Internship',
        'company' => 'DataTech Labs',
        'domain' => 'Technology',
        'location' => 'Mumbai',
        'duration' => '6 months',
        'stipend' => '₹20,000/month',
        'description' => 'Work with big data, machine learning models, and data visualization tools to extract meaningful insights from complex datasets.',
        'requirements' => 'Python, SQL, basic ML knowledge, statistics background',
        'responsibilities' => [
            'Analyze large datasets to identify patterns and trends',
            'Develop and implement machine learning models',
            'Create data visualizations and dashboards',
            'Clean and preprocess raw data',
            'Collaborate with cross-functional teams',
            'Present findings to stakeholders'
        ],
        'qualifications' => [
            'Currently pursuing degree in Data Science, Computer Science, Statistics, or related field',
            'Proficiency in Python and SQL',
            'Understanding of statistical concepts and methods',
            'Familiarity with machine learning algorithms',
            'Experience with data visualization tools (Tableau, Power BI, or similar)',
            'Strong analytical and problem-solving skills'
        ],
        'skills' => ['Python', 'SQL', 'Machine Learning', 'Data Analysis'],
        'posted_date' => '2024-08-08',
        'application_deadline' => '2024-09-08',
        'start_date' => '2024-09-10',
        'company_description' => 'DataTech Labs is at the forefront of data science and analytics, helping organizations make data-driven decisions through advanced analytics and machine learning.',
        'perks' => [
            'Access to cutting-edge technology',
            'Mentorship from data science experts',
            'Conference and workshop attendance',
            'Opportunity to work on diverse projects',
            'Potential for research publication'
        ]
    ]
];

// Get internship ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$show_application = isset($_GET['apply']) && $_GET['apply'] == '1';

// Check if internship exists
if (!isset($internships[$id])) {
    header('Location: index.php');
    exit;
}

$internship = $internships[$id];

// Handle form submission
$application_success = false;
$application_error = '';

if ($_POST && isset($_POST['submit_application'])) {
    // Basic validation
    $required_fields = ['name', 'email', 'phone', 'education', 'experience'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst($field) . ' is required';
        }
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    if (empty($errors)) {
        // In a real application, you would save to database
        $application_success = true;
    } else {
        $application_error = implode(', ', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($internship['title']); ?> - Nexttern</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Main Content */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        /* Internship Details */
        .internship-details {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .internship-header {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #f1f3f4;
        }

        .internship-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 1rem;
        }

        .company-name {
            font-size: 1.5rem;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .internship-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .meta-icon {
            font-size: 1.2rem;
        }

        .stipend-highlight {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .section {
            margin-bottom: 2rem;
        }

        .section h3 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
            padding-left: 1rem;
        }

        .section p {
            margin-bottom: 1rem;
            color: #555;
            line-height: 1.7;
        }

        .section ul {
            list-style: none;
            padding-left: 0;
        }

        .section li {
            margin-bottom: 0.75rem;
            padding-left: 1.5rem;
            position: relative;
            color: #555;
        }

        .section li:before {
            content: '✓';
            color: #28a745;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .skill-tag {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .perks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .perk-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #28a745;
        }

        /* Application Form */
        .application-sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .application-form {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .form-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .file-input {
            padding: 0.5rem !important;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: transform 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .application-steps {
            margin-bottom: 2rem;
        }

        .step {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: bold;
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .main-container {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .application-sidebar {
                position: static;
                order: -1;
            }

            .internship-title {
                font-size: 2rem;
            }

            .internship-meta {
                grid-template-columns: 1fr;
            }

            .nav-container {
                padding: 0 1rem;
            }

            .main-container {
                padding: 1rem;
            }
        }

        @media (max-width: 600px) {
            .internship-title {
                font-size: 1.5rem;
            }

            .company-name {
                font-size: 1.2rem;
            }

            .perks-grid {
                grid-template-columns: 1fr;
            }
        }

        .quick-apply-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .quick-apply-btn:hover {
            transform: translateY(-3px);
        }

        @media (max-width: 968px) {
            .quick-apply-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">Nexttern</a>
            <a href="index.php" class="back-btn">← Back to Search</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Internship Details -->
        <div class="internship-details">
            <div class="internship-header">
                <h1 class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></h1>
                <div class="company-name"><?php echo htmlspecialchars($internship['company']); ?></div>
                
                <div class="internship-meta">
                    <div class="meta-item">
                        <span class="meta-icon">📍</span>
                        <span><?php echo htmlspecialchars($internship['location']); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-icon">⏱️</span>
                        <span><?php echo htmlspecialchars($internship['duration']); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-icon">🏷️</span>
                        <span><?php echo htmlspecialchars($internship['domain']); ?></span>
                    </div>
                    <div class="meta-item stipend-highlight">
                        <span class="meta-icon">💰</span>
                        <span><?php echo htmlspecialchars($internship['stipend']); ?></span>
                    </div>
                </div>

                <div class="internship-meta">
                    <div class="meta-item">
                        <span class="meta-icon">📅</span>
                        <span>Starts: <?php echo date('M d, Y', strtotime($internship['start_date'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-icon">⏰</span>
                        <span>Apply by: <?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?></span>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3>About the Internship</h3>
                <p><?php echo htmlspecialchars($internship['description']); ?></p>
            </div>

            <div class="section">
                <h3>Key Responsibilities</h3>
                <ul>
                    <?php foreach ($internship['responsibilities'] as $responsibility): ?>
                        <li><?php echo htmlspecialchars($responsibility); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="section">
                <h3>Qualifications</h3>
                <ul>
                    <?php foreach ($internship['qualifications'] as $qualification): ?>
                        <li><?php echo htmlspecialchars($qualification); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="section">
                <h3>Required Skills</h3>
                <div class="skills-list">
                    <?php foreach ($internship['skills'] as $skill): ?>
                        <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section">
                <h3>About <?php echo htmlspecialchars($internship['company']); ?></h3>
                <p><?php echo htmlspecialchars($internship['company_description']); ?></p>
            </div>

            <div class="section">
                <h3>Perks & Benefits</h3>
                <div class="perks-grid">
                    <?php foreach ($internship['perks'] as $perk): ?>
                        <div class="perk-item">
                            <strong><?php echo htmlspecialchars($perk); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Application Sidebar -->
        <div class="application-sidebar">
            <div class="application-form">
                <?php if ($application_success): ?>
                    <div class="success-message">
                        <h3>Application Submitted Successfully!</h3>
                        <p>Thank you for your interest. We'll review your application and get back to you soon.</p>
                    </div>
                <?php else: ?>
                    <h2 class="form-title">Apply for this Internship</h2>
                    
                    <div class="application-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <span>Fill out the application form</span>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <span>Upload your resume</span>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <span>Submit and wait for response</span>
                        </div>
                    </div>

                    <?php if ($application_error): ?>
                        <div class="error-message">
                            <strong>Error:</strong> <?php echo htmlspecialchars($application_error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" required 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="education">Education *</label>
                            <input type="text" id="education" name="education" 
                                   placeholder="e.g., B.Tech Computer Science, XYZ University" required 
                                   value="<?php echo htmlspecialchars($_POST['education'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="experience">Experience Level *</label>
                            <select id="experience" name="experience" required>
                                <option value="">Select experience level</option>
                                <option value="fresher" <?php echo ($_POST['experience'] ?? '') === 'fresher' ? 'selected' : ''; ?>>Fresher</option>
                                <option value="0-6months" <?php echo ($_POST['experience'] ?? '') === '0-6months' ? 'selected' : ''; ?>>0-6 months</option>
                                <option value="6months-1year" <?php echo ($_POST['experience'] ?? '') === '6months-1year' ? 'selected' : ''; ?>>6 months - 1 year</option>
                                <option value="1-2years" <?php echo ($_POST['experience'] ?? '') === '1-2years' ? 'selected' : ''; ?>>1-2 years</option>
                                <option value="2+years" <?php echo ($_POST['experience'] ?? '') === '2+years' ? 'selected' : ''; ?>>2+ years</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="resume">Resume (PDF/DOC) *</label>
                            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" 
                                   class="file-input" required>
                        </div>

                        <div class="form-group">
                            <label for="cover_letter">Cover Letter</label>
                            <textarea id="cover_letter" name="cover_letter" 
                                      placeholder="Tell us why you're interested in this internship..."><?php echo htmlspecialchars($_POST['cover_letter'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="portfolio">Portfolio/LinkedIn URL</label>
                            <input type="url" id="portfolio" name="portfolio" 
                                   placeholder="https://..." 
                                   value="<?php echo htmlspecialchars($_POST['portfolio'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="availability">When can you start?</label>
                            <input type="date" id="availability" name="availability" 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo htmlspecialchars($_POST['availability'] ?? ''); ?>">
                        </div>

                        <button type="submit" name="submit_application" class="submit-btn">
                            Submit Application
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Apply Button (for mobile) -->
    <button class="quick-apply-btn" onclick="scrollToApplication()">
        Apply Now
    </button>

    <script>
        function scrollToApplication() {
            document.querySelector('.application-form').scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['name', 'email', 'phone', 'education', 'experience'];
            const errors = [];

            requiredFields.forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (!input.value.trim()) {
                    errors.push(`${field.charAt(0).toUpperCase() + field.slice(1)} is required`);
                    input.style.borderColor = '#dc3545';
                } else {
                    input.style.borderColor = '#28a745';
                }
            });

            // Email validation
            const email = document.querySelector('[name="email"]').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && !emailRegex.test(email)) {
                errors.push('Please enter a valid email address');
                document.querySelector('[name="email"]').style.borderColor = '#dc3545';
            }

            // Resume file validation
            const resume = document.querySelector('[name="resume"]').files[0];
            if (resume) {
                const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                if (!allowedTypes.includes(resume.type)) {
                    errors.push('Resume must be a PDF or DOC file');
                    document.querySelector('[name="resume"]').style.borderColor = '#dc3545';
                }
                if (resume.size > 5 * 1024 * 1024) { // 5MB limit
                    errors.push('Resume file size must be less than 5MB');
                    document.querySelector('[name="resume"]').style.borderColor = '#dc3545';
                }
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n' + errors.join('\n'));
            }
        });

        // Real-time form validation
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.style.borderColor = '#dc3545';
                } else if (this.value.trim()) {
                    this.style.borderColor = '#28a745';
                } else {
                    this.style.borderColor = '#e1e5e9';
                }
            });
        });

        // Auto-scroll to application form if apply=1 in URL
        <?php if ($show_application): ?>
            window.addEventListener('load', function() {
                setTimeout(() => {
                    scrollToApplication();
                }, 500);
            });
        <?php endif; ?>
    </script>
</body>
</html>