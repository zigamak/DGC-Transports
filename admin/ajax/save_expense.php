<?php
// admin/ajax/save_expense.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Enforce admin-only access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$investor_id = isset($_POST['investor_id']) ? (int)$_POST['investor_id'] : 0;
$vehicle_id = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;
$expense_type = trim($_POST['expense_type'] ?? '');
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$expense_date = trim($_POST['expense_date'] ?? '');
$description = trim($_POST['description'] ?? '');

// Validation
if ($vehicle_id <= 0 || empty($expense_type) || $amount <= 0 || empty($expense_date) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Verify vehicle belongs to investor
$verify_query = "SELECT id FROM vehicles WHERE id = ? AND investor_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $vehicle_id, $investor_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle selection']);
    exit;
}
$stmt->close();

// Handle receipt upload
$receipt_path = null;
if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../uploads/receipts/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF are allowed.']);
        exit;
    }
    
    // Check file size (5MB max)
    if ($_FILES['receipt']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
        exit;
    }
    
    $filename = 'receipt_' . time() . '_' . uniqid() . '.' . $file_extension;
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target_path)) {
        $receipt_path = 'uploads/receipts/' . $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload receipt']);
        exit;
    }
}

try {
    $query = "INSERT INTO vehicle_expenses (vehicle_id, expense_type, amount, description, receipt_path, expense_date, created_by) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isdssi", $vehicle_id, $expense_type, $amount, $description, $receipt_path, $expense_date, $_SESSION['user']['id']);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Expense added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}