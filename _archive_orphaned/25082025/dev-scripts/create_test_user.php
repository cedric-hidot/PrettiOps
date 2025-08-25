<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Entity\User;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Doctrine\DBAL\DriverManager;

// Database configuration from container
$connectionParams = [
    'host' => 'database',
    'port' => 5432,
    'dbname' => 'app',
    'user' => 'app',
    'password' => '!ChangeMe!',
    'driver' => 'pdo_pgsql',
];

try {
    // Create a simple user directly with SQL
    $connection = DriverManager::getConnection($connectionParams);
    
    $sql = "INSERT INTO users (id, email, first_name, last_name, password_hash, timezone, locale, status, subscription_plan, monthly_snippet_limit, monthly_snippets_used, monthly_usage_reset_at, two_factor_enabled, failed_login_attempts, marketing_consent, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $uuid = Uuid::v7();
    $now = new DateTimeImmutable();
    
    $connection->executeStatement($sql, [
        $uuid->toString(),
        'test-direct@example.com',
        'Direct',
        'Test', 
        password_hash('TestPassword123!', PASSWORD_DEFAULT),
        'UTC',
        'en',
        'active',
        'freemium',
        10,
        0,
        $now->format('Y-m-d H:i:s'),
        0,  // two_factor_enabled as integer
        0,  // failed_login_attempts
        0,  // marketing_consent as integer
        $now->format('Y-m-d H:i:s'),
        $now->format('Y-m-d H:i:s')
    ]);

    echo "User created successfully with SQL!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}