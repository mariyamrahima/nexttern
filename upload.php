<?php
$host = "localhost";
$user = "root";       // default in XAMPP
$pass = "";           // default empty in XAMPP
$db   = "nexttern_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$targetDir = "uploads/";
$fileName  = basename($_FILES["photo"]["name"]);
$targetFile = $targetDir . $fileName;
$imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

$check = getimagesize($_FILES["photo"]["tmp_name"]);
if ($check === false) {
    die("File is not an image.");
}

if ($_FILES["photo"]["size"] > 2000000) {
    die("Sorry, your file is too large.");
}

$allowedTypes = ["jpg", "jpeg", "png", "gif"];
if (!in_array($imageFileType, $allowedTypes)) {
    die("Sorry, only JPG, JPEG, PNG & GIF files are allowed.");
}

if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
    // Insert into database
    $stmt = $conn->prepare("INSERT INTO photos (filename) VALUES (?)");
    $stmt->bind_param("s", $fileName);
    $stmt->execute();
    $stmt->close();

    echo "The file ". htmlspecialchars($fileName) . " has been uploaded and saved to DB.";
    echo "<br><img src='$targetFile' width='200'>";
} else {
    echo "Sorry, there was an error uploading your file.";
}

$conn->close();
?>