<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexttern - Internship Opportunities</title>
    <style>
        :root {
            --primary: #035946;
            --primary-light: #0a7058;
            --primary-dark: #023d32;
            --accent: #4ecdc4;
            --bg-light: #f5fbfa;
            --white: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --success: #10b981;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-light);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            background: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 0;
        }

        .nav-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3rem;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            flex-shrink: 0;
            min-width: 140px;
        }

        .logo img {
            height: 60px;
            width: auto;
            transition: all 0.3s ease;
        }

        .logo:hover img {
            transform: scale(1.05);
        }

        .nav-search {
            flex: 1;
            max-width: 600px;
            position: relative;
            margin: 0 2rem;
        }

        .nav-search input {
            width: 100%;
            padding: 1rem 3.5rem 1rem 1.5rem;
            border: 2px solid var(--border);
            border-radius: 50px;
            font-size: 1.05rem;
            outline: none;
            transition: all 0.3s ease;
            background: var(--bg-light);
        }

        .nav-search input:focus {
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(3, 89, 70, 0.1);
        }

        .nav-search input::placeholder {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .search-btn {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary);
            color: white;
            border: none;
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-50%) scale(1.05);
        }

        .nav-profile {
            flex-shrink: 0;
            min-width: 120px;
            display: flex;
            justify-content: flex-end;
        }

        .profile-btn {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 0.875rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .profile-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .profile-icon {
            font-size: 1.2rem;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Filter Section */
        .filter-section {
            background: var(--white);
            padding: 2rem 0;
            box-shadow: var(--shadow);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .filter-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .clear-btn {
            background: var(--text-secondary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }

        .clear-btn:hover {
            background: var(--text-primary);
            transform: translateY(-1px);
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }

        .results-count {
            font-size: 1.1rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* Internship Grid */
        .internships-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            padding: 0 1rem;
        }

        /* Internship Card */
        .internship-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
        }

        .internship-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }

        .card-header {
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .card-company {
            color: var(--primary);
            font-weight: 600;
            font-size: 1rem;
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: var(--bg-light);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .card-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .card-skills {
            margin-bottom: 1.5rem;
        }

        .skill-tag {
            display: inline-block;
            background: rgba(3, 89, 70, 0.1);
            color: var(--primary);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }

        .stipend {
            font-weight: 700;
            color: var(--success);
            font-size: 1.1rem;
        }

        .apply-btn {
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .apply-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(78, 205, 196, 0.3);
        }

        /* Mode Badge */
        .mode-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .mode-remote {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .mode-onsite {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .mode-hybrid {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .no-results h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1.5rem;
                padding: 1.5rem 1rem;
            }

            .nav-search {
                order: 2;
                max-width: 100%;
                margin: 0;
            }

            .nav-profile {
                order: 3;
                min-width: auto;
                justify-content: center;
            }

            .profile-text {
                display: none;
            }

            .logo {
                min-width: auto;
            }
            
            .logo img {
                height: 50px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .results-info {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .internships-grid {
                grid-template-columns: 1fr;
            }

            .internship-card {
                padding: 1.25rem;
            }

            .card-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .apply-btn {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 1024px) {
            .nav-container {
                gap: 2rem;
            }
            
            .nav-search {
                margin: 0 1rem;
            }
        }

        /* Loading State */
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php
    // Sample data structure - Replace with actual database queries
    $sample_internships = [
        [
            'id' => 1,
            'title' => 'Full Stack Web Development',
            'company' => 'TechCorp Solutions',
            'course' => 'Web Development',
            'mode' => 'remote',
            'duration' => '3 months',
            'stipend' => '₹15,000/month',
            'description' => 'Learn modern web development with React, Node.js, and MongoDB. Build real-world projects and gain hands-on experience.',
            'skills' => ['React', 'Node.js', 'MongoDB', 'JavaScript'],
            'posted_date' => '2024-08-15'
        ],
        [
            'id' => 2,
            'title' => 'Digital Marketing Specialist',
            'company' => 'Creative Agency',
            'course' => 'Digital Marketing',
            'mode' => 'hybrid',
            'duration' => '4 months',
            'stipend' => '₹12,000/month',
            'description' => 'Master digital marketing strategies including SEO, social media, and content marketing with real client projects.',
            'skills' => ['SEO', 'Social Media', 'Analytics', 'Content Marketing'],
            'posted_date' => '2024-08-14'
        ],
        [
            'id' => 3,
            'title' => 'Data Science & Analytics',
            'company' => 'DataTech Labs',
            'course' => 'Data Science',
            'mode' => 'onsite',
            'duration' => '6 months',
            'stipend' => '₹20,000/month',
            'description' => 'Dive into data science with Python, machine learning, and data visualization. Work on real business problems.',
            'skills' => ['Python', 'Machine Learning', 'SQL', 'Tableau'],
            'posted_date' => '2024-08-13'
        ],
        [
            'id' => 4,
            'title' => 'UI/UX Design Bootcamp',
            'company' => 'Design Studio Pro',
            'course' => 'Design',
            'mode' => 'remote',
            'duration' => '4 months',
            'stipend' => '₹18,000/month',
            'description' => 'Create beautiful user experiences with Figma, Adobe XD, and design thinking methodologies.',
            'skills' => ['Figma', 'Adobe XD', 'Prototyping', 'User Research'],
            'posted_date' => '2024-08-12'
        ],
        [
            'id' => 5,
            'title' => 'Mobile App Development',
            'company' => 'AppInnovate',
            'course' => 'Mobile Development',
            'mode' => 'hybrid',
            'duration' => '5 months',
            'stipend' => '₹22,000/month',
            'description' => 'Build native and cross-platform mobile apps using React Native and Flutter frameworks.',
            'skills' => ['React Native', 'Flutter', 'iOS', 'Android'],
            'posted_date' => '2024-08-11'
        ],
        [
            'id' => 6,
            'title' => 'Content Writing & Copywriting',
            'company' => 'ContentCraft Media',
            'course' => 'Content Writing',
            'mode' => 'remote',
            'duration' => '3 months',
            'stipend' => '₹10,000/month',
            'description' => 'Master the art of compelling content creation for blogs, websites, and marketing campaigns.',
            'skills' => ['Content Writing', 'SEO Writing', 'Copywriting', 'Research'],
            'posted_date' => '2024-08-10'
        ]
    ];

    // Get unique values for filters
    $courses = array_unique(array_column($sample_internships, 'course'));
    $companies = array_unique(array_column($sample_internships, 'company'));
    $modes = array_unique(array_column($sample_internships, 'mode'));
    sort($courses);
    sort($companies);
    sort($modes);

    // Filter logic
    $filtered_internships = $sample_internships;
    
    if ($_GET) {
        $course_filter = $_GET['course'] ?? '';
        $company_filter = $_GET['company'] ?? '';
        $mode_filter = $_GET['mode'] ?? '';
        
        $filtered_internships = array_filter($sample_internships, function($internship) use ($course_filter, $company_filter, $mode_filter) {
            $course_match = empty($course_filter) || $internship['course'] === $course_filter;
            $company_match = empty($company_filter) || $internship['company'] === $company_filter;
            $mode_match = empty($mode_filter) || $internship['mode'] === $mode_filter;
            
            return $course_match && $company_match && $mode_match;
        });
    }
    ?>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="#" class="logo">
                <img src="NEXTTERN.png" alt="Nexttern - Internship Hub">
            </a>
            
            <div class="nav-search">
                <input type="text" placeholder="What do you want to learn?" id="nav-search-input">
                <button type="button" class="search-btn">🔍</button>
            </div>
            
            <div class="nav-profile">
                <button class="profile-btn">
                    <span class="profile-icon">👤</span>
                    <span class="profile-text">Profile</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>Internship Opportunities</h1>
            <p>Discover your perfect learning path with top companies</p>
        </div>
    </header>

    <!-- Filter Section -->
    <section class="filter-section">
        <div class="container">
            <form class="filter-form" method="GET" action="">
                <div class="filter-group">
                    <label for="course">Course</label>
                    <select id="course" name="course">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo htmlspecialchars($course); ?>" 
                                    <?php echo ($_GET['course'] ?? '') === $course ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="company">Company</label>
                    <select id="company" name="company">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo htmlspecialchars($company); ?>" 
                                    <?php echo ($_GET['company'] ?? '') === $company ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="mode">Mode of Study</label>
                    <select id="mode" name="mode">
                        <option value="">All Modes</option>
                        <?php foreach ($modes as $mode): ?>
                            <option value="<?php echo htmlspecialchars($mode); ?>" 
                                    <?php echo ($_GET['mode'] ?? '') === $mode ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($mode)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" class="filter-btn">
                        <span class="btn-text">Filter</span>
                    </button>
                </div>

                <div class="filter-group">
                    <a href="?" class="clear-btn">Clear All</a>
                </div>
            </form>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
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
                <div class="internships-grid">
                    <?php foreach ($filtered_internships as $internship): ?>
                        <div class="internship-card">
                            <div class="mode-badge mode-<?php echo $internship['mode']; ?>">
                                <?php echo ucfirst($internship['mode']); ?>
                            </div>
                            
                            <div class="card-header">
                                <h3 class="card-title"><?php echo htmlspecialchars($internship['title']); ?></h3>
                                <div class="card-company"><?php echo htmlspecialchars($internship['company']); ?></div>
                            </div>

                            <div class="card-meta">
                                <div class="meta-item">📚 <?php echo htmlspecialchars($internship['course']); ?></div>
                                <div class="meta-item">⏱️ <?php echo htmlspecialchars($internship['duration']); ?></div>
                            </div>

                            <p class="card-description">
                                <?php echo htmlspecialchars($internship['description']); ?>
                            </p>

                            <div class="card-skills">
                                <?php foreach (array_slice($internship['skills'], 0, 4) as $skill): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($internship['skills']) > 4): ?>
                                    <span class="skill-tag">+<?php echo count($internship['skills']) - 4; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="card-footer">
                                <div class="stipend"><?php echo htmlspecialchars($internship['stipend']); ?></div>
                                <button class="apply-btn" onclick="applyNow(<?php echo $internship['id']; ?>)">
                                    Apply Now
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Navigation search functionality
        document.getElementById('nav-search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        document.querySelector('.search-btn').addEventListener('click', function() {
            performSearch();
        });

        function performSearch() {
            const searchTerm = document.getElementById('nav-search-input').value.trim();
            if (searchTerm) {
                console.log('Searching for:', searchTerm);
                // This will filter cards based on search term
                filterCardsBySearch(searchTerm);
            }
        }

        function filterCardsBySearch(searchTerm) {
            const cards = document.querySelectorAll('.internship-card');
            const searchLower = searchTerm.toLowerCase();
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.querySelector('.card-title').textContent.toLowerCase();
                const company = card.querySelector('.card-company').textContent.toLowerCase();
                const description = card.querySelector('.card-description').textContent.toLowerCase();
                const skills = Array.from(card.querySelectorAll('.skill-tag')).map(skill => skill.textContent.toLowerCase()).join(' ');

                if (title.includes(searchLower) || 
                    company.includes(searchLower) || 
                    description.includes(searchLower) || 
                    skills.includes(searchLower)) {
                    card.style.display = 'block';
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
        document.getElementById('nav-search-input').addEventListener('input', function() {
            if (this.value.trim() === '') {
                // Show all cards
                document.querySelectorAll('.internship-card').forEach(card => {
                    card.style.display = 'block';
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

        // Apply function - ready for integration
        function applyNow(internshipId) {
            // This function can be connected to your application system
            console.log('Applying for internship ID:', internshipId);
            
            // Example: redirect to application form
            // window.location.href = `apply.php?id=${internshipId}`;
            
            // Example: show modal
            alert(`Application process for internship ID ${internshipId} will be implemented here.`);
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

        // Add loading state to cards
        document.querySelectorAll('.apply-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const originalText = this.textContent;
                this.innerHTML = 'Applying... <span class="loading"></span>';
                this.disabled = true;
                
                // Reset after animation (remove this in production)
                setTimeout(() => {
                    this.textContent = originalText;
                    this.disabled = false;
                }, 2000);
            });
        });
    </script>
</body>
</html>