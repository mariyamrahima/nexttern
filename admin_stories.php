<?php
// Add this at the top of admin_dashboard.php for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexttern_db";

// Initialize variables
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = '';
$error_message = '';
$active_tab = 'all-stories'; // Default tab

// Initialize stats array with default values FIRST
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];

// Get story statistics EARLY - before any HTML output
$stats_query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM stories";

$stats_result = $conn->query($stats_query);
if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
    $stats = $stats_row;
    // Ensure all values are integers
    foreach ($stats as $key => $value) {
        $stats[$key] = (int)$value;
    }
}

// Handle story status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_story'])) {
    $action = $_POST['action_story'];
    $story_id = (int)$_POST['story_id'];
    
    if ($action === 'approved' || $action === 'rejected') {
        $update_status = $conn->prepare("UPDATE stories SET status=?, updated_date=CURRENT_TIMESTAMP WHERE story_id=?");
        if ($update_status) {
            $update_status->bind_param("si", $action, $story_id);
            if ($update_status->execute()) {
                $status_text = $action === 'approved' ? 'approved' : 'rejected';
                $success_message = "Story has been {$status_text} successfully!";
                
                // Refresh stats after update
                $stats_result = $conn->query($stats_query);
                if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
                    $stats = $stats_row;
                    foreach ($stats as $key => $value) {
                        $stats[$key] = (int)$value;
                    }
                }
            } else {
                $error_message = 'Error updating story status.';
            }
            $update_status->close();
        }
    }
}

// Pagination setup with validation
$stories_per_page = 5; // Reduced for better content display
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $stories_per_page;

// Filter by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$where_clause = "";
$params = [];
$param_types = "";

if ($status_filter !== 'all') {
    $where_clause = "WHERE status = ?";
    $params[] = $status_filter;
    $param_types = "s";
}

// Build the query properly
$base_query = "SELECT * FROM stories {$where_clause} ORDER BY submission_date DESC";
$stories_query = $base_query . " LIMIT ? OFFSET ?";

// Add LIMIT and OFFSET parameters
$params[] = $stories_per_page;
$params[] = $offset;
$param_types .= "ii";

// Execute the query
$stories_stmt = $conn->prepare($stories_query);
if ($stories_stmt) {
    if (!empty($params)) {
        $stories_stmt->bind_param($param_types, ...$params);
    }
    $stories_stmt->execute();
    $stories_result = $stories_stmt->get_result();
} else {
    die("Error preparing stories query: " . $conn->error);
}

// Get total story count for pagination
$total_stories_query = "SELECT COUNT(*) as total FROM stories {$where_clause}";
$total_stmt = $conn->prepare($total_stories_query);
if ($total_stmt) {
    $count_params = array_slice($params, 0, -2);
    $count_param_types = $status_filter !== 'all' ? 's' : '';
    
    if (!empty($count_params)) {
        $total_stmt->bind_param($count_param_types, ...$count_params);
    }
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $total_stories = $total_result->fetch_assoc()['total'];
    $total_pages = ceil($total_stories / $stories_per_page);
} else {
    die("Error preparing count query: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stories Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
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
        margin: 0;
        padding: 20px;
        min-height: 100vh;
        color: var(--secondary);
        line-height: 1.6;
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
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .page-title i {
        font-size: 1.75rem;
        color: var(--accent);
    }

    .page-description {
        font-family: 'Roboto', sans-serif;
        color: var(--secondary);
        opacity: 0.8;
        font-size: 1.1rem;
    }

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

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--blur));
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--shadow-light);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-medium);
    }

    .stat-card.pending::before { background: var(--warning); }
    .stat-card.approved::before { background: var(--success); }
    .stat-card.rejected::before { background: var(--danger); }
    .stat-card.total::before { background: var(--info); }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: var(--secondary);
        font-weight: 500;
        opacity: 0.8;
    }

    .stories-container {
        background: var(--glass-bg);
        backdrop-filter: blur(var(--blur));
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--shadow-light);
        margin-bottom: 2rem;
    }

    .section-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid rgba(78, 205, 196, 0.2);
    }

    .filter-controls {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .filter-select {
        padding: 0.5rem 1rem;
        border: 1px solid var(--glass-border);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.7);
        color: var(--secondary);
        font-weight: 500;
    }

    .story-card {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        border-left: 4px solid var(--accent);
        transition: var(--transition);
        box-shadow: var(--shadow-light);
    }

    .story-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .story-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .story-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        color: var(--primary);
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .story-meta {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .story-meta small {
        color: var(--secondary);
        opacity: 0.7;
    }

    .status-badge {
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-pending { 
        background: rgba(243, 156, 18, 0.2); 
        color: var(--warning);
        border: 1px solid var(--warning);
    }
    .status-approved { 
        background: rgba(39, 174, 96, 0.2); 
        color: var(--success);
        border: 1px solid var(--success);
    }
    .status-rejected { 
        background: rgba(231, 76, 60, 0.2); 
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .rating-stars {
        color: #ffd700;
        font-size: 1rem;
    }

    .category-tag {
        background: var(--accent);
        color: white;
        padding: 0.3rem 0.8rem;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .story-content {
        background: rgba(255, 255, 255, 0.5);
        padding: 1.5rem;
        border-radius: 12px;
        line-height: 1.7;
        color: var(--secondary);
        margin-bottom: 1.5rem;
        border: 1px solid var(--glass-border);
    }

    .story-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .btn {
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        font-size: 0.95rem;
    }

    .btn-success {
        background: var(--success);
        color: white;
        box-shadow: 0 4px 15px rgba(39, 174, 96, 0.25);
    }

    .btn-success:hover {
        background: #219a52;
        transform: translateY(-2px);
    }

    .btn-danger {
        background: var(--danger);
        color: white;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.25);
    }

    .btn-danger:hover {
        background: #c0392b;
        transform: translateY(-2px);
    }

    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .pagination a, .pagination span {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        text-decoration: none;
        color: var(--primary);
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid var(--glass-border);
        transition: var(--transition);
    }

    .pagination a:hover {
        background: var(--accent);
        color: white;
    }

    .pagination .current {
        background: var(--primary);
        color: white;
    }

    .no-stories {
        text-align: center;
        padding: 3rem;
        color: var(--secondary);
        opacity: 0.7;
    }

    .no-stories i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: var(--accent);
    }

    @media (max-width: 768px) {
        .page-header { padding: 1.5rem; }
        .page-title { font-size: 1.5rem; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .story-header { flex-direction: column; align-items: stretch; }
        .story-actions { justify-content: center; }
        .filter-controls { flex-direction: column; align-items: stretch; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .stories-container { padding: 1.5rem; }
        .story-card { padding: 1.5rem; }
        .story-meta { flex-direction: column; align-items: flex-start; }
    }
    </style>
</head>
<body>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-star"></i>
        Success Stories Management
    </h1>
    <p class="page-description">Review and manage student success stories for website publication.</p>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?= $success_message ?>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i>
    <?= $error_message ?>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card total">
        <div class="stat-number"><?= $stats['total'] ?></div>
        <div class="stat-label">Total Stories</div>
    </div>
    <div class="stat-card pending">
        <div class="stat-number"><?= $stats['pending'] ?></div>
        <div class="stat-label">Pending Review</div>
    </div>
    <div class="stat-card approved">
        <div class="stat-number"><?= $stats['approved'] ?></div>
        <div class="stat-label">Approved</div>
    </div>
    <div class="stat-card rejected">
        <div class="stat-number"><?= $stats['rejected'] ?></div>
        <div class="stat-label">Rejected</div>
    </div>
</div>

<div class="stories-container">
    <h3 class="section-title">
        <i class="fas fa-book"></i>
        Student Success Stories
    </h3>

    <div class="filter-controls">
        <label for="status-filter"><strong>Filter by Status:</strong></label>
        <select id="status-filter" class="filter-select" onchange="filterStories()">
            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Stories</option>
            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
    </div>

    <?php if ($stories_result && $stories_result->num_rows > 0): ?>
        <?php while ($story = $stories_result->fetch_assoc()): ?>
            <div class="story-card">
                <div class="story-header">
                    <div>
                        <h4 class="story-title"><?= htmlspecialchars($story['story_title']) ?></h4>
                        <div class="story-meta">
                            <span class="category-tag"><?= htmlspecialchars($story['story_category']) ?></span>
                            <strong><?= htmlspecialchars($story['first_name'] . ' ' . $story['last_name']) ?></strong>
                            <?php if ($story['student_id']): ?>
                                <small>ID: <?= htmlspecialchars($story['student_id']) ?></small>
                            <?php endif; ?>
                            <?php if ($story['feedback_rating']): ?>
                                <span class="rating-stars">
                                    <?= str_repeat('★', $story['feedback_rating']) ?>
                                    <?= str_repeat('☆', 5 - $story['feedback_rating']) ?>
                                </span>
                            <?php endif; ?>
                            <small><?= date('M j, Y', strtotime($story['submission_date'])) ?></small>
                        </div>
                    </div>
                    <span class="status-badge status-<?= $story['status'] ?>">
                        <?= ucfirst($story['status']) ?>
                    </span>
                </div>

                <div class="story-content">
                    <?= nl2br(htmlspecialchars($story['story_content'])) ?>
                </div>

                <div class="story-actions">
                    <?php if ($story['status'] !== 'approved'): ?>
                        <form style="display: inline;" method="post">
                            <input type="hidden" name="action_story" value="approved">
                            <input type="hidden" name="story_id" value="<?= $story['story_id'] ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i>
                                Approve Story
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($story['status'] !== 'rejected'): ?>
                        <form style="display: inline;" method="post" onsubmit="return confirm('Are you sure you want to reject this story?')">
                            <input type="hidden" name="action_story" value="rejected">
                            <input type="hidden" name="story_id" value="<?= $story['story_id'] ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times"></i>
                                Reject Story
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php 
            $current_params = $_GET;
            for ($i = 1; $i <= $total_pages; $i++): 
                $current_params['page'] = $i;
                $url = '?' . http_build_query($current_params);
            ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= $url ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="no-stories">
            <i class="fas fa-book-open"></i>
            <p>No stories found.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    // Filter stories by status
    function filterStories() {
        const status = document.getElementById('status-filter').value;
        const urlParams = new URLSearchParams(window.location.search);
        
        if (status === 'all') {
            urlParams.delete('status');
        } else {
            urlParams.set('status', status);
        }
        
        urlParams.delete('page'); // Reset to first page when filtering
        
        window.location.search = urlParams.toString();
    }
</script>

</body>
</html>

<?php
// Close database connection
$conn->close();
?>