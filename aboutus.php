<?php
// Database connection and operations
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    // In a production environment, you would log this error and show a generic message.
    die("Connection failed: " . $conn->connect_error);
}

// Fetch current data for display
$about_data = [];
$result = $conn->query("SELECT section_key, content FROM about_content");
while ($row = $result->fetch_assoc()) {
    $about_data[$row['section_key']] = $row['content'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Nexttern</title>
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
        }
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow-x: hidden;
            padding: 2rem;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }
        .container {
            max-width: 900px;
            width: 100%;
            margin: auto;
            position: relative;
            z-index: 1;
        }
        .content-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow-medium);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out both;
        }
        .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 20px 20px 0 0;
        }
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            text-align: center;
            margin-bottom: 2rem;
        }
        .section-block {
            margin-bottom: 2.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .section-block:last-child {
            margin-bottom: 0;
        }
        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .section-title i {
            color: var(--accent);
            font-size: 1.5rem;
        }
        .section-text {
            color: var(--secondary);
            line-height: 1.8;
            font-size: 1.1rem;
            opacity: 0.9;
            text-align: center;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
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
    </style>
</head>
<body>
    <div class="blob blob1"></div>
    <div class="blob blob2"></div>
    <div class="blob blob3"></div>
    <div class="blob blob4"></div>
    <div class="container">
        <div class="content-card">
            <h1 class="page-title">About Nexttern</h1>
            
            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-bullseye"></i> Our Mission</h2>
                <p class="section-text"><?= htmlspecialchars($about_data['mission'] ?? 'Mission not set.') ?></p>
            </div>
            
            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-eye"></i> Our Vision</h2>
                <p class="section-text"><?= htmlspecialchars($about_data['vision'] ?? 'Vision not set.') ?></p>
            </div>
            
            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-heart"></i> Our Values</h2>
                <p class="section-text"><?= htmlspecialchars($about_data['values'] ?? 'Values not set.') ?></p>
            </div>
        </div>
    </div>
</body>
</html>
