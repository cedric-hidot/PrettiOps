<?php

namespace App\User\Domain\Repository;

use App\User\Domain\Entity\User;

/**
 * User repository interface following DDD principles
 */
interface UserRepositoryInterface
{
    
    public function findAll(): array;
    
    public function findOneByEmail(string $email): ?User;
    
    public function findOneByUsername(string $username): ?User;
    
    public function findOneByOAuth(string $provider, string $oauthId): ?User;
    
    public function findActive(): array;
    
    public function findBySubscriptionPlan(string $plan): array;
    
    public function findWithExpiredSubscriptions(): array;
    
    public function findUsersNeedingUsageReset(): array;
    
    public function getStatistics(): array;
    
    public function findUsersWithFailedLogins(int $minAttempts = 5): array;
    
    public function findLockedUsers(): array;
    
    public function findUsersForDataRetentionCleanup(): array;
    
    public function getRecentlyRegistered(int $days = 30): array;
    
    public function searchByEmailOrUsername(string $query, int $limit = 20): array;
    
    public function resetMonthlyUsage(User $user): void;
    
    public function softDelete(User $user): void;
    
    public function findUsersWithTwoFactor(): array;
    
    public function findByTimezone(string $timezone): array;
    
    public function findPremiumUsers(): array;
    
    public function save(User $user): void;
    
    public function delete(User $user): void;
}