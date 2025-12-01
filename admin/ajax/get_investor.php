<?php
// admin/ajax/get_investor.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$investor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($investor_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid investor ID']);
    exit;
}

$query = "SELECT id, first_name, last_name, email, phone, created_at FROM users WHERE id = ? AND role = 'investor'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $investor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Investor not found']);
    exit;
}

$investor = $result->fetch_assoc();
$stmt->close();

echo json_encode(['success' => true, 'investor' => $investor]);