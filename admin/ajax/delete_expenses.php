<?php
// admin/ajax/delete_expense.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Enforce admin-only access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$expense_id = isset($data['expense_id']) ? (int)$data['expense_id'] : 0;

if ($expense_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid expense ID']);
    exit;
}

try {
    // Get receipt path before deleting
    $query = "SELECT receipt_path FROM vehicle_expenses WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        exit;
    }
    
    $expense = $result->fetch_assoc();
    $stmt->close();
    
    // Delete the expense
    $delete_query = "DELETE FROM vehicle_expenses WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    
    // Delete the receipt file if it exists
    if (!empty($expense['receipt_path']) && file_exists('../../' . $expense['receipt_path'])) {
        unlink('../../' . $expense['receipt_path']);
    }
    
    echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}