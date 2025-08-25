<?php

namespace App\Domain\Snippet\Repository;

use App\Domain\Snippet\Entity\Snippet;
use App\Domain\User\Entity\User;

/**
 * Snippet repository interface following DDD principles
 */
interface SnippetRepositoryInterface
{
    
    public function findAll(): array;
    
    public function findByUser(User $user): array;
    
    public function findPublic(): array;
    
    public function findByLanguage(string $language): array;
    
    public function findByVisibility(string $visibility): array;
    
    public function findExpired(): array;
    
    public function findWithSensitiveData(): array;
    
    public function findByContentHash(string $hash): ?Snippet;
    
    public function searchByContent(string $query, int $limit = 20): array;
    
    public function findPopular(int $limit = 10): array;
    
    public function findRecent(int $limit = 10): array;
    
    public function findForksOf(Snippet $snippet): array;
    
    public function findVersionsOf(Snippet $snippet): array;
    
    public function getStatistics(): array;
    
    public function cleanupExpired(): void;
    
    public function save(Snippet $snippet): void;
    
    public function delete(Snippet $snippet): void;
}