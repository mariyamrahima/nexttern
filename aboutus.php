<?php
// Enable error reporting for troubleshooting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Error connecting to the database.");
}

// Fetch content from the `about_content` table
$about_data = [];
$result = $conn->query("SELECT section_key, content FROM about_content");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $about_data[$row['section_key']] = $row['content'];
    }
}
$conn->close();
