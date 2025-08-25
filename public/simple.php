<?php
// Simple test file to debug blank page issue

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Simple Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test { background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Simple PHP Test</h1>
    <div class='test'>
        <h2>PHP Version</h2>
        <p>" . phpversion() . "</p>
    </div>
    <div class='test'>
        <h2>Server Time</h2>
        <p>" . date('Y-m-d H:i:s') . "</p>
    </div>
</body>
</html>";
?>