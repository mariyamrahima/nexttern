<?php $page = $_GET['page'] ?? 'home'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Nexttern</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
   <link href="styles.css" rel="stylesheet">
</head>
<body>
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>
    <div class="blob blob3"></div>
  <div class="blob blob4"></div>
 <!--   <div class="blob blob5"></div>-->
    
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2>Nexttern Admin</h2>
        </div>
        
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
        </div>
    </nav>

    <main class="main">
        <?php
        if ($page === 'students') {
            include 'admin_students.php';
        } elseif ($page === 'companies') {
            include 'admin_companies.php';
        } elseif ($page === 'courses') {
            include 'admin_courses.php';
        } elseif ($page === 'about') {
            // Include content for the new 'About Us' page
            include 'admin_aboutus.php';
        } elseif ($page === 'contact') {
            // Include content for the new 'Contact' page
            include 'admin_contact.php';
        } else {
            echo '
            <div class="welcome-container loading">
                <div class="welcome-header">
                    <h1>Welcome to Admin Dashboard</h1>
                    <p>Manage your platform efficiently with our comprehensive admin tools. Monitor students, companies, and courses all in one place.</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-number">1,247</div>
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
                                <div class="stat-number">84</div>
                                <div class="stat-label">Partner Companies</div>
                            </div>
                            <div class="stat-icon companies">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-number">156</div>
                                <div class="stat-label">Active Courses</div>
                            </div>
                            <div class="stat-icon courses">
                                <i class="fas fa-book"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-number">342</div>
                                <div class="stat-label">Active Internships</div>
                            </div>
                            <div class="stat-icon active">
                                <i class="fas fa-briefcase"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
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
            ';
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

        // Add loading animations
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.loading');
            elements.forEach(el => {
                el.classList.remove('loading');
            });
        });
    </script>
</body>
</html>
