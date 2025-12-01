<?php
// bookings/process_roundtrip_selection.php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$outbound = json_decode($_POST['outbound'], true);
$return = json_decode($_POST['return'], true);
$num_seats = isset($_POST['num_seats']) ? (int)$_POST['num_seats'] : 0;

if (!$outbound || !$return || $num_seats <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid trip selection']);
    exit();
}

$_SESSION['roundtrip'] = [
    'outbound' => $outbound,
    'return' => $return,
    'num_seats' => $num_seats
];

echo json_encode(['success' => true]);