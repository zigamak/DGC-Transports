<?php
// auth/signup.php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already logged in, redirect them
if (isLoggedIn()) {
    redirectUser();
}

// Handle form submission
$error = '';
$success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = isset($_POST['role']) ? $_POST['role'] : 'customer';

    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^\+?[0-9\s\-\(\)]{10,}$/', $phone)) {
        $error = "Invalid phone number (must be at least 10 digits).";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error = "Email is already registered.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into database
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, phone, role, credits) VALUES (?, ?, ?, ?, ?, ?, 0.00)");
            $stmt->bind_param("ssssss", $first_name, $last_name, $email, $hashed_password, $phone, $role);
            if ($stmt->execute()) {
                // Get the inserted user's ID
                $user_id = $stmt->insert_id;

                // Generate and save affiliate ID
                $affiliate_id = generateAffiliateId($conn, $first_name, $user_id);
                $update_stmt = $conn->prepare("UPDATE users SET affiliate_id = ? WHERE id = ?");
                $update_stmt->bind_param("si", $affiliate_id, $user_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Store user data in session
                $_SESSION['user'] = [
                    'id' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                    'affiliate_id' => $affiliate_id,
                    'credits' => 0.00
                ];

                $success = "Registration successful! Please log in.";
                // Redirect to login
                header("Location: " . SITE_URL . "/login.php?success=" . urlencode($success));
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Signup - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .signup-card {
            max-width: 500px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full signup-card">
            <div class="bg-white rounded-3xl shadow-2xl p-8 lg:p-12">
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-black mb-2">
                        Create an Account
                    </h1>
                    <p class="text-gray-600 text-lg">Join DGC Transports today</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($_GET['success']) ?></span>
                    </div>
                <?php endif; ?>

                <form action="signup.php" method="POST" class="space-y-6" id="signupForm">
                    <div>
                        <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-primary mr-2"></i>First Name
                        </label>
                        <input type="text" id="first_name" name="first_name" required
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="Enter your first name" value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-primary mr-2"></i>Last Name
                        </label>
                        <input type="text" id="last_name" name="last_name" required
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="Enter your last name" value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-envelope text-primary mr-2"></i>Email Address
                        </label>
                        <input type="email" id="email" name="email" required
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="Enter your email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-phone text-primary mr-2"></i>Phone Number
                        </label>
                        <input type="tel" id="phone" name="phone" required
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="+234 xxx xxx xxxx" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                    </div>
                    <div class="relative">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock text-primary mr-2"></i>Password
                        </label>
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="••••••••">
                        <span class="absolute inset-y-0 right-0 top-6 pr-4 flex items-center text-sm leading-5">
                            <i class="fas fa-eye text-gray-400 cursor-pointer" id="togglePassword"></i>
                        </span>
                    </div>
                    <div class="relative">
                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock text-primary mr-2"></i>Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="••••••••">
                        <span class="absolute inset-y-0 right-0 top-6 pr-4 flex items-center text-sm leading-5">
                            <i class="fas fa-eye text-gray-400 cursor-pointer" id="toggleConfirmPassword"></i>
                        </span>
                    </div>
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="fas fa-user-plus mr-2"></i>
                        Sign Up
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">Already have an account?
                        <a href="login.php" class="text-primary hover:underline font-semibold">Log In</a>
                    </p>
                </div>

                <div class="mt-4 text-center">
                    <p class="text-gray-600">Join our affiliate program! Earn credits by referring others.</p>
                    <p class="text-sm text-gray-500">Your unique affiliate ID will be generated upon signup.</p>
                </div>
            </div>
        </div>
    </div>
       <?php require_once __DIR__ . '/templates/footer.php'; ?>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Toggle confirm password visibility
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPasswordInput = document.getElementById('confirm_password');
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Client-side form validation
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value.trim();
            const confirmPassword = document.getElementById('confirm_password').value.trim();

            let isValid = true;
            let firstInvalidField = null;

            if (!firstName) {
                isValid = false;
                alert('Please enter your first name.');
                firstInvalidField = document.getElementById('first_name');
            } else if (!lastName) {
                isValid = false;
                alert('Please enter your last name.');
                firstInvalidField = document.getElementById('last_name');
            } else if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                isValid = false;
                alert('Please enter a valid email address.');
                firstInvalidField = document.getElementById('email');
            } else if (!phone || !/^\+?[0-9\s\-\(\)]{10,}$/.test(phone)) {
                isValid = false;
                alert('Please enter a valid phone number (at least 10 digits).');
                firstInvalidField = document.getElementById('phone');
            } else if (!password || password.length < 6) {
                isValid = false;
                alert('Password must be at least 6 characters long.');
                firstInvalidField = document.getElementById('password');
            } else if (password !== confirmPassword) {
                isValid = false;
                alert('Passwords do not match.');
                firstInvalidField = document.getElementById('confirm_password');
            }

            if (!isValid) {
                e.preventDefault();
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
                return false;
            }
        });
    </script>
</body>
</html>