<?php
require_once 'config.php';

$max_attempts = 5;
$attempt = 0;
$conn = null;

while ($attempt < $max_attempts) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn->connect_error) {
            break; // Successful connection
        }
        
        error_log("DB Connection attempt " . ($attempt + 1) . " failed: " . $conn->connect_error);
    } catch (mysqli_sql_exception $e) {
        error_log("DB Connection attempt " . ($attempt + 1) . " (exception): " . $e->getMessage());
    }
    $attempt++;
    if ($attempt < $max_attempts) {
        sleep(10);
    }
}

if ($conn === null || $conn->connect_error) {
    
    http_response_code(500);
    die("Database connection failed after " . $max_attempts . " attempts. Error: " . ($conn ? $conn->connect_error : 'Unknown mysqli error or exception during connection process. Check PHP error logs.'));
}


?>
