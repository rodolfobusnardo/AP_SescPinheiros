<?php
require_once 'config.php';

$max_attempts = 5;
$attempt = 0;
$conn = null;

while ($attempt < $max_attempts) {
    //mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Optional: Enable strict error reporting for mysqli
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn->connect_error) {
            break; // Successful connection
        }
        // Log error or handle more gracefully in a real app
        error_log("DB Connection attempt " . ($attempt + 1) . " failed: " . $conn->connect_error);
    } catch (mysqli_sql_exception $e) {
        error_log("DB Connection attempt " . ($attempt + 1) . " (exception): " . $e->getMessage());
    }
    $attempt++;
    if ($attempt < $max_attempts) {
        sleep(10); // Wait 2 seconds before retrying
    }
}

if ($conn === null || $conn->connect_error) {
    // In a real application, you might want to log this error or display a more user-friendly message.
    http_response_code(500);
    die("Database connection failed after " . $max_attempts . " attempts. Error: " . ($conn ? $conn->connect_error : 'Unknown mysqli error or exception during connection process. Check PHP error logs.'));
}

// The connection object $conn is now available
?>
