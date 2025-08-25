<?php

/**
 * Script to fix DDD namespaces and imports after refactoring
 */

$files = [
    // Fix UI Controllers
    'src/UI/Http/Web/Controller/*.php' => [
        'namespace' => 'App\UI\Http\Web\Controller',
        'replacements' => [
            'use App\Entity\\' => 'use App\Domain\User\Entity\\',
            'use App\Repository\\' => 'use App\Infrastructure\Persistence\Doctrine\Repository\\',
            'use App\Service\\' => 'use App\Infrastructure\Security\\',
        ]
    ],
    'src/UI/Http/Api/Controller/*.php' => [
        'namespace' => 'App\UI\Http\Api\Controller',
        'replacements' => [
            'use App\Entity\\' => 'use App\Domain\User\Entity\\',
            'use App\Repository\\' => 'use App\Infrastructure\Persistence\Doctrine\Repository\\',
            'use App\Service\\' => 'use App\Infrastructure\Security\\',
        ]
    ],
];

foreach ($files as $pattern => $config) {
    foreach (glob($pattern) as $file) {
        $content = file_get_contents($file);
        
        // Fix namespace
        $content = preg_replace(
            '/namespace App\\\\Controller\\\\[^;]+;/',
            'namespace ' . $config['namespace'] . ';',
            $content
        );
        
        // Fix imports
        foreach ($config['replacements'] as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        
        // Fix specific entity imports
        $content = str_replace('use App\Domain\User\Entity\User;', 'use App\Domain\User\Entity\User;', $content);
        $content = str_replace('use App\Domain\User\Entity\Snippet;', 'use App\Domain\Snippet\Entity\Snippet;', $content);
        $content = str_replace('use App\Domain\User\Entity\Share;', 'use App\Domain\Share\Entity\Share;', $content);
        $content = str_replace('use App\Domain\User\Entity\Attachment;', 'use App\Domain\Snippet\Entity\Attachment;', $content);
        $content = str_replace('use App\Domain\User\Entity\Favorite;', 'use App\Domain\Snippet\Entity\Favorite;', $content);
        
        file_put_contents($file, $content);
        echo "Fixed: $file\n";
    }
}

echo "Namespace fixing complete!\n";