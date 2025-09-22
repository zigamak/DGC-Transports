<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login
requireLogin();

// Get current user
$user = $_SESSION['user'];

// Initialize variables
$error = '';
$success = '';
$first_name = $user['first_name'] ?? '';
$last_name = $user['last_name'] ?? '';
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';

// Function to normalize phone number
function normalizePhoneNumber($phone) {
    // Remove any non-digit characters except the leading +
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Handle different formats
    if (preg_match('/^0[7-9][0-1]\d{8}$/', $phone)) {
        // Convert 08012345678 to +2348012345678
        $phone = '+234' . substr($phone, 1);
    } elseif (preg_match('/^234[7-9][0-1]\d{8}$/', $phone)) {
        // Convert 2348012345678 to +2348012345678
        $phone = '+' . $phone;
    } elseif (!preg_match('/^\+234[7-9][0-1]\d{8}$/', $phone)) {
        // If it doesn't match expected formats, return original for error handling
        return $phone;
    }
    return $phone;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $normalized_phone = normalizePhoneNumber($phone);

    // Validate inputs
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^\+234[7-9][0-1]\d{8}$/', $normalized_phone)) {
        $error = 'Please enter a valid Nigerian phone number (e.g., +2348012345678, 08012345678, or 2348012345678).';
    } else {
        // Check if email is already used by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error = 'This email is already in use by another account.';
        } else {
            // Update user profile
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $normalized_phone, $user['id']);
            if ($stmt->execute()) {
                $success = 'Profile updated successfully.';
                // Update session data
                $_SESSION['user']['first_name'] = $first_name;
                $_SESSION['user']['last_name'] = $last_name;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['phone'] = $normalized_phone;
                $user = $_SESSION['user'];
            } else {
                $error = 'An error occurred while updating your profile. Please try again.';
            }
            $stmt->close();
        }
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect.';
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user['id']);
        if ($stmt->execute()) {
            $success = 'Password updated successfully.';
            $_SESSION['user']['password'] = $hashed_password;
            $user = $_SESSION['user'];
        } else {
            $error = 'An error occurred while updating your password. Please try again.';
        }
        $stmt->close();
    }
}

require_once 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #dc2626;
            --secondary-red: #991b1b;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
        }
        body {
            background: linear-gradient(to bottom right, var(--gray), #e5e7eb);
            font-family: 'Inter', sans-serif;
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: var(--primary-red);
            color: var(--white);
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: var(--secondary-red);
        }
        .input-field {
            border: 1px solid var(--black);
            border-radius: 6px;
            padding: 8px;
            width: 100%;
            color: var(--black);
        }
        .input-field:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 5px rgba(220, 38, 38, 0.3);
        }
        .error-message {
            color: #dc2626;
            font-size: 0.9rem;
        }
        .success-message {
            color: #10b981;
            font-size: 0.9rem;
        }
        @media (max-width: 640px) {
            .text-3xl { font-size: 1.5rem; }
            .text-2xl { font-size: 1.25rem; }
            .text-xl { font-size: 1.125rem; }
            .p-8 { padding: 1rem; }
            .p-6 { padding: 1rem; }
            .max-w-4xl { max-width: 100%; }
            .px-4 { padding-left: 1rem; padding-right: 1rem; }
            .gap-8 { gap: 1.5rem; }
            .py-3 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
            .px-6 { padding-left: 1rem; padding-right: 1rem; }
        }
    </style>
</head>
<body class="min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4">
        <div class="card p-4 sm:p-8 mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold mb-4 sm:mb-6">
                <i class="fas fa-user-edit text-primary-red mr-2"></i>Update Profile
            </h1>
            <p class="text-gray-600 mb-4 sm:mb-6 text-sm sm:text-base">Manage your personal information and password below.</p>

            <?php if ($error): ?>
                <p class="error-message mb-4"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success-message mb-4"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>

            <!-- Profile Update Form -->
            <div class="mb-8">
                <h2 class="text-xl sm:text-2xl font-bold mb-4 text-gray-900">
                    <i class="fas fa-user text-primary-red mr-2"></i>Personal Information
                </h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($first_name) ?>" class="input-field mt-1 text-sm sm:text-base" required>
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($last_name) ?>" class="input-field mt-1 text-sm sm:text-base" required>
                        </div>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($email) ?>" class="input-field mt-1 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($phone) ?>" class="input-field mt-1 text-sm sm:text-base" placeholder="e.g., +2348012345678 or 08012345678" required>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary flex items-center justify-center text-sm sm:text-base">
                            <i class="fas fa-save mr-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password Update Form -->
            <div>
                <h2 class="text-xl sm:text-2xl font-bold mb-4 text-gray-900">
                    <i class="fas fa-lock text-primary-red mr-2"></i>Change Password
                </h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_password">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                        <input type="password" name="current_password" id="current_password" class="input-field mt-1 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                        <input type="password" name="new_password" id="new_password" class="input-field mt-1 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="input-field mt-1 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary flex items-center justify-center text-sm sm:text-base">
                            <i class="fas fa-key mr-2"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-center mt-8">
            <a href="dashboard.php" class="bg-green-600 text-white font-bold py-2 sm:py-3 px-4 sm:px-6 rounded-xl hover:bg-green-700 transition-colors duration-200 flex items-center justify-center text-sm sm:text-base mx-auto">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
<?php require_once 'templates/footer.php'; ?>
</body>
</html>