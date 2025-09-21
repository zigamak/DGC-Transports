<?php
// bookings/test_includes.php - Test if all required files can be loaded
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Testing File Includes and Dependencies</h2>";

// Test 1: Check if files exist
$files_to_check = [
    '../includes/db.php',
    '../includes/config.php', 
    '../includes/functions.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<span style='color: green'>✓</span> $file exists<br>";
    } else {
        echo "<span style='color: red'>✗</span> $file NOT FOUND<br>";
    }
}

echo "<hr>";

// Test 2: Try to include files
try {
    echo "Including db.php...<br>";
    require_once '../includes/db.php';
    echo "<span style='color: green'>✓</span> db.php included successfully<br>";
    
    if (isset($conn)) {
        echo "<span style='color: green'>✓</span> Database connection variable exists<br>";
        if ($conn->connect_error) {
            echo "<span style='color: red'>✗</span> Database connection error: " . $conn->connect_error . "<br>";
        } else {
            echo "<span style='color: green'>✓</span> Database connection successful<br>";
        }
    } else {
        echo "<span style='color: red'>✗</span> Database connection variable not found<br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red'>✗</span> Error including db.php: " . $e->getMessage() . "<br>";
}

try {
    echo "Including config.php...<br>";
    require_once '../includes/config.php';
    echo "<span style='color: green'>✓</span> config.php included successfully<br>";
    
    // Check for required constants
    $required_constants = ['PAYSTACK_PUBLIC_KEY', 'PAYSTACK_SECRET_KEY', 'SITE_NAME'];
    foreach ($required_constants as $constant) {
        if (defined($constant)) {
            $value = constant($constant);
            if (!empty($value)) {
                echo "<span style='color: green'>✓</span> $constant is defined and not empty<br>";
            } else {
                echo "<span style='color: orange'>!</span> $constant is defined but empty<br>";
            }
        } else {
            echo "<span style='color: red'>✗</span> $constant is not defined<br>";
        }
    }
} catch (Exception $e) {
    echo "<span style='color: red'>✗</span> Error including config.php: " . $e->getMessage() . "<br>";
}

try {
    echo "Including functions.php...<br>";
    require_once '../includes/functions.php';
    echo "<span style='color: green'>✓</span> functions.php included successfully<br>";
    
    // Check for required functions
    $required_functions = ['generatePNR', 'verifyPaystackPayment'];
    foreach ($required_functions as $function) {
        if (function_exists($function)) {
            echo "<span style='color: green'>✓</span> Function $function exists<br>";
        } else {
            echo "<span style='color: red'>✗</span> Function $function NOT FOUND<br>";
        }
    }
} catch (Exception $e) {
    echo "<span style='color: red'>✗</span> Error including functions.php: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Test 3: Test session
try {
    session_start();
    echo "<span style='color: green'>✓</span> Session started successfully<br>";
} catch (Exception $e) {
    echo "<span style='color: red'>✗</span> Session start error: " . $e->getMessage() . "<br>";
}

// Test 4: Test database table structure
if (isset($conn) && !$conn->connect_error) {
    echo "<h3>Database Table Check</h3>";
    
    $tables_to_check = ['bookings', 'seat_bookings', 'trips', 'payments'];
    foreach ($tables_to_check as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<span style='color: green'>✓</span> Table '$table' exists<br>";
            
            // Check table structure
            $structure = $conn->query("DESCRIBE $table");
            if ($structure) {
                echo "&nbsp;&nbsp;Columns: ";
                $columns = [];
                while ($row = $structure->fetch_assoc()) {
                    $columns[] = $row['Field'];
                }
                echo implode(', ', $columns) . "<br>";
            }
        } else {
            echo "<span style='color: red'>✗</span> Table '$table' NOT FOUND<br>";
        }
    }
}

echo "<hr>";
echo "<p><strong>If all checks pass, your verify_payment.php should work. If any checks fail, fix those issues first.</strong></p>";
?>