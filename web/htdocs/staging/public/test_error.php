<?php
// Check error_log path
echo "Error log path: " . (ini_get('error_log') ?: "Not explicitly set") . "<br>";

// Check if error logging is enabled
echo "Error logging enabled: " . (ini_get('log_errors') ? "Yes" : "No") . "<br>";

// Check where errors will go if not explicitly set
echo "Error messages will go to: ";
if (ini_get('error_log')) {
    echo ini_get('error_log');
} else {
    echo "Server's default error log";
}
echo "<br>";

// Try writing to the error log
error_log("Test message from error logging script");
echo "Test message written to error log.<br>";

// Show common log locations
echo "<h3>Common error log locations:</h3>";
$log_locations = [
    "/var/log/apache2/error.log",
    "/var/log/httpd/error.log",
    "/var/log/nginx/error.log",
    "/var/log/php-errors.log",
    "/var/log/php_errors.log"
];

foreach ($log_locations as $log) {
    echo "$log: " . (file_exists($log) && is_readable($log) ? "Exists and readable" : "Not accessible") . "<br>";
}

// Show phpinfo for more details
echo "<br><h3>PHP Configuration:</h3>";
phpinfo(INFO_CONFIGURATION);
?>