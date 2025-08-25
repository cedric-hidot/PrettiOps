<?php

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Symfony\Component\Uid\Uuid;

echo "Testing simple user insertion...\n";

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
    echo "✅ Database connected\n";
    
    // Count users before
    $countBefore = $connection->executeQuery('SELECT COUNT(*) FROM users')->fetchOne();
    echo "👥 Users before: $countBefore\n";
    
    // Simple user insertion
    $uuid = Uuid::v7();
    $now = new DateTimeImmutable();
    $hashedPassword = password_hash('TestSimple123!', PASSWORD_BCRYPT, ['cost' => 13]);
    
    // Clean up first
    $connection->executeStatement("DELETE FROM users WHERE email = ?", ['test-simple@example.com']);
    
    // Insert with minimal required fields
    $sql = "INSERT INTO users (
        id, email, first_name, last_name, password_hash, 
        timezone, locale, status, subscription_plan, 
        monthly_snippet_limit, monthly_snippets_used, monthly_usage_reset_at,
        two_factor_enabled, failed_login_attempts, marketing_consent,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $result = $connection->executeStatement($sql, [
        $uuid->toString(),                      // id
        'test-simple@example.com',              // email
        'Simple',                               // first_name
        'Test',                                 // last_name
        $hashedPassword,                        // password_hash
        'UTC',                                  // timezone
        'en',                                   // locale
        'active',                               // status
        'freemium',                             // subscription_plan
        10,                                     // monthly_snippet_limit (int)
        0,                                      // monthly_snippets_used (int)
        $now->format('Y-m-d H:i:s'),           // monthly_usage_reset_at
        0,                                      // two_factor_enabled (boolean as 0/1)
        0,                                      // failed_login_attempts (int)
        0,                                      // marketing_consent (boolean as 0/1)
        $now->format('Y-m-d H:i:s'),           // created_at
        $now->format('Y-m-d H:i:s')            // updated_at
    ]);
    
    echo "📝 Insert result: $result row(s) affected\n";
    
    // Count users after
    $countAfter = $connection->executeQuery('SELECT COUNT(*) FROM users')->fetchOne();
    echo "👥 Users after: $countAfter\n";
    
    // Verify the user exists
    $user = $connection->executeQuery(
        'SELECT id, email, first_name, last_name FROM users WHERE email = ?', 
        ['test-simple@example.com']
    )->fetchAssociative();
    
    if ($user) {
        echo "✅ User found: " . $user['first_name'] . " " . $user['last_name'] . " (" . $user['email'] . ")\n";
        echo "🆔 User ID: " . $user['id'] . "\n";
    } else {
        echo "❌ User not found after insertion\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}