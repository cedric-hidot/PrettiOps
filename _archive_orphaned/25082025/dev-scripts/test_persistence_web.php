<?php

// Simple test to replicate the exact registration flow
require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', ($_SERVER['APP_DEBUG'] ?? '1') === '1');
$kernel->boot();
$container = $kernel->getContainer();

echo "Testing Symfony ORM persistence...\n";

$entityManager = $container->get('doctrine.orm.entity_manager');
$userRepository = $entityManager->getRepository(User::class);

try {
    // Create user the exact same way as in AuthController
    $user = new User();
    $user->setEmail('test-symfony-orm@example.com');
    $user->setFirstName('Symfony');
    $user->setLastName('ORM');
    
    $hashedPassword = password_hash('TestSymfonyORM123!', PASSWORD_BCRYPT, ['cost' => 13]);
    $user->setPassword($hashedPassword);
    
    $user->setTimezone('UTC');
    $user->setLocale('en');
    $user->setStatus(User::STATUS_ACTIVE);
    $user->setSubscriptionPlan(User::PLAN_FREEMIUM);
    $user->setMonthlySnippetLimit(10);
    $user->setMonthlySnippetsUsed(0);
    $user->setMonthlyUsageResetAt(new \DateTimeImmutable());
    $user->setTwoFactorEnabled(false);
    $user->setFailedLoginAttempts(0);
    $user->setMarketingConsent(false);
    
    echo "User created with email: " . $user->getEmail() . "\n";
    echo "User ID: " . $user->getId()->toString() . "\n";
    
    // Clean up any existing user first
    $existing = $userRepository->findOneBy(['email' => $user->getEmail()]);
    if ($existing) {
        $entityManager->remove($existing);
        $entityManager->flush();
        echo "Removed existing user\n";
    }
    
    // Persist and flush
    $entityManager->persist($user);
    echo "User persisted to EntityManager\n";
    
    $entityManager->flush();
    echo "EntityManager flushed\n";
    
    // Check if user exists with direct SQL
    $connection = $entityManager->getConnection();
    $count = $connection->executeQuery(
        'SELECT COUNT(*) FROM users WHERE email = ?', 
        [$user->getEmail()]
    )->fetchOne();
    
    echo "User count in database: $count\n";
    
    if ($count > 0) {
        echo "✅ SUCCESS: User persisted correctly\n";
        
        // Get the user data
        $userData = $connection->executeQuery(
            'SELECT id, email, first_name, last_name FROM users WHERE email = ?', 
            [$user->getEmail()]
        )->fetchAssociative();
        
        echo "User data: " . json_encode($userData) . "\n";
    } else {
        echo "❌ FAILED: User not found in database\n";
        
        // Debug entity state
        $unitOfWork = $entityManager->getUnitOfWork();
        echo "Entity state: " . $unitOfWork->getEntityState($user) . "\n";
        echo "Entity changeset: " . json_encode($unitOfWork->getEntityChangeSet($user)) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}