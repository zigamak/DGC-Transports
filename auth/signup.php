<?php
// auth/signup.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    // If already logged in, redirect back to passenger_details.php or appropriate dashboard
    if (isset($_SESSION['selected_trip']) && isset($_SESSION['selected_seats'])) {
        header("Location: " . SITE_URL . "/bookings/passenger_details.php");
    } else {
        redirectUser();
    }
    exit();
}

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^\+?[0-9\s\-\(\)]{10,}$/', $phone)) {
        $error = "Invalid phone number (must be at least 10 digits).";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error = "Email is already registered.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $affiliate_id = generateAffiliateId($conn, $first_name, $conn->insert_id + 1);

            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, phone, role, affiliate_id, credits) VALUES (?, ?, ?, ?, ?, 'customer', ?, 0.00)");
            $stmt->bind_param("ssssss", $first_name, $last_name, $email, $password_hash, $phone, $affiliate_id);
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                $_SESSION['user'] = [
                    'id' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => 'customer',
                    'affiliate_id' => $affiliate_id,
                    'credits' => 0.00
                ];
                // Redirect back to passenger_details.php if booking in progress, else to dashboard
                if (isset($_SESSION['selected_trip']) && isset($_SESSION['selected_seats'])) {
                    header("Location: " . SITE_URL . "/bookings/passenger_details.php");
                } else {
                    redirectUser();
                }
                exit();
            } else {
                $error = "Error creating account: " . $conn->error;
            }
        }
        $stmt->close();
    }
}
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
                    <h1 class="text-4xl font-bold text-black mb-2">Create an Account</h1>
                    <p class="text-gray-600 text-lg">Join DGC Transports today!</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-6">
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
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="fas fa-user-plus mr-2"></i>Sign Up
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">Already have an account?
                        <a href="<?= SITE_URL ?>/auth/login.php" class="text-primary hover:underline font-semibold">Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

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