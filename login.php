<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/auth.php'; // Include the auth file

// If user is already logged in, redirect them based on their role
if (isLoggedIn()) {
    redirectUser();
}

// Handle form submission
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        redirectUser();
    } else {
        $error = "Invalid email or password.";
    }
}

require_once 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .login-card {
            max-width: 500px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full login-card">
            <div class="bg-white rounded-3xl shadow-2xl p-8 lg:p-12">
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-black mb-2">
                        Welcome Back
                    </h1>
                    <p class="text-gray-600 text-lg">Sign in to access your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-envelope text-primary mr-2"></i>Email Address
                        </label>
                        <input type="email" id="email" name="email" required
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                               placeholder="Enter your email">
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
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Login
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">Don't have an account?
                        <a href="signup.php" class="text-primary hover:underline font-semibold">Sign Up</a>
                    </p>
                </div>

                <div class="mt-4 text-center">
                    <p class="text-gray-600">Join our affiliate program! Earn credits by referring others.</p>
                    <p class="text-sm text-gray-500">Sign up to get your unique affiliate ID.</p>
                </div>
            </div>
        </div>
    </div>
       <?php require_once __DIR__ . '/templates/footer.php'; ?>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
?>