<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find a user by email
     */
    public function findOneByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find a user by username
     */
    public function findOneByUsername(string $username): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.username = :username')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find a user by OAuth provider and ID
     */
    public function findOneByOAuth(string $provider, string $oauthId): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.oauthProvider = :provider')
            ->andWhere('u.oauthId = :oauthId')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('provider', $provider)
            ->setParameter('oauthId', $oauthId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active users (not soft deleted)
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.status = :status')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('status', User::STATUS_ACTIVE)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users by subscription plan
     */
    public function findBySubscriptionPlan(string $plan): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.subscriptionPlan = :plan')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('plan', $plan)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users with expired subscriptions
     */
    public function findWithExpiredSubscriptions(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.subscriptionExpiresAt IS NOT NULL')
            ->andWhere('u.subscriptionExpiresAt <= :now')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users who need their monthly usage reset
     */
    public function findUsersNeedingUsageReset(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.monthlyUsageResetAt <= :now')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id) as total')
            ->addSelect('SUM(CASE WHEN u.status = :active THEN 1 ELSE 0 END) as active')
            ->addSelect('SUM(CASE WHEN u.subscriptionPlan = :freemium THEN 1 ELSE 0 END) as freemium')
            ->addSelect('SUM(CASE WHEN u.subscriptionPlan = :pro THEN 1 ELSE 0 END) as pro')
            ->addSelect('SUM(CASE WHEN u.subscriptionPlan = :team THEN 1 ELSE 0 END) as team')
            ->addSelect('SUM(CASE WHEN u.subscriptionPlan = :enterprise THEN 1 ELSE 0 END) as enterprise')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('active', User::STATUS_ACTIVE)
            ->setParameter('freemium', User::PLAN_FREEMIUM)
            ->setParameter('pro', User::PLAN_PRO)
            ->setParameter('team', User::PLAN_TEAM)
            ->setParameter('enterprise', User::PLAN_ENTERPRISE);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Find users with failed login attempts
     */
    public function findUsersWithFailedLogins(int $minAttempts = 5): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.failedLoginAttempts >= :minAttempts')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('minAttempts', $minAttempts)
            ->orderBy('u.failedLoginAttempts', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find locked users
     */
    public function findLockedUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.lockedUntil IS NOT NULL')
            ->andWhere('u.lockedUntil > :now')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('u.lockedUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users for GDPR data retention cleanup
     */
    public function findUsersForDataRetentionCleanup(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.dataRetentionExpiresAt IS NOT NULL')
            ->andWhere('u.dataRetentionExpiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recently registered users (last 30 days)
     */
    public function getRecentlyRegistered(int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");
        
        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :since')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('since', $since)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search users by email or username
     */
    public function searchByEmailOrUsername(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('(u.email ILIKE :query OR u.username ILIKE :query)')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Reset monthly usage for a user
     */
    public function resetMonthlyUsage(User $user): void
    {
        $user->setMonthlySnippetsUsed(0);
        $user->setMonthlyUsageResetAt(new \DateTimeImmutable('+1 month'));
        
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Soft delete a user (GDPR compliant)
     */
    public function softDelete(User $user): void
    {
        $user->setDeletedAt(new \DateTimeImmutable());
        $user->setStatus(User::STATUS_DELETED);
        
        // Anonymize sensitive data
        $user->setEmail('deleted_' . $user->getId() . '@deleted.local');
        $user->setPassword(null);
        $user->setFirstName(null);
        $user->setLastName(null);
        $user->setOauthProvider(null);
        $user->setOauthId(null);
        $user->setOauthData(null);
        $user->setTwoFactorSecret(null);
        $user->setBackupCodes(null);
        
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Get users with 2FA enabled
     */
    public function findUsersWithTwoFactor(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.twoFactorEnabled = true')
            ->andWhere('u.deletedAt IS NULL')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get users by timezone
     */
    public function findByTimezone(string $timezone): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.timezone = :timezone')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('timezone', $timezone)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get premium users (non-freemium)
     */
    public function findPremiumUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.subscriptionPlan != :freemium')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('freemium', User::PLAN_FREEMIUM)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}