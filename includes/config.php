<?php
// includes/config.php

define('SITE_URL', 'http://localhost/dgctransports');
//define('BASE_DIR', 'C:/xampp/htdocs/dgctransports'); // e.g., /home/dgctrans/public_html/booking.dgctransports.com
//define('LOGS_DIR', BASE_DIR . '/logs'); // e.g., /home/dgctrans/public_html/booking.dgctransports.com/logs



// The name of the transport platform.
define('SITE_NAME', 'DGC TRANSPORTS');
define('SITE_EMAIL', 'admin@dgctransports.com');
define('SITE_PHONE', '+234373783838');


// SMTP Email settings
define('SMTP_HOST', 'dgctransports.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@dgctransports.com');
define('SMTP_PASSWORD', 'EI8WT6[Y{%q-AUFx');
define('FROM_EMAIL', 'noreply@dgctransports.com');
define('FROM_NAME', 'DGC Transports');
define('SMTP_SECURE', 'tls');

// Payment gateway settings
define('PAYSTACK_SECRET_KEY', 'sk_test_6ceb75d8532032bc4bdb45113b71d0e95c9b7afc');
define('PAYSTACK_PUBLIC_KEY', 'pk_test_3446d9fc9c4e3851058d1ba46b326e762ec72319');
define('PAYSTACK_VERIFY_URL', 'https://api.paystack.co/transaction/verify/');
define('PAYSTACK_INITIALIZE_URL', 'https://api.paystack.co/transaction/initialize');


// Application settings
define('DEFAULT_TIMEZONE', 'Africa/Lagos');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');


?>
