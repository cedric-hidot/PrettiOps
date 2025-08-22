<?php

namespace App\Repository;

use App\Entity\Attachment;
use App\Entity\Snippet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attachment>
 */
class AttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }

    /**
     * Find attachments by user
     */
    public function findByUser(User $user, bool $includeDeleted = false): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC');

        if (!$includeDeleted) {
            $qb->andWhere('a.deletedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find attachments by snippet
     */
    public function findBySnippet(Snippet $snippet, bool $includeDeleted = false): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.snippet = :snippet')
            ->setParameter('snippet', $snippet)
            ->orderBy('a.createdAt', 'DESC');

        if (!$includeDeleted) {
            $qb->andWhere('a.deletedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find attachment by stored filename
     */
    public function findOneByStoredFilename(string $filename): ?Attachment
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.storedFilename = :filename')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('filename', $filename)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find attachment by file hash (for deduplication)
     */
    public function findByFileHash(string $hash, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.fileHash = :hash')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('hash', $hash)
            ->orderBy('a.createdAt', 'ASC');

        if ($user) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find attachments pending virus scan
     */
    public function findPendingVirusScan(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.virusScanStatus = :status')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('status', Attachment::VIRUS_STATUS_PENDING)
            ->orderBy('a.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find infected attachments
     */
    public function findInfectedAttachments(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.virusScanStatus = :status')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('status', Attachment::VIRUS_STATUS_INFECTED)
            ->orderBy('a.virusScanAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get attachment statistics for a user
     */
    public function getStatistics(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id) as total')
            ->addSelect('COUNT(CASE WHEN a.virusScanStatus = :clean THEN a.id END) as clean')
            ->addSelect('COUNT(CASE WHEN a.virusScanStatus = :infected THEN a.id END) as infected')
            ->addSelect('COUNT(CASE WHEN a.virusScanStatus = :pending THEN a.id END) as pending')
            ->addSelect('COUNT(CASE WHEN a.isEncrypted = true THEN a.id END) as encrypted')
            ->addSelect('SUM(a.fileSize) as totalSize')
            ->addSelect('SUM(a.downloadCount) as totalDownloads')
            ->andWhere('a.user = :user')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('clean', Attachment::VIRUS_STATUS_CLEAN)
            ->setParameter('infected', Attachment::VIRUS_STATUS_INFECTED)
            ->setParameter('pending', Attachment::VIRUS_STATUS_PENDING)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Find most downloaded attachments
     */
    public function findMostDownloaded(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.downloadCount > 0')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('a.downloadCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently uploaded attachments
     */
    public function findRecentlyUploaded(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find large attachments (over specified size)
     */
    public function findLargeAttachments(int $sizeThreshold, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.fileSize >= :size')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('size', $sizeThreshold)
            ->orderBy('a.fileSize', 'DESC');

        if ($user) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find attachments by mime type
     */
    public function findByMimeType(string $mimeType, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.mimeType = :mimeType')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('mimeType', $mimeType)
            ->orderBy('a.createdAt', 'DESC');

        if ($user) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get file type statistics
     */
    public function getFileTypeStatistics(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.mimeType')
            ->addSelect('COUNT(a.id) as count')
            ->addSelect('SUM(a.fileSize) as totalSize')
            ->addSelect('SUM(a.downloadCount) as totalDownloads')
            ->andWhere('a.deletedAt IS NULL')
            ->groupBy('a.mimeType')
            ->orderBy('count', 'DESC');

        if ($user) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Calculate total storage used by user
     */
    public function getTotalStorageUsed(User $user): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('SUM(a.fileSize) as totalSize')
            ->andWhere('a.user = :user')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return (int) ($result['totalSize'] ?? 0);
    }

    /**
     * Find old attachments that may need cleanup
     */
    public function findOldAttachments(int $days = 365): array
    {
        $threshold = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('a')
            ->andWhere('a.createdAt < :threshold')
            ->andWhere('a.lastDownloadedAt IS NULL OR a.lastDownloadedAt < :threshold')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('threshold', $threshold)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find encrypted attachments
     */
    public function findEncrypted(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.isEncrypted = true')
            ->andWhere('a.deletedAt IS NULL')
            ->orderBy('a.createdAt', 'DESC');

        if ($user) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find attachments with scan errors
     */
    public function findWithScanErrors(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.virusScanStatus = :status')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('status', Attachment::VIRUS_STATUS_ERROR)
            ->orderBy('a.virusScanAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Soft delete an attachment
     */
    public function softDelete(Attachment $attachment): void
    {
        $attachment->setDeletedAt(new \DateTimeImmutable());
        
        $this->getEntityManager()->persist($attachment);
        $this->getEntityManager()->flush();
    }

    /**
     * Hard delete old soft-deleted attachments
     */
    public function hardDeleteOldSoftDeleted(int $days = 30): int
    {
        $threshold = new \DateTimeImmutable("-{$days} days");

        $attachments = $this->createQueryBuilder('a')
            ->andWhere('a.deletedAt IS NOT NULL')
            ->andWhere('a.deletedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        $count = count($attachments);

        foreach ($attachments as $attachment) {
            $this->getEntityManager()->remove($attachment);
        }

        $this->getEntityManager()->flush();

        return $count;
    }

    /**
     * Get storage usage by month for user
     */
    public function getMonthlyStorageUsage(User $user, int $months = 12): array
    {
        $result = $this->createQueryBuilder('a')
            ->select("DATE_TRUNC('month', a.createdAt) as month")
            ->addSelect('SUM(a.fileSize) as totalSize')
            ->addSelect('COUNT(a.id) as count')
            ->andWhere('a.user = :user')
            ->andWhere('a.createdAt >= :since')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('since', new \DateTimeImmutable("-{$months} months"))
            ->groupBy('month')
            ->orderBy('month', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}