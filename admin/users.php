<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/send_email.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Define available roles
$valid_roles = ['admin', 'staff', 'customer'];

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $success = 'User deleted successfully.';
    } else {
        $error = 'Failed to delete user.';
    }
    $stmt->close();
}

// Handle add user request
$errors = [];
$add_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');

    // Validate inputs
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    } else {
        // Check if email is unique
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email already exists.';
        }
        $stmt->close();
    }
    if (!in_array($role, $valid_roles)) {
        $errors[] = 'Invalid role selected.';
    }

    // Insert user and send setup password email if no errors
    if (empty($errors)) {
        // Generate temporary password
        $temp_password = bin2hex(random_bytes(8)); // 16-character random password
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $hashed_password, $role);
        if ($stmt->execute()) {
            // Generate token for password setup
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $token, $expires_at);
            if ($stmt->execute()) {
                // Send setup password email
                $reset_data = [
                    'email' => $email,
                    'token' => $token,
                    'expires_at' => $expires_at
                ];
                if (sendPasswordResetEmail($reset_data)) {
                    $add_success = 'User added successfully. A link to set up their password has been sent to their email.';
                } else {
                    $errors[] = 'Failed to send password setup email.';
                }
            } else {
                $errors[] = 'Failed to generate password setup token.';
            }
        } else {
            $errors[] = 'Failed to add user.';
        }
        $stmt->close();
    }
}

// Fetch users with search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "
    SELECT id, email, role
    FROM users
    WHERE email LIKE ?
    ORDER BY email
";
$search_param = "%$search%";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $search_param);
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - DGC Transports</title>
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
            --accent-blue: #3b82f6;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            min-height: 100vh;
        }
        header {
            background: #000;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 40;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
        }
        .header-logo img {
            height: 40px;
        }
        .header-nav {
            display: flex;
            gap: 20px;
        }
        .header-nav a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .header-nav a:hover {
            color: #dc2626;
        }
        #mobile-menu {
            display: none;
            background: #000;
            padding: 20px;
        }
        #mobile-menu.active {
            display: block;
        }
        .mobile-nav a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 10px;
            font-weight: 600;
            transition: color 0.2s;
        }
        .mobile-nav a:hover {
            color: #dc2626;
        }
        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            display: none;
        }
        .toggle-btn:hover {
            color: #dc2626;
        }
        main {
            flex: 1;
            padding-top: 60px;
            background: linear-gradient(135deg, var(--white), var(--gray));
            transition: margin-left 0.3s ease-in-out;
        }
        @media (min-width: 768px) {
            main {
                margin-left: 256px;
            }
            .header-nav {
                display: flex;
            }
            .toggle-btn {
                display: none;
            }
            header.md-hidden {
                display: none;
            }
        }
        @media (max-width: 767px) {
            .toggle-btn {
                display: block;
            }
            .header-nav {
                display: none;
            }
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
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
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        .mobile-card-row {
            display: flex;
            flex-direction: column;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 0.75rem;
        }
        .mobile-card-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .mobile-card-item:last-child {
            margin-bottom: 0;
        }
        .mobile-card-label {
            font-weight: 600;
            color: #4b5563;
            width: 100px;
            min-width: 100px;
        }
        .mobile-card-content {
            flex-grow: 1;
        }
        @media (min-width: 768px) {
            .mobile-card-row {
                display: none;
            }
            .table-container {
                display: block;
            }
            .table-header {
                background: var(--gray);
                color: var(--black);
                font-weight: 600;
            }
            .table-row:hover {
                background: #f9fafb;
            }
        }
        @media (max-width: 767px) {
            .table-container {
                display: none;
            }
        }
        .text-red-600 { color: #e30613; }
        .bg-red-600 { background-color: #e30613; }
        .hover\:bg-red-600:hover { background-color: #e30613; }
        .hover\:text-red-600:hover { color: #e30613; }
        .bg-red-700 { background-color: #c70410; }
        .hover\:bg-red-700:hover { background-color: #c70410; }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
        }
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231a1a1a'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5rem;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php if (isLoggedIn()): ?>
        <?php include '../templates/sidebar.php'; ?>
    <?php endif; ?>

    <div class="flex-1">
        <header class="fixed top-0 left-0 w-full bg-black text-white p-4 shadow-md flex items-center justify-between z-40 md-hidden">
            <button id="sidebar-toggle-mobile" class="toggle-btn">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="flex-1 text-center">
                <a href="<?= SITE_URL ?>">
                    <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="Logo" class="h-8 mx-auto">
                </a>
            </div>
            <div class="w-10"></div>
        </header>
        <div id="mobile-menu" class="mobile-menu">
            <nav class="mobile-nav">
                <a href="<?= SITE_URL ?>/index.php">Book a Trip</a>
                <a href="<?= SITE_URL ?>/bookings/manage_booking.php">Manage Booking</a>
                <a href="<?= SITE_URL ?>/contact.php">Contact Us</a>
                <a href="<?= SITE_URL ?>/login.php">Login</a>
            </nav>
        </div>

        <main class="ml-0 md:ml-64 p-4 sm:p-6 lg:p-10">
            <div class="container mx-auto">
                <div class="card p-6 sm:p-8 lg:p-10">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8">
                        <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-4 sm:mb-0">
                            <i class="fas fa-users text-primary-red mr-3"></i>Manage Users
                        </h1>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <form method="GET" class="flex items-center">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by email" class="border border-gray-300 rounded-lg p-2 w-full sm:w-64">
                                <button type="submit" class="btn-primary ml-2"><i class="fas fa-search mr-2"></i>Search</button>
                            </form>
                            <button onclick="toggleAddUserForm()" class="btn-primary inline-flex items-center">
                                <i class="fas fa-plus mr-2"></i>Add New User
                            </button>
                        </div>
                    </div>
                    <?php if (!empty($errors)): ?>
                        <?php foreach ($errors as $error): ?>
                            <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($add_success): ?>
                        <p class="success-message"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($add_success) ?></p>
                    <?php endif; ?>
                    <?php if (isset($success)): ?>
                        <p class="success-message"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($success) ?></p>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                    <!-- Add User Form -->
                    <div id="add-user-form" class="hidden mb-8">
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="add_user" value="1">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" placeholder="Enter user email" required>
                            </div>
                            <div class="form-group">
                                <label for="role">Role</label>
                                <select id="role" name="role" required>
                                    <option value="">Select a role</option>
                                    <?php foreach ($valid_roles as $r): ?>
                                        <option value="<?= $r ?>" <?= isset($role) && $role == $r ? 'selected' : '' ?>>
                                            <?= ucfirst($r) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex justify-end space-x-4">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-save mr-2"></i>Add User
                                </button>
                                <button type="button" onclick="toggleAddUserForm()" class="btn-primary bg-gray-300 text-gray-800 hover:bg-gray-400 transform-none">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                    <!-- Users List -->
                    <?php if (empty($users)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-2xl font-bold text-gray-700 mb-2">No Users Found</h3>
                            <p class="text-gray-500 mb-6">Add a new user to get started.</p>
                            <button onclick="toggleAddUserForm()" class="btn-primary">
                                <i class="fas fa-plus mr-2"></i>Add User
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($users as $user): ?>
                                <div class="mobile-card-row bg-white shadow-md rounded-xl relative">
                                    <div class="absolute top-4 right-4">
                                        <div class="relative inline-block text-left">
                                            <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none menu-toggle" data-id="<?= $user['id'] ?>">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 hidden menu-content" data-id="<?= $user['id'] ?>">
                                                <div class="py-1">
                                                    <button onclick="showDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['email']) ?>')" class="text-gray-700 w-full text-left block px-4 py-2 text-sm hover:bg-gray-100">
                                                        <i class="fas fa-trash mr-2"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label"><i class="fas fa-envelope text-primary-red mr-2"></i>Email:</span>
                                        <span class="mobile-card-content"><?= htmlspecialchars($user['email']) ?></span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label"><i class="fas fa-user-tag text-primary-red mr-2"></i>Role:</span>
                                        <span class="mobile-card-content"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="overflow-x-auto hidden md:block">
                            <table class="w-full table-auto border-collapse">
                                <thead>
                                    <tr class="table-header rounded-lg">
                                        <th class="px-4 py-3 text-left">Email</th>
                                        <th class="px-4 py-3 text-left">Role</th>
                                        <th class="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="table-row border-b border-gray-200">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-envelope text-primary-red mr-2"></i>
                                                    <span><?= htmlspecialchars($user['email']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-user-tag text-primary-red mr-2"></i>
                                                    <span><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right relative">
                                                <div class="relative inline-block text-left">
                                                    <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none menu-toggle" data-id="<?= $user['id'] ?>">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 hidden menu-content" data-id="<?= $user['id'] ?>">
                                                        <div class="py-1">
                                                            <button onclick="showDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['email']) ?>')" class="text-gray-700 w-full text-left block px-4 py-2 text-sm hover:bg-gray-100">
                                                                <i class="fas fa-trash mr-2"></i>Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 hidden flex items-center justify-center modal-overlay">
        <div class="modal-content bg-white rounded-lg p-6 max-w-sm mx-auto shadow-xl">
            <h3 class="text-xl font-bold mb-4 text-center">Confirm Deletion</h3>
            <p id="deleteMessage" class="text-gray-700 mb-6 text-center"></p>
            <div class="flex justify-center space-x-4">
                <button id="cancelButton" class="btn-primary bg-gray-300 text-gray-800 hover:bg-gray-400 transform-none">Cancel</button>
                <a id="confirmDeleteLink" href="#" class="btn-primary bg-red-600 hover:bg-red-700">Delete</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Delete Modal
            const deleteModal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmDeleteLink = document.getElementById('confirmDeleteLink');
            const cancelButton = document.getElementById('cancelButton');
            window.showDeleteModal = (id, email) => {
                deleteMessage.textContent = `Are you sure you want to delete the user ${email}?`;
                confirmDeleteLink.href = `users.php?delete=${id}`;
                deleteModal.classList.remove('hidden');
            };
            cancelButton.addEventListener('click', () => {
                deleteModal.classList.add('hidden');
            });
            window.addEventListener('click', (event) => {
                if (event.target === deleteModal) {
                    deleteModal.classList.add('hidden');
                }
            });

            // Dropdown Menu
            const menuToggles = document.querySelectorAll('.menu-toggle');
            menuToggles.forEach(toggle => {
                toggle.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const id = toggle.dataset.id;
                    const menu = document.querySelector(`.menu-content[data-id="${id}"]`);
                    document.querySelectorAll('.menu-content').forEach(otherMenu => {
                        if (otherMenu !== menu) {
                            otherMenu.classList.add('hidden');
                        }
                    });
                    menu.classList.toggle('hidden');
                });
            });
            window.addEventListener('click', (event) => {
                document.querySelectorAll('.menu-content').forEach(menu => {
                    if (!menu.classList.contains('hidden')) {
                        menu.classList.add('hidden');
                    }
                });
            });

            // Add User Form Toggle
            window.toggleAddUserForm = () => {
                const form = document.getElementById('add-user-form');
                form.classList.toggle('hidden');
            };

            // Sidebar and Mobile Menu
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const mobileMenuCancel = document.getElementById('mobile-menu-cancel');
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileMenuIcon = document.getElementById('mobile-menu-icon');
            if (mobileMenuToggle && mobileMenuCancel && mobileMenu && mobileMenuIcon) {
                mobileMenuToggle.addEventListener('click', function() {
                    mobileMenu.classList.toggle('active');
                    mobileMenuToggle.style.display = mobileMenu.classList.contains('active') ? 'none' : 'block';
                    mobileMenuCancel.style.display = mobileMenu.classList.contains('active') ? 'block' : 'none';
                    mobileMenuIcon.classList.toggle('fa-bars');
                    mobileMenuIcon.classList.toggle('fa-times');
                });
                mobileMenuCancel.addEventListener('click', function() {
                    mobileMenu.classList.remove('active');
                    mobileMenuToggle.style.display = 'block';
                    mobileMenuCancel.style.display = 'none';
                    mobileMenuIcon.classList.remove('fa-times');
                    mobileMenuIcon.classList.add('fa-bars');
                });
            }
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const sidebarToggleMobile = document.getElementById('sidebar-toggle-mobile');
            const sidebarClose = document.getElementById('sidebar-close');
            if (sidebarToggleMobile && sidebarClose && sidebar && sidebarOverlay) {
                sidebarToggleMobile.addEventListener('click', () => {
                    sidebar.classList.remove('-translate-x-full');
                    sidebarOverlay.classList.remove('hidden');
                });
                const closeSidebar = () => {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                };
                sidebarClose.addEventListener('click', closeSidebar);
                sidebarOverlay.addEventListener('click', closeSidebar);
            }
        });
    </script>
</body>
</html>