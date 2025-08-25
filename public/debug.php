<?php
// Debug file to check Symfony bootstrap
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Output</h1>";

try {
    echo "<h2>1. Testing Autoloader</h2>";
    require_once __DIR__.'/../vendor/autoload.php';
    echo "✅ Autoloader loaded<br>";
    
    echo "<h2>2. Testing Environment</h2>";
    if (class_exists('\Symfony\Component\Dotenv\Dotenv')) {
        $dotenv = new \Symfony\Component\Dotenv\Dotenv();
        $dotenv->bootEnv(__DIR__.'/../.env');
        echo "✅ Environment loaded<br>";
    } else {
        echo "❌ Dotenv not available<br>";
    }
    
    echo "<h2>3. Testing Kernel</h2>";
    if (class_exists('App\Kernel')) {
        echo "✅ App\\Kernel class found<br>";
        
        $kernel = new App\Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));
        $kernel->boot();
        echo "✅ Kernel booted successfully<br>";
        
        $container = $kernel->getContainer();
        echo "✅ Container available<br>";
        
        // Test if router service is available
        if ($container->has('router')) {
            $router = $container->get('router');
            echo "✅ Router service available<br>";
            
            $routes = $router->getRouteCollection();
            echo "✅ Routes loaded: " . count($routes) . " routes<br>";
        } else {
            echo "❌ Router service not available<br>";
        }
        
    } else {
        echo "❌ App\\Kernel class not found<br>";
    }
    
    echo "<h2>4. Testing Twig</h2>";
    if ($container->has('twig')) {
        $twig = $container->get('twig');
        echo "✅ Twig service available<br>";
        
        // Test if template exists
        try {
            $loader = $twig->getLoader();
            if ($loader->exists('base.html.twig')) {
                echo "✅ base.html.twig exists<br>";
            } else {
                echo "❌ base.html.twig not found<br>";
            }
        } catch (Exception $e) {
            echo "❌ Template check failed: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Twig service not available<br>";
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "❌ <strong>Fatal Error:</strong> " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p>Current working directory: " . getcwd() . "</p>";
echo "<p>This file location: " . __FILE__ . "</p>";
?>