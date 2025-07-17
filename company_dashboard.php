<?php
session_start();

// Redirect to login if session not set
if (!isset($_SESSION['email'])) {
    header("Location: login.html");
    exit();
}

// Get session data
$loggedInEmail = $_SESSION['email'];

// Connect to DB
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch student info
$stmt = $conn->prepare("SELECT first_name, last_name FROM students WHERE email = ?");
$stmt->bind_param("s", $loggedInEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<h2>User not found</h2>";
    exit();
}

$row = $result->fetch_assoc();
$first_name = htmlspecialchars($row['first_name']);
$last_name = htmlspecialchars($row['last_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Student Dashboard | Nexttern</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f5fbfa;
      margin: 0;
      padding: 0;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .dashboard {
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(12px);
      padding: 40px 60px;
      border-radius: 20px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.3);
      max-width: 500px;
      width: 90%;
    }

    h1 {
      color: #035946;
      margin-bottom: 10px;
    }

    p {
      color: #333;
      font-size: 16px;
      margin: 8px 0;
    }

    .logout-btn {
      margin-top: 30px;
      padding: 10px 24px;
      background-color: #035946;
      color: white;
      border: none;
      border-radius: 20px;
      font-size: 14px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .logout-btn:hover {
      background-color: #035946;
    }
    .capital-name {
  text-transform: uppercase;
}
  </style>
</head>
<body>
  <div class="dashboard">
    <h1 class="capital-name">Welcome, <?php echo $first_name . " " . $last_name; ?> 👋</h1>
    <p>You are successfully logged in to your student dashboard.</p>

    <form action="logout.php" method="post">
      <button class="logout-btn" type="submit">Logout</button>
    </form>
  </div>
</body>
</html>