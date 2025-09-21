<?php
// create_credentials.php

// This file is for one-time use only. Remove it immediately after creating the accounts.
// Do not deploy this file to a live server.

require_once 'includes/db.php';

// Define the credentials and roles
$credentials = [
    [
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email'      => 'admin@dgctransports.com',
        'password'   => 'adminpassword', // Change this to a strong, temporary password
        'role'       => 'admin'
    ],
    [
        'first_name' => 'Staff',
        'last_name' => 'Member',
        'email'      => 'staff@dgctransports.com',
        'password'   => 'staffpassword', // Change this to a strong, temporary password
        'role'       => 'staff'
    ]
];

$results = [];

foreach ($credentials as $user) {
    // Hash the password
    $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
    
    // Check if the user already exists to prevent duplicates
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $user['email']);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $results[] = "User with email '{$user['email']}' already exists. Skipping.";
        $stmt->close();
        continue;
    }
    $stmt->close();

    // Insert the new user into the database
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $user['first_name'], $user['last_name'], $user['email'], $hashed_password, $user['role']);

    if ($stmt->execute()) {
        $results[] = "Successfully created {$user['role']} user: {$user['email']}";
    } else {
        $results[] = "Error creating {$user['role']} user: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credential Creation Result</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .container {
            max-width: 600px;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="container bg-white p-8 rounded-lg shadow-xl">
        <h1 class="text-2xl font-bold mb-4">Credential Creation Report</h1>
        <div class="space-y-4">
            <?php foreach ($results as $result): ?>
                <div class="p-3 rounded-lg <?= strpos($result, 'Successfully') !== false ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                    <?= htmlspecialchars($result) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-6 text-center text-gray-600">
            <p><strong>IMPORTANT:</strong> Delete this file from your server immediately after use.</p>
        </div>
    </div>
</body>
</html>