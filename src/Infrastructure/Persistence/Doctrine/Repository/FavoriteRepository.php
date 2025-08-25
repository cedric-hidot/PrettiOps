<?php

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Snippet\Entity\Favorite;
use App\Domain\Snippet\Entity\Snippet;
use App\Domain\User\Entity\User;
use App\Domain\Snippet\Repository\FavoriteRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository implements FavoriteRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    /**
     * Find favorites by user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find if user has favorited a snippet
     */
    public function findOneByUserAndSnippet(User $user, Snippet $snippet): ?Favorite
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.snippet = :snippet')
            ->setParameter('user', $user)
            ->setParameter('snippet', $snippet)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if user has favorited a snippet
     */
    public function isSnippetFavoritedByUser(User $user, Snippet $snippet): bool
    {
        return $this->findOneByUserAndSnippet($user, $snippet) !== null;
    }

    /**
     * Find favorites by folder
     */
    public function findByFolder(User $user, ?string $folderName): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC');

        if ($folderName === null) {
            $qb->andWhere('f.folderName IS NULL');
        } else {
            $qb->andWhere('f.folderName = :folder')
                ->setParameter('folder', $folderName);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all folder names for a user
     */
    public function getFolders(User $user): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('DISTINCT f.folderName')
            ->andWhere('f.user = :user')
            ->andWhere('f.folderName IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('f.folderName', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'folderName');
    }

    /**
     * Count favorites by folder for a user
     */
    public function countByFolder(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.folderName')
            ->addSelect('COUNT(f.id) as count')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->groupBy('f.folderName')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most recent favorites
     */
    public function findRecent(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find favorites with notes
     */
    public function findWithNotes(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.notes IS NOT NULL')
            ->andWhere('f.notes != :empty')
            ->setParameter('user', $user)
            ->setParameter('empty', '')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search favorites by snippet title or notes
     */
    public function search(User $user, string $query): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.snippet', 's')
            ->andWhere('f.user = :user')
            ->andWhere('(s.title ILIKE :query OR f.notes ILIKE :query)')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find favorites by snippet language
     */
    public function findByLanguage(User $user, string $language): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.snippet', 's')
            ->andWhere('f.user = :user')
            ->andWhere('s.language = :language')
            ->setParameter('user', $user)
            ->setParameter('language', $language)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get global favorite statistics
     */
    public function getStatistics(): array
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id) as total')
            ->addSelect('COUNT(DISTINCT f.user) as users')
            ->addSelect('COUNT(DISTINCT f.folderName) as folders')
            ->addSelect('COUNT(CASE WHEN f.notes IS NOT NULL AND f.notes != :empty THEN f.id END) as withNotes')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleResult();
    }
    
    /**
     * Get favorite statistics for a user
     */
    public function getStatisticsForUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id) as total')
            ->addSelect('COUNT(DISTINCT f.folderName) as folders')
            ->addSelect('COUNT(CASE WHEN f.notes IS NOT NULL AND f.notes != :empty THEN f.id END) as withNotes')
            ->join('f.snippet', 's')
            ->addSelect('COUNT(DISTINCT s.language) as languages')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Find favorites by snippet tags
     */
    public function findByTags(User $user, array $tags): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.snippet', 's')
            ->andWhere('f.user = :user')
            ->andWhere('s.tags && :tags')
            ->setParameter('user', $user)
            ->setParameter('tags', $tags)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get language distribution in favorites
     */
    public function getLanguageDistribution(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->select('s.language')
            ->addSelect('COUNT(f.id) as count')
            ->join('f.snippet', 's')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->groupBy('s.language')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Move favorites to a different folder
     */
    public function moveToFolder(User $user, array $favoriteIds, ?string $folderName): int
    {
        return $this->createQueryBuilder('f')
            ->update()
            ->set('f.folderName', ':folder')
            ->andWhere('f.user = :user')
            ->andWhere('f.id IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('folder', $folderName)
            ->setParameter('ids', $favoriteIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete all favorites in a folder
     */
    public function deleteFolder(User $user, string $folderName): int
    {
        $favorites = $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.folderName = :folder')
            ->setParameter('user', $user)
            ->setParameter('folder', $folderName)
            ->getQuery()
            ->getResult();

        $count = count($favorites);

        foreach ($favorites as $favorite) {
            $this->getEntityManager()->remove($favorite);
        }

        $this->getEntityManager()->flush();

        return $count;
    }

    /**
     * Rename a folder
     */
    public function renameFolder(User $user, string $oldName, string $newName): int
    {
        return $this->createQueryBuilder('f')
            ->update()
            ->set('f.folderName', ':newName')
            ->andWhere('f.user = :user')
            ->andWhere('f.folderName = :oldName')
            ->setParameter('user', $user)
            ->setParameter('oldName', $oldName)
            ->setParameter('newName', $newName)
            ->getQuery()
            ->execute();
    }

    /**
     * Clean up favorites for deleted snippets
     */
    public function cleanupForDeletedSnippets(): int
    {
        $favorites = $this->createQueryBuilder('f')
            ->join('f.snippet', 's')
            ->andWhere('s.deletedAt IS NOT NULL')
            ->getQuery()
            ->getResult();

        $count = count($favorites);

        foreach ($favorites as $favorite) {
            $this->getEntityManager()->remove($favorite);
        }

        $this->getEntityManager()->flush();

        return $count;
    }

    /**
     * Export user favorites as array
     */
    public function exportUserFavorites(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.folderName', 'f.notes', 'f.createdAt')
            ->addSelect('s.title', 's.description', 's.language', 's.framework', 's.tags')
            ->join('f.snippet', 's')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total favorites for a snippet
     */
    public function countForSnippet(Snippet $snippet): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.snippet = :snippet')
            ->setParameter('snippet', $snippet)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Interface implementation methods
    public function findByUserAndSnippet(User $user, Snippet $snippet): ?Favorite
    {
        return $this->findOneByUserAndSnippet($user, $snippet);
    }

    public function findBySnippet(Snippet $snippet): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.snippet = :snippet')
            ->setParameter('snippet', $snippet)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getFoldersByUser(User $user): array
    {
        return $this->getFolders($user);
    }

    private function getStatisticsInternal(): array
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id) as total')
            ->addSelect('COUNT(DISTINCT f.user) as users')
            ->addSelect('COUNT(DISTINCT f.folderName) as folders')
            ->addSelect('COUNT(CASE WHEN f.notes IS NOT NULL AND f.notes != :empty THEN f.id END) as withNotes')
            ->join('f.snippet', 's')
            ->addSelect('COUNT(DISTINCT s.language) as languages')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleResult();
    }

    public function save(Favorite $favorite): void
    {
        $this->getEntityManager()->persist($favorite);
        $this->getEntityManager()->flush();
    }

    public function delete(Favorite $favorite): void
    {
        $this->getEntityManager()->remove($favorite);
        $this->getEntityManager()->flush();
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}