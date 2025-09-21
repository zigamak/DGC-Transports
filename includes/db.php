<?php
// includes/db.php
ini_set('error_log', __DIR__ . '/../logs/error.log'); // Custom log file
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dgctransports";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }
    $conn->set_charset("utf8mb4");
    // Enable strict error reporting for queries
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}
?>