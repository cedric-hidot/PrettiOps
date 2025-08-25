<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Entity\User;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Uid\Uuid;

echo "Testing database connection and persistence...\n";

// Database connection parameters matching the app
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
    echo "✅ Database connection successful\n";
    
    // Test basic query
    $result = $connection->executeQuery('SELECT NOW()');
    $time = $result->fetchOne();
    echo "✅ Database time: $time\n";
    
    // Check if users table exists
    $tables = $connection->createSchemaManager()->listTableNames();
    if (in_array('users', $tables)) {
        echo "✅ Users table exists\n";
        
        // Show table structure
        $columns = $connection->createSchemaManager()->listTableColumns('users');
        echo "📋 Users table columns:\n";
        foreach ($columns as $column) {
            echo "  - " . $column->getName() . " (" . $column->getType() . ")\n";
        }
        
        // Count existing users
        $userCount = $connection->executeQuery('SELECT COUNT(*) FROM users')->fetchOne();
        echo "👥 Current user count: $userCount\n";
        
        // Test inserting a user directly
        echo "\n🧪 Testing direct user insertion...\n";
        
        $uuid = Uuid::v7();
        $now = new DateTimeImmutable();
        $hashedPassword = password_hash('TestDirectInsert123!', PASSWORD_BCRYPT, ['cost' => 13]);
        
        // Clean up any existing test user
        $connection->executeStatement("DELETE FROM users WHERE email = ?", ['test-direct-persistence@example.com']);
        
        // Insert new test user
        $sql = "INSERT INTO users (id, email, first_name, last_name, password_hash, timezone, locale, status, subscription_plan, monthly_snippet_limit, monthly_snippets_used, monthly_usage_reset_at, two_factor_enabled, failed_login_attempts, marketing_consent, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $result = $connection->executeStatement($sql, [
            $uuid->toString(),
            'test-direct-persistence@example.com',
            'Direct',
            'Test',
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
        
        echo "📝 Insert result: $result row(s) affected\n";
        
        // Verify insertion
        $insertedUser = $connection->executeQuery(
            'SELECT email, first_name, last_name FROM users WHERE email = ?', 
            ['test-direct-persistence@example.com']
        )->fetchAssociative();
        
        if ($insertedUser) {
            echo "✅ User successfully inserted and verified: " . $insertedUser['first_name'] . " " . $insertedUser['last_name'] . " (" . $insertedUser['email'] . ")\n";
        } else {
            echo "❌ User insertion failed - not found after insert\n";
        }
        
    } else {
        echo "❌ Users table does not exist\n";
        echo "Available tables: " . implode(', ', $tables) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}