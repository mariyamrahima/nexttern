<?php
session_start();
if (!isset($_SESSION['company_id'])) {
    header("Location: logincompany.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$company_id = $_SESSION['company_id'];

// Handle Internship Post Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['internship_title'];
    $category = "Internship"; // Fixed category
    $duration = $_POST['duration'];
    $difficulty = $_POST['difficulty_level'];
    $mode = $_POST['mode'];
    $description = $_POST['course_description'];
    $skills = $_POST['skills_taught'];
    $prerequisites = $_POST['prerequisites'];
    $start_date = $_POST['start_date'];
    $deadline = $_POST['enrollment_deadline'];
    $certificate = isset($_POST['certificate_provided']) ? 1 : 0;
    $placement_support = isset($_POST['job_placement_support']) ? 1 : 0;
    $stipend = $_POST['stipend']; // We can store it in price_amount

    $stmt = $conn->prepare("INSERT INTO course 
        (company_id, course_title, course_category, duration, difficulty_level, mode, course_description, skills_taught, prerequisites, enrollment_deadline, start_date, certificate_provided, job_placement_support, course_status, price_amount) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
    $stmt->bind_param("isssssssssssid", $company_id, $title, $category, $duration, $difficulty, $mode, $description, $skills, $prerequisites, $deadline, $start_date, $certificate, $placement_support, $stipend);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Internship posted successfully!');</script>";
}

// Fetch Posted Internships
$result = $conn->query("SELECT * FROM course WHERE company_id = $company_id AND course_category='Internship' ORDER BY created_at DESC");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Company Dashboard - Post Internships</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .container { width: 80%; margin: auto; padding: 20px; }
        .form-box, .list-box { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { color: #006666; }
        input, select, textarea { width: 100%; padding: 8px; margin: 8px 0; border: 1px solid #ccc; border-radius: 5px; }
        button { background: #006666; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #004d4d; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #006666; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome, Company Dashboard</h2>

        <div class="form-box">
            <h3>Post New Internship</h3>
            <form method="POST">
                <label>Internship Title</label>
                <input type="text" name="internship_title" required>

                <label>Duration</label>
                <input type="text" name="duration" placeholder="e.g. 3 Months" required>

                <label>Difficulty Level</label>
                <select name="difficulty_level" required>
                    <option value="Beginner">Beginner</option>
                    <option value="Intermediate">Intermediate</option>
                    <option value="Advanced">Advanced</option>
                </select>

                <label>Mode</label>
                <select name="mode" required>
                    <option value="Online">Online</option>
                    <option value="Offline">Offline</option>
                    <option value="Hybrid">Hybrid</option>
                </select>

                <label>Internship Description</label>
                <textarea name="course_description" required></textarea>

                <label>Skills Required</label>
                <textarea name="skills_taught" required></textarea>

                <label>Prerequisites</label>
                <textarea name="prerequisites"></textarea>

                <label>Start Date</label>
                <input type="date" name="start_date" required>

                <label>Application Deadline</label>
                <input type="date" name="enrollment_deadline" required>

                <label>Stipend (₹)</label>
                <input type="number" name="stipend" placeholder="e.g. 5000">

                <label><input type="checkbox" name="certificate_provided"> Certificate Provided</label><br>
                <label><input type="checkbox" name="job_placement_support"> Job Placement Support</label><br><br>

                <button type="submit">Post Internship</button>
            </form>
        </div>

        <div class="list-box">
            <h3>Your Posted Internships</h3>
            <table>
                <tr>
                    <th>Title</th>
                    <th>Duration</th>
                    <th>Mode</th>
                    <th>Difficulty</th>
                    <th>Skills</th>
                    <th>Stipend</th>
                    <th>Deadline</th>
                    <th>Start Date</th>
                    <th>Status</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['course_title']); ?></td>
                        <td><?= htmlspecialchars($row['duration']); ?></td>
                        <td><?= htmlspecialchars($row['mode']); ?></td>
                        <td><?= htmlspecialchars($row['difficulty_level']); ?></td>
                        <td><?= htmlspecialchars($row['skills_taught']); ?></td>
                        <td>₹<?= htmlspecialchars($row['price_amount']); ?></td>
                        <td><?= htmlspecialchars($row['enrollment_deadline']); ?></td>
                        <td><?= htmlspecialchars($row['start_date']); ?></td>
                        <td><?= htmlspecialchars($row['course_status']); ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
</body>
</html>