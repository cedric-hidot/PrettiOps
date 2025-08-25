#!/usr/bin/env php
<?php

/**
 * Fix remaining namespace issues after DDD reorganization
 */

$projectRoot = dirname(__DIR__);

// Namespace mappings from old to new
$namespaceMappings = [
    'App\\Domain\\Snippet\\Entity\\Attachment' => 'App\\Attachment\\Domain\\Entity\\Attachment',
    'App\\Domain\\Snippet\\Entity\\Snippet' => 'App\\Snippet\\Domain\\Entity\\Snippet',
    'App\\Domain\\Snippet\\Entity\\Favorite' => 'App\\Snippet\\Domain\\Entity\\Favorite',
    'App\\Domain\\User\\Entity\\User' => 'App\\User\\Domain\\Entity\\User',
    'App\\Domain\\Share\\Entity\\Share' => 'App\\Sharing\\Domain\\Entity\\Share',
    'App\\Domain\\Snippet\\Repository\\AttachmentRepositoryInterface' => 'App\\Attachment\\Domain\\Repository\\AttachmentRepositoryInterface',
    'App\\Domain\\Snippet\\Repository\\SnippetRepositoryInterface' => 'App\\Snippet\\Domain\\Repository\\SnippetRepositoryInterface',
    'App\\Domain\\Snippet\\Repository\\FavoriteRepositoryInterface' => 'App\\Snippet\\Domain\\Repository\\FavoriteRepositoryInterface',
    'App\\Domain\\User\\Repository\\UserRepositoryInterface' => 'App\\User\\Domain\\Repository\\UserRepositoryInterface',
    'App\\Domain\\Share\\Repository\\ShareRepositoryInterface' => 'App\\Sharing\\Domain\\Repository\\ShareRepositoryInterface',
    'App\\Infrastructure\\Persistence\\Doctrine\\Repository\\AttachmentRepository' => 'App\\Attachment\\Infrastructure\\Persistence\\Doctrine\\AttachmentRepository',
    'App\\Infrastructure\\Persistence\\Doctrine\\Repository\\SnippetRepository' => 'App\\Snippet\\Infrastructure\\Persistence\\Doctrine\\SnippetRepository',
    'App\\Infrastructure\\Persistence\\Doctrine\\Repository\\FavoriteRepository' => 'App\\Snippet\\Infrastructure\\Persistence\\Doctrine\\FavoriteRepository',
    'App\\Infrastructure\\Persistence\\Doctrine\\Repository\\UserRepository' => 'App\\User\\Infrastructure\\Persistence\\Doctrine\\UserRepository',
    'App\\Infrastructure\\Persistence\\Doctrine\\Repository\\ShareRepository' => 'App\\Sharing\\Infrastructure\\Persistence\\Doctrine\\ShareRepository',
    'App\\Infrastructure\\Security\\SecurityService' => 'App\\User\\Infrastructure\\Security\\SecurityService',
    'App\\Infrastructure\\Security\\EncryptionService' => 'App\\Shared\\Infrastructure\\Security\\Encryption\\EncryptionService',
    'App\\Infrastructure\\Storage\\FileUploadService' => 'App\\Attachment\\Infrastructure\\Storage\\FileUploadService',
    'App\\Infrastructure\\Security\\TokenMaskingService' => 'App\\Shared\\Infrastructure\\Security\\TokenMaskingService',
    'App\\Infrastructure\\Security\\OAuth2\\JwtTokenService' => 'App\\User\\Infrastructure\\Security\\OAuth2\\JwtTokenService',
    'App\\Infrastructure\\Security\\OAuth2\\AppCustomAuthenticator' => 'App\\User\\Infrastructure\\Security\\OAuth2\\AppCustomAuthenticator',
    'App\\Infrastructure\\Security\\RgpdComplianceService' => 'App\\User\\Infrastructure\\Security\\RgpdComplianceService',
    'App\\Service\\EncryptionService' => 'App\\Shared\\Infrastructure\\Security\\Encryption\\EncryptionService',
    'App\\Service\\SecurityService' => 'App\\User\\Infrastructure\\Security\\SecurityService',
    'App\\Repository\\UserRepository' => 'App\\User\\Infrastructure\\Persistence\\Doctrine\\UserRepository',
    'App\\Repository\\SnippetRepository' => 'App\\Snippet\\Infrastructure\\Persistence\\Doctrine\\SnippetRepository',
    'App\\Repository\\AttachmentRepository' => 'App\\Attachment\\Infrastructure\\Persistence\\Doctrine\\AttachmentRepository',
    'App\\Repository\\ShareRepository' => 'App\\Sharing\\Infrastructure\\Persistence\\Doctrine\\ShareRepository',
    'App\\Repository\\FavoriteRepository' => 'App\\Snippet\\Infrastructure\\Persistence\\Doctrine\\FavoriteRepository',
];

function updateFile($filePath, $namespaceMappings) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    foreach ($namespaceMappings as $old => $new) {
        // Update use statements
        $content = str_replace('use ' . $old . ';', 'use ' . $new . ';', $content);
        $content = str_replace('use ' . $old . ' ', 'use ' . $new . ' ', $content);
        
        // Update fully qualified class names in code
        $content = str_replace('\\' . $old, '\\' . $new, $content);
        $content = str_replace('"' . $old . '"', '"' . $new . '"', $content);
        $content = str_replace("'" . $old . "'", "'" . $new . "'", $content);
    }
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return true;
    }
    
    return false;
}

// Find all PHP files in src directory
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot . '/src'),
    RecursiveIteratorIterator::SELF_FIRST
);

$updatedFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        if (updateFile($file->getPathname(), $namespaceMappings)) {
            $updatedFiles[] = str_replace($projectRoot . '/', '', $file->getPathname());
        }
    }
}

// Also update config files
$configFiles = [
    'config/services.yaml',
    'config/packages/security.yaml',
];

foreach ($configFiles as $configFile) {
    $fullPath = $projectRoot . '/' . $configFile;
    if (file_exists($fullPath)) {
        if (updateFile($fullPath, $namespaceMappings)) {
            $updatedFiles[] = $configFile;
        }
    }
}

echo "Updated " . count($updatedFiles) . " files:\n";
foreach ($updatedFiles as $file) {
    echo "  - $file\n";
}

echo "\nNamespace fixing complete!\n";