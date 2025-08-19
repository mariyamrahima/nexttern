<?php
// filepath: c:\xampp\htdocs\Nexttern_Git\nexttern\registercompany.php
header('Content-Type: application/json');

// Simple validation (expand as needed)
$required = ['company_name','industry_type','company_email','year_established','contact_name','designation','contact_phone','password','confirm_password'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success'=>false, 'message'=>"All fields are required."]);
        exit;
    }
}
if ($_POST['password'] !== $_POST['confirm_password']) {
    echo json_encode(['success'=>false, 'message'=>"Passwords do not match."]);
    exit;
}
if (!filter_var($_POST['company_email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false, 'message'=>"Invalid email address."]);
    exit;
}
// TODO: Check if email already exists in DB

// TODO: Save to database (example, not secure!)
// $conn = new mysqli('localhost','root','','nexttern');
// $stmt = $conn->prepare("INSERT INTO companies (company_name, industry_type, company_email, year_established, contact_name, designation, contact_phone, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
// $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
// $stmt->bind_param("ssssssss", $_POST['company_name'], $_POST['industry_type'], $_POST['company_email'], $_POST['year_established'], $_POST['contact_name'], $_POST['designation'], $_POST['contact_phone'], $hashed);
// $stmt->execute();

echo json_encode(['success'=>true, 'message'=>"Company registered successfully!"]);
?>