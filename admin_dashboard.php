<?php 
// Start session and check authentication
session_start();

// Check if admin is logged in (add your authentication logic here)
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: admin_login.php');
//     exit();
// }

$page = $_GET['page'] ?? 'home';

// Database connection function with error handling
function getDatabaseConnection() {
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "nexttern_db";
    
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }
    
    return $conn;
}

// Function to safely fetch count from database
function getCount($conn, $query, $default = 0) {
    if (!$conn) return $default;
    
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['count'];
    }
    
    return $default;
}

// Initialize statistics variables
$total_students = 0;
$active_companies = 0;
$active_courses = 0;

// Only fetch statistics for home page to avoid unnecessary queries
if ($page === 'home') {
    $conn = getDatabaseConnection();
    
    if ($conn) {
        try {
            // Fetch statistics with error handling
            $total_students = getCount($conn, "SELECT COUNT(*) as count FROM students");
            $active_companies = getCount($conn, "SELECT COUNT(*) as count FROM companies WHERE status = 'active'");
            $active_courses = getCount($conn, "SELECT COUNT(*) as count FROM course");
            
        } catch (Exception $e) {
            error_log("Error fetching statistics: " . $e->getMessage());
            // Keep default values (0) if there's an error
        } finally {
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
    --bg-light: #f5fbfa;
    --glass-bg: rgba(255, 255, 255, 0.2);
    --glass-border: rgba(255, 255, 255, 0.3);
    --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
    --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
    --blur: 14px;
    --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    --sidebar-width: 280px;
    --sidebar-collapsed: 70px;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Roboto', sans-serif;
    background: #f5fbfa;
    min-height: 100vh;
    display: flex;
    position: relative;
    overflow-x: hidden;
}

h1, h2, h3, h4, h5, h6 {
    font-family: 'Poppins', sans-serif;
}

/* Background Blobs */
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

@keyframes moveBlob {
    0% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(30px, -30px) scale(1.1); }
    100% { transform: translate(-30px, 30px) scale(0.9); }
}

/* Loading Spinner for Statistics */
.stat-loading {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
}

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(3, 89, 70, 0.2);
    border-top: 2px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Error Message */
.error-message {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger);
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    border-left: 4px solid var(--danger);
    font-weight: 500;
}

/* Sidebar - Fixed to extend full height */
.sidebar {
    width: var(--sidebar-width);
    background: var(--primary-dark);
    color: white;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    box-shadow: 4px 0 20px rgba(0,0,0,0.1);
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 10;
    transition: var(--transition);
    overflow-y: auto;
    overflow-x: hidden;
}

.sidebar.collapsed {
    width: var(--sidebar-collapsed);
    padding: 1.5rem 0.8rem;
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    transition: var(--transition);
    padding: 0.75rem;
    border-radius: 15px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    flex-shrink: 0;
}

.sidebar.collapsed .logo {
    justify-content: center;
    gap: 0;
}

.sidebar.collapsed .logo h2 {
    opacity: 0;
    width: 0;
    overflow: hidden;
    transition: var(--transition);
}

.logo i {
    font-size: 1.75rem;
    color: var(--accent);
    transition: var(--transition);
}

.sidebar.collapsed .logo i {
    font-size: 1.5rem;
}

.logo h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: white;
    transition: var(--transition);
}

/* Admin Profile Section */
.admin-profile {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 1rem;
    text-align: center;
    margin-bottom: 1rem;
    transition: var(--transition);
    flex-shrink: 0;
}

.sidebar.collapsed .admin-profile {
    padding: 0.5rem;
}

.admin-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--accent) 0%, #45b7b8 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-dark);
    transition: var(--transition);
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.sidebar.collapsed .admin-avatar {
    width: 40px;
    height: 40px;
    font-size: 1rem;
    margin-bottom: 0;
}

.admin-name {
    font-weight: 600;
    color: white;
    margin-bottom: 0.25rem;
    transition: var(--transition);
}

.admin-role {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    transition: var(--transition);
}

.sidebar.collapsed .admin-name,
.sidebar.collapsed .admin-role {
    opacity: 0;
    height: 0;
    margin: 0;
    overflow: hidden;
}

/* Navigation - Scrollable content area */
.nav-content {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.nav-section {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-bottom: 1rem;
}

.nav-section h4 {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 0.5rem;
    font-weight: 500;
    transition: var(--transition);
    padding-left: 1rem;
}

.sidebar.collapsed .nav-section h4 {
    opacity: 0;
    height: 0;
    margin: 0;
    overflow: hidden;
}

.nav-link {
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    font-weight: 500;
    padding: 0.8rem 1rem;
    border-radius: 12px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
    overflow: hidden;
    margin: 2px 0;
    background: transparent;
    border: 1px solid transparent;
    white-space: nowrap;
}

.sidebar.collapsed .nav-link {
    justify-content: center;
    padding: 0.8rem;
    margin: 4px 0;
}

.sidebar.collapsed .nav-link span {
    opacity: 0;
    width: 0;
    overflow: hidden;
    transition: var(--transition);
}

.nav-link i {
    font-size: 1rem;
    width: 18px;
    text-align: center;
    transition: var(--transition);
    flex-shrink: 0;
}

.nav-link:hover {
    background: var(--primary-light);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateX(5px);
    color: white;
}

.sidebar.collapsed .nav-link:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.nav-link.active {
    background: var(--accent);
    color: var(--primary-dark);
    box-shadow: 0 8px 32px rgba(78, 205, 196, 0.3);
    transform: translateX(5px);
}

.sidebar.collapsed .nav-link.active {
    transform: scale(1.08);
}

.nav-link:hover i,
.nav-link.active i {
    transform: scale(1.15);
}

/* Advanced Logout Button Styles */
.logout-link {
    margin-top: 30px;
    padding: 16px 20px !important;
    position: relative;
    background: linear-gradient(135deg, #ff416c 0%, #ff4757 25%, #ff3742 50%, #e84393 75%, #fd79a8 100%);
    background-size: 300% 300%;
    color: #fff !important;
    border-radius: 15px;
    border: none;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 
        0 8px 25px rgba(255, 65, 108, 0.3),
        0 4px 15px rgba(255, 71, 87, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    animation: gradientShift 4s ease infinite;
    backdrop-filter: blur(10px);
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.logout-link::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(45deg, #ff416c, #ff4757, #e84393, #fd79a8, #ff416c);
    background-size: 400% 400%;
    border-radius: 17px;
    z-index: -1;
    animation: glowingBorder 3s ease infinite;
    opacity: 0;
    transition: opacity 0.3s ease;
}

@keyframes glowingBorder {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.logout-link::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.1) 20%,
        rgba(255, 255, 255, 0.3) 50%,
        rgba(255, 255, 255, 0.1) 80%,
        transparent 100%
    );
    transition: left 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    z-index: 1;
}

.logout-link:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 
        0 20px 40px rgba(255, 65, 108, 0.4),
        0 10px 25px rgba(255, 71, 87, 0.3),
        0 5px 15px rgba(232, 67, 147, 0.2),
        inset 0 2px 0 rgba(255, 255, 255, 0.3);
    filter: brightness(1.1) saturate(1.2);
}

.logout-link:hover::before {
    opacity: 1;
}

.logout-link:hover::after {
    left: 100%;
}

.logout-link:active {
    transform: translateY(-4px) scale(0.98);
    transition: all 0.1s ease;
}

.logout-link i {
    color: #fff;
    margin-right: 15px;
    font-size: 18px;
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    position: relative;
    z-index: 2;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.logout-link:hover i {
    transform: translateX(-5px) rotate(-10deg);
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
}

.logout-link span {
    font-weight: 700;
    font-size: 15px;
    letter-spacing: 1px;
    text-transform: uppercase;
    position: relative;
    z-index: 2;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.logout-link:hover span {
    letter-spacing: 1.5px;
    text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.nav-section:last-child .logout-link {
    border-top: 2px solid rgba(255, 255, 255, 0.1);
    margin-top: 25px;
    padding-top: 25px !important;
    position: relative;
}

.sidebar.collapsed .logout-link {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    padding: 0 !important;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 25px auto 0 auto;
    background: linear-gradient(135deg, #ff416c, #ff4757, #e84393);
    position: relative;
}

.sidebar.collapsed .logout-link i {
    margin: 0;
    font-size: 20px;
    transform: none;
}

.sidebar.collapsed .logout-link:hover {
    transform: scale(1.15) rotate(5deg);
    box-shadow: 
        0 15px 30px rgba(255, 65, 108, 0.5),
        0 8px 20px rgba(255, 71, 87, 0.4);
}

.sidebar.collapsed .logout-link:hover i {
    transform: rotate(-15deg);
}

/* Sidebar Toggle - Fixed positioning */
.sidebar-toggle {
    position: fixed;
    top: 20px;
    left: var(--sidebar-width);
    z-index: 1001;
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 50%;
    padding: 0;
    cursor: pointer;
    color: var(--primary);
    font-size: 1.1rem;
    box-shadow: var(--shadow-light);
    transition: var(--transition);
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: translateX(10px);
    border-left: none;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.sidebar.collapsed ~ .main .sidebar-toggle {
    left: var(--sidebar-collapsed);
}

.sidebar-toggle:hover {
    background: rgba(255, 255, 255, 0.4);
    transform: translateX(10px) scale(1.1);
    box-shadow: var(--shadow-medium);
}

/* Main Content - Adjusted for sidebar */
.main {
    flex: 1;
    margin-left: var(--sidebar-width);
    padding: 2rem;
    overflow-y: auto;
    background: transparent;
    position: relative;
    transition: var(--transition);
    z-index: 1;
    min-height: 100vh;
}

.sidebar.collapsed ~ .main {
    margin-left: var(--sidebar-collapsed);
}

/* Welcome Container */
.welcome-container {
    max-width: 1200px;
    margin: 0 auto;
}

.welcome-header {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: var(--shadow-light);
    text-align: center;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.welcome-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent 30%, rgba(78, 205, 196, 0.08) 50%, transparent 70%);
    animation: shimmer 8s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.welcome-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 1rem;
    position: relative;
    z-index: 2;
}

.welcome-header p {
    font-size: 1.1rem;
    color: var(--secondary);
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
    position: relative;
    z-index: 2;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    padding: 1.8rem;
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
    background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: 18px 18px 0 0;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--secondary);
    opacity: 0.8;
    font-weight: 500;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    transition: var(--transition);
}

.stat-icon.students {
    background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
}

.stat-icon.companies {
    background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%);
}

.stat-icon.courses {
    background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
}

.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
}

/* Quick Actions */
.quick-actions {
    margin-top: 3rem;
}

.section-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title i {
    color: var(--accent);
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
}

.action-card {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.8rem;
    box-shadow: var(--shadow-light);
    transition: var(--transition);
    text-decoration: none;
    color: inherit;
    display: block;
    position: relative;
    overflow: hidden;
}

.action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(78, 205, 196, 0.05) 0%, rgba(3, 89, 70, 0.05) 100%);
    opacity: 0;
    transition: var(--transition);
    z-index: 1;
}

.action-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-medium);
    text-decoration: none;
    color: inherit;
}

.action-card:hover::before {
    opacity: 1;
}

.action-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 2;
}

.action-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: white;
    transition: var(--transition);
}

.action-card:nth-child(1) .action-icon {
    background: linear-gradient(135deg, var(--info) 0%, #2980b9 100%);
}

.action-card:nth-child(2) .action-icon {
    background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%);
}

.action-card:nth-child(3) .action-icon {
    background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
}

.action-card:hover .action-icon {
    transform: scale(1.15) rotate(-5deg);
}

.action-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary);
}

.action-desc {
    color: var(--secondary);
    opacity: 0.85;
    line-height: 1.5;
    position: relative;
    z-index: 2;
}

/* Page Content Containers */
.page-container {
    max-width: 1200px;
    margin: 0 auto;
}

.page-header {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 20px;
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
    border-radius: 20px 20px 0 0;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title i {
    color: var(--accent);
}

.page-subtitle {
    color: var(--secondary);
    opacity: 0.85;
    font-size: 1rem;
    line-height: 1.6;
}

/* Tables */
.table-container {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.table th {
    background: rgba(3, 89, 70, 0.8);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.table tbody tr {
    transition: var(--transition);
}

.table tbody tr:hover {
    background: rgba(78, 205, 196, 0.05);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* Forms */
.form-container {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--blur));
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-light);
    position: relative;
    overflow: hidden;
}

.form-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: 16px 16px 0 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-group label i {
    color: var(--accent);
    font-size: 0.8rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 1rem;
    border: 2px solid rgba(3, 89, 70, 0.1);
    border-radius: 12px;
    font-size: 1rem;
    transition: var(--transition);
    background: rgba(255, 255, 255, 0.7);
    color: var(--secondary);
    font-family: 'Roboto', sans-serif;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--accent);
    background: white;
    box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
    transform: translateY(-2px);
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

/* Buttons */
.btn {
    padding: 0.8rem 1.5rem;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 500;
    transition: var(--transition);
    min-width: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
    font-family: 'Roboto', sans-serif;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(3, 89, 70, 0.3);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

/* Button Groups */
.btn-group {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 1.5rem;
}

/* Loading States */
.loading {
    opacity: 0;
    animation: slideInUp 0.6s ease-out 0.1s forwards;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
    
    .actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        transform: translateX(-100%);
        position: fixed;
        height: 100vh;
        z-index: 1000;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main {
        margin-left: 0;
        padding: 1.5rem;
        width: 100%;
    }
    
    .sidebar-toggle {
        left: 15px;
        top: 15px;
        transform: translateX(0);
        border-radius: 50%;
    }
    
    .welcome-header {
        padding: 2rem 1.5rem;
    }
    
    .welcome-header h1 {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 1.6rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .table {
        min-width: 600px;
    }
    
    .btn-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .blob1, .blob2, .blob3, .blob4 {
        display: none;
    }
}

@media (max-width: 480px) {
    .main {
        padding: 1rem;
    }
    
    .welcome-header {
        padding: 1.5rem 1rem;
    }
    
    .welcome-header h1 {
        font-size: 1.8rem;
    }
    
    .page-header {
        padding: 1.5rem 1rem;
    }
    
    .page-title {
        font-size: 1.4rem;
    }
    
    .form-container {
        padding: 1.5rem 1rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .section-title {
        font-size: 1.5rem;
    }
}

/* Print Styles */
@media print {
    .sidebar,
    .sidebar-toggle,
    .blob,
    .blob1,
    .blob2,
    .blob3,
    .blob4 {
        display: none !important;
    }
    
    .main {
        margin: 0;
        padding: 1rem;
    }
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
    
    .blob,
    .blob1,
    .blob2,
    .blob3,
    .blob4 {
        animation: none;
    }
}

/* Focus indicators for accessibility */
*:focus {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
}

.nav-link:focus,
.btn:focus {
    outline: 2px solid rgba(78, 205, 196, 0.5);
    outline-offset: 2px;
}
</style>
</head>
<body>
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>
    <div class="blob blob3"></div>
    <div class="blob blob4"></div>
    
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2>Nexttern Admin</h2>
        </div>
        
        <div class="admin-profile">
            <div class="admin-avatar">A</div>
            <div class="admin-name">Admin User</div>
            <div class="admin-role">System Administrator</div>
        </div>
        
        <div class="nav-content">
            <div class="nav-section">
                <h4>Main</h4>
                <a href="?page=home" class="nav-link <?= ($page === 'home') ? 'active' : '' ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Management</h4>
                <a href="?page=students" class="nav-link <?= ($page === 'students') ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>
                <a href="?page=companies" class="nav-link <?= ($page === 'companies') ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
                </a>
                <a href="?page=courses" class="nav-link <?= ($page === 'courses') ? 'active' : '' ?>">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>General</h4>
                <a href="?page=about" class="nav-link <?= ($page === 'about') ? 'active' : '' ?>">
                    <i class="fas fa-info-circle"></i>
                    <span>About Us</span>
                </a>
                
                <a href="?page=contact" class="nav-link <?= ($page === 'contact') ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i>
                    <span>Contact</span>
                </a>
                
                <a href="admin_logout.php" class="nav-link logout-link" onclick="return confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="main">
        <?php
        if ($page === 'students') {
            if (file_exists('admin_students.php')) {
                include 'admin_students.php';
            } else {
                echo '<div class="error-message">Students management page not found.</div>';
            }
        } elseif ($page === 'companies') {
            if (file_exists('admin_company.php')) {
                include 'admin_company.php';
            } else {
                echo '<div class="error-message">Company management page not found.</div>';
            }
        } elseif ($page === 'courses') {
            if (file_exists('admin_courses.php')) {
                include 'admin_courses.php';
            } else {
                echo '<div class="error-message">Course management page not found.</div>';
            }
        } elseif ($page === 'about') {
            if (file_exists('admin_aboutus.php')) {
                include 'admin_aboutus.php';
            } else {
                echo '<div class="error-message">About us page not found.</div>';
            }
        } elseif ($page === 'contact') {
            if (file_exists('admin_contact.php')) {
                include 'admin_contact.php';
            } else {
                echo '<div class="error-message">Contact page not found.</div>';
            }
        } else {
            // Home page - Dashboard with statistics
            ?>
            <div class="welcome-container">
                <div class="welcome-header">
                    <h1>Welcome to Admin Dashboard</h1>
                    <p>Manage your platform efficiently with our comprehensive admin tools. Monitor students, companies, and courses all in one place.</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-number" id="students-count"><?= number_format($total_students) ?></div>
                                <div class="stat-label">Total Students</div>
                            </div>
                            <div class="stat-icon students">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-number" id="companies-count"><?= number_format($active_companies) ?></div>
                                <div class="stat-label">Active Companies</div>
                            </div>
                            <div class="stat-icon companies">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-number" id="courses-count"><?= number_format($active_courses) ?></div>
                                <div class="stat-label">Active Courses</div>
                            </div>
                            <div class="stat-icon courses">
                                <i class="fas fa-book"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($total_students == 0 && $active_companies == 0 && $active_courses == 0): ?>
                <div class="error-message">
                    <i class="fas fa-database"></i>
                    Unable to fetch statistics. Please check your database connection and ensure the required tables exist.
                </div>
                <?php endif; ?>
                
                <div class="quick-actions">
                    <h3 class="section-title">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </h3>
                    
                    <div class="actions-grid">
                        <a href="?page=students" class="action-card">
                            <div class="action-header">
                                <div class="action-icon">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="action-title">Manage Students</div>
                            </div>
                            <div class="action-desc">View, edit, block, or remove student accounts. Monitor student activities and engagement.</div>
                        </a>
                        
                        <a href="?page=companies" class="action-card">
                            <div class="action-header">
                                <div class="action-icon">
                                    <i class="fas fa-handshake"></i>
                                </div>
                                <div class="action-title">Company Partners</div>
                            </div>
                            <div class="action-desc">Manage company partnerships, review applications, and oversee collaboration agreements.</div>
                        </a>
                        
                        <a href="?page=courses" class="action-card">
                            <div class="action-header">
                                <div class="action-icon">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div class="action-title">Course Management</div>
                            </div>
                            <div class="action-desc">Create, modify, and organize courses. Track enrollment and monitor course performance.</div>
                        </a>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>
    </main>

    <script>
        let sidebarCollapsed = false;
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle i');
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile) {
                sidebar.classList.toggle('show');
                toggleBtn.className = sidebar.classList.contains('show') ? 'fas fa-times' : 'fas fa-bars';
            } else {
                sidebarCollapsed = !sidebarCollapsed;
                sidebar.classList.toggle('collapsed');
                
                if (sidebarCollapsed) {
                    toggleBtn.className = 'fas fa-arrow-right';
                } else {
                    toggleBtn.className = 'fas fa-bars';
                }
            }
        }

        // Confirm logout function
        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
        }

        // Close mobile sidebar when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.querySelector('.sidebar-toggle i').className = 'fas fa-bars';
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle i');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                if (sidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    toggleBtn.className = 'fas fa-arrow-right';
                } else {
                    sidebar.classList.remove('collapsed');
                    toggleBtn.className = 'fas fa-bars';
                }
            } else {
                sidebar.classList.remove('collapsed');
                toggleBtn.className = 'fas fa-bars';
            }
        });

        // Add loading animations and refresh stats
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.loading');
            elements.forEach(el => {
                el.classList.remove('loading');
            });

            // Auto-refresh statistics every 30 seconds
            if (window.location.search.includes('page=home') || !window.location.search) {
                refreshStats();
                setInterval(refreshStats, 30000);
            }
        });

        // Function to refresh statistics without page reload
        function refreshStats() {
            fetch('get_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('students-count').textContent = data.students.toLocaleString();
                        document.getElementById('companies-count').textContent = data.companies.toLocaleString();
                        document.getElementById('courses-count').textContent = data.courses.toLocaleString();
                    }
                })
                .catch(error => {
                    console.log('Stats refresh failed:', error);
                });
        }

        // Smooth scrolling for action cards
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                
                // Add a loading class
                this.style.opacity = '0.7';
                this.style.pointerEvents = 'none';
                
                // Navigate after a short delay for smooth transition
                setTimeout(() => {
                    window.location.href = href;
                }, 200);
            });
        });

        // Add keyboard navigation support
        document.addEventListener('keydown', function(e) {
            // Alt + S for Students
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                window.location.href = '?page=students';
            }
            // Alt + C for Companies
            if (e.altKey && e.key === 'c') {
                e.preventDefault();
                window.location.href = '?page=companies';
            }
            // Alt + R for Courses
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                window.location.href = '?page=courses';
            }
            // Alt + H for Home
            if (e.altKey && e.key === 'h') {
                e.preventDefault();
                window.location.href = '?page=home';
            }
        });
    </script>
</body>
</html>