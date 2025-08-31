<?php
// Start session to check login status
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['logged_in']); // Adjust based on your session variables

// Database connection parameters
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
// --- NEW --- Add a filter for duration
$duration_filter = isset($_GET['duration']) ? $conn->real_escape_string($_GET['duration']) : '';

$where_clauses = [];
$params = [];
$types = '';

if (!empty($course_filter)) {
    $where_clauses[] = "course_category = ?";
    $params[] = $course_filter;
    $types .= 's';
}

// --- NEW --- Add the duration filter to the SQL query
if (!empty($duration_filter)) {
    $where_clauses[] = "duration = ?";
    $params[] = $duration_filter;
    $types .= 's';
}

$sql = "SELECT * FROM courses";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

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
        // Here we assign the database's 'created_at' to 'posted_date' for use in the HTML.
        // The mock data lines have been removed as the database should now provide the company name.
        $row['posted_date'] = $row['created_at'] ?? 'N/A';
        
        // Mock a random mode since it's not in your database table
        $mock_modes = ['remote', 'onsite', 'hybrid'];
        $row['mode'] = $row['mode'] ?? $mock_modes[array_rand($mock_modes)];
        
        $courses_data[] = $row;
    }
}

// Close statement and connection
if (isset($stmt)) $stmt->close();
$conn->close();

// Filter for `mode` in PHP since it's not in the database schema
$filtered_internships = $courses_data;
if (!empty($mode_filter)) {
    $filtered_internships = array_filter($courses_data, function($course) use ($mode_filter) {
        return $course['mode'] === $mode_filter;
    });
}

// --- UPDATED --- Use a static list for `course_category` as requested
$courses_categories = [
    'programming',
    'design',
    'business',
    'marketing',
    'data_science',
    'ai_ml',
    'cybersecurity'
];

// --- NEW --- Define a static list for `duration` options
$available_durations = [
    '1_week' => '1 Week',
    '2_weeks' => '2 Weeks',
    '1_month' => '1 Month',
    '2_months' => '2 Months',
    '3_months' => '3 Months',
    '6_months' => '6 Months',
    'self_paced' => 'Self-Paced'
];

$available_modes = ['remote', 'onsite', 'hybrid']; // Use a static list for `mode` as it's not in the DB
sort($courses_categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexttern - Internship Opportunities</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="internship.css">
    <style>
   /* Modal Styles */
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
    animation: fadeIn 0.2s ease-out;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 0;
    max-width: 450px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.8);
    opacity: 0;
    transition: all 0.2s ease-out;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 1.5rem 0;
    border-bottom: 1px solid #f0f0f0;
    margin-bottom: 1.5rem;
}

.modal-header h3 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.4rem;
    font-weight: 600;
    color: var(--primary-dark);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #999;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.modal-close:hover {
    background: #f5f5f5;
    color: #333;
}

.modal-body {
    padding: 0 1.5rem 1.5rem;
    text-align: center;
}

.modal-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: white;
    font-size: 1.5rem;
}

.modal-body p {
    color: var(--text-secondary);
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.modal-btn {
    flex: 1;
    padding: 0.875rem 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    text-align: center;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.modal-btn-primary {
    background: var(--gradient-primary);
    color: white;
}

.modal-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(3, 89, 70, 0.3);
}

.modal-btn-secondary {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.modal-btn-secondary:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(3, 89, 70, 0.2);
}

.modal-footer-text {
    font-size: 0.9rem;
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

/* Content Blur Overlay - Enhanced for 4th row */
.content-blur-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(transparent 0%, rgba(255, 255, 255, 0.1) 20%, rgba(255, 255, 255, 0.9) 70%, rgba(255, 255, 255, 0.95) 100%);
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.blur-message {
    pointer-events: auto;
    text-align: center;
    padding: 2rem;
    max-width: 500px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.blur-content i {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
    opacity: 0.8;
    display: block;
}

.blur-content h3 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-dark);
    margin-bottom: 0.8rem;
}

.blur-content p {
    font-size: 1rem;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.blur-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.blur-btn {
    padding: 0.8rem 1.5rem;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    min-width: 120px;
    text-align: center;
}

.blur-btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 15px rgba(3, 89, 70, 0.3);
}

.blur-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(3, 89, 70, 0.4);
}

.blur-btn-secondary {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.blur-btn-secondary:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(3, 89, 70, 0.3);
}

.blurred-content {
    position: relative;
    /* Removed the blur filter to unblur the content */
    pointer-events: none;
    user-select: none;
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Responsive Blur Overlay */
@media (max-width: 768px) {
    .content-blur-overlay {
        height: 60vh;
    }

    .blur-message {
        padding: 2rem 1rem;
        margin-top: 5rem;
    }

    .blur-content h3 {
        font-size: 1.5rem;
    }

    .blur-content i {
        font-size: 3rem;
    }

    .blur-content p {
        font-size: 1rem;
    }

    .blur-actions {
        flex-direction: column;
        align-items: center;
    }

    .blur-btn {
        width: 100%;
        max-width: 280px;
    }
}

/* Responsive Modal */
@media (max-width: 768px) {
    .modal-content {
        margin: 1rem;
        width: calc(100% - 2rem);
    }

    .modal-actions {
        flex-direction: column;
    }

    .modal-btn {
        width: 100%;
    }

    .modal-header h3 {
        font-size: 1.2rem;
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
 <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="nav-brand">
                <img src="nextternnavbar.png" alt="Nexttern Logo" class="nav-logo">
            </a>
            
            <ul class="nav-menu">
                <li><a href="ind.html" class="nav-link">Home</a></li>
                <li><a href="internship1.php" class="nav-link">Internships</a></li>
                <li><a href="#" class="nav-link">Companies</a></li>
                <li><a href="aboutus.php" class="nav-link">About</a></li>
                <li><a href="contactus.php" class="nav-link">Contact</a></li>
            </ul>
            
            <div class="nav-cta">
                <?php if ($isLoggedIn): ?>
                    <a href="profile.php" class="btn btn-primary">Profile</a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <a href="login.html" class="btn btn-primary">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>Internship Opportunities</h1>
            <p>Discover your perfect learning path with top companies</p>
            <?php if ($isLoggedIn): ?>
                <div class="welcome-message">
                    <p>Welcome back! Explore all available opportunities below.</p>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Search Section -->
        <section class="search-section">
            <div class="search-container">
                <input type="text" placeholder="What do you want to learn?" id="search-input">
                <button type="button" class="search-btn">🔍</button>
            </div>
        </section>

        <!-- Filter Section (Horizontal) -->
        <section class="filter-section">
            <h2>Filter Your Search</h2>
            <form class="filter-form" method="GET" action="">
                <div class="filter-group">
                    <label for="course">Course</label>
                    <select id="course" name="course">
                        <option value="">All Courses</option>
                        <?php foreach ($courses_categories as $course): ?>
                            <option value="<?php echo htmlspecialchars($course); ?>" 
                                    <?php echo ($_GET['course'] ?? '') === $course ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $course))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="mode">Mode of Study</label>
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
                    <button type="submit" class="filter-btn">
                        <span class="btn-text">Filter</span>
                    </button>
                    <a href="?" class="clear-btn">Clear All</a>
                </div>
            </form>
        </section>

        <!-- Main Content -->
        <main class="main-content">
            <div class="results-info">
                <div class="results-count">
                    <?php echo count($filtered_internships); ?> internship<?php echo count($filtered_internships) !== 1 ? 's' : ''; ?> found
                </div>
            </div>

            <?php if (empty($filtered_internships)): ?>
                <div class="no-results">
                    <h3>No internships found</h3>
                    <p>Try adjusting your filters to see more opportunities</p>
                </div>
            <?php else: ?>
                <div class="internships-grid" style="position: relative;">
                    <?php 
                    $card_index = 0;
                    $cards_per_row = 3; // Assuming 3 cards per row based on typical grid layout
                    $cards_before_blur = $cards_per_row * 3; // First 3 complete rows (9 cards)
                    
                    foreach ($filtered_internships as $course): 
                        $card_index++;
                        
                        // Only show blur overlay if user is NOT logged in and we have more than 9 cards
                        if (!$isLoggedIn && $card_index == $cards_before_blur + 1 && count($filtered_internships) > $cards_before_blur): ?>
                            </div>
                            
                            <!-- Container for blurred cards -->
                            <div class="internships-grid blurred-content" style="position: relative;">
                                <!-- Login prompt overlay for 4th row onwards -->
                                <div class="content-blur-overlay">
                                    <div class="blur-message">
                                        <div class="blur-content">
                                            <i class="fas fa-lock"></i>
                                            <h3>Login to See All Courses</h3>
                                            <p>Join thousands of students and access our complete library of courses and internships</p>
                                            <div class="blur-actions">
                                                <a href="login.html" class="blur-btn blur-btn-primary">Login Now</a>
                                                <a href="registerstudent.html" class="blur-btn blur-btn-secondary">Sign Up Free</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php endif; ?>
                        
                        <div class="internship-card" id="card-<?php echo htmlspecialchars($course['id']); ?>" onclick="<?php echo ($isLoggedIn || $card_index <= $cards_before_blur) ? 'showCourseDetails(' . $course['id'] . ')' : 'showLoginModal(\'view\', ' . $course['id'] . ')'; ?>">
                            <div class="mode-badge mode-<?php echo htmlspecialchars($course['mode']); ?>">
                                <?php echo ucfirst(htmlspecialchars($course['mode'])); ?>
                            </div>
                            
                            <div class="card-top">
                                <div class="card-company-logo">💼</div>
                                <div class="card-company-info">
                                    <div class="card-company-name"><?php echo htmlspecialchars($course['company_name']); ?></div>
                                    <div class="card-posted-date">Posted on <?php echo date('M d, Y', strtotime($course['posted_date'])); ?></div>
                                </div>
                                <div class="card-actions">
                                    <span class="action-icon share-btn" data-id="<?php echo htmlspecialchars($course['id']); ?>"><i class="fas fa-share-alt"></i></span>
                                </div>
                            </div>

                            <div class="card-header">
                                <h3 class="card-title"><?php echo htmlspecialchars($course['course_title']); ?></h3>
                            </div>

                            <div class="card-meta">
                                📚 <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $course['course_category']))); ?>
                                <div class="meta-item">⏱️ <?php echo htmlspecialchars($course['duration']); ?></div>
                                <div class="meta-item">📊 <?php echo htmlspecialchars(ucfirst($course['difficulty_level'])); ?></div>
                            </div>

                            <p class="card-description">
                                <?php echo htmlspecialchars($course['course_description']); ?>
                            </p>

                            <div class="card-skills">
                                <?php 
                                    $skills = explode(',', $course['skills']);
                                    foreach ($skills as $skill): 
                                ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                <?php endforeach; ?>
                            </div>

                            <div class="card-footer">
                                <div class="stipend">
                                    <?php 
                                        if ($course['course_price_type'] === 'free'|| $course['price_amount'] == '0.00') {
                                            echo 'Free';
                                        } else {
                                            echo '₹' . htmlspecialchars($course['price_amount']);
                                        }
                                    ?>
                                </div>
                                <button class="apply-btn" onclick="<?php echo $isLoggedIn ? 'applyToCourse(' . $course['id'] . ')' : 'showLoginModal(\'apply\', ' . $course['id'] . ')'; ?>">
                                    Apply Now
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (!$isLoggedIn && count($filtered_internships) > $cards_before_blur): ?>
                        </div> <!-- Close blurred internships-grid -->
                    <?php endif; ?>
                </div> <!-- Close main internships-grid -->
            <?php endif; ?>
        </main>
    </div>

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

    <script>
        // Pass PHP variable to JavaScript
        const isUserLoggedIn = <?php echo json_encode($isLoggedIn); ?>;
        
        // Search functionality
        document.getElementById('search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        document.querySelector('.search-btn').addEventListener('click', function() {
            performSearch();
        });

        function performSearch() {
            const searchTerm = document.getElementById('search-input').value.trim();
            if (searchTerm) {
                console.log('Searching for:', searchTerm);
                filterCardsBySearch(searchTerm);
            }
        }

        function filterCardsBySearch(searchTerm) {
            const cards = document.querySelectorAll('.internship-card');
            const searchLower = searchTerm.toLowerCase();
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.querySelector('.card-title').textContent.toLowerCase();
                const description = card.querySelector('.card-description').textContent.toLowerCase();
                const skills = Array.from(card.querySelectorAll('.skill-tag')).map(skill => skill.textContent.toLowerCase()).join(' ');
                const company = card.querySelector('.card-company-name').textContent.toLowerCase();

                if (title.includes(searchLower) || 
                    description.includes(searchLower) || 
                    skills.includes(searchLower) ||
                    company.includes(searchLower)) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Update results count
            document.querySelector('.results-count').textContent = `${visibleCount} internship${visibleCount !== 1 ? 's' : ''} found`;

            // Show no results message if needed
            const noResults = document.querySelector('.no-results');
            const internshipsGrid = document.querySelector('.internships-grid');
            
            if (visibleCount === 0) {
                if (!noResults) {
                    const noResultsDiv = document.createElement('div');
                    noResultsDiv.className = 'no-results';
                    noResultsDiv.innerHTML = `
                        <h3>No internships found</h3>
                        <p>Try different keywords or clear your search</p>
                    `;
                    internshipsGrid.parentNode.insertBefore(noResultsDiv, internshipsGrid.nextSibling);
                }
                internshipsGrid.style.display = 'none';
            } else {
                if (noResults) {
                    noResults.remove();
                }
                internshipsGrid.style.display = 'grid';
            }
        }

        // Clear search when input is empty
        document.getElementById('search-input').addEventListener('input', function() {
            if (this.value.trim() === '') {
                // Show all cards
                document.querySelectorAll('.internship-card').forEach(card => {
                    card.style.display = 'flex';
                });
                
                // Reset count
                const totalCards = document.querySelectorAll('.internship-card').length;
                document.querySelector('.results-count').textContent = `${totalCards} internship${totalCards !== 1 ? 's' : ''} found`;
                
                // Remove no results message
                const noResults = document.querySelector('.no-results');
                if (noResults) {
                    noResults.remove();
                }
                
                document.querySelector('.internships-grid').style.display = 'grid';
            }
        });

        // Share functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.share-btn')) {
                const button = e.target.closest('.share-btn');
                const internshipId = button.getAttribute('data-id');
                const card = document.getElementById('card-' + internshipId);
                const title = card.querySelector('.card-title').textContent;
                const company = card.querySelector('.card-company-name').textContent;
                const shareText = `Check out this internship opportunity at ${company}: "${title}"!`;
                
                // Use the Clipboard API for a modern approach, with a fallback
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(shareText + ' ' + window.location.href)
                        .then(() => {
                            showNotification('Link copied to clipboard!');
                            console.log('Link copied to clipboard');
                        })
                        .catch(err => {
                            showNotification('Could not copy link. Try again.');
                            console.error('Failed to copy text: ', err);
                        });
                } else {
                    // Fallback for older browsers
                    const tempInput = document.createElement('textarea');
                    tempInput.value = shareText + ' ' + window.location.href;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    showNotification('Link copied to clipboard! (Fallback)');
                }
            }
        });
        
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background-color: var(--primary-dark);
                color: white;
                padding: 10px 20px;
                border-radius: 20px;
                z-index: 2000;
                opacity: 0;
                transition: opacity 0.5s ease-in-out;
            `;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.opacity = '1';
            }, 100);
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }

        // Filter form enhancement
        document.querySelector('.filter-btn').addEventListener('click', function(e) {
            const btnText = this.querySelector('.btn-text');
            btnText.innerHTML = 'Filtering... <span class="loading"></span>';
        });

        // Auto-submit form on select change (optional)
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function() {
                // Uncomment to enable auto-filter
                // this.closest('form').submit();
            });
        });

        // This ensures the initial filter is applied correctly
        document.addEventListener('DOMContentLoaded', () => {
            // Re-run the search on page load to handle query string filters
            const searchInput = document.getElementById('search-input');
            const urlParams = new URLSearchParams(window.location.search);
            const searchTermFromUrl = urlParams.get('search');
            if (searchTermFromUrl) {
                searchInput.value = searchTermFromUrl;
                filterCardsBySearch(searchTermFromUrl);
            }
        });

        // Auto-hide navbar functionality - FIXED VERSION
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');

        function handleScroll() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down - hide navbar
                navbar.style.transform = 'translateY(-100%)';
                navbar.style.transition = 'transform 0.3s ease-in-out';
            } else if (scrollTop < lastScrollTop) {
                // Scrolling up - show navbar
                navbar.style.transform = 'translateY(0)';
                navbar.style.transition = 'transform 0.3s ease-in-out';
            }
            
            // If at top of page, ensure navbar is visible
            if (scrollTop <= 10) {
                navbar.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        }

        // Login Modal Functions
        function showLoginModal(action, courseId) {
            const modal = document.getElementById('loginModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            
            if (action === 'apply') {
                modalTitle.textContent = 'Login to Apply';
                modalMessage.textContent = 'You need to login to apply for this internship. Join thousands of students already learning!';
            } else if (action === 'view') {
                modalTitle.textContent = 'Login to View More';
                modalMessage.textContent = 'Login to view all available courses and internships. Unlock your learning potential!';
            } else {
                modalTitle.textContent = 'Login Required';
                modalMessage.textContent = 'Please login to access this feature and continue your learning journey.';
            }
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Add animation
            setTimeout(() => {
                modal.querySelector('.modal-content').style.transform = 'scale(1)';
                modal.querySelector('.modal-content').style.opacity = '1';
            }, 10);
        }

        function closeLoginModal() {
            const modal = document.getElementById('loginModal');
            const modalContent = modal.querySelector('.modal-content');
            
            modalContent.style.transform = 'scale(0.8)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }, 200);
        }

        function showCourseDetails(courseId) {
            // This function shows course details for logged-in users or visible cards
            if (isUserLoggedIn) {
                // Redirect to course details page or show detailed modal
                console.log('Showing course details for course ID:', courseId);
                // You can redirect to a detailed course page
                // window.location.href = `course-details.php?id=${courseId}`;
                
                // Or show a detailed modal with course information
                showDetailedCourseModal(courseId);
            } else {
                // This shouldn't happen for visible cards, but just in case
                showLoginModal('view', courseId);
            }
        }

        function showDetailedCourseModal(courseId) {
            // Create a detailed course modal for logged-in users
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                           background: white; padding: 2rem; border-radius: 20px; 
                           box-shadow: 0 20px 60px rgba(0,0,0,0.3); z-index: 10000; 
                           max-width: 500px; width: 90%;">
                    <h3 style="margin: 0 0 1rem 0; color: var(--primary-dark);">Course Details</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Detailed course information would be displayed here for course ID: ${courseId}
                    </p>
                    <button onclick="this.closest('div').remove(); document.body.style.overflow = 'auto';" 
                            style="background: var(--primary); color: white; border: none; 
                                   padding: 0.8rem 1.5rem; border-radius: 10px; cursor: pointer;">
                        Close
                    </button>
                </div>
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                           background: rgba(0,0,0,0.6); z-index: 9999;" 
                     onclick="this.closest('div').remove(); document.body.style.overflow = 'auto';"></div>
            `;
            document.body.appendChild(notification);
            document.body.style.overflow = 'hidden';
        }

        function applyToCourse(courseId) {
            // Function for logged-in users to apply to courses
            if (isUserLoggedIn) {
                // Process the application
                const notification = document.createElement('div');
                notification.innerHTML = `
                    <div style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); 
                               background: var(--primary); color: white; padding: 12px 24px; 
                               border-radius: 25px; z-index: 2000; opacity: 0; 
                               transition: opacity 0.3s ease;">
                        Application submitted successfully! We'll contact you soon.
                    </div>
                `;
                document.body.appendChild(notification);
                setTimeout(() => notification.querySelector('div').style.opacity = '1', 100);
                setTimeout(() => {
                    notification.querySelector('div').style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
                
                // You can also redirect to an application form or process the application via AJAX
                // window.location.href = `apply.php?course_id=${courseId}`;
            } else {
                showLoginModal('apply', courseId);
            }
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('loginModal');
            if (e.target === modal) {
                closeLoginModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLoginModal();
            }
        });

        // Throttle scroll events for better performance
        function throttle(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            }
        }

        // Add scroll event listener with throttling
        window.addEventListener('scroll', throttle(handleScroll, 10));

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const navMenu = document.querySelector('.nav-menu');

        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', function() {
                navMenu.style.display = navMenu.style.display === 'flex' ? 'none' : 'flex';
            });
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Enhanced functionality for logged-in users
        if (isUserLoggedIn) {
            console.log('User is logged in - full access granted');
            
            // Remove any blur overlays that might still exist
            const blurOverlays = document.querySelectorAll('.content-blur-overlay');
            blurOverlays.forEach(overlay => overlay.remove());
            
            // Remove blurred-content class from grids
            const blurredGrids = document.querySelectorAll('.blurred-content');
            blurredGrids.forEach(grid => {
                grid.classList.remove('blurred-content');
                grid.style.pointerEvents = 'auto';
                grid.style.userSelect = 'auto';
            });
            
            // Enable all interactive elements
            const internshipCards = document.querySelectorAll('.internship-card');
            internshipCards.forEach(card => {
                card.style.pointerEvents = 'auto';
                card.style.userSelect = 'auto';
            });
        }
    </script>
</body>
</html>