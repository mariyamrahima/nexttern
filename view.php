<?php
$conn = new mysqli("localhost", "root", "", "nexttern_db");
$result = $conn->query("SELECT * FROM photos ORDER BY uploaded_at DESC");
while ($row = $result->fetch_assoc()) {
    echo "<div>";
    echo "<img src='uploads/" . $row['filename'] . "' width='150'>";
    echo "<p>Uploaded: " . $row['uploaded_at'] . "</p>";
    echo "</div><hr>";
}
$conn->close();
?>