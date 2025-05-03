<?php
// Include database connection file
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Admin information
$username = 'admin';
$email = 'admin@example.com';
$password = '123456';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert admin user
$stmt = $pdo->prepare("
    INSERT INTO users (username, password, email, role, created_at, updated_at)
    VALUES (?, ?, ?, 'admin', NOW(), NOW())
");

if ($stmt->execute([$username, $hashedPassword, $email])) {
    echo "Admin account created successfully!<br>";
    echo "Username: " . $username . "<br>";
    echo "Email: " . $email . "<br>";
    echo "Password: " . $password . "<br>";
    echo "<p>Please remember this information and change your password after first login.</p>";
} else {
    echo "Error creating admin account.";
}

echo "<br><br><a href='index.php'>Return to Homepage</a>";
?> 