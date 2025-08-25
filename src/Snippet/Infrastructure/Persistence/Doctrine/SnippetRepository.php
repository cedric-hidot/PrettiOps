<?php

namespace App\Snippet\Infrastructure\Persistence\Doctrine;

use App\Snippet\Domain\Entity\Snippet;
use App\User\Domain\Entity\User;
use App\Snippet\Domain\Repository\SnippetRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Snippet>
 */
class SnippetRepository extends ServiceEntityRepository implements SnippetRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Snippet::class);
    }

    /**
     * Find snippets by user
     */
    public function findByUser(User $user, bool $includeDeleted = false): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.updatedAt', 'DESC');

        if (!$includeDeleted) {
            $qb->andWhere('s.deletedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find public snippets
     */
    public function findPublicSnippets(int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.visibility = :visibility')
            ->andWhere('s.allowPublicIndexing = true')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.autoExpireAt IS NULL OR s.autoExpireAt > :now')
            ->setParameter('visibility', Snippet::VISIBILITY_PUBLIC)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find snippets by language
     */
    public function findByLanguage(string $language, ?User $user = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.language = :language')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('language', $language)
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->andWhere('(s.visibility = :public OR s.user = :user)')
                ->setParameter('public', Snippet::VISIBILITY_PUBLIC)
                ->setParameter('user', $user);
        } else {
            $qb->andWhere('s.visibility = :public')
                ->andWhere('s.allowPublicIndexing = true')
                ->setParameter('public', Snippet::VISIBILITY_PUBLIC);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search snippets using optimized full-text search
     */
    public function search(string $query, ?User $user = null, array $languages = [], int $limit = 50, int $offset = 0): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT * FROM optimized_snippet_search(
                :search_query,
                :user_uuid,
                :language_filter,
                :tags_filter,
                NULL, -- visibility_filter
                :limit_count,
                :offset_count
            )
        ';
        
        $stmt = $connection->prepare($sql);
        $result = $stmt->executeQuery([
            'search_query' => $query,
            'user_uuid' => $user?->getId()->toRfc4122(),
            'language_filter' => !empty($languages) ? $languages[0] : null, // Take first language for now
            'tags_filter' => null, // Could be added as parameter
            'limit_count' => $limit,
            'offset_count' => $offset
        ]);
        
        $snippetIds = array_column($result->fetchAllAssociative(), 'snippet_id');
        
        if (empty($snippetIds)) {
            return [];
        }
        
        // Fetch full entities maintaining search order
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.id IN (:ids)')
            ->setParameter('ids', $snippetIds);
            
        $snippets = $qb->getQuery()->getResult();
        
        // Maintain search order
        $snippetMap = [];
        foreach ($snippets as $snippet) {
            $snippetMap[$snippet->getId()->toRfc4122()] = $snippet;
        }
        
        $orderedSnippets = [];
        foreach ($snippetIds as $id) {
            if (isset($snippetMap[$id])) {
                $orderedSnippets[] = $snippetMap[$id];
            }
        }
        
        return $orderedSnippets;
    }

    /**
     * Find snippets by tags
     */
    public function findByTags(array $tags, ?User $user = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.tags && :tags')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('tags', $tags)
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->andWhere('(s.visibility = :public OR s.user = :user)')
                ->setParameter('public', Snippet::VISIBILITY_PUBLIC)
                ->setParameter('user', $user);
        } else {
            $qb->andWhere('s.visibility = :public')
                ->andWhere('s.allowPublicIndexing = true')
                ->setParameter('public', Snippet::VISIBILITY_PUBLIC);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find most popular snippets (by view count)
     */
    public function findMostPopular(int $limit = 20, int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.visibility = :public')
            ->andWhere('s.allowPublicIndexing = true')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.createdAt >= :since')
            ->setParameter('public', Snippet::VISIBILITY_PUBLIC)
            ->setParameter('since', $since)
            ->orderBy('s.viewCount', 'DESC')
            ->addOrderBy('s.favoriteCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently updated snippets
     */
    public function findRecentlyUpdated(?User $user = null, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->andWhere('(s.visibility = :public OR s.user = :user)')
                ->setParameter('public', Snippet::VISIBILITY_PUBLIC)
                ->setParameter('user', $user);
        } else {
            $qb->andWhere('s.visibility = :public')
                ->andWhere('s.allowPublicIndexing = true')
                ->setParameter('public', Snippet::VISIBILITY_PUBLIC);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find snippets with sensitive data
     */
    public function findWithSensitiveData(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.containsSensitiveData = true')
            ->andWhere('s.deletedAt IS NULL')
            ->orderBy('s.updatedAt', 'DESC');

        if ($user) {
            $qb->andWhere('s.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find expired snippets that need cleanup
     */
    public function findExpiredSnippets(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.autoExpireAt IS NOT NULL')
            ->andWhere('s.autoExpireAt <= :now')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Get snippet statistics
     */
    public function getStatistics(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id) as total')
            ->addSelect('COUNT(CASE WHEN s.visibility = :public THEN s.id END) as public')
            ->addSelect('COUNT(CASE WHEN s.visibility = :private THEN s.id END) as private')
            ->addSelect('COUNT(CASE WHEN s.visibility = :shared THEN s.id END) as shared')
            ->addSelect('COUNT(CASE WHEN s.containsSensitiveData = true THEN s.id END) as sensitive')
            ->addSelect('SUM(s.viewCount) as totalViews')
            ->addSelect('SUM(s.favoriteCount) as totalFavorites')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('public', Snippet::VISIBILITY_PUBLIC)
            ->setParameter('private', Snippet::VISIBILITY_PRIVATE)
            ->setParameter('shared', Snippet::VISIBILITY_SHARED);

        if ($user) {
            $qb->andWhere('s.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Find single snippet by content hash (for deduplication)
     */
    public function findByContentHash(string $hash): ?Snippet
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.contentHash = :hash')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('hash', $hash)
            ->orderBy('s.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    
    /**
     * Find multiple snippets by content hash for a user
     */
    public function findByContentHashForUser(string $contentHash, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.contentHash = :hash')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('hash', $contentHash)
            ->orderBy('s.createdAt', 'ASC');

        if ($user) {
            $qb->andWhere('s.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find snippet versions
     */
    public function findVersions(Snippet $snippet): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.parentSnippet = :snippet OR s = :snippet')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('snippet', $snippet)
            ->orderBy('s.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest version of a snippet
     */
    public function findLatestVersion(Snippet $snippet): ?Snippet
    {
        return $this->createQueryBuilder('s')
            ->andWhere('(s.parentSnippet = :snippet OR s = :snippet)')
            ->andWhere('s.isLatestVersion = true')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('snippet', $snippet)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get language statistics
     */
    public function getLanguageStatistics(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.language')
            ->addSelect('COUNT(s.id) as count')
            ->addSelect('SUM(s.viewCount) as totalViews')
            ->andWhere('s.deletedAt IS NULL')
            ->groupBy('s.language')
            ->orderBy('count', 'DESC');

        if ($user) {
            $qb->andWhere('s.user = :user')
                ->setParameter('user', $user);
        } else {
            $qb->andWhere('s.visibility = :public')
                ->andWhere('s.allowPublicIndexing = true')
                ->setParameter('public', Snippet::VISIBILITY_PUBLIC);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find similar snippets (by language and tags)
     */
    public function findSimilar(Snippet $snippet, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.id != :snippetId')
            ->andWhere('s.language = :language')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('snippetId', $snippet->getId())
            ->setParameter('language', $snippet->getLanguage())
            ->setMaxResults($limit);

        // If snippet has tags, find snippets with similar tags
        if ($snippet->getTags() && !empty($snippet->getTags())) {
            $qb->andWhere('s.tags && :tags')
                ->setParameter('tags', $snippet->getTags())
                ->orderBy('s.favoriteCount', 'DESC');
        } else {
            $qb->orderBy('s.viewCount', 'DESC');
        }

        // Only show public snippets to other users
        if ($snippet->getVisibility() !== Snippet::VISIBILITY_PRIVATE) {
            $qb->andWhere('s.visibility = :public')
                ->andWhere('s.allowPublicIndexing = true')
                ->setParameter('public', Snippet::VISIBILITY_PUBLIC);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Soft delete a snippet
     */
    public function softDelete(Snippet $snippet): void
    {
        $snippet->setDeletedAt(new \DateTimeImmutable());
        
        $this->getEntityManager()->persist($snippet);
        $this->getEntityManager()->flush();
    }

    /**
     * Find snippets that need render cache refresh
     */
    public function findSnippetsNeedingCacheRefresh(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.renderCacheExpiresAt IS NULL OR s.renderCacheExpiresAt <= :now')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(100) // Process in batches
            ->getQuery()
            ->getResult();
    }

    // Interface implementation methods
    public function findPublic(): array
    {
        return $this->findPublicSnippets();
    }

    public function findExpired(): array
    {
        return $this->findExpiredSnippets();
    }


    public function findByContentHashMultiple(string $contentHash, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.contentHash = :hash')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('hash', $contentHash)
            ->orderBy('s.createdAt', 'ASC');

        if ($user) {
            $qb->andWhere('s.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    public function findVersionsOf(Snippet $snippet): array
    {
        return $this->findVersions($snippet);
    }

    public function findForksOf(Snippet $snippet): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.parentSnippet = :snippet')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('snippet', $snippet)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchByContent(string $query, int $limit = 20): array
    {
        return $this->search($query, null, [], $limit, 0);
    }

    public function findPopular(int $limit = 10): array
    {
        return $this->findMostPopular($limit);
    }

    public function findRecent(int $limit = 10): array
    {
        return $this->findRecentlyUpdated(null, $limit);
    }

    public function cleanupExpired(): void
    {
        $expired = $this->findExpired();
        foreach ($expired as $snippet) {
            $this->softDelete($snippet);
        }
    }

    public function save(Snippet $snippet): void
    {
        $this->getEntityManager()->persist($snippet);
        $this->getEntityManager()->flush();
    }

    public function delete(Snippet $snippet): void
    {
        $this->getEntityManager()->remove($snippet);
        $this->getEntityManager()->flush();
    }

    public function findByVisibility(string $visibility): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.visibility = :visibility')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('visibility', $visibility)
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}