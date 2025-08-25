<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP Test</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Time: " . date('Y-m-d H:i:s') . "</p>";

// Test if Symfony can load
try {
    echo "<h2>Testing Symfony Bootstrap</h2>";
    
    require_once '../vendor/autoload.php';
    echo "<p>✅ Autoloader loaded successfully</p>";
    
    if (file_exists('../.env')) {
        echo "<p>✅ .env file exists</p>";
    } else {
        echo "<p>❌ .env file missing</p>";
    }
    
    if (class_exists('App\Kernel')) {
        echo "<p>✅ App\Kernel class found</p>";
    } else {
        echo "<p>❌ App\Kernel class not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

?>