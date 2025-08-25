<?php

namespace App\Sharing\Infrastructure\Persistence\Doctrine;

use App\Sharing\Domain\Entity\Share;
use App\User\Domain\Entity\User;
use App\Snippet\Domain\Entity\Snippet;
use App\Sharing\Domain\Repository\ShareRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Share>
 */
class ShareRepository extends ServiceEntityRepository implements ShareRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Share::class);
    }

    /**
     * Find share by token
     */
    public function findOneByToken(string $token): ?Share
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.shareToken = :token')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find shares by user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.createdByUser = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active shares (not expired, not revoked)
     */
    public function findActiveShares(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.createdByUser = :user')
            ->andWhere('s.revokedAt IS NULL')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
            ->andWhere('s.maxViews IS NULL OR s.currentViews < s.maxViews')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expired shares that need cleanup
     */
    public function findExpiredShares(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.expiresAt IS NOT NULL')
            ->andWhere('s.expiresAt <= :now')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find shares that have reached their view limit
     */
    public function findSharesWithReachedViewLimit(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.maxViews IS NOT NULL')
            ->andWhere('s.currentViews >= s.maxViews')
            ->andWhere('s.revokedAt IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get global share statistics
     */
    public function getStatistics(): array
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id) as total')
            ->addSelect('COUNT(CASE WHEN s.revokedAt IS NULL THEN s.id END) as active')
            ->addSelect('COUNT(CASE WHEN s.revokedAt IS NOT NULL THEN s.id END) as revoked')
            ->addSelect('COUNT(CASE WHEN s.expiresAt IS NOT NULL AND s.expiresAt <= :now THEN s.id END) as expired')
            ->addSelect('COUNT(CASE WHEN s.shareType = :view THEN s.id END) as viewShares')
            ->addSelect('COUNT(CASE WHEN s.shareType = :edit THEN s.id END) as editShares')
            ->addSelect('COUNT(CASE WHEN s.shareType = :review THEN s.id END) as reviewShares')
            ->addSelect('COUNT(CASE WHEN s.requirePassword = true THEN s.id END) as passwordProtected')
            ->addSelect('SUM(s.currentViews) as totalViews')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('view', Share::TYPE_VIEW)
            ->setParameter('edit', Share::TYPE_EDIT)
            ->setParameter('review', Share::TYPE_REVIEW)
            ->getQuery()
            ->getSingleResult();
    }
    
    /**
     * Get share statistics for a user
     */
    public function getStatisticsForUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id) as total')
            ->addSelect('COUNT(CASE WHEN s.revokedAt IS NULL THEN s.id END) as active')
            ->addSelect('COUNT(CASE WHEN s.revokedAt IS NOT NULL THEN s.id END) as revoked')
            ->addSelect('COUNT(CASE WHEN s.expiresAt IS NOT NULL AND s.expiresAt <= :now THEN s.id END) as expired')
            ->addSelect('COUNT(CASE WHEN s.shareType = :view THEN s.id END) as viewShares')
            ->addSelect('COUNT(CASE WHEN s.shareType = :edit THEN s.id END) as editShares')
            ->addSelect('COUNT(CASE WHEN s.shareType = :review THEN s.id END) as reviewShares')
            ->addSelect('SUM(s.currentViews) as totalViews')
            ->andWhere('s.createdByUser = :user')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('view', Share::TYPE_VIEW)
            ->setParameter('edit', Share::TYPE_EDIT)
            ->setParameter('review', Share::TYPE_REVIEW)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Find most accessed shares
     */
    public function findMostAccessed(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.createdByUser = :user')
            ->andWhere('s.currentViews > 0')
            ->setParameter('user', $user)
            ->orderBy('s.currentViews', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently accessed shares
     */
    public function findRecentlyAccessed(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.createdByUser = :user')
            ->andWhere('s.lastAccessedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('s.lastAccessedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find shares by type
     */
    public function findByType(string $type, User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.shareType = :type')
            ->andWhere('s.createdByUser = :user')
            ->setParameter('type', $type)
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find password protected shares
     */
    public function findPasswordProtected(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.requirePassword = true')
            ->andWhere('s.createdByUser = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up expired shares (mark as revoked)
     */
    public function cleanupExpiredShares(): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.revokedAt', ':now')
            ->andWhere('s.expiresAt IS NOT NULL')
            ->andWhere('s.expiresAt <= :now')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Clean up shares that reached view limit
     */
    public function cleanupViewLimitReached(): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.revokedAt', ':now')
            ->andWhere('s.maxViews IS NOT NULL')
            ->andWhere('s.currentViews >= s.maxViews')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Find shares with email restrictions
     */
    public function findWithEmailRestrictions(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.createdByUser = :user')
            ->andWhere('s.allowedEmails IS NOT NULL OR s.allowedDomains IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find shares requiring authentication
     */
    public function findRequiringAuthentication(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.createdByUser = :user')
            ->andWhere('s.requireAuthentication = true')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get global share statistics (admin)
     */
    public function getGlobalStatistics(): array
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id) as total')
            ->addSelect('COUNT(CASE WHEN s.revokedAt IS NULL THEN s.id END) as active')
            ->addSelect('COUNT(CASE WHEN s.revokedAt IS NOT NULL THEN s.id END) as revoked')
            ->addSelect('COUNT(CASE WHEN s.expiresAt IS NOT NULL AND s.expiresAt <= :now THEN s.id END) as expired')
            ->addSelect('COUNT(CASE WHEN s.requirePassword = true THEN s.id END) as passwordProtected')
            ->addSelect('COUNT(CASE WHEN s.requireAuthentication = true THEN s.id END) as authRequired')
            ->addSelect('SUM(s.currentViews) as totalViews')
            ->addSelect('AVG(s.currentViews) as averageViews')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Find shares created in date range
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.createdAt >= :from')
            ->andWhere('s.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.createdAt', 'DESC');

        if ($user) {
            $qb->andWhere('s.createdByUser = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count active shares for a user
     */
    public function countActiveShares(User $user): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.createdByUser = :user')
            ->andWhere('s.revokedAt IS NULL')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Interface implementation methods
    public function findByToken(string $token): ?Share
    {
        return $this->findOneByToken($token);
    }

    public function findBySnippet(Snippet $snippet): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.snippet = :snippet')
            ->setParameter('snippet', $snippet)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExpired(): array
    {
        return $this->findExpiredShares();
    }

    public function findExpiringSoon(int $hours = 24): array
    {
        $threshold = new \DateTimeImmutable("+{$hours} hours");
        return $this->createQueryBuilder('s')
            ->andWhere('s.expiresAt IS NOT NULL')
            ->andWhere('s.expiresAt <= :threshold')
            ->andWhere('s.expiresAt > :now')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('threshold', $threshold)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function findByMaxViewsReached(): array
    {
        return $this->findSharesWithReachedViewLimit();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.revokedAt IS NULL')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
            ->andWhere('s.maxViews IS NULL OR s.currentViews < s.maxViews')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRevoked(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.revokedAt IS NOT NULL')
            ->orderBy('s.revokedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }


    public function cleanupExpired(): void
    {
        $this->cleanupExpiredShares();
        $this->cleanupViewLimitReached();
    }

    public function save(Share $share): void
    {
        $this->getEntityManager()->persist($share);
        $this->getEntityManager()->flush();
    }

    public function delete(Share $share): void
    {
        $this->getEntityManager()->remove($share);
        $this->getEntityManager()->flush();
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}