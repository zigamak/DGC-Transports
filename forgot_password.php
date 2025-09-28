<?php
// forgot_password.php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Start session
session_start();

// Include required files
try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/send_email.php';
} catch (Exception $e) {
    error_log("Failed to include required files: " . $e->getMessage());
    die("Error: Server configuration issue. Please check server logs.");
}

// Redirect if already logged in
if (isLoggedIn()) {
    redirectUser();
}

// Handle form submission
$message = '';
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if email exists in users table
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                // Generate a secure reset token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store token in password_resets table
                $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $email, $token, $expires_at);
                $success = $stmt->execute();
                $stmt->close();

                if ($success) {
                    // Send password reset email
                    $reset_data = [
                        'email' => $email,
                        'token' => $token,
                        'expires_at' => $expires_at
                    ];
                    if (sendPasswordEmail($reset_data, 'forgot_password')) {
                        $message = "A password reset link has been sent to your email. Please check your inbox (and spam/junk folder).";
                    } else {
                        $error = "Failed to send password reset email. Please try again later.";
                    }
                } else {
                    $error = "An error occurred. Please try again.";
                }
            } else {
                $error = "No account found with that email address.";
            }
        } catch (Exception $e) {
            error_log("Error processing password reset: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }
}

// Create or modify password_resets table
try {
    $result = $conn->query("SHOW TABLES LIKE 'password_resets'");
    if ($result->num_rows === 0) {
        $create_table = "
            CREATE TABLE password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $conn->query($create_table);
        error_log("Created password_resets table");
    } else {
        // Check for and remove any unique constraint on email
        $indexes = $conn->query("SHOW INDEXES FROM password_resets WHERE Key_name = 'email' OR (Key_name = 'PRIMARY' AND Column_name = 'email')");
        if ($indexes->num_rows > 0) {
            $conn->query("ALTER TABLE password_resets DROP INDEX email");
            if ($conn->query("SHOW INDEXES FROM password_resets WHERE Key_name = 'PRIMARY' AND Column_name = 'email'")->num_rows > 0) {
                $conn->query("ALTER TABLE password_resets DROP PRIMARY KEY, ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST, ADD INDEX idx_email (email), ADD INDEX idx_token (token)");
            }
            error_log("Removed unique constraint on email and set id as primary key in password_resets");
        }
    }
} catch (Exception $e) {
    error_log("Failed to create or modify password_resets table: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= defined('SITE_NAME') ? SITE_NAME : 'DGC Transports' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #e30613;
            --secondary: #c70410;
        }
        body {
            background: linear-gradient(135deg, #ffffff, #f5f5f5);
            font-family: 'Inter', sans-serif;
        }
        .forgot-password-card {
            max-width: 500px;
        }
        .spinner {
            display: none;
        }
        .spinner-active {
            display: inline-block;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php
    try {
        require_once __DIR__ . '/templates/header.php';
    } catch (Exception $e) {
        error_log("Failed to include header.php: " . $e->getMessage());
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert"><span class="block sm:inline">Failed to load header. Please contact support.</span></div>';
    }
    ?>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full forgot-password-card">
            <div class="bg-white rounded-3xl shadow-2xl p-8 lg:p-12">
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-black mb-2">
                        Forgot Password
                    </h1>
                    <p class="text-gray-600 text-lg">Enter your email to receive a password reset link</p>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form id="forgotPasswordForm" action="forgot_password.php" method="POST" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-envelope text-primary mr-2"></i>Email Address
                        </label>
                        <input type="email" id="email" name="email" required
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="Enter your email">
                    </div>
                    <button type="submit" id="submitButton"
                            class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-spinner fa-spin spinner mr-2"></i>
                        <span class="button-text">Send Reset Link</span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Remember your password? <a href="login.php" class="text-primary hover:underline font-semibold">Sign In</a>
                    </p>
                    <p class="text-gray-600 mt-2">
                        Don't have an account? <a href="signup.php" class="text-primary hover:underline font-semibold">Sign Up</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php
    try {
        require_once __DIR__ . '/templates/footer.php';
    } catch (Exception $e) {
        error_log("Failed to include footer.php: " . $e->getMessage());
        echo '<footer class="py-4 text-center text-gray-500 text-sm">© ' . date('Y') . ' DGC Transports. All rights reserved.</footer>';
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            const submitButton = document.getElementById('submitButton');
            const spinner = submitButton.querySelector('.spinner');
            const buttonText = submitButton.querySelector('.button-text');

            if (form) {
                form.addEventListener('submit', function(e) {
                    const email = document.getElementById('email').value.trim();

                    // Clear previous error messages
                    document.querySelectorAll('.bg-red-100').forEach(el => el.remove());

                    // Client-side validation
                    if (!email) {
                        e.preventDefault();
                        showError('Please enter your email address.');
                        return false;
                    }

                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(email)) {
                        e.preventDefault();
                        showError('Please enter a valid email address.');
                        return false;
                    }

                    // Show spinner and disable button
                    submitButton.disabled = true;
                    spinner.classList.add('spinner-active');
                    buttonText.textContent = 'Sending...';
                });

                function showError(message) {
                    const formContainer = form.parentNode;
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6';
                    errorDiv.innerHTML = `<span class="block sm:inline">${message}</span>`;
                    form.prepend(errorDiv);
                    setTimeout(() => errorDiv.remove(), 5000);
                    submitButton.disabled = false;
                    spinner.classList.remove('spinner-active');
                    buttonText.textContent = 'Send Reset Link';
                }
            }
        });
    </script>
    <?php
    $conn->close();
    ?>
</body>
</html>