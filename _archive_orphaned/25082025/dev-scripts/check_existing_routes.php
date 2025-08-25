<?php

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

// Check routes we need
$needed_routes = ['homepage', 'pricing', 'login', 'register', 'app_register', 'features_editor', 'demo'];

echo "Checking needed routes:\n";
foreach ($needed_routes as $route_name) {
    $route = $routes->get($route_name);
    if ($route) {
        echo "✅ $route_name: " . $route->getPath() . "\n";
    } else {
        echo "❌ $route_name: NOT found\n";
        
        // Try to find similar routes
        foreach ($routes as $name => $r) {
            if (stripos($name, str_replace('_', '', $route_name)) !== false) {
                echo "   🔍 Similar: $name: " . $r->getPath() . "\n";
            }
        }
    }
}
?>