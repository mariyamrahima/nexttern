<?php $page = $_GET['page'] ?? 'home'; ?>
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
            --bg-light: #f5fbfa;
            --glass-bg: rgba(255, 255, 255, 0.2);
            --glass-border: rgba(255, 255, 255, 0.3);
            --shadow-light: 0 8px 32px rgba(3, 89, 70, 0.1);
            --shadow-medium: 0 12px 48px rgba(3, 89, 70, 0.15);
            --blur: 14px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 240px;
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

        /* Heading Font */
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

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(3, 89, 70, 0.9);
            backdrop-filter: blur(var(--blur));
            border-right: 1px solid var(--glass-border);
            color: white;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            box-shadow: var(--shadow-medium);
            position: relative;
            z-index: 10;
            transition: var(--transition);
            transform: translateX(0);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
            padding: 1.5rem 0.8rem;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
            border-radius: inherit;
            pointer-events: none;
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

        .nav-section {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
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
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
            margin: 2px 0;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
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
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.1) 100%);
            transition: var(--transition);
        }

        .nav-link:hover,
        .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(4px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.2);
            backdrop-filter: blur(var(--blur));
        }

        .sidebar.collapsed .nav-link:hover,
        .sidebar.collapsed .nav-link.active {
            transform: scale(1.08);
        }

        .nav-link:hover::before,
        .nav-link.active::before {
            left: 0;
        }

        .nav-link.active {
            background: var(--accent);
            color: var(--primary-dark);
            box-shadow: 0 8px 32px rgba(78, 205, 196, 0.3);
        }

        .nav-link:hover i,
        .nav-link.active i {
            transform: scale(1.15);
        }

        /* Main Content */
        .main {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: transparent;
            position: relative;
            transition: var(--transition);
            margin-left: 0;
            z-index: 1;
        }

        .sidebar-toggle {
            position: fixed;
            top: 1.5rem;
            left: calc(var(--sidebar-width) + 1rem);
            z-index: 1001;
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 0.7rem;
            cursor: pointer;
            color: var(--primary);
            font-size: 1.1rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar.collapsed + .main .sidebar-toggle {
            left: calc(var(--sidebar-collapsed) + 1rem);
        }

        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: scale(1.1);
            box-shadow: var(--shadow-medium);
        }

        .sidebar-toggle:active {
            transform: scale(0.95);
        }

        /* Page Content Styles - Dashboard Home */
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
            position: relative;
            overflow: hidden;
            margin-bottom: 2.5rem;
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
            color: var(--primary);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
            animation: slideInDown 0.6s ease-out 0.2s both;
        }

        .welcome-header p {
            font-size: 1rem;
            color: var(--secondary);
            opacity: 0.85;
            position: relative;
            z-index: 2;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
            animation: slideInDown 0.6s ease-out 0.4s both;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-top: 2.5rem;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            padding: 2rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

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
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-medium);
            background: rgba(255, 255, 255, 0.25);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.15) rotate(8deg);
        }

        .stat-icon.students { background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%); }
        .stat-icon.companies { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.courses { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.active { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); }

        .stat-number {
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.3rem;
            transition: var(--transition);
        }

        .stat-card:hover .stat-number {
            transform: scale(1.08);
        }

        .stat-label {
            color: var(--secondary);
            font-weight: 500;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .quick-actions {
            margin-top: 2.5rem;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--accent);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            padding: 1.8rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: var(--transition);
        }

        .action-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: var(--shadow-medium);
            text-decoration: none;
            color: inherit;
            background: rgba(255, 255, 255, 0.25);
        }

        .action-card:hover::before {
            left: 100%;
        }

        .action-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .action-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            background: var(--primary);
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(3, 89, 70, 0.25);
        }

        .action-card:hover .action-icon {
            transform: scale(1.15) rotate(8deg);
            background: var(--primary-light);
        }

        .action-title {
            font-weight: 600;
            color: var(--primary);
            font-size: 1.05rem;
        }

        .action-desc {
            color: var(--secondary);
            opacity: 0.85;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        /* Entry Animation for Login Box */
        @keyframes bounceIn {
            0% { transform: scale(0.9); opacity: 0; }
            60% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); }
        }

        .welcome-container {
            animation: bounceIn 0.8s ease-out;
        }

        /* Responsive Design */
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
            }
            
            .sidebar-toggle {
                left: 1rem;
                top: 1rem;
            }
            
            .welcome-header {
                padding: 2rem;
            }
            
            .welcome-header h1 {
                font-size: 1.8rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .blob1, .blob2, .blob3, .blob4, .blob5 {
                display: none;
            }
        }

        /* Loading animation */
        .loading {
            opacity: 0;
            animation: fadeIn 0.5s ease-out 0.1s forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }
    </style>
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