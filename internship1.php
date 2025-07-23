
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>All Internships | Nexttern</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #035946;
      --secondary: #2e3944;
      --bg-light: #f5fbfa;
    }
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: var(--bg-light);
      color: var(--secondary);
      overflow-x: hidden;
    }

    /* BLOBS */
    .blob {
      position: absolute;
      border-radius: 50%;
      z-index: 0;
      filter: blur(50px);
      opacity: 0.5;
      animation: moveBlob 20s infinite alternate ease-in-out;
    }
    .blob1 { width: 450px; height: 450px; background: rgba(3, 89, 70, 0.15); top: -100px; right: -150px; }
    .blob2 { width: 300px; height: 300px; background: rgba(3, 89, 70, 0.2); top: 150px; right: -80px; animation-delay: 2s; }
    .blob3 { width: 250px; height: 250px; background: rgba(3, 89, 70, 0.12); bottom: 50px; left: -100px; animation-delay: 4s; }
    @keyframes moveBlob {
      0% { transform: translate(0,0) scale(1); }
      50% { transform: translate(20px,-20px) scale(1.05); }
      100% { transform: translate(-20px,20px) scale(1); }
    }

    /* HEADER */
    .header {
      background: rgba(255,255,255,0.15);
      backdrop-filter: blur(12px);
      color: var(--primary);
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 10;
      border-bottom: 1px solid rgba(255,255,255,0.3);
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .header h1 {
      font-size: 24px;
      margin: 0;
      font-weight: bold;
      color: var(--primary);
    }
    .header .nav-links a {
      color: var(--secondary);
      margin-left: 20px;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.3s;
    }
    .header .nav-links a:hover {
      color: var(--primary);
    }

    .container {
      padding: 40px 20px;
      max-width: 1200px;
      margin: auto;
      position: relative;
      z-index: 1;
    }

    .section-title {
      font-size: 28px;
      color: var(--primary);
      text-align: center;
      margin-bottom: 30px;
      font-weight: bold;
    }

    /* GRID */
    .internship-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 25px;
    }

    /* CARD */
    .card {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(12px);
      border-radius: 20px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.15);
      padding: 20px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: 1px solid rgba(255,255,255,0.3);
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }

    .card h3 {
      font-size: 20px;
      color: var(--secondary);
      margin-bottom: 8px;
    }
    .card .company {
      font-weight: bold;
      color: var(--primary);
      margin-bottom: 5px;
    }
    .card .location, .card .stipend, .card .duration {
      font-size: 14px;
      color: var(--secondary);
      margin-bottom: 6px;
    }
    .card p.description {
      font-size: 14px;
      color: #333;
      margin: 10px 0;
      height: 60px;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* BUTTONS */
    .card .actions {
      display: flex;
      justify-content: space-between;
      margin-top: 15px;
    }
    .btn {
      padding: 8px 14px;
      font-size: 14px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: transform 0.2s, background 0.3s;
    }
    .btn:hover { transform: scale(1.05); }
    .btn-save {
      background-color: var(--primary);
      color: #fff;
    }
    .btn-save:hover { background-color: #024437; }
    .btn-details {
      background-color: var(--secondary);
      color: #fff;
    }
    .btn-details:hover { background-color: #1e2933; }

    @media (max-width: 768px) {
      .section-title { font-size: 24px; }
      .header h1 { font-size: 20px; }
    }
  </style>
</head>
<body>
  <!-- BLOBS -->
  <div class="blob blob1"></div>
  <div class="blob blob2"></div>
  <div class="blob blob3"></div>

  <!-- HEADER -->
  <div class="header">
    <h1>Nexttern Internships</h1>
    <div class="nav-links">
      <a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
      <a href="saved_internships.php"><i class="fas fa-bookmark"></i> Saved</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="container">
    <div class="section-title">Available Internships</div>
    <div class="internship-grid">
      <?php
      if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          echo '<div class="card">
                  <h3>'.htmlspecialchars($row['title']).'</h3>
                  <div class="company">'.htmlspecialchars($row['company']).'</div>
                  <div class="location"><i class="fas fa-map-marker-alt"></i> '.htmlspecialchars($row['location']).'</div>
                  <div class="stipend"><i class="fas fa-coins"></i> Stipend: '.htmlspecialchars($row['stipend']).'</div>
                  <div class="duration"><i class="fas fa-clock"></i> Duration: '.htmlspecialchars($row['duration']).'</div>
                  <p class="description">'.htmlspecialchars(substr($row['description'], 0, 100)).'...</p>
                  <div class="actions">
                    <form action="save_internship.php" method="post" style="margin:0;">
                      <input type="hidden" name="internship_id" value="'.$row['id'].'">
                      <button type="submit" class="btn btn-save"><i class="fas fa-bookmark"></i> Save</button>
                    </form>
                    <a href="internship_details.php?id='.$row['id'].'" class="btn btn-details"><i class="fas fa-info-circle"></i> Details</a>
                  </div>
                </div>';
        }
      } else {
        echo "<p>No internships available at the moment.</p>";
      }
      ?>
    </div>
  </div>
</body>
</html>