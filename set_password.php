<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Initialize variables
$errors = [];
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$email = '';

// Validate token
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $errors[] = 'Invalid or expired setup token.';
    } else {
        $row = $result->fetch_assoc();
        $email = $row['email'];
        if (strtotime($row['expires_at']) < time()) {
            $errors[] = 'This setup link has expired. Please contact the administrator.';
        }
    }
    $stmt->close();
} else {
    $errors[] = 'No setup token provided.';
}

// Handle password setup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validate passwords
    if (empty($new_password) || strlen($new_password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    } else {
        // Update password in users table
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        if ($stmt->execute()) {
            // Delete the used token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
            $success = 'Password set successfully. You can now <a href="' . SITE_URL . '/login.php" class="text-red-600 hover:underline">log in</a>.';
        } else {
            $errors[] = 'Failed to set password. Please try again.';
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
            --light-gray: #e5e7eb;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--white), var(--gray));
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
            max-width: 450px;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            width: 100%;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
        }
        .error-message {
            color: #ef4444;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .success-message {
            color: #10b981;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 0.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            background-color: var(--gray);
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.2);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <main class="p-4 sm:p-6 lg:p-10 flex flex-col items-center">
        <a href="<?= SITE_URL ?>" class="mb-8">
            <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="DGC Transports Logo" class="h-12 sm:h-16">
        </a>
        <div class="card p-6 sm:p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">
                <i class="fas fa-lock text-primary-red mr-3"></i>Set Your Password
            </h1>
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
                <p class="text-center text-gray-600 mt-4">Contact the administrator to request a new setup link.</p>
            <?php elseif ($success): ?>
                <p class="success-message text-center"><i class="fas fa-check-circle mr-1"></i><?= $success ?></p>
            <?php else: ?>
                <form method="POST" class="space-y-6">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save mr-2"></i>Set Password
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>