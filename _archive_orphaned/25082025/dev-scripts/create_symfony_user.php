<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Entity\User;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Doctrine\DBAL\DriverManager;

// Simple approach: use SQL with the expected password hash format
$connectionParams = [
    'host' => 'database',
    'port' => 5432,
    'dbname' => 'app', 
    'user' => 'app',
    'password' => '!ChangeMe!',
    'driver' => 'pdo_pgsql',
];

try {
    $connection = DriverManager::getConnection($connectionParams);
    
    // Delete the old test user first
    $connection->executeStatement("DELETE FROM users WHERE email = 'test-direct@example.com'");
    
    // Create user with standard bcrypt hash that Symfony expects
    // TestPassword123! hashed with bcrypt (Symfony default)
    $hashedPassword = '$2y$13$UfuW8bMOZsHJQq.j.vTn7.H/qqq5vTD6G5VQ5vSfV5T2nXWvjZx8q'; // This is TestPassword123! hashed
    
    // Or generate it properly using password_hash with bcrypt
    $hashedPassword = password_hash('TestPassword123!', PASSWORD_BCRYPT, ['cost' => 13]);
    
    $sql = "INSERT INTO users (id, email, first_name, last_name, password_hash, timezone, locale, status, subscription_plan, monthly_snippet_limit, monthly_snippets_used, monthly_usage_reset_at, two_factor_enabled, failed_login_attempts, marketing_consent, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $uuid = \Symfony\Component\Uid\Uuid::v7();
    $now = new DateTimeImmutable();
    
    $connection->executeStatement($sql, [
        $uuid->toString(),
        'test-symfony@example.com',
        'Symfony',
        'User',
        $hashedPassword,
        'UTC',
        'en',
        'active',
        'freemium',
        10,
        0,
        $now->format('Y-m-d H:i:s'),
        0,
        0,
        0,
        $now->format('Y-m-d H:i:s'),
        $now->format('Y-m-d H:i:s')
    ]);

    echo "Symfony user created successfully!\n";
    echo "Email: test-symfony@example.com\n";
    echo "Password: TestPassword123!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}