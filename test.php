<?php
// test_curl.php - Create this file to test if cURL works on your localhost
require_once 'includes/config.php';

echo "<h2>Testing cURL and Paystack API Connection</h2>";

// Test basic cURL functionality
if (!function_exists('curl_init')) {
    echo "<p style='color: red;'>❌ cURL is not enabled in PHP</p>";
    exit;
}
echo "<p style='color: green;'>✅ cURL is enabled</p>";

// Test Paystack API connection with a dummy reference
$test_reference = 'test_123';
$url = 'https://api.paystack.co/transaction/verify/' . $test_reference;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
    "Cache-Control: no-cache",
));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For localhost
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For localhost

$result = curl_exec($ch);
$err = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>cURL Test Results:</h3>";
echo "<p><strong>HTTP Code:</strong> $http_code</p>";
echo "<p><strong>cURL Error:</strong> " . ($err ?: 'None') . "</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($result) . "</pre>";

if ($err) {
    echo "<p style='color: red;'>❌ cURL failed: $err</p>";
} elseif ($http_code === 200) {
    echo "<p style='color: green;'>✅ Successfully connected to Paystack API</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Connected but got HTTP $http_code (this is expected for a dummy reference)</p>";
}
?>