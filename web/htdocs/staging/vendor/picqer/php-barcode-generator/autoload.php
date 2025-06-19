<?php
// File: web/htdocs/vendor/picqer/php-barcode-generator/autoload.php

// Simple autoloader for the manually installed barcode generator
spl_autoload_register(function ($class) {
    // Check if this is a Picqer\Barcode class
    if (strpos($class, 'Picqer\\Barcode\\') === 0) {
        // Convert namespace to file path
        $classPath = str_replace('\\', '/', $class);
        $classPath = str_replace('Picqer/Barcode/', '', $classPath);
        
        $file = __DIR__ . '/src/' . $classPath . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
?>