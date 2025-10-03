<?php
// Start session to check login status
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

function validateUserSession() {
    // Check admin session
    if (isset($_SESSION['admin_id'])) {
        return [
            'isLoggedIn' => true,
            'userType' => 'admin',
            'userId' => $_SESSION['admin_id'],
            'userName' => 'Admin User',
            'userRole' => 'admin'
        ];
    }
    
    // Check company session
    if (isset($_SESSION['company_id'])) {
        return validateCompanyUser();
    }
    
    // Check student/regular user session
    if (isset($_SESSION['user_id']) || isset($_SESSION['logged_in']) || isset($_SESSION['email'])) {
        return validateRegularUser();
    }
    
    return [
        'isLoggedIn' => false,
        'userType' => null,
        'userId' => null,
        'userName' => '',
        'userRole' => ''
    ];
}

function validateCompanyUser() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $company_conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($company_conn->connect_error) {
        return ['isLoggedIn' => false, 'userType' => null, 'userId' => null, 'userName' => '', 'userRole' => ''];
    }
    
    $company_id = $_SESSION['company_id'];
    $stmt = $company_conn->prepare("SELECT company_id, company_name, industry_type FROM companies WHERE company_id = ? AND status = 'active'");
    $stmt->bind_param("s", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $company_data = $result->fetch_assoc();
        $stmt->close();
        $company_conn->close();
        
        return [
            'isLoggedIn' => true,
            'userType' => 'company',
            'userId' => $company_data['company_id'],
            'userName' => $company_data['company_name'],
            'userRole' => 'company',
            'industryType' => $company_data['industry_type'] ?? ''
        ];
    }
    
    $stmt->close();
    $company_conn->close();
    
    return ['isLoggedIn' => false, 'userType' => null, 'userId' => null, 'userName' => '', 'userRole' => ''];
}

function validateRegularUser() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $user_conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($user_conn->connect_error) {
        return ['isLoggedIn' => false, 'userType' => null, 'userId' => null, 'userName' => '', 'userRole' => ''];
    }
    
    $user_data = null;
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $user_conn->prepare("SELECT id, name, email, profile_picture, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        }
        $stmt->close();
    }
    
    if (!$user_data && isset($_SESSION['email'])) {
        $email = $_SESSION['email'];
        $stmt = $user_conn->prepare("SELECT student_id as id, CONCAT(first_name, ' ', last_name) as name, email, profile_photo as profile_picture, 'student' as role FROM students WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        }
        $stmt->close();
    }
    
    $user_conn->close();
    
    if ($user_data) {
        return [
            'isLoggedIn' => true,
            'userType' => $user_data['role'],
            'userId' => $user_data['id'],
            'userName' => $user_data['name'],
            'userRole' => $user_data['role'],
            'userEmail' => $user_data['email'],
            'userProfilePicture' => $user_data['profile_picture'] ?? ''
        ];
    }
    
    return ['isLoggedIn' => false, 'userType' => null, 'userId' => null, 'userName' => '', 'userRole' => ''];
}

$sessionData = validateUserSession();
$isLoggedIn = $sessionData['isLoggedIn'];
$user_id = $sessionData['userId'] ?? '';
$user_name = $sessionData['userName'] ?? 'User';
$user_email = $sessionData['userEmail'] ?? '';
$user_profile_picture = $sessionData['userProfilePicture'] ?? '';
$user_role = $sessionData['userRole'] ?? 'student';
$industry_type = $sessionData['industryType'] ?? '';

$unread_count = 0;
if ($isLoggedIn && $user_id) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nexttern_db";
    
    $user_conn = new mysqli($servername, $username, $password, $dbname);
    
    if (!$user_conn->connect_error) {
        if ($user_role === 'student') {
            $unread_stmt = $user_conn->prepare("SELECT COUNT(*) as unread_count FROM student_messages WHERE receiver_type = 'student' AND receiver_id = ? AND is_read = FALSE");
            $unread_stmt->bind_param("s", $user_id);
        } elseif ($user_role === 'admin') {
            $unread_count = 0;
        } elseif ($user_role === 'company') {
            // Companies don't have messages in this system yet
            $unread_count = 0;
        } else {
            $unread_stmt = $user_conn->prepare("SELECT COUNT(*) as unread_count FROM user_messages WHERE receiver_id = ? AND is_read = FALSE");
            $unread_stmt->bind_param("i", $user_id);
        }
        
        if (isset($unread_stmt)) {
            $unread_stmt->execute();
            $unread_result = $unread_stmt->get_result();
            $unread_count = $unread_result->fetch_assoc()['unread_count'];
            $unread_stmt->close();
        }
        $user_conn->close();
    }
}

// Database connection parameters for courses
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Dynamic filtering logic
$course_filter = isset($_GET['course']) ? $conn->real_escape_string($_GET['course']) : '';
$mode_filter = isset($_GET['mode']) ? $conn->real_escape_string($_GET['mode']) : '';
$duration_filter = isset($_GET['duration']) ? $conn->real_escape_string($_GET['duration']) : '';

$where_clauses = [];
$params = [];
$types = '';

if (!empty($course_filter)) {
    $where_clauses[] = "course_category = ?";
    $params[] = $course_filter;
    $types .= 's';
}

if (!empty($duration_filter)) {
    $where_clauses[] = "duration = ?";
    $params[] = $duration_filter;
    $types .= 's';
}

if (!empty($mode_filter)) {
    $where_clauses[] = "mode = ?";
    $params[] = $mode_filter;
    $types .= 's';
}

// Updated SQL query to fetch ONLY from Course table 
$sql = "SELECT id,company_name, course_title, course_category, duration, difficulty_level, mode, 
               max_students, course_description, skills_taught, course_price_type, 
               price_amount, certificate_provided, featured, created_at, course_status
        FROM course 
        WHERE course_status = 'Active'";

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY created_at DESC";

// Prepare and execute the statement
if (!empty($where_clauses)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$courses_data = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $courses_data[] = $row;
    }
}

// Close statement and connection
if (isset($stmt)) $stmt->close();
$conn->close();

// Define available categories and durations for filters
$courses_categories = [
    'Programming',
    'Design', 
    'Business',
    'Marketing',
    'Data Science',
    'AI/ML',
    'Cybersecurity',
    'Finance',
    'Healthcare',
    'Engineering'
];

$available_durations = [
    '1 Week' => '1 Week',
    '2 Weeks' => '2 Weeks',
    '1 Month' => '1 Month',
    '2 Months' => '2 Months',
    '3 Months' => '3 Months',
    '6 Months' => '6 Months',
    'Self-Paced' => 'Self-Paced'
];

$available_modes = ['online', 'offline', 'hybrid'];

// Define course categories with descriptions - Fixed for better organization
$course_categories_detailed = [
    'Full Stack Development' => [
        'categories' => ['Programming', 'Engineering'],
        'description' => 'Master both front-end and back-end development technologies',
        'icon' => 'fas fa-code'
    ],
    'Data Science & AI' => [
        'categories' => ['Data Science', 'AI/ML'],
        'description' => 'Explore data analytics, machine learning, and artificial intelligence',
        'icon' => 'fas fa-brain'
    ],
    'Digital Marketing' => [
        'categories' => ['Marketing', 'Business'],
        'description' => 'Learn modern marketing strategies and digital advertising',
        'icon' => 'fas fa-bullhorn'
    ],
    'UI/UX Design' => [
        'categories' => ['Design'],
        'description' => 'Create beautiful and intuitive user experiences',
        'icon' => 'fas fa-palette'
    ],
    'Cybersecurity' => [
        'categories' => ['Cybersecurity', 'Engineering'],
        'description' => 'Protect systems and data from digital threats',
        'icon' => 'fas fa-shield-alt'
    ]
];

// Organize courses by categories
$categorized_courses = [];
$max_courses_per_category = 6; // Increased to show more courses per section

foreach ($course_categories_detailed as $category_name => $category_info) {
    $categorized_courses[$category_name] = [
        'info' => $category_info,
        'courses' => []
    ];
    
    $count = 0;
    foreach ($courses_data as $course) {
        if ($count >= $max_courses_per_category) break;
        
        if (in_array($course['course_category'], $category_info['categories'])) {
            $categorized_courses[$category_name]['courses'][] = $course;
            $count++;
        }
    }
}
// Fixed renderCourseCard function with proper blur logic
function renderCourseCard($course, $isLoggedIn, $card_index, $cards_before_blur) {
    // For non-logged users, don't render cards beyond the limit
    if (!$isLoggedIn && $card_index > $cards_before_blur) {
        return '';
    }
    
    $card_html = '<div class="internship-card" id="card-' . htmlspecialchars($course['id']) . '" 
                       onclick="' . ($isLoggedIn ? 'redirectToDetail(' . $course['id'] . ')' : 'showLoginModal(\'view\', ' . $course['id'] . ')') . '">
                    
                    <!-- Mode Badge -->
                    <div class="mode-badge mode-' . htmlspecialchars($course['mode'] ?? 'online') . '">
                        ' . ucfirst(htmlspecialchars($course['mode'] ?? 'online')) . '
                    </div>
                    
                    <!-- Featured/New Badge -->
                    ';
    
    if ($course['featured'] == 1) {
        $card_html .= '<div class="status-badge status-featured">Featured</div>';
    } elseif (strtotime($course['created_at']) > strtotime('-7 days')) {
        $card_html .= '<div class="status-badge status-new">New</div>';
    }
    
    $card_html .= '
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="card-header-content">
                            <h4 class="course-title">' . htmlspecialchars($course['course_title']) . '</h4>
                            <div class="course-category">' . htmlspecialchars($course['course_category']) . '</div>
                        </div>
                        <div class="card-actions">
                            <button class="action-icon save-btn" data-id="' . htmlspecialchars($course['id']) . '" onclick="event.stopPropagation(); toggleSaveCourse(' . $course['id'] . ')">
                                <i class="fas fa-bookmark"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Card Meta -->
                    <div class="card-meta">
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            ' . htmlspecialchars($course['duration']) . '
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-signal"></i>
                            ' . htmlspecialchars(ucfirst($course['difficulty_level'])) . '
                        </div>';
    
    if ($course['max_students'] > 0) {
        $card_html .= '
                        <div class="meta-item">
                            <i class="fas fa-users"></i>
                            Max: ' . htmlspecialchars($course['max_students']) . '
                        </div>';
    }
    
    $card_html .= '
                    </div>

                    <!-- Description -->
                    <p class="card-description">
                        ' . htmlspecialchars($course['course_description']) . '
                    </p>';
    
    // Skills section
    if (!empty($course['skills_taught'])) {
        $card_html .= '<div class="card-skills">';
        $skills = array_slice(explode(',', $course['skills_taught']), 0, 3);
        foreach ($skills as $skill) {
            $card_html .= '<span class="skill-tag">' . htmlspecialchars(trim($skill)) . '</span>';
        }
        if (count(explode(',', $course['skills_taught'])) > 3) {
            $card_html .= '<span class="skill-tag">+' . (count(explode(',', $course['skills_taught'])) - 3) . ' more</span>';
        }
        $card_html .= '</div>';
    }
    
    $card_html .= '
                    <!-- Card Footer -->
                    <div class="card-footer">
                        <div class="price-info">
                            <div class="price-amount">';
    
    if ($course['course_price_type'] === 'free' || $course['price_amount'] == 0) {
        $card_html .= 'Free';
    } else {
        $card_html .= '₹' . number_format($course['price_amount'], 0);
    }
    
    $card_html .= '</div>';
    
    if ($course['certificate_provided']) {
        $card_html .= '<div class="price-type">Certificate Included</div>';
    }
    
    $card_html .= '
                        </div>
                        
                        <button class="apply-btn" onclick="event.stopPropagation(); ' . ($isLoggedIn ? 'redirectToDetail(' . $course['id'] . ')' : 'showLoginModal(\'apply\', ' . $course['id'] . ')') . ';">
                            ' . ($course['course_price_type'] === 'free' ? 'Enroll Free' : 'Enroll Now') . '
                        </button>
                    </div>
                </div>';
    
    return $card_html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexttern - Course Opportunities</title>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;600;700&display=swap" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
      
        /* Root Variables - Optimized */
        :root {
            --primary: #035946;
            --primary-light: #0a7058;
            --primary-dark: #023d32;
            --secondary: #2e3944;
            --accent: #4ecdc4;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --text-muted: #95a5a6;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --border-light: #e9ecef;
            --border-medium: #dee2e6;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(3, 89, 70, 0.08);
            --shadow-lg: 0 8px 25px rgba(3, 89, 70, 0.12);
            --shadow-xl: 0 12px 48px rgba(3, 89, 70, 0.15);
            --transition: all 0.2s ease; /* Reduced from 0.3s */
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            --gradient-accent: linear-gradient(135deg, var(--accent) 0%, #7dd3d8 100%);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --text-dark: #1f2937;
            --white: #ffffff;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e3f2fd 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Enhanced Navbar - Optimized */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 1rem 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(3, 89, 70, 0.1);
            z-index: 1000;
            transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary);
            gap: 0.5rem;
        }

        .nav-logo {
            height: 50px;
            width: auto;
            transition: var(--transition);
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            padding: 0.5rem 0;
        }

        .nav-link:hover {
            color: var(--primary);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-primary);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-cta {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

  
/* Enhanced Profile Navigation with Photo Support */
.nav-profile {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.profile-trigger {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 25px;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    color: var(--primary);
    font-weight: 500;
    box-shadow: var(--shadow-light);
    border: none;
    position: relative;
}

.profile-trigger:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
    background: rgba(255, 255, 255, 0.4);
}

.profile-avatar-container {
    position: relative;
}

.profile-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--primary-light);
    transition: var(--transition);
}

.profile-avatar.default {
    background: var(--gradient-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    font-family: 'Poppins', sans-serif;
}

.profile-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.1rem;
}

.profile-name {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    color: var(--primary-dark);
    font-size: 0.9rem;
    line-height: 1.2;
}

.profile-id {
    font-family: 'Roboto', sans-serif;
    font-weight: 400;
    color: var(--text-secondary);
    font-size: 0.75rem;
    line-height: 1;
}

.message-badge {
    background: var(--danger);
    color: white;
    border-radius: 50%;
    padding: 0.2rem 0.5rem;
    font-size: 0.7rem;
    font-weight: bold;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
    position: absolute;
    top: -5px;
    right: -5px;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

        /* Standard Login Button */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: var(--white);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Menu Toggle for Mobile */
        .menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 0.5rem;
        }

        .menu-toggle span {
            width: 25px;
            height: 3px;
            background: var(--primary);
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 2px;
        }

        /* Header - Optimized */
        .header {
            padding: 8rem 0 3rem;
            text-align: center;
            background: linear-gradient(135deg, rgba(3, 89, 70, 0.02) 0%, rgba(78, 205, 196, 0.02) 100%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 1rem;
        }

        .header p {
            font-size: clamp(1.1rem, 2.5vw, 1.2rem);
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto 2rem;
        }

        /* Fixed Enhanced Welcome Section - Optimized */
        .enhanced-welcome {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(3, 89, 70, 0.1);
            color: var(--primary);
            padding: 2rem;
            margin: 2rem auto;
            border-radius: 16px;
            text-align: center;
            max-width: 800px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .enhanced-welcome:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .enhanced-welcome::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 50%, var(--primary-light) 100%);
            border-radius: 16px 16px 0 0;
        }

        .enhanced-welcome > * {
            position: relative;
            z-index: 1;
        }

        .enhanced-welcome h2 {
            font-size: clamp(1.3rem, 3vw, 1.6rem);
            margin-bottom: 1.2rem;
            font-weight: 600;
            color: var(--primary-dark);
            font-family: 'Poppins', sans-serif;
            line-height: 1.3;
        }

        .welcome-details {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.2rem;
        }

        .welcome-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--primary);
            background: rgba(3, 89, 70, 0.06);
            padding: 0.6rem 1rem;
            border-radius: 20px;
            border: 1px solid rgba(3, 89, 70, 0.1);
            transition: var(--transition);
        }

        .welcome-detail:hover {
            transform: translateY(-1px);
            background: rgba(3, 89, 70, 0.1);
        }

        .welcome-detail i {
            color: var(--accent);
            font-size: 0.9rem;
        }

        .welcome-message {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 400;
            line-height: 1.5;
            margin: 0;
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Search Section - Optimized */
        .search-section {
            margin-bottom: 2.5rem;
        }

        .search-container {
            display: flex;
            max-width: 500px;
            margin: 0 auto;
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        .search-container input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            outline: none;
            font-size: 1rem;
            background: transparent;
        }

        .search-btn {
            padding: 1rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .search-btn:hover {
            background: var(--primary-dark);
        }
        /* Search Results Specific Styling */
.search-results-section {
    margin-bottom: 3rem;
    opacity: 1;
    transform: translateY(0);
}

.search-results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Ensure consistent card heights in search results */
.search-results-grid .internship-card {
    display: flex;
    flex-direction: column;
    height: 100%; /* Make all cards same height */
    min-height: 350px; /* Ensure minimum height */
}

/* Make card content flex to push footer to bottom */
.search-results-grid .internship-card .card-footer {
    margin-top: auto; /* Push footer to bottom */
}

/* Fix grid alignment issues */
.internships-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    align-items: start; /* Align items to start instead of stretch */
}

/* Ensure all cards have consistent structure */
.internship-card {
    display: flex;
    flex-direction: column;
    background: var(--bg-white);
    border-radius: var(--border-radius-lg);
    padding: 1.8rem;
    padding-top: 3rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-light);
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    height: fit-content;
    min-height: 320px;
}

/* Fix for card content distribution */
.internship-card .card-description {
    flex-grow: 1; /* Allow description to grow */
    margin-bottom: 1rem;
}

.internship-card .card-footer {
    margin-top: auto; /* Always push to bottom */
    padding-top: 1rem;
    border-top: 1px solid var(--border-light);
}

/* Search results header styling */
.search-results-section .category-title {
    color: var(--primary);
}

.search-results-section .category-title i {
    color: var(--accent);
}

/* Responsive fixes */
@media (max-width: 768px) {
    .search-results-grid,
    .internships-grid {
        grid-template-columns: 1fr;
        gap: 1.2rem;
    }
    
    .search-results-grid .internship-card,
    .internship-card {
        min-height: 300px;
    }
}

@media (max-width: 480px) {
    .search-results-grid .internship-card,
    .internship-card {
        min-height: 280px;
        padding: 1.5rem;
    }
}

        /* Filter Section - Optimized */
        .filter-section {
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
        }

        .filter-section h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-group select {
            padding: 0.8rem;
            border: 1px solid var(--border-medium);
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            background: var(--bg-white);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(3, 89, 70, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-btn {
            padding: 0.8rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .clear-btn {
            padding: 0.8rem 1.5rem;
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-medium);
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .clear-btn:hover {
            background: var(--bg-light);
            color: var(--text-primary);
        }

        /* Results Info */
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-count {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .enhanced-access-badge {
            background: var(--gradient-primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        /* Course Cards Grid - Optimized */
        .internships-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .internship-card {
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            padding: 1.8rem;
            padding-top: 3rem; /* Extra padding for badges */
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            height: fit-content;
            min-height: 320px;
        }

        .internship-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(3, 89, 70, 0.2);
        }

        /* Card Header - Updated */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .card-header-content {
            flex: 1;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: 1rem;
        }

        .action-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
            font-size: 0.9rem;
            border: none;
        }

        .action-icon:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }

        .save-btn.saved {
            background: var(--primary);
            color: white;
        }

        .save-btn.saved i {
            color: white;
        }

        .course-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.4rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .course-category {
            font-size: 0.8rem;
            color: var(--accent);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Mode and Status Badges - Compact uniform sizing */
        .mode-badge,
        .status-badge {
            position: absolute;
            top: 0.8rem;
            border-radius: 15px;
            padding: 0.3rem 0.8rem;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
            text-align: center;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .mode-badge {
            left: 1rem;
        }

        .status-badge {
            right: 1rem;
        }

        /* When both badges are present, adjust positioning */
        .internship-card:has(.mode-badge):has(.status-badge) .status-badge {
            top: 0.8rem;
            right: 1rem;
        }

        .internship-card:has(.mode-badge):has(.status-badge) .mode-badge {
            top: 0.8rem;
            left: 1rem;
        }

        /* Card Meta - Optimized */
        .card-meta {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
            background: var(--bg-light);
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-weight: 500;
        }

        .meta-item i {
            color: var(--accent);
            font-size: 0.8rem;
        }

        /* Card Description */
        .card-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Skills Tags */
        .card-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.2rem;
        }

        .skill-tag {
            background: rgba(3, 89, 70, 0.08);
            color: var(--primary);
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid rgba(3, 89, 70, 0.15);
        }

        /* Card Footer */
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
            margin-top: auto;
        }

        .price-info {
            display: flex;
            flex-direction: column;
        }

        .price-amount {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }

        .price-type {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .apply-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 0.7rem 1.3rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .apply-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Mode and Status Badges */
        .mode-badge,
        .status-badge {
            border-radius: 999px;
            padding: 0.2rem 0.8rem;
            font-size: 0.75rem;
            font-weight: 600;
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 2;
        }

        .status-badge {
            right: 1rem;
        }

        .mode-badge {
            right: 4rem;
        }

        .status-featured {
            background: linear-gradient(135deg, var(--warning) 0%, #ff9500 100%);
            color: white;
        }

        .status-new {
            background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%);
            color: white;
        }

        .mode-online {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success);
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .mode-offline {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.3);
        }

        .mode-hybrid {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
            border: 1px solid rgba(155, 89, 182, 0.3);
        }

        /* Course Categories Section - Fixed */
        .course-categories {
            margin-bottom: 3rem;
        }

        .category-section {
            margin-bottom: 3rem;
            opacity: 1;
            transform: translateY(0);
        }

        .category-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .category-title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.8rem;
            display: inline-block;
            position: relative;
        }

        .category-title::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 2px;
        }

        .category-title i {
            margin-right: 0.6rem;
            color: var(--accent);
        }

        .category-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 400;
            max-width: 500px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* Fixed Content Blur Overlay */
        .blur-section {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .blur-overlay {
            background: linear-gradient(135deg, rgba(3, 89, 70, 0.95) 0%, rgba(78, 205, 196, 0.95) 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            margin: 2rem 0;
        }
        
        .blur-overlay-content {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .blur-overlay-content i {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }
        
        .blur-overlay-content h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .blur-overlay-content p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .blur-overlay-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .blur-overlay-btn {
            padding: 1rem 2rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            min-width: 140px;
            text-align: center;
        }
        
        .blur-overlay-btn-primary {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .blur-overlay-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .blur-overlay-btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.8);
        }
        
        .blur-overlay-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
            transform: translateY(-2px);
        }
        
        /* Hidden content for non-logged users */
        .hidden-content {
            display: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .blur-overlay {
                padding: 3rem 1.5rem;
                margin: 1.5rem 0;
            }
            
            .blur-overlay-content h3 {
                font-size: 1.6rem;
            }
            
            .blur-overlay-content p {
                font-size: 1rem;
            }
            
            .blur-overlay-actions {
                flex-direction: column;
            }
            
            .blur-overlay-btn {
                width: 100%;
            }
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .no-results h3 {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        /* Login Modal - Optimized */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            padding: 0;
            max-width: 420px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            transform: scale(0.9);
            opacity: 0;
            transition: all 0.2s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 1.5rem 0;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.1rem;
            cursor: pointer;
            color: var(--text-muted);
            padding: 0.5rem;
            border-radius: 50%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
        }

        .modal-close:hover {
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 0 1.5rem 1.5rem;
            text-align: center;
        }

        .modal-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.3rem;
            color: white;
            font-size: 1.4rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.3rem;
        }

        .modal-btn {
            flex: 1;
            padding: 0.8rem 1.3rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .modal-btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .modal-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .modal-footer-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .modal-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .modal-link:hover {
            text-decoration: underline;
        }

        /* Footer - Optimized */
        .footer {
            background: var(--secondary);
            color: white;
            padding: 3.5rem 0 2rem;
            margin-top: 3rem;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 3.5rem;
            margin-bottom: 2.5rem;
        }

        .footer-brand {
            max-width: 300px;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Poppins', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .footer-logo i {
            color: var(--accent);
        }

        .footer-brand p {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin-bottom: 1.8rem;
        }

        .social-links {
            display: flex;
            gap: 0.8rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .social-links a:hover {
            background: var(--gradient-primary);
            transform: translateY(-2px);
        }

        .footer-links {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.8rem;
        }

        .footer-column h4 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            margin-bottom: 1.3rem;
            color: white;
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column ul li {
            margin-bottom: 0.7rem;
        }

        .footer-column ul li a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-column ul li a:hover {
            color: var(--accent);
            transform: translateX(3px);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1.8rem;
        }

        .footer-bottom-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-bottom p {
            color: rgba(255, 255, 255, 0.5);
            margin: 0;
        }

        .footer-bottom-links {
            display: flex;
            gap: 1.8rem;
        }

        .footer-bottom-links a {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .footer-bottom-links a:hover {
            color: var(--accent);
        }

        /* Responsive Design - Optimized */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .menu-toggle {
                display: flex;
            }

            .profile-info {
                display: none;
            }

            .profile-trigger {
                padding: 0.5rem;
            }

            .profile-avatar {
                width: 34px;
                height: 34px;
            }

            .header {
                padding: 6rem 0 2.5rem;
            }

            .enhanced-welcome {
                padding: 1.5rem;
                margin: 1.5rem;
            }

            .welcome-details {
                flex-direction: column;
                gap: 0.8rem;
            }

            .welcome-detail {
                width: 100%;
                justify-content: center;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .internships-grid {
                grid-template-columns: 1fr;
                gap: 1.2rem;
            }

            .card-header {
                flex-direction: column;
                gap: 0.8rem;
            }

            .card-meta {
                gap: 0.5rem;
            }

            .card-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .apply-btn {
                width: 100%;
            }

            .footer-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .footer-links {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 0 1rem;
            }

            .container {
                padding: 0 1rem;
            }

            .enhanced-welcome {
                margin: 1rem;
                padding: 1.3rem;
            }

            .blur-message {
                margin: 1rem;
                padding: 1.3rem;
            }

            .blur-actions {
                flex-direction: column;
            }

            .blur-btn {
                width: 100%;
            }

            .nav-container {
                padding: 0 1rem;
            }
            
            .nav-logo {
                height: 45px;
            }

            .internship-card {
                padding: 1.5rem;
                min-height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <img src="nextternnavbar.png" alt="Nexttern Logo" class="nav-logo">
            </a>
            
            <div class="menu-toggle" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="course.php" class="nav-link active">Internships</a></li>
                
                <li><a href="aboutus.php" class="nav-link">About</a></li>
                <li><a href="contactus.php" class="nav-link">Contact</a></li>
            </ul>
            
            <div class="nav-cta">
                <?php if ($isLoggedIn): ?>
                    <div class="nav-profile">
                        <button class="profile-trigger" onclick="redirectToDashboard('<?php echo $user_role; ?>')">
                            <div class="profile-avatar-container">
                                <?php if ($user_role === 'company'): ?>
                                    <!-- Company Avatar: Show first letter of company name -->
                                    <div class="company-initial">
                                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                    </div>
                                <?php elseif (!empty($user_profile_picture) && file_exists($user_profile_picture)): ?>
                                    <!-- Student/User Avatar: Show profile picture -->
                                    <img src="<?php echo htmlspecialchars($user_profile_picture); ?>?v=<?php echo time(); ?>" alt="Profile" class="profile-avatar">
                                <?php else: ?>
                                    <!-- Default Avatar: Show first letter -->
                                    <div class="profile-avatar default">
                                        <?php echo strtoupper(substr($user_name ?: 'U', 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?php echo htmlspecialchars($user_name ?: 'User'); ?></span>
                                <span class="profile-id">ID: <?php echo htmlspecialchars($user_id ?: 'N/A'); ?></span>
                            </div>
                            <?php if ($unread_count > 0): ?>
                                <span class="message-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                <?php else: ?>
                    <a href="login.html" class="btn btn-primary">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>Course Opportunities</h1>
            <p>Master new skills with industry-leading courses and certifications</p>
            <?php if ($isLoggedIn): ?>
                <div class="enhanced-welcome">
                    <h2>Welcome back, <?php echo htmlspecialchars($user_name ?: 'Student'); ?>!</h2>
                    <div class="welcome-details">
                        <div class="welcome-detail">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Role: <?php echo ucfirst(htmlspecialchars($user_role ?: 'Student')); ?></span>
                        </div>
                        <div class="welcome-detail">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Joined: <?php echo !empty($user_joined) ? date('M Y', strtotime($user_joined)) : 'Recently'; ?></span>
                        </div>
                        <?php if ($unread_count > 0): ?>
                        <div class="welcome-detail">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo $unread_count; ?> New Messages</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="welcome-message">
                        Explore all available courses below - full access enabled!
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Login Modal -->
    <div id="loginModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Login Required</h3>
                <button class="modal-close" onclick="closeLoginModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <p id="modalMessage">Please login to access this feature</p>
                <div class="modal-actions">
                    <a href="login.html" class="modal-btn modal-btn-primary">Login</a>
                </div>
                <p class="modal-footer-text">
                    Don't have an account? <a href="registerstudent.html" class="modal-link">Create one now</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Search Section -->
        <section class="search-section">
            <div class="search-container">
                <input type="text" placeholder="Search for courses, skills, or topics..." id="search-input">
                <button type="button" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </section>

        <!-- Filter Section -->
        <section class="filter-section">
            <h2>Find Your Perfect Course</h2>
            <form class="filter-form" method="GET" action="">
                <div class="filter-group">
                    <label for="course">Category</label>
                    <select id="course" name="course">
                        <option value="">All Categories</option>
                        <?php foreach ($courses_categories as $course): ?>
                            <option value="<?php echo htmlspecialchars($course); ?>" 
                                    <?php echo ($_GET['course'] ?? '') === $course ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="mode">Mode</label>
                    <select id="mode" name="mode">
                        <option value="">All Modes</option>
                        <?php foreach ($available_modes as $mode): ?>
                            <option value="<?php echo htmlspecialchars($mode); ?>" 
                                    <?php echo ($_GET['mode'] ?? '') === $mode ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($mode)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="duration">Duration</label>
                    <select id="duration" name="duration">
                        <option value="">All Durations</option>
                        <?php foreach ($available_durations as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                    <?php echo ($_GET['duration'] ?? '') === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="filter-btn">Apply Filters</button>
                    <a href="?" class="clear-btn">Clear All</a>
                </div>
            </form>
        </section>

     <main class="main-content">
            <div class="results-info">
                <div class="results-count">
                    <?php 
                    if (!$isLoggedIn) {
                        echo min(6, count($courses_data)) . " of " . count($courses_data);
                    } else {
                        echo count($courses_data);
                    }
                    ?> course<?php echo count($courses_data) !== 1 ? 's' : ''; ?> <?php echo !$isLoggedIn ? 'preview' : 'available'; ?>
                </div>
                <?php if ($isLoggedIn): ?>
                    <div class="enhanced-access-badge">
                        <i class="fas fa-crown"></i> Full Access
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($courses_data)): ?>
                <div class="no-results">
                    <h3>No courses found</h3>
                    <p>Try adjusting your filters to discover more learning opportunities</p>
                </div>
            <?php else: ?>
                <!-- Course Categories Section with Fixed Blur Logic -->
                <div class="course-categories">
                    <?php 
                    $total_card_index = 0;
                    $cards_before_blur = 6; // Show exactly 6 cards before blur
                    $blur_shown = false;
                    
                    foreach ($categorized_courses as $category_name => $category_data): 
                        if (empty($category_data['courses'])) continue;
                        
                        // Check if we have any visible cards in this category
                        $visible_courses = [];
                        foreach ($category_data['courses'] as $course) {
                            $total_card_index++;
                            if ($isLoggedIn || $total_card_index <= $cards_before_blur) {
                                $visible_courses[] = $course;
                            }
                        }
                        
                        // Only show category if it has visible courses
                        if (empty($visible_courses)) continue;
                    ?>
                        <section class="category-section">
                            <div class="category-header">
                                <h2 class="category-title">
                                    <i class="<?php echo $category_data['info']['icon']; ?>"></i>
                                    <?php echo htmlspecialchars($category_name); ?>
                                </h2>
                                <p class="category-subtitle">
                                    <?php echo htmlspecialchars($category_data['info']['description']); ?>
                                </p>
                            </div>
                            
                            <div class="internships-grid">
                                <?php 
                                $temp_index = $total_card_index - count($category_data['courses']);
                                foreach ($visible_courses as $course):
                                    $temp_index++;
                                    echo renderCourseCard($course, $isLoggedIn, $temp_index, $cards_before_blur);
                                endforeach;
                                ?>
                            </div>
                        </section>
                    <?php 
                        // Show blur overlay after exactly 6 cards for non-logged users
                        if (!$isLoggedIn && !$blur_shown && $total_card_index >= $cards_before_blur && count($courses_data) > $cards_before_blur): 
                            $blur_shown = true;
                        ?>
                            <div class="blur-section">
                                <div class="blur-overlay">
                                    <div class="blur-overlay-content">
                                        <i class="fas fa-graduation-cap"></i>
                                        <h3>Unlock All <?php echo count($courses_data); ?> Courses</h3>
                                        <p>You've previewed <?php echo $cards_before_blur; ?> courses. Join our learning platform to access all <?php echo count($courses_data); ?> courses, track your progress, and advance your career!</p>
                                        <div class="blur-overlay-actions">
                                            <a href="login.html" class="blur-overlay-btn blur-overlay-btn-primary">Login Now</a>
                                            <a href="registerstudent.html" class="blur-overlay-btn blur-overlay-btn-secondary">Sign Up Free</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php break; // Stop rendering after blur overlay ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <!-- Remaining courses section - only for logged in users -->
                    <?php if ($isLoggedIn): ?>
                        <?php
                        $categorized_course_ids = [];
                        foreach ($categorized_courses as $category_data) {
                            foreach ($category_data['courses'] as $course) {
                                $categorized_course_ids[] = $course['id'];
                            }
                        }
                        
                        $remaining_courses = array_filter($courses_data, function($course) use ($categorized_course_ids) {
                            return !in_array($course['id'], $categorized_course_ids);
                        });
                        
                        if (!empty($remaining_courses)):
                        ?>
                            <section class="category-section">
                                <div class="category-header">
                                    <h2 class="category-title">
                                        <i class="fas fa-star"></i>
                                        Other Specializations
                                    </h2>
                                    <p class="category-subtitle">
                                        Explore more specialized courses and unique learning opportunities
                                    </p>
                                </div>
                                
                                <div class="internships-grid">
                                    <?php 
                                    foreach ($remaining_courses as $course):
                                        $total_card_index++;
                                        echo renderCourseCard($course, $isLoggedIn, $total_card_index, $cards_before_blur);
                                    endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand">
                <a href="#" class="footer-logo">
                    <i class="fas fa-graduation-cap"></i>
                    Nexttern
                </a>
                <p>Empowering the next generation of professionals through meaningful course experiences.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-facebook"></i></a>
                </div>
            </div>
            
            <div class="footer-links">
                <div class="footer-column">
                    <h4>For Students</h4>
                    <ul>
                        <li><a href="internship.php">Find Internships</a></li>
                        <li><a href="aboutus.php">Career Resources</a></li>
                        <li><a href="#">Success Stories</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>For Companies</h4>
                    <ul>
                        <li><a href="internship_posting.php">Post Internships</a></li>
                        <li><a href="registercompany.html">Partner with us</a></li>
                        <li><a href="logincompany.html">Start Journey</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="contactus.php">Contact Us</a></li>
                        <li><a href="contactus.php">Help Center</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Nexttern</h4>
                    <ul>
                        <li><a href="aboutus.php">About Us</a></li>
                        <li><a href="contactus.php">FAQS</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-bottom-container">
                <p>&copy; 2025 Nexttern. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
  // Pass PHP variables to JavaScript (keep existing variables)
const isUserLoggedIn = <?php echo json_encode($isLoggedIn); ?>;
const userData = <?php echo json_encode([
    'id' => $user_id,
    'name' => $user_name,
    'email' => $user_email,
    'role' => $user_role,
    'profile_picture' => $user_profile_picture,
    'phone' => $user_phone,
    'location' => $user_location,
    'joined' => $user_joined,
    'dob' => $user_dob
]); ?>;
const unreadMessagesCount = <?php echo json_encode($unread_count); ?>;

// Store original card data for reset functionality
let originalCardData = [];
const CARDS_BEFORE_BLUR = 6;

// Core Navigation Functions (unchanged)
function toggleMobileMenu() {
    const navMenu = document.querySelector('.nav-menu');
    if (navMenu) {
        navMenu.style.display = navMenu.style.display === 'flex' ? 'none' : 'flex';
    }
}

function redirectToDetail(courseId) {
    window.location.href = 'course_detail.php?id=' + courseId;
}

 function redirectToDashboard(userRole) {
            const dashboards = {
                'admin': 'admin_dashboard.php',
                'company': 'company_dashboard.php',
                'student': 'student_dashboard.php'
            };
            window.location.href = dashboards[userRole] || 'student_dashboard.php';
        }
// Modal Functions (unchanged)
function showLoginModal(action, courseId) {
    const modal = document.getElementById('loginModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    
    if (action === 'apply') {
        modalTitle.textContent = 'Login to Apply';
        modalMessage.textContent = 'You need to login to apply for this course. Join thousands of students already learning!';
    } else if (action === 'view') {
        modalTitle.textContent = 'Login to View More';
        modalMessage.textContent = 'Login to view all available courses. Unlock your learning potential!';
    } else if (action === 'save') {
        modalTitle.textContent = 'Login to Save Courses';
        modalMessage.textContent = 'Login to save your favorite courses and access them anytime from your dashboard!';
    } else {
        modalTitle.textContent = 'Login Required';
        modalMessage.textContent = 'Please login to access this feature and continue your learning journey.';
    }
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
        modal.querySelector('.modal-content').style.transform = 'scale(1)';
        modal.querySelector('.modal-content').style.opacity = '1';
    }, 10);
}

function closeLoginModal() {
    const modal = document.getElementById('loginModal');
    const modalContent = modal.querySelector('.modal-content');
    
    modalContent.style.transform = 'scale(0.9)';
    modalContent.style.opacity = '0';
    
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }, 200);
}

// Initialize original card data
function initializeCardData() {
    const cards = document.querySelectorAll('.internship-card');
    originalCardData = [];
    
    cards.forEach((card, index) => {
        const categorySection = card.closest('.category-section');
        originalCardData.push({
            element: card,
            categorySection: categorySection,
            originalDisplay: card.style.display || 'flex',
            cardIndex: index + 1,
            isVisibleToUser: isUserLoggedIn || (index < CARDS_BEFORE_BLUR)
        });
    });
}
// Fixed search function that maintains proper grid layout
function performSearch() {
    const searchTerm = document.getElementById('search-input').value.trim().toLowerCase();
    if (!searchTerm) {
        resetCourseDisplay();
        return;
    }
    
    let totalVisibleCount = 0;
    let totalMatchCount = 0;
    let matchedCards = []; // Store all matched cards
    
    // Hide blur overlay during search
    const blurOverlay = document.querySelector('.blur-overlay');
    if (blurOverlay) {
        blurOverlay.style.display = 'none';
    }
    
    // First, collect all matching cards
    const categorySections = document.querySelectorAll('.category-section');
    categorySections.forEach(categorySection => {
        const cards = categorySection.querySelectorAll('.internship-card');
        
        cards.forEach((card) => {
            const cardTitle = card.querySelector('.course-title').textContent.toLowerCase();
            const cardDescription = card.querySelector('.card-description').textContent.toLowerCase();
            const cardSkills = Array.from(card.querySelectorAll('.skill-tag'))
                .map(skill => skill.textContent.toLowerCase()).join(' ');
            const cardCategory = card.querySelector('.course-category').textContent.toLowerCase();
            
            const isMatch = cardTitle.includes(searchTerm) || 
                           cardDescription.includes(searchTerm) || 
                           cardSkills.includes(searchTerm) ||
                           cardCategory.includes(searchTerm);
            
            if (isMatch) {
                matchedCards.push({
                    card: card,
                    categorySection: categorySection
                });
                totalMatchCount++;
            }
        });
        
        // Hide all category sections initially
        categorySection.style.display = 'none';
    });
    
    // Create or get search results section
    let searchResultsSection = document.querySelector('.search-results-section');
    if (!searchResultsSection) {
        searchResultsSection = document.createElement('section');
        searchResultsSection.className = 'category-section search-results-section';
        searchResultsSection.innerHTML = `
            <div class="category-header">
                <h2 class="category-title">
                    <i class="fas fa-search"></i>
                    Search Results
                </h2>
                <p class="category-subtitle">
                    Courses matching your search criteria
                </p>
            </div>
            <div class="internships-grid search-results-grid"></div>
        `;
        
        // Insert before first category section
        const firstCategory = document.querySelector('.category-section');
        if (firstCategory) {
            firstCategory.parentNode.insertBefore(searchResultsSection, firstCategory);
        }
    }
    
    const searchGrid = searchResultsSection.querySelector('.search-results-grid');
    searchGrid.innerHTML = ''; // Clear previous results
    
    // Add matched cards to search results grid
    matchedCards.forEach((matchData, index) => {
        if (isUserLoggedIn || totalVisibleCount < CARDS_BEFORE_BLUR) {
            // Clone the card to avoid moving it from original position
            const clonedCard = matchData.card.cloneNode(true);
            
            // Update click handlers for cloned card
            updateClonedCardHandlers(clonedCard);
            
            searchGrid.appendChild(clonedCard);
            totalVisibleCount++;
        }
    });
    
    // Show search results section if there are results
    if (totalVisibleCount > 0) {
        searchResultsSection.style.display = 'block';
    } else {
        searchResultsSection.style.display = 'none';
    }
    
    // Update results count
    updateResultsCount(totalVisibleCount, totalMatchCount);
    
    // Handle no results
    handleNoResults(totalVisibleCount);
    
    // Show blur message if there are more matches but user is not logged in
    if (!isUserLoggedIn && totalMatchCount > CARDS_BEFORE_BLUR && totalVisibleCount === CARDS_BEFORE_BLUR) {
        showSearchBlurMessage(totalMatchCount);
    }
}

// Helper function to update event handlers on cloned cards
function updateClonedCardHandlers(clonedCard) {
    // Update main card click handler
    const cardId = clonedCard.id.replace('card-', '');
    clonedCard.onclick = function() {
        if (isUserLoggedIn) {
            redirectToDetail(cardId);
        } else {
            showLoginModal('view', cardId);
        }
    };
    
    // Update save button handler
    const saveBtn = clonedCard.querySelector('.save-btn');
    if (saveBtn) {
        saveBtn.onclick = function(event) {
            event.stopPropagation();
            toggleSaveCourse(cardId);
        };
    }
    
    // Update apply button handler
    const applyBtn = clonedCard.querySelector('.apply-btn');
    if (applyBtn) {
        applyBtn.onclick = function(event) {
            event.stopPropagation();
            if (isUserLoggedIn) {
                redirectToDetail(cardId);
            } else {
                showLoginModal('apply', cardId);
            }
        };
    }
}

// Updated reset function
function resetCourseDisplay() {
    // Hide search results section
    const searchResultsSection = document.querySelector('.search-results-section');
    if (searchResultsSection) {
        searchResultsSection.style.display = 'none';
    }
    
    // Show all original category sections
    const categorySections = document.querySelectorAll('.category-section:not(.search-results-section)');
    categorySections.forEach(section => {
        section.style.display = 'block';
    });
    
    // Reset all cards to original state
    originalCardData.forEach(cardData => {
        if (cardData.isVisibleToUser) {
            cardData.element.style.display = 'flex';
        } else {
            cardData.element.style.display = 'none';
        }
    });
    
    // Show blur overlay for non-logged users if needed
    if (!isUserLoggedIn && originalCardData.length > CARDS_BEFORE_BLUR) {
        const blurOverlay = document.querySelector('.blur-overlay');
        if (blurOverlay) {
            blurOverlay.style.display = 'block';
        }
    }
    
    // Update results count to original
    const totalCards = originalCardData.length;
    updateResultsCount(
        isUserLoggedIn ? totalCards : Math.min(totalCards, CARDS_BEFORE_BLUR), 
        totalCards
    );
    
    // Remove no results message
    const noResults = document.querySelector('.no-results');
    if (noResults) {
        noResults.remove();
    }
    
    // Remove search blur message
    const searchBlurMessage = document.querySelector('.search-blur-message');
    if (searchBlurMessage) {
        searchBlurMessage.remove();
    }
}


function updateResultsCount(visibleCount, totalCount) {
    const resultsCount = document.querySelector('.results-count');
    if (resultsCount) {
        if (isUserLoggedIn) {
            resultsCount.textContent = `${visibleCount} course${visibleCount !== 1 ? 's' : ''} ${totalCount ? 'found' : 'available'}`;
        } else {
            if (totalCount > visibleCount) {
                resultsCount.textContent = `${visibleCount} of ${totalCount} courses found (preview)`;
            } else {
                resultsCount.textContent = `${visibleCount} course${visibleCount !== 1 ? 's' : ''} found`;
            }
        }
    }
}

function handleNoResults(visibleCount) {
    const mainContent = document.querySelector('.main-content');
    const existingNoResults = document.querySelector('.no-results');
    const courseCategories = document.querySelector('.course-categories');
    
    if (visibleCount === 0) {
        if (!existingNoResults) {
            const noResultsDiv = document.createElement('div');
            noResultsDiv.className = 'no-results';
            noResultsDiv.innerHTML = `
                <div style="text-align: center; padding: 3rem;">
                    <i class="fas fa-search" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <h3>No courses found</h3>
                    <p>Try different keywords or browse our course categories</p>
                    <button onclick="document.getElementById('search-input').value=''; resetCourseDisplay();" 
                            class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-times"></i> Clear Search
                    </button>
                </div>
            `;
            courseCategories.parentNode.insertBefore(noResultsDiv, courseCategories);
        }
        courseCategories.style.display = 'none';
    } else {
        if (existingNoResults) {
            existingNoResults.remove();
        }
        courseCategories.style.display = 'block';
    }
}

function showSearchBlurMessage(totalMatches) {
    const existingMessage = document.querySelector('.search-blur-message');
    if (existingMessage) return;
    
    const courseCategories = document.querySelector('.course-categories');
    const blurMessage = document.createElement('div');
    blurMessage.className = 'search-blur-message blur-section';
    blurMessage.innerHTML = `
        <div class="blur-overlay">
            <div class="blur-overlay-content">
                <i class="fas fa-search"></i>
                <h3>More Results Available!</h3>
                <p>Found ${totalMatches} matching courses! You're viewing ${CARDS_BEFORE_BLUR} results. Login to see all ${totalMatches} matching courses.</p>
                <div class="blur-overlay-actions">
                    <a href="login.html" class="blur-overlay-btn blur-overlay-btn-primary">Login to See All</a>
                    <a href="registerstudent.html" class="blur-overlay-btn blur-overlay-btn-secondary">Sign Up Free</a>
                </div>
            </div>
        </div>
    `;
    
    courseCategories.appendChild(blurMessage);
}

// Save Course Functions (unchanged)
function toggleSaveCourse(courseId) {
    if (!isUserLoggedIn) {
        showLoginModal('save', courseId);
        return;
    }
    
    const saveBtn = document.querySelector(`[data-id="${courseId}"].save-btn`);
    const isSaved = saveBtn.classList.contains('saved');
    
    if (isSaved) {
        saveBtn.classList.remove('saved');
        saveBtn.innerHTML = '<i class="fas fa-bookmark"></i>';
    } else {
        saveBtn.classList.add('saved');
        saveBtn.innerHTML = '<i class="fas fa-bookmark"></i>';
    }
    
    const formData = new FormData();
    formData.append('action', isSaved ? 'unsave' : 'save');
    formData.append('course_id', courseId);
    formData.append('user_id', userData.id);
    formData.append('user_type', userData.role);
    
    fetch('save_course.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
        } else {
            if (isSaved) {
                saveBtn.classList.add('saved');
            } else {
                saveBtn.classList.remove('saved');
            }
            showNotification(data.message || 'Error occurred', 'error');
        }
    })
    .catch(error => {
        if (isSaved) {
            saveBtn.classList.add('saved');
        } else {
            saveBtn.classList.remove('saved');
        }
        showNotification('Network error occurred', 'error');
        console.error('Error:', error);
    });
}

function loadSavedCoursesStatus() {
    if (!isUserLoggedIn) return;
    
    fetch(`get_saved_courses.php?user_id=${userData.id}&user_type=${userData.role}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.saved_courses.forEach(courseId => {
                    const saveBtn = document.querySelector(`[data-id="${courseId}"].save-btn`);
                    if (saveBtn) {
                        saveBtn.classList.add('saved');
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading saved courses:', error);
        });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.textContent = message;
    
    let backgroundColor = 'var(--primary-dark)';
    if (type === 'success') backgroundColor = 'var(--success)';
    if (type === 'error') backgroundColor = 'var(--danger)';
    if (type === 'warning') backgroundColor = 'var(--warning)';
    
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background-color: ${backgroundColor};
        color: white;
        padding: 12px 24px;
        border-radius: 25px;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
        box-shadow: var(--shadow-lg);
        font-weight: 500;
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => notification.style.opacity = '1', 100);
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Auto-hide navbar (unchanged)
let lastScrollTop = 0;
const navbar = document.querySelector('.navbar');

function handleScroll() {
    const scrollTop = window.pageYOffset;
    
    if (scrollTop > lastScrollTop && scrollTop > 100) {
        navbar.style.transform = 'translateY(-100%)';
    } else if (scrollTop < lastScrollTop) {
        navbar.style.transform = 'translateY(0)';
    }
    
    lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        if (!inThrottle) {
            func.apply(this, arguments);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// FIXED: Single DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Initializing search functionality');
    
    // Initialize card data for search functionality
    initializeCardData();
    
    // Initialize search functionality
    const searchInput = document.getElementById('search-input');
    const searchBtn = document.querySelector('.search-btn');
    
    if (searchInput) {
        console.log('Search input found - attaching event listeners');
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                console.log('Enter key pressed - performing search');
                performSearch();
            }
        });
        
        searchInput.addEventListener('input', function() {
            if (this.value.trim() === '') {
                console.log('Search input cleared - resetting display');
                resetCourseDisplay();
            }
        });
    } else {
        console.error('Search input not found');
    }
    
    if (searchBtn) {
        console.log('Search button found - attaching click listener');
        searchBtn.addEventListener('click', function() {
            console.log('Search button clicked - performing search');
            performSearch();
        });
    } else {
        console.error('Search button not found');
    }
    
    // Initialize scroll handling
    window.addEventListener('scroll', throttle(handleScroll, 16));
    
    // Initialize modal close handlers
    document.addEventListener('click', e => {
        const loginModal = document.getElementById('loginModal');
        if (e.target === loginModal) closeLoginModal();
    });
    
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeLoginModal();
    });
    
    // Initialize user access
    if (isUserLoggedIn) {
        console.log('User logged in - full access granted');
        loadSavedCoursesStatus();
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    
    console.log('All event listeners initialized successfully');
});
        
    </script>
</body>
</html>