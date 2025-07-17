<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$email = $_SESSION['email'];

$stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}

$row = $result->fetch_assoc();
$student_id = htmlspecialchars($row['student_id']);
$first_name = htmlspecialchars($row['first_name']);
$last_name = htmlspecialchars($row['last_name']);
$contact = htmlspecialchars($row['contact']);
$gender = htmlspecialchars($row['gender']);
$dob = htmlspecialchars(date('d M Y', strtotime($row['dob'])));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard | Nexttern</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5fbfa;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .dashboard {
      background: white;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
      max-width: 600px;
      width: 90%;
      border: 2px solid #035946;
    }

    h1 {
      color: #035946;
      margin-bottom: 20px;
    }

    .profile-info {
      font-size: 16px;
      color: #333;
      line-height: 1.8;
    }

    .profile-info span {
      font-weight: bold;
      color: #035946;
    }

    .logout-btn {
      background-color: #035946;
      color: white;
      border: none;
      padding: 10px 20px;
      margin-top: 30px;
      font-size: 15px;
      border-radius: 25px;
      cursor: pointer;
      transition: 0.3s;
    }

    .logout-btn:hover {
      background-color: #023f34;
    }
  </style>
</head>
<body>
  <div class="dashboard">
    <h1>🎓 Welcome, <?php echo $first_name; ?>!</h1>
    <div class="profile-info">
      <p><span>Student ID:</span> <?php echo $student_id; ?></p>
      <p><span>Name:</span> <?php echo $first_name . ' ' . $last_name; ?></p>
      <p><span>Email:</span> <?php echo $email; ?></p>
      <p><span>Phone:</span> <?php echo $contact; ?></p>
      <p><span>Gender:</span> <?php echo $gender; ?></p>
      <p><span>Date of Birth:</span> <?php echo $dob; ?></p>
    </div>

    <form action="logout.php" method="post">
      <button type="submit" class="logout-btn">Logout</button>
    </form>
  </div>
</body>
</html>