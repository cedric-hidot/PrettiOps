<?php

namespace App\Attachment\Domain\Repository;

use App\Attachment\Domain\Entity\Attachment;
use App\Snippet\Domain\Entity\Snippet;
use App\User\Domain\Entity\User;

/**
 * Attachment repository interface following DDD principles
 */
interface AttachmentRepositoryInterface
{
    
    public function findAll(): array;
    
    public function findByUser(User $user): array;
    
    public function findBySnippet(Snippet $snippet): array;
    
    public function findByFileHash(string $hash): ?Attachment;
    
    public function findPendingVirusScan(): array;
    
    public function findInfected(): array;
    
    public function findLargeFiles(int $sizeLimit): array;
    
    public function findOrphaned(): array;
    
    public function getTotalStorageUsedByUser(User $user): int;
    
    public function getStatistics(): array;
    
    public function cleanupDeleted(): void;
    
    public function save(Attachment $attachment): void;
    
    public function delete(Attachment $attachment): void;
}