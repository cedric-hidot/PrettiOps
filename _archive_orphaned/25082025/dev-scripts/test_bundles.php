<?php
/**
 * Test script to verify all bundles can be loaded
 */

echo "Testing bundle autoloading...\n\n";

require_once __DIR__ . '/vendor/autoload.php';

$bundles = [
    'Doctrine\\Bundle\\FixturesBundle\\DoctrineFixturesBundle',
    'Liip\\TestFixturesBundle\\LiipTestFixturesBundle', 
    'Symfony\\Bundle\\DebugBundle\\DebugBundle',
    'Symfony\\Bundle\\MakerBundle\\MakerBundle',
    'Symfony\\Bundle\\WebProfilerBundle\\WebProfilerBundle',
    'App\\Kernel'
];

foreach ($bundles as $bundleClass) {
    if (class_exists($bundleClass)) {
        echo "✅ $bundleClass - OK\n";
    } else {
        echo "❌ $bundleClass - MISSING\n";
    }
}

echo "\nDone!\n";