<?php
// signup_admin.php (temporary file for creating default admin and staff)

require_once 'includes/db.php';
require_once 'includes/config.php';

$users = [
    [
        'first_name' => 'Super',
        'last_name'  => 'Admin',
        'email'      => 'admin@dgctransports.com',
        'password'   => 'TestAdmin', // change this later
        'role'       => 'admin'
    ],
    [
        'first_name' => 'Test',
        'last_name'  => 'Staff',
        'email'      => 'staff@dgctransports.com',
        'password'   => 'TestStaff', // change this later
        'role'       => 'staff'
    ]
];

foreach ($users as $u) {
    $hashedPassword = password_hash($u['password'], PASSWORD_DEFAULT);

    // Check if already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $u['email']);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "{$u['role']} with email {$u['email']} already exists.<br>";
    } else {
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $u['first_name'], $u['last_name'], $u['email'], $hashedPassword, $u['role']);

        if ($stmt->execute()) {
            echo ucfirst($u['role']) . " created successfully with email: {$u['email']} and password: {$u['password']}<br>";
        } else {
            echo "Error creating {$u['role']}: " . $stmt->error . "<br>";
        }
    }
    $stmt->close();
}

$conn->close();
