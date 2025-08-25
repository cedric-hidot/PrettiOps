<?php

namespace App\Domain\Share\Repository;

use App\Domain\Share\Entity\Share;
use App\Domain\Snippet\Entity\Snippet;
use App\Domain\User\Entity\User;

/**
 * Share repository interface following DDD principles
 */
interface ShareRepositoryInterface
{
    
    public function findAll(): array;
    
    public function findByToken(string $token): ?Share;
    
    public function findByUser(User $user): array;
    
    public function findBySnippet(Snippet $snippet): array;
    
    public function findExpired(): array;
    
    public function findExpiringSoon(int $hours = 24): array;
    
    public function findByMaxViewsReached(): array;
    
    public function findActive(): array;
    
    public function findRevoked(): array;
    
    public function getStatistics(): array;
    
    public function cleanupExpired(): void;
    
    public function save(Share $share): void;
    
    public function delete(Share $share): void;
}