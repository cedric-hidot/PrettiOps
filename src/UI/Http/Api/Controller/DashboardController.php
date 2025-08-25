<?php

namespace App\UI\Http\Api\Controller;

use App\Infrastructure\Persistence\Doctrine\Repository\SnippetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Domain\User\Entity\User;

/**
 * API Dashboard Controller
 * Handles dashboard data requests
 */
#[Route('/api/dashboard', name: 'api_dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SnippetRepository $snippetRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get dashboard statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(#[CurrentUser] User $user): JsonResponse
    {
        try {
            // Get user's snippets count
            $totalSnippets = $this->snippetRepository->count(['user' => $user]);
            
            // Get public snippets count
            $publicSnippets = $this->snippetRepository->count([
                'user' => $user,
                'visibility' => 'public'
            ]);

            // For now, return mock data for other stats
            // In production, these would be calculated from real data
            $stats = [
                'totalSnippets' => $totalSnippets,
                'totalViews' => rand(0, $totalSnippets * 10), // Mock data
                'emailSends' => rand(0, $totalSnippets * 5), // Mock data
                'activeLinks' => $publicSnippets,
                'snippetsChange' => rand(-5, 10),
                'viewsChange' => rand(-50, 100),
                'viewsChangePercent' => rand(-20, 30),
                'emailSendsChange' => rand(-10, 20),
                'activeLinksChange' => rand(-2, 5),
            ];

            return $this->json($stats);

        } catch (\Exception $e) {
            $this->logger->error('Failed to load dashboard stats', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json([
                'error' => 'Failed to load dashboard stats'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get user's snippets for dashboard
     */
    #[Route('/snippets', name: 'snippets', methods: ['GET'])]
    public function snippets(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(50, (int) $request->query->get('limit', 20)));
            $sort = $request->query->get('sort', 'recent');
            $language = $request->query->get('language', '');
            $status = $request->query->get('status', '');
            $search = $request->query->get('search', '');

            // Build query criteria
            $criteria = ['user' => $user];
            
            if ($language) {
                $criteria['language'] = $language;
            }

            // Get snippets with filtering
            $queryBuilder = $this->snippetRepository->createQueryBuilder('s')
                ->where('s.user = :user')
                ->setParameter('user', $user);

            if ($language) {
                $queryBuilder->andWhere('s.language = :language')
                    ->setParameter('language', $language);
            }

            if ($status) {
                switch ($status) {
                    case 'active':
                        $queryBuilder->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
                            ->setParameter('now', new \DateTime());
                        break;
                    case 'expiring':
                        $queryBuilder->andWhere('s.expiresAt BETWEEN :soon AND :later')
                            ->setParameter('soon', new \DateTime())
                            ->setParameter('later', (new \DateTime())->modify('+7 days'));
                        break;
                    case 'expired':
                        $queryBuilder->andWhere('s.expiresAt IS NOT NULL AND s.expiresAt < :now')
                            ->setParameter('now', new \DateTime());
                        break;
                }
            }

            if ($search) {
                $queryBuilder->andWhere('s.title LIKE :search OR s.description LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }

            // Apply sorting
            switch ($sort) {
                case 'name':
                    $queryBuilder->orderBy('s.title', 'ASC');
                    break;
                case 'popular':
                case 'views':
                    $queryBuilder->orderBy('s.viewCount', 'DESC');
                    break;
                case 'recent':
                default:
                    $queryBuilder->orderBy('s.updatedAt', 'DESC');
                    break;
            }

            // Get total count for pagination
            $totalQuery = clone $queryBuilder;
            $totalCount = $totalQuery->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

            // Apply pagination
            $snippets = $queryBuilder
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            // Serialize snippets
            $snippetsData = array_map([$this, 'serializeSnippet'], $snippets);

            return $this->json([
                'snippets' => $snippetsData,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ],
                'filters' => [
                    'language' => $language,
                    'status' => $status,
                    'search' => $search,
                    'sort' => $sort
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to load dashboard snippets', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json([
                'error' => 'Failed to load snippets'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export dashboard data
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(#[CurrentUser] User $user): JsonResponse
    {
        try {
            // Get all user snippets
            $snippets = $this->snippetRepository->findBy(['user' => $user]);

            // Create CSV data
            $csvData = [];
            $csvData[] = ['Title', 'Language', 'Visibility', 'Views', 'Created At', 'Updated At', 'Expires At'];

            foreach ($snippets as $snippet) {
                $csvData[] = [
                    $snippet->getTitle(),
                    $snippet->getLanguage(),
                    $snippet->getVisibility(),
                    $snippet->getViewCount(),
                    $snippet->getCreatedAt()->format('Y-m-d H:i:s'),
                    $snippet->getUpdatedAt()->format('Y-m-d H:i:s'),
                    $snippet->getExpiresAt()?->format('Y-m-d H:i:s') ?? 'Never'
                ];
            }

            // Generate CSV content
            $output = fopen('php://temp', 'r+');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

            return new Response(
                $csvContent,
                200,
                [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="dashboard-export-' . date('Y-m-d') . '.csv"'
                ]
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to export dashboard data', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json([
                'error' => 'Failed to export dashboard data'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get dashboard analytics summary
     */
    #[Route('/analytics', name: 'analytics', methods: ['GET'])]
    public function analytics(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $snippets = $this->snippetRepository->findBy(['user' => $user]);
            
            // Calculate analytics data
            $totalViews = array_sum(array_map(fn($s) => $s->getViewCount(), $snippets));
            $languageStats = [];
            $visibilityStats = ['public' => 0, 'private' => 0, 'unlisted' => 0];
            
            foreach ($snippets as $snippet) {
                $language = $snippet->getLanguage();
                $visibility = $snippet->getVisibility();
                
                $languageStats[$language] = ($languageStats[$language] ?? 0) + 1;
                $visibilityStats[$visibility] = ($visibilityStats[$visibility] ?? 0) + 1;
            }

            // Sort language stats by usage
            arsort($languageStats);

            return $this->json([
                'summary' => [
                    'total_snippets' => count($snippets),
                    'total_views' => $totalViews,
                    'average_views' => count($snippets) > 0 ? round($totalViews / count($snippets), 1) : 0,
                    'most_used_language' => !empty($languageStats) ? array_key_first($languageStats) : null
                ],
                'language_distribution' => $languageStats,
                'visibility_distribution' => $visibilityStats,
                'recent_activity' => $this->getRecentActivity($user, 7) // Last 7 days
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to load dashboard analytics', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json([
                'error' => 'Failed to load analytics'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Serialize snippet for API response
     */
    private function serializeSnippet($snippet): array
    {
        return [
            'id' => $snippet->getId(),
            'title' => $snippet->getTitle(),
            'description' => $snippet->getDescription(),
            'language' => $snippet->getLanguage(),
            'visibility' => $snippet->getVisibility(),
            'view_count' => $snippet->getViewCount(),
            'created_at' => $snippet->getCreatedAt()->format('c'),
            'updated_at' => $snippet->getUpdatedAt()->format('c'),
            'expires_at' => $snippet->getExpiresAt()?->format('c'),
            'is_expired' => $snippet->getExpiresAt() && $snippet->getExpiresAt() < new \DateTime(),
            'url' => $this->generateUrl('snippet_view', ['id' => $snippet->getId()], true)
        ];
    }

    /**
     * Get recent activity for analytics
     */
    private function getRecentActivity(User $user, int $days): array
    {
        $since = (new \DateTime())->modify("-{$days} days");
        
        $recentSnippets = $this->snippetRepository->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function($snippet) {
            return [
                'type' => 'snippet_created',
                'snippet_id' => $snippet->getId(),
                'snippet_title' => $snippet->getTitle(),
                'timestamp' => $snippet->getCreatedAt()->format('c')
            ];
        }, $recentSnippets);
    }
}