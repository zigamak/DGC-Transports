<?php
// admin/ajax/save_investor.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Enforce admin-only access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Admin privileges required.']);
    exit;
}

// Get and sanitize input data
$investor_id = isset($_POST['investor_id']) ? (int)$_POST['investor_id'] : 0;
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = trim($_POST['password'] ?? '');

// Comprehensive Validation
$errors = [];

// Name validation
if (empty($first_name)) {
    $errors[] = 'First name is required';
} elseif (strlen($first_name) < 2) {
    $errors[] = 'First name must be at least 2 characters';
} elseif (strlen($first_name) > 50) {
    $errors[] = 'First name must not exceed 50 characters';
} elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $first_name)) {
    $errors[] = 'First name contains invalid characters';
}

if (empty($last_name)) {
    $errors[] = 'Last name is required';
} elseif (strlen($last_name) < 2) {
    $errors[] = 'Last name must be at least 2 characters';
} elseif (strlen($last_name) > 50) {
    $errors[] = 'Last name must not exceed 50 characters';
} elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $last_name)) {
    $errors[] = 'Last name contains invalid characters';
}

// Email validation
if (empty($email)) {
    $errors[] = 'Email address is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address format';
} elseif (strlen($email) > 150) {
    $errors[] = 'Email address is too long';
}

// Phone validation
if (empty($phone)) {
    $errors[] = 'Phone number is required';
} elseif (!preg_match("/^[\d\s\+\-\(\)]+$/", $phone)) {
    $errors[] = 'Phone number contains invalid characters';
} elseif (strlen($phone) < 10) {
    $errors[] = 'Phone number must be at least 10 digits';
} elseif (strlen($phone) > 20) {
    $errors[] = 'Phone number is too long';
}

// Password validation (only for new investors or when changing password)
if ($investor_id === 0 && empty($password)) {
    $errors[] = 'Password is required for new investor';
} elseif (!empty($password)) {
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    } elseif (strlen($password) > 255) {
        $errors[] = 'Password is too long';
    }
}

// Return validation errors
if (!empty($errors)) {
    echo json_encode([
        'success' => false, 
        'message' => implode('. ', $errors),
        'errors' => $errors
    ]);
    exit;
}

// Check for duplicate email
try {
    if ($investor_id > 0) {
        // Editing existing investor - check email not used by other users
        $check_query = "SELECT id, role FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("si", $email, $investor_id);
    } else {
        // New investor - check email not used at all
        $check_query = "SELECT id, role FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $email);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $existing_user = $result->fetch_assoc();
        $role_display = ucfirst($existing_user['role']);
        echo json_encode([
            'success' => false, 
            'message' => "This email is already registered to another user ($role_display)"
        ]);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // If updating, verify the investor exists
    if ($investor_id > 0) {
        $verify_query = "SELECT id FROM users WHERE id = ? AND role = 'investor'";
        $stmt = $conn->prepare($verify_query);
        $stmt->bind_param("i", $investor_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Investor not found or invalid investor ID'
            ]);
            $stmt->close();
            exit;
        }
        $stmt->close();
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    if ($investor_id > 0) {
        // Update existing investor
        if (!empty($password)) {
            // Update with new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, password = ? WHERE id = ? AND role = 'investor'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $hashed_password, $investor_id);
        } else {
            // Update without changing password
            $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ? AND role = 'investor'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $investor_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update investor information');
        }
        
        if ($stmt->affected_rows === 0) {
            // No rows affected might mean no changes or investor not found
            $stmt->close();
            $conn->rollback();
            echo json_encode([
                'success' => false, 
                'message' => 'No changes made or investor not found'
            ]);
            exit;
        }
        
        $stmt->close();
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Investor updated successfully',
            'investor_id' => $investor_id,
            'investor_name' => $first_name . ' ' . $last_name
        ]);
        
    } else {
        // Add new investor
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (first_name, last_name, email, phone, password, role, created_at) 
                  VALUES (?, ?, ?, ?, ?, 'investor', NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssss", $first_name, $last_name, $email, $phone, $hashed_password);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create new investor');
        }
        
        $new_investor_id = $conn->insert_id;
        $stmt->close();
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Investor added successfully',
            'investor_id' => $new_investor_id,
            'investor_name' => $first_name . ' ' . $last_name
        ]);
    }
    
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    
    // Handle specific MySQL errors
    if ($e->getCode() == 1062) {
        echo json_encode([
            'success' => false, 
            'message' => 'Duplicate entry detected. Email or phone may already exist.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}