#!/usr/bin/env php
<?php

/**
 * Script to reorganize PrettiOps to clean DDD architecture
 * Based on PRETTIOPS_DDD_ARBO.md
 */

$projectRoot = dirname(__DIR__);

// Color codes for output
$GREEN = "\033[0;32m";
$YELLOW = "\033[1;33m";
$RED = "\033[0;31m";
$NC = "\033[0m"; // No Color

function createDirectory($path) {
    global $GREEN;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        echo "{$GREEN}Created directory: $path{$NC}\n";
    }
}

function moveFile($from, $to) {
    global $YELLOW, $RED, $NC;
    if (file_exists($from)) {
        $toDir = dirname($to);
        if (!is_dir($toDir)) {
            mkdir($toDir, 0755, true);
        }
        
        if (rename($from, $to)) {
            echo "{$YELLOW}Moved: $from -> $to{$NC}\n";
            return true;
        } else {
            echo "{$RED}Failed to move: $from{$NC}\n";
            return false;
        }
    }
    return false;
}

function updateNamespace($file, $oldNamespace, $newNamespace) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $content = str_replace($oldNamespace, $newNamespace, $content);
        file_put_contents($file, $content);
    }
}

// Create main directory structure
echo "\n{$GREEN}=== Creating Clean DDD Structure ==={$NC}\n\n";

// Create Shared kernel structure
$sharedDirs = [
    'src/Shared/Domain/Event',
    'src/Shared/Domain/ValueObject',
    'src/Shared/Domain/Specification',
    'src/Shared/Domain/Policy',
    'src/Shared/Domain/Exception',
    'src/Shared/Application/Bus',
    'src/Shared/Application/DTO',
    'src/Shared/Infrastructure/Bus',
    'src/Shared/Infrastructure/Persistence/Doctrine/Type',
    'src/Shared/Infrastructure/Security/Encryption',
    'src/Shared/Infrastructure/Serialization',
    'src/Shared/Infrastructure/Http',
    'src/Shared/UI/Twig',
    'src/Shared/UI/Request',
    'src/Shared/UI/Response',
];

foreach ($sharedDirs as $dir) {
    createDirectory($projectRoot . '/' . $dir);
}

// File mappings for reorganization
$mappings = [
    // === SHARED KERNEL ===
    // Move common value objects to Shared
    'src/Domain/Common/ValueObject/Email.php' => 'src/Shared/Domain/ValueObject/Email.php',
    'src/Domain/Common/ValueObject/Uuid.php' => 'src/Shared/Domain/ValueObject/Uuid.php',
    'src/Domain/Common/ValueObject/DateTimeUtc.php' => 'src/Shared/Domain/ValueObject/DateTimeUtc.php',
    
    // Move encryption services to Shared
    'src/Infrastructure/Security/EncryptionService.php' => 'src/Shared/Infrastructure/Security/Encryption/EncryptionService.php',
    
    // === USER BOUNDED CONTEXT ===
    'src/Domain/User/Entity/User.php' => 'src/User/Domain/Entity/User.php',
    'src/Domain/User/Entity/Team.php' => 'src/User/Domain/Entity/Team.php',
    'src/Domain/User/Repository/UserRepositoryInterface.php' => 'src/User/Domain/Repository/UserRepositoryInterface.php',
    'src/Domain/User/Repository/TeamRepositoryInterface.php' => 'src/User/Domain/Repository/TeamRepositoryInterface.php',
    'src/Infrastructure/Persistence/Doctrine/Repository/UserRepository.php' => 'src/User/Infrastructure/Persistence/Doctrine/UserRepository.php',
    'src/Infrastructure/Persistence/Doctrine/Repository/TeamRepository.php' => 'src/User/Infrastructure/Persistence/Doctrine/TeamRepository.php',
    'src/Infrastructure/Security/SecurityService.php' => 'src/User/Infrastructure/Security/SecurityService.php',
    'src/UI/Http/Web/Controller/AuthController.php' => 'src/User/UI/Http/Controller/AuthController.php',
    'src/UI/Http/Api/Controller/UserController.php' => 'src/User/UI/Http/Controller/Api/UserController.php',
    
    // === SNIPPET BOUNDED CONTEXT ===
    'src/Domain/Snippet/Entity/Snippet.php' => 'src/Snippet/Domain/Entity/Snippet.php',
    'src/Domain/Snippet/Entity/SnippetVersion.php' => 'src/Snippet/Domain/Entity/SnippetVersion.php',
    'src/Domain/Snippet/Entity/Language.php' => 'src/Snippet/Domain/ValueObject/Language.php',
    'src/Domain/Snippet/Entity/Theme.php' => 'src/Snippet/Domain/ValueObject/Theme.php',
    'src/Domain/Snippet/Repository/SnippetRepositoryInterface.php' => 'src/Snippet/Domain/Repository/SnippetRepositoryInterface.php',
    'src/Infrastructure/Persistence/Doctrine/Repository/SnippetRepository.php' => 'src/Snippet/Infrastructure/Persistence/Doctrine/SnippetRepository.php',
    'src/Application/UseCase/Snippet/CreateSnippetHandler.php' => 'src/Snippet/Application/CommandHandler/CreateSnippetHandler.php',
    'src/UI/Http/Api/Controller/SnippetController.php' => 'src/Snippet/UI/Http/Controller/SnippetController.php',
    
    // === TOKEN BOUNDED CONTEXT (new - extract from existing) ===
    // Will need to extract token-related functionality into its own BC
    
    // === ATTACHMENT BOUNDED CONTEXT ===
    'src/Domain/Snippet/Entity/Attachment.php' => 'src/Attachment/Domain/Entity/Attachment.php',
    'src/Domain/Snippet/Repository/AttachmentRepositoryInterface.php' => 'src/Attachment/Domain/Repository/AttachmentRepositoryInterface.php',
    'src/Infrastructure/Persistence/Doctrine/Repository/AttachmentRepository.php' => 'src/Attachment/Infrastructure/Persistence/Doctrine/AttachmentRepository.php',
    'src/Infrastructure/Storage/FileUploadService.php' => 'src/Attachment/Infrastructure/Storage/FileUploadService.php',
    'src/UI/Http/Api/Controller/FileController.php' => 'src/Attachment/UI/Http/Controller/FileController.php',
    
    // === SHARING BOUNDED CONTEXT ===
    'src/Domain/Snippet/Entity/Share.php' => 'src/Sharing/Domain/Entity/ShareLink.php',
    'src/Domain/Snippet/Repository/ShareRepositoryInterface.php' => 'src/Sharing/Domain/Repository/ShareRepositoryInterface.php',
    'src/Infrastructure/Persistence/Doctrine/Repository/ShareRepository.php' => 'src/Sharing/Infrastructure/Persistence/Doctrine/ShareRepository.php',
    'src/UI/Http/Web/Controller/ShareController.php' => 'src/Sharing/UI/Http/Controller/ShareController.php',
    
    // === INTEGRATION BOUNDED CONTEXT ===
    'src/Domain/Integration/Entity/Integration.php' => 'src/Integration/Domain/Entity/Integration.php',
    'src/Domain/Integration/Repository/IntegrationRepositoryInterface.php' => 'src/Integration/Domain/Repository/IntegrationRepositoryInterface.php',
    'src/Infrastructure/Persistence/Doctrine/Repository/IntegrationRepository.php' => 'src/Integration/Infrastructure/Persistence/Doctrine/IntegrationRepository.php',
    
    // === COLLABORATION BOUNDED CONTEXT ===
    'src/Domain/Snippet/Entity/Review.php' => 'src/Collaboration/Domain/Entity/Review.php',
    'src/Domain/Snippet/Entity/Comment.php' => 'src/Collaboration/Domain/Entity/Comment.php',
];

// === FRONTEND REORGANIZATION ===
$frontendMappings = [
    'assets/controllers/dashboard_controller.js' => 'assets/js/pages/dashboard.ts',
    'assets/controllers/mega_menu_controller.js' => 'assets/js/components/mega-menu.ts',
    'assets/controllers/toolbar_controller.js' => 'assets/js/components/toolbar.ts',
    'assets/controllers/codemirror_editor_controller.js' => 'assets/js/editors/codemirror-wrapper.ts',
    'assets/js/editor.js' => 'assets/js/features/snippet/editor.ts',
];

// === SCRIPTS REORGANIZATION ===
$scriptsMappings = [
    'create_symfony_user.php' => 'scripts/db/create_user.php',
    'generate_jwt_keys.php' => 'scripts/security/generate_jwt_keys.php',
    'test_database_persistence.php' => 'scripts/db/test_persistence.php',
];

// === DOCS CREATION ===
$docsDirs = [
    'docs/adr',
    'docs/api',
    'docs/guides',
    'docs/changelog',
];

foreach ($docsDirs as $dir) {
    createDirectory($projectRoot . '/' . $dir);
}

// === OPS REORGANIZATION ===
$opsDirs = [
    'ops/platform',
    'ops/n8n',
];

foreach ($opsDirs as $dir) {
    createDirectory($projectRoot . '/' . $dir);
}

// === TESTS REORGANIZATION ===
$testsDirs = [
    'tests/Unit/Shared',
    'tests/Unit/User/Domain',
    'tests/Unit/Snippet/Domain',
    'tests/Unit/Attachment/Domain',
    'tests/Application/User/Application',
    'tests/Application/Snippet/Application',
    'tests/Integration/User/Infrastructure',
    'tests/Integration/Snippet/Infrastructure',
    'tests/E2E/Smoke',
];

foreach ($testsDirs as $dir) {
    createDirectory($projectRoot . '/' . $dir);
}

// Create archive directory for orphaned files
$archiveDir = $projectRoot . '/_archive_orphaned/' . date('dmY');
createDirectory($archiveDir);

// Execute file movements
echo "\n{$GREEN}=== Moving Files to Clean DDD Structure ==={$NC}\n\n";

foreach ($mappings as $from => $to) {
    $fromPath = $projectRoot . '/' . $from;
    $toPath = $projectRoot . '/' . $to;
    
    if (file_exists($fromPath)) {
        moveFile($fromPath, $toPath);
        
        // Update namespace in the moved file
        $oldNamespace = str_replace('/', '\\', dirname($from));
        $oldNamespace = str_replace('src', 'App', $oldNamespace);
        $newNamespace = str_replace('/', '\\', dirname($to));
        $newNamespace = str_replace('src', 'App', $newNamespace);
        
        updateNamespace($toPath, $oldNamespace, $newNamespace);
    }
}

// Move frontend files
echo "\n{$GREEN}=== Reorganizing Frontend Assets ==={$NC}\n\n";

foreach ($frontendMappings as $from => $to) {
    $fromPath = $projectRoot . '/' . $from;
    $toPath = $projectRoot . '/' . $to;
    moveFile($fromPath, $toPath);
}

// Move script files
echo "\n{$GREEN}=== Reorganizing Scripts ==={$NC}\n\n";

foreach ($scriptsMappings as $from => $to) {
    $fromPath = $projectRoot . '/' . $from;
    $toPath = $projectRoot . '/' . $to;
    moveFile($fromPath, $toPath);
}

// Create Token bounded context (new)
echo "\n{$GREEN}=== Creating Token Bounded Context ==={$NC}\n\n";

$tokenDirs = [
    'src/Token/Domain/Entity',
    'src/Token/Domain/ValueObject',
    'src/Token/Domain/Repository',
    'src/Token/Domain/Service',
    'src/Token/Domain/Event',
    'src/Token/Application/Command',
    'src/Token/Application/CommandHandler',
    'src/Token/Application/Query',
    'src/Token/Application/QueryHandler',
    'src/Token/Application/DTO',
    'src/Token/Infrastructure/Security/Encryption',
    'src/Token/Infrastructure/Persistence/Doctrine',
    'src/Token/UI/Http/Controller',
];

foreach ($tokenDirs as $dir) {
    createDirectory($projectRoot . '/' . $dir);
}

// Create Billing bounded context structure
echo "\n{$GREEN}=== Creating Billing Bounded Context ==={$NC}\n\n";

$billingDirs = [
    'src/Billing/Domain/Entity',
    'src/Billing/Domain/ValueObject',
    'src/Billing/Domain/Repository',
    'src/Billing/Domain/Event',
    'src/Billing/Application/Command',
    'src/Billing/Application/Query',
    'src/Billing/Infrastructure/Provider',
    'src/Billing/Infrastructure/Persistence/Doctrine',
    'src/Billing/UI/Http/Controller',
];

foreach ($billingDirs as $dir) {
    createDirectory($projectRoot . '/' . $dir);
}

// Create Analytics bounded context structure
echo "\n{$GREEN}=== Creating Analytics Bounded Context ==={$NC}\n\n";

$analyticsDirs = [
    'src/Analytics/Domain/Entity',
    'src/Analytics/Domain/ValueObject',
    'src/Analytics/Domain/Repository',
    'src/Analytics/Application/Query',
    'src/Analytics/Infrastructure/Persistence/Doctrine',
    'src/Analytics/UI/Http/Controller',
];

foreach ($analyticsDirs as $dir) {
    createDirectory($projectRoot . '/' . $dir);
}

// Clean up empty old directories
echo "\n{$GREEN}=== Cleaning Up Empty Directories ==={$NC}\n\n";

$oldDirs = [
    'src/Domain/Common',
    'src/Domain/User',
    'src/Domain/Snippet',
    'src/Domain/Integration',
    'src/Application/UseCase',
    'src/Infrastructure/Persistence/Doctrine/Repository',
    'src/UI/Http/Web/Controller',
    'src/UI/Http/Api/Controller',
];

foreach ($oldDirs as $dir) {
    $path = $projectRoot . '/' . $dir;
    if (is_dir($path) && count(scandir($path)) == 2) { // Only . and ..
        rmdir($path);
        echo "{$YELLOW}Removed empty directory: $path{$NC}\n";
    }
}

echo "\n{$GREEN}=== DDD Reorganization Complete! ==={$NC}\n\n";
echo "Next steps:\n";
echo "1. Update config/packages/doctrine.yaml with new entity mappings\n";
echo "2. Update config/services.yaml with new service paths\n";
echo "3. Run 'composer dump-autoload -o' to update autoloading\n";
echo "4. Run 'php bin/console cache:clear' to clear cache\n";
echo "5. Test all functionality\n";