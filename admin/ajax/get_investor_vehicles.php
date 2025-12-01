<?php
// admin/ajax/get_investor_vehicles.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Enforce admin-only access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$investor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($investor_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid investor ID']);
    exit;
}

// Get investor name
$investor_query = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ? AND role = 'investor'";
$stmt = $conn->prepare($investor_query);
$stmt->bind_param("i", $investor_id);
$stmt->execute();
$investor_result = $stmt->get_result();

if ($investor_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Investor not found']);
    exit;
}

$investor_name = $investor_result->fetch_assoc()['name'];

// Get all vehicles
$vehicles_query = "
    SELECT v.id, v.vehicle_number, v.driver_name, vt.type as vehicle_type, v.investor_id,
           CONCAT(u.first_name, ' ', u.last_name) as current_investor
    FROM vehicles v
    JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
    LEFT JOIN users u ON v.investor_id = u.id AND u.role = 'investor'
    ORDER BY v.vehicle_number
";
$vehicles_result = $conn->query($vehicles_query);
$vehicles = [];
while ($row = $vehicles_result->fetch_assoc()) {
    $vehicles[] = $row;
}

echo json_encode([
    'success' => true,
    'investor_name' => $investor_name,
    'vehicles' => $vehicles
]);