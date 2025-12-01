<?php
// get_vehicle_expenses.php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('investor');

$vehicle_id = intval($_GET['vehicle_id'] ?? 0);
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$investor_id = $_SESSION['user']['id'];

// Security: Ensure vehicle belongs to investor
$stmt = $conn->prepare("SELECT id FROM vehicles WHERE id = ? AND investor_id = ?");
$stmt->bind_param("ii", $vehicle_id, $investor_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$stmt->close();

$query = "
    SELECT 
        ve.expense_type,
        ve.amount,
        ve.description,
        ve.receipt_path,
        ve.expense_date
    FROM vehicle_expenses ve
    WHERE ve.vehicle_id = ? 
      AND ve.expense_date BETWEEN ? AND ?
    ORDER BY ve.expense_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $vehicle_id, $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'expenses' => $expenses
]);