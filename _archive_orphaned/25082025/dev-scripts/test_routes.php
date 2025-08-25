<?php

// Simple test to check if our routes are being loaded
require_once __DIR__.'/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

if (class_exists(Dotenv::class) && is_file(__DIR__.'/.env')) {
    (new Dotenv())->bootEnv(__DIR__.'/.env');
}

$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));
$kernel->boot();

$container = $kernel->getContainer();
$router = $container->get('router');
$routes = $router->getRouteCollection();

echo "Total routes: " . count($routes) . "\n";

// Check if homepage route exists
if ($routes->get('homepage')) {
    echo "✅ homepage route found!\n";
} else {
    echo "❌ homepage route NOT found\n";
}

// List some routes for debugging
echo "\nSome routes:\n";
foreach ($routes as $name => $route) {
    if (strpos($name, 'app_') === 0 || strpos($name, 'homepage') !== false) {
        echo "- $name: " . $route->getPath() . "\n";
    }
}
?>