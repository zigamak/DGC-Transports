<?php
// bookings/check_referral.php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$referral_code = isset($_POST['referral_code']) ? strtoupper(trim($_POST['referral_code'])) : '';

if (empty($referral_code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a referral code']);
    exit();
}

error_log("AJAX: Processing referral code: $referral_code");

try {
    // Check if referral code exists and find the referrer (any role)
    $stmt = $conn->prepare("SELECT u.id as referrer_id, u.first_name, u.last_name, u.role 
                           FROM users u 
                           WHERE u.affiliate_id = ?");
    
    if (!$stmt) {
        error_log("AJAX: Failed to prepare referral code query: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'System error processing referral code']);
        exit();
    }

    $stmt->bind_param("s", $referral_code);
    $stmt->execute();
    $result = $stmt->get_result();
    error_log("AJAX: Query returned " . $result->num_rows . " rows for referral code: $referral_code");
    $referrer = $result->fetch_assoc();
    $stmt->close();

    if ($referrer) {
        // Get referral credit amount from settings
        $stmt = $conn->prepare("SELECT credits FROM referral_settings WHERE id = 1");
        if (!$stmt) {
            error_log("AJAX: Failed to prepare referral settings query: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'System error processing referral code']);
            exit();
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $referral_settings = $result->fetch_assoc();
        $referral_credits = $referral_settings['credits'] ?? 2000;
        $stmt->close();

        // Store in session
        $_SESSION['referral_code'] = $referral_code;
        $_SESSION['referrer_id'] = $referrer['referrer_id'];
        $referrer_name = $referrer['first_name'] . ' ' . $referrer['last_name'];
   $_SESSION['referral_message'] = "Referral code valid! Referred by $referrer_name.";

        error_log("AJAX: Referral code $referral_code applied successfully. Referrer: $referrer_name, Role: {$referrer['role']}, Credits: $referral_credits");

        echo json_encode([
            'success' => true,
            'message' => "Referral code valid! {$referrer_name}",
            'referrer_name' => $referrer_name,
            'credits' => $referral_credits,
            'formatted_credits' => number_format($referral_credits, 0)
        ]);
    } else {
        error_log("AJAX: No user found with affiliate_id: $referral_code");
        $_SESSION['referral_error'] = 'Invalid referral code';
        echo json_encode(['success' => false, 'message' => 'Invalid referral code']);
    }

} catch (Exception $e) {
    error_log("AJAX: Exception in referral check: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error processing referral code']);
}
?>