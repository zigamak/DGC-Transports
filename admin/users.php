<?php
// admin/users.php

// --- PHP Logic ---
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
    header("Location: users.php" . (!empty($success) ? '?msg=' . urlencode($success) : '') . (!empty($error) ? '?err=' . urlencode($error) : ''));
    exit;
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
        $temp_password = bin2hex(random_bytes(8));
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
                // Prepare reset data for email
                $reset_data = [
                    'email' => $email,
                    'token' => $token,
                    'expires_at' => $expires_at
                ];
                // Check if email template exists
                $template_path = __DIR__ . '/../includes/templates/email_set_password.php';
                if (!file_exists($template_path)) {
                    $errors[] = 'Email template not found. Please contact support.';
                    error_log("Email template not found: $template_path");
                } else {
                    // Send setup password email
                    if (function_exists('sendPasswordEmail') && sendPasswordEmail($reset_data, 'set_password')) {
                        $add_success = 'User added successfully. A link to set up their password has been sent to their email.';
                    } else {
                        $add_success = 'User added successfully, but failed to send the password setup email. Please contact the user directly.';
                        error_log("Failed to send set_password email to: $email");
                    }
                }
            } else {
                $errors[] = 'Failed to generate password setup token.';
            }
        } else {
            $errors[] = 'Failed to add user.';
        }
        $stmt->close();
        // Redirect on successful add to clear POST data and show success message
        if (empty($errors)) {
            header("Location: users.php?msg=" . urlencode($add_success));
            exit;
        }
    }
}

// Handle edit user request (via AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    header('Content-Type: application/json');
    $user_id = (int)($_POST['user_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');

    // Validate inputs
    $edit_errors = [];
    if ($user_id <= 0) {
        $edit_errors[] = 'Invalid User ID.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $edit_errors[] = 'A valid email is required.';
    } else {
        // Check if email is unique (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $edit_errors[] = 'Email already exists.';
        }
        $stmt->close();
    }
    if (!in_array($role, $valid_roles)) {
        $edit_errors[] = 'Invalid role selected.';
    }

    if (empty($edit_errors)) {
        $stmt = $conn->prepare("UPDATE users SET email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $email, $role, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully.', 'email' => htmlspecialchars($email), 'role' => ucfirst(htmlspecialchars($role))]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user.']);
        }
        $stmt->close();
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => implode('<br>', $edit_errors)]);
        exit;
    }
}

// Handle GET messages after redirect
if (isset($_GET['msg'])) {
    $success = htmlspecialchars($_GET['msg']);
}
if (isset($_GET['err'])) {
    $error = htmlspecialchars($_GET['err']);
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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-red': '#e30613',
                        'dark-red': '#c70410',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .form-input {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.875rem;
            width: 100%;
            transition: all 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #e30613;
            box-shadow: 0 0 0 2px rgba(227, 6, 19, 0.2);
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .btn-primary {
            background: #e30613;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
            white-space: nowrap;
        }
        
        .btn-primary:hover {
            background: #c70410;
            transform: translateY(-1px);
        }

        .btn-icon {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: background-color 0.2s ease;
        }

        .btn-edit {
            color: #1e40af;
            background-color: #eff6ff;
        }
        .btn-edit:hover {
            background-color: #dbeafe;
        }
        .btn-delete {
            color: #dc2626;
            background-color: #fee2e2;
        }
        .btn-delete:hover {
            background-color: #fecaca;
        }

        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }
        
        .success-message {
            color: #10b981;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }

        .mobile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .table-row:hover {
            background-color: #f8fafc;
        }
        
        .display-view {
            display: block;
        }

        .edit-form-row {
            display: none;
        }

        .edit-form-row.active {
            display: table-row;
        }

        .mobile-card .edit-form {
            display: none;
        }
        .mobile-card .edit-form.active {
            display: block;
        }
        
        @media (min-width: 768px) {
            .mobile-view {
                display: none;
            }
        }
        
        @media (max-width: 767px) {
            .desktop-view {
                display: none;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <div class="p-4 md:p-6 lg:p-8">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                            <i class="fas fa-users text-primary-red mr-3"></i>
                            Manage Users
                        </h1>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <form method="GET" class="flex items-center">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by email" class="form-input w-full sm:w-64">
                                <button type="submit" class="btn-primary ml-2"><i class="fas fa-search"></i></button>
                            </form>
                            <button onclick="toggleAddUserForm()" class="btn-primary inline-flex items-center">
                                <i class="fas fa-plus mr-2"></i>Add New User
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="mb-6 bg-red-100 border border-red-300 rounded-lg p-4">
                        <?php foreach ($errors as $error): ?>
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                <span class="text-red-800 text-sm"><?= htmlspecialchars($error) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="mb-6 bg-green-100 border border-green-300 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 mr-2"></i>
                            <span class="text-green-800 text-sm"><?= htmlspecialchars($success) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (isset($error) && !isset($_GET['err'])): ?>
                    <div class="mb-6 bg-red-100 border border-red-300 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                            <span class="text-red-800 text-sm"><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="add-user-form" class="bg-white rounded-xl card-shadow p-6 mb-8 <?= !empty($errors) ? 'block' : 'hidden' ?>">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900">Add New User</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="add_user" value="1">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" placeholder="Enter user email" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="role" class="form-label">Role</label>
                                <select id="role" name="role" class="form-input" required>
                                    <option value="">Select a role</option>
                                    <?php foreach ($valid_roles as $r): ?>
                                        <option value="<?= $r ?>" <?= isset($role) && $role == $r ? 'selected' : '' ?>>
                                            <?= ucfirst($r) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-4 pt-2">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save mr-2"></i>Add User
                            </button>
                            <button type="button" onclick="toggleAddUserForm()" class="px-4 py-2 bg-gray-300 text-gray-800 hover:bg-gray-400 rounded-lg transition-colors inline-flex items-center">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (empty($users)): ?>
                    <div class="bg-white rounded-xl card-shadow p-8 text-center">
                        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-users text-3xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No Users Found</h3>
                        <p class="text-gray-600 mb-6">Add a new user to get started.</p>
                        <button onclick="toggleAddUserForm()" class="btn-primary">
                            <i class="fas fa-plus mr-2"></i>Add User
                        </button>
                    </div>
                <?php else: ?>
                    
                    <div class="space-y-4 mobile-view">
                        <h2 class="text-xl font-semibold mb-2 text-gray-900">User Accounts</h2>
                        <?php foreach ($users as $user): ?>
                            <div class="mobile-card p-4 relative">
                                <div id="display-card-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="display-view">
                                    <div class="text-lg font-medium text-gray-900 mb-2"><?= htmlspecialchars($user['email']) ?></div>
                                    <div class="space-y-1 text-sm text-gray-600">
                                        <div class="flex items-center">
                                            <i class="fas fa-user-tag text-primary-red w-4 mr-2"></i>
                                            <span>Role: <?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="flex justify-end space-x-2 mt-4">
                                        <button type="button" class="btn-icon btn-edit inline-flex items-center" onclick="showEditForm(<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>)">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </button>
                                        <button type="button" class="btn-icon btn-delete inline-flex items-center" onclick="showDeleteModal(<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>, '<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                                <div id="edit-form-card-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="edit-form p-0">
                                    <h3 class="text-lg font-semibold mb-3 text-gray-900">Edit User</h3>
                                    <form onsubmit="submitEditForm(event, <?= htmlspecialchars($user['id'], ENT_QUOTES) ?>)">
                                        <input type="hidden" name="edit_user" value="1">
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>">
                                        <div class="form-group mb-4">
                                            <label for="edit-email-mobile-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="form-label">Email</label>
                                            <input type="email" id="edit-email-mobile-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-input" required>
                                        </div>
                                        <div class="form-group mb-4">
                                            <label for="edit-role-mobile-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="form-label">Role</label>
                                            <select id="edit-role-mobile-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" name="role" class="form-input" required>
                                                <?php foreach ($valid_roles as $r): ?>
                                                    <option value="<?= $r ?>" <?= $user['role'] == $r ? 'selected' : '' ?>>
                                                        <?= ucfirst($r) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="flex justify-end space-x-4">
                                            <button type="submit" class="btn-primary flex items-center">
                                                <i class="fas fa-save mr-2"></i>Save
                                            </button>
                                            <button type="button" onclick="hideEditForm(<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>)" class="px-4 py-2 bg-gray-300 text-gray-800 hover:bg-gray-400 rounded-lg transition-colors flex items-center">
                                                <i class="fas fa-times mr-2"></i>Cancel
                                            </button>
                                        </div>
                                        <div id="edit-error-card-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="error-message mt-2 hidden"></div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="desktop-view overflow-x-auto bg-white rounded-xl card-shadow">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/2">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Role</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <tr id="display-row-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="table-row display-view">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <div class="flex items-center">
                                                <i class="fas fa-envelope text-primary-red mr-2"></i>
                                                <span><?= htmlspecialchars($user['email']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" id="display-role-text-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>">
                                            <div class="flex items-center">
                                                <i class="fas fa-user-tag text-primary-red mr-2"></i>
                                                <span><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end space-x-2">
                                                <button type="button" class="btn-icon btn-edit inline-flex items-center" onclick="showEditForm(<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn-icon btn-delete inline-flex items-center" onclick="showDeleteModal(<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>, '<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr id="edit-form-row-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="edit-form-row bg-gray-50">
                                        <td colspan="3" class="px-6 py-4">
                                            <form onsubmit="submitEditForm(event, <?= htmlspecialchars($user['id'], ENT_QUOTES) ?>)" class="space-y-4">
                                                <input type="hidden" name="edit_user" value="1">
                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>">
                                                <div class="flex items-end gap-4">
                                                    <div class="flex-grow">
                                                        <label for="edit-email-desktop-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="form-label">Email</label>
                                                        <input type="email" id="edit-email-desktop-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-input" required>
                                                    </div>
                                                    <div class="w-40">
                                                        <label for="edit-role-desktop-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="form-label">Role</label>
                                                        <select id="edit-role-desktop-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" name="role" class="form-input" required>
                                                            <?php foreach ($valid_roles as $r): ?>
                                                                <option value="<?= $r ?>" <?= $user['role'] == $r ? 'selected' : '' ?>>
                                                                    <?= ucfirst($r) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="flex space-x-2">
                                                        <button type="submit" class="btn-primary py-2 px-3 flex items-center">
                                                            <i class="fas fa-save"></i>
                                                        </button>
                                                        <button type="button" onclick="hideEditForm(<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>)" class="px-3 py-2 bg-gray-300 text-gray-800 hover:bg-gray-400 rounded-lg transition-colors flex items-center">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div id="edit-error-desktop-<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>" class="error-message hidden"></div>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center modal-overlay transition-opacity duration-300 ease-in-out opacity-0">
        <div class="bg-white rounded-xl p-6 max-w-sm mx-auto shadow-2xl transform scale-95 transition-transform duration-300 ease-in-out">
            <h3 class="text-xl font-bold mb-4 text-center text-gray-900">Confirm Deletion</h3>
            <p id="deleteMessage" class="text-gray-700 mb-6 text-center"></p>
            <div class="flex justify-center space-x-4">
                <button id="cancelButton" class="px-4 py-2 bg-gray-300 text-gray-800 hover:bg-gray-400 rounded-lg transition-colors">Cancel</button>
                <a id="confirmDeleteLink" href="#" class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-lg transition-colors">Delete</a>
            </div>
        </div>
    </div>

    <script>
        function toggleAddUserForm() {
            const form = document.getElementById('add-user-form');
            form.classList.toggle('hidden');
        }

        function showDeleteModal(userId, userEmail) {
            const modal = document.getElementById('deleteModal');
            const message = document.getElementById('deleteMessage');
            const deleteLink = document.getElementById('confirmDeleteLink');
            const cancelButton = document.getElementById('cancelButton');

            message.innerHTML = `Are you sure you want to delete user <strong>${userEmail}</strong>? This action cannot be undone.`;
            deleteLink.href = `users.php?delete=${userId}`;
            
            modal.classList.remove('hidden', 'opacity-0');
            modal.classList.add('flex', 'opacity-100');

            const closeModal = () => {
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0');
                setTimeout(() => modal.classList.add('hidden'), 300);
            };

            cancelButton.onclick = closeModal;
            modal.onclick = (e) => {
                if (e.target.id === 'deleteModal') {
                    closeModal();
                }
            };
        }

        function showEditForm(userId) {
            const displayRow = document.getElementById(`display-row-${userId}`);
            const editFormRow = document.getElementById(`edit-form-row-${userId}`);
            if (displayRow) displayRow.classList.add('hidden');
            if (editFormRow) editFormRow.classList.add('active');

            const displayCard = document.getElementById(`display-card-${userId}`);
            const editFormCard = document.getElementById(`edit-form-card-${userId}`);
            if (displayCard) displayCard.classList.add('hidden');
            if (editFormCard) editFormCard.classList.add('active');
        }

        function hideEditForm(userId) {
            const displayRow = document.getElementById(`display-row-${userId}`);
            const editFormRow = document.getElementById(`edit-form-row-${userId}`);
            if (displayRow) displayRow.classList.remove('hidden');
            if (editFormRow) editFormRow.classList.remove('active');

            const displayCard = document.getElementById(`display-card-${userId}`);
            const editFormCard = document.getElementById(`edit-form-card-${userId}`);
            if (displayCard) displayCard.classList.remove('hidden');
            if (editFormCard) editFormCard.classList.remove('active');
        }

        function submitEditForm(event, userId) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const errorDesktop = document.getElementById(`edit-error-desktop-${userId}`);
            const errorCard = document.getElementById(`edit-error-card-${userId}`);

            if (errorDesktop) {
                errorDesktop.classList.add('hidden');
                errorDesktop.innerHTML = '';
            }
            if (errorCard) {
                errorCard.classList.add('hidden');
                errorCard.innerHTML = '';
            }

            fetch('users.php', {
                method: 'POST',
                body: new URLSearchParams(formData),
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const displayRoleText = document.getElementById(`display-role-text-${userId}`);
                    const desktopEmailDisplay = document.getElementById(`display-row-${userId}`).querySelector('td:first-child span');
                    const mobileDisplay = document.getElementById(`display-card-${userId}`);
                    const mobileEmailDisplay = mobileDisplay.querySelector('.text-lg.font-medium');
                    const mobileRoleDisplay = mobileDisplay.querySelector('.space-y-1 .flex:last-child span');

                    if (displayRoleText) {
                        displayRoleText.querySelector('span').textContent = data.role;
                    }
                    if (desktopEmailDisplay) {
                        desktopEmailDisplay.textContent = data.email;
                    }
                    if (mobileEmailDisplay) mobileEmailDisplay.textContent = data.email;
                    if (mobileRoleDisplay) mobileRoleDisplay.textContent = `Role: ${data.role}`;

                    hideEditForm(userId);

                    const successDiv = document.createElement('div');
                    successDiv.className = 'mb-6 bg-green-100 border border-green-300 rounded-lg p-4';
                    successDiv.innerHTML = `<div class="flex items-center"><i class="fas fa-check-circle text-green-600 mr-2"></i><span class="text-green-800 text-sm">${data.message}</span></div>`;
                    document.querySelector('.max-w-4xl.mx-auto').insertBefore(successDiv, document.querySelector('.max-w-4xl.mx-auto').children[1]);
                    setTimeout(() => successDiv.remove(), 5000);
                } else {
                    if (errorDesktop) {
                        errorDesktop.classList.remove('hidden');
                        errorDesktop.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${data.message}`;
                    }
                    if (errorCard) {
                        errorCard.classList.remove('hidden');
                        errorCard.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${data.message}`;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const errorMessage = 'An unexpected error occurred. Please try again.';
                if (errorDesktop) {
                    errorDesktop.classList.remove('hidden');
                    errorDesktop.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${errorMessage}`;
                }
                if (errorCard) {
                    errorCard.classList.remove('hidden');
                    errorCard.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${errorMessage}`;
                }
            });
        }
    </script>
</body>
</html>