<?php
// admin/ajax/manage_vehicle_assignment.php
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
$vehicle_id = isset($data['vehicle_id']) ? (int)$data['vehicle_id'] : 0;
$investor_id = isset($data['investor_id']) ? (int)$data['investor_id'] : 0;
$action = isset($data['action']) ? $data['action'] : '';

if ($vehicle_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle ID']);
    exit;
}

try {
    if ($action === 'assign') {
        if ($investor_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid investor ID']);
            exit;
        }
        
        // Check if investor exists
        $check_query = "SELECT id FROM users WHERE id = ? AND role = 'investor'";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $investor_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Investor not found']);
            exit;
        }
        $stmt->close();
        
        // Assign vehicle
        $query = "UPDATE vehicles SET investor_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $investor_id, $vehicle_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Vehicle assigned successfully']);
    } elseif ($action === 'unassign') {
        // Unassign vehicle
        $query = "UPDATE vehicles SET investor_id = NULL WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $vehicle_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Vehicle unassigned successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}