<?php
// includes/config.php

define('SITE_URL', 'http://localhost/dgctransports');

// The name of the transport platform.
define('SITE_NAME', 'DGC TRANSPORTS');
define('SITE_EMAIL', 'admin@dgctransports.com');
define('SITE_PHONE', '+234373783838');


// SMTP Email settings
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@smartinvite.net');
define('SMTP_PASSWORD', 'fU[P=:V>0z');
define('FROM_EMAIL', 'noreply@smartinvite.net');
define('FROM_NAME', 'Smart Invites');
define('SMTP_SECURE', 'tls');

// Payment gateway settings
define('PAYSTACK_SECRET_KEY', 'sk_test_6ceb75d8532032bc4bdb45113b71d0e95c9b7afc');
define('PAYSTACK_PUBLIC_KEY', 'pk_test_3446d9fc9c4e3851058d1ba46b326e762ec72319');

// Application settings
define('DEFAULT_TIMEZONE', 'Africa/Lagos');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');


?>
