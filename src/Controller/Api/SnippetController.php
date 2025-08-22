<?php

namespace App\Controller\Api;

use App\Entity\Snippet;
use App\Entity\User;
use App\Repository\SnippetRepository;
use App\Service\SecurityService;
use App\Service\TokenMaskingService;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API Snippet Controller
 * Handles CRUD operations, versioning, sharing, and advanced features for code snippets
 */
#[Route('/api/snippets', name: 'api_snippets_')]
class SnippetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SnippetRepository $snippetRepository,
        private readonly SecurityService $securityService,
        private readonly TokenMaskingService $tokenMaskingService,
        private readonly EncryptionService $encryptionService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get all snippets for authenticated user
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 30)));
            $search = $request->query->get('search', '');
            $language = $request->query->get('language', '');
            $visibility = $request->query->get('visibility', '');
            $sortBy = $request->query->get('sort_by', 'created_at');
            $sortOrder = $request->query->get('sort_order', 'DESC');

            $queryBuilder = $this->snippetRepository->createQueryBuilder('s')
                ->where('s.user = :user')
                ->andWhere('s.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->orderBy("s.$sortBy", $sortOrder)
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);

            // Apply filters
            if ($search) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->like('s.title', ':search'),
                        $queryBuilder->expr()->like('s.description', ':search'),
                        $queryBuilder->expr()->like('s.contentSearchVector', ':search')
                    )
                )->setParameter('search', '%' . $search . '%');
            }

            if ($language) {
                $queryBuilder->andWhere('s.language = :language')
                    ->setParameter('language', $language);
            }

            if ($visibility) {
                $queryBuilder->andWhere('s.visibility = :visibility')
                    ->setParameter('visibility', $visibility);
            }

            $snippets = $queryBuilder->getQuery()->getResult();

            // Get total count for pagination
            $totalQuery = $this->snippetRepository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.user = :user')
                ->andWhere('s.deletedAt IS NULL')
                ->setParameter('user', $user);

            if ($search) {
                $totalQuery->andWhere(
                    $totalQuery->expr()->orX(
                        $totalQuery->expr()->like('s.title', ':search'),
                        $totalQuery->expr()->like('s.description', ':search'),
                        $totalQuery->expr()->like('s.contentSearchVector', ':search')
                    )
                )->setParameter('search', '%' . $search . '%');
            }

            if ($language) {
                $totalQuery->andWhere('s.language = :language')
                    ->setParameter('language', $language);
            }

            if ($visibility) {
                $totalQuery->andWhere('s.visibility = :visibility')
                    ->setParameter('visibility', $visibility);
            }

            $total = $totalQuery->getQuery()->getSingleScalarResult();

            $snippetData = [];
            foreach ($snippets as $snippet) {
                $snippetData[] = $this->serializeSnippet($snippet, false);
            }

            return $this->json([
                'snippets' => $snippetData,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to list snippets', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve snippets'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific snippet
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $snippet = $this->snippetRepository->find($id);

            if (!$snippet) {
                return $this->json(['error' => 'Snippet not found'], Response::HTTP_NOT_FOUND);
            }

            // Check access permissions
            if (!$this->canAccessSnippet($snippet, $user)) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            // Increment view count if not owner
            if ($snippet->getUser() !== $user) {
                $snippet->incrementViewCount();
                $this->entityManager->flush();
            }

            return $this->json([
                'snippet' => $this->serializeSnippet($snippet, true)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve snippet', [
                'error' => $e->getMessage(),
                'snippet_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve snippet'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new snippet
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            // Check user's snippet limit
            if (!$user->canCreateSnippet()) {
                return $this->json([
                    'error' => 'Monthly snippet limit exceeded',
                    'limit' => $user->getMonthlySnippetLimit(),
                    'used' => $user->getMonthlySnippetsUsed()
                ], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['title', 'content', 'language'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return $this->json(['error' => "Field '$field' is required"], Response::HTTP_BAD_REQUEST);
                }
            }

            // Sanitize and validate input
            $title = $this->securityService->sanitizeInput($data['title']);
            $description = $this->securityService->sanitizeInput($data['description'] ?? '');
            $content = $data['content'];
            $language = strtolower(trim($data['language']));
            
            // Validate language
            if (!in_array($language, Snippet::SUPPORTED_LANGUAGES, true)) {
                return $this->json(['error' => 'Unsupported language'], Response::HTTP_BAD_REQUEST);
            }

            // Security checks
            $securityCheck = $this->securityService->detectSuspiciousContent($content);
            if ($securityCheck['suspicious'] && $securityCheck['risk_level'] === 'high') {
                return $this->json([
                    'error' => 'Content contains suspicious elements',
                    'issues' => $securityCheck['issues']
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check for sensitive data
            $containsSensitiveData = $this->tokenMaskingService->containsSensitiveData($content);
            $encryptContent = $data['encrypt'] ?? false;

            // Create snippet
            $snippet = new Snippet();
            $snippet->setUser($user);
            $snippet->setTitle($title);
            $snippet->setDescription($description);
            $snippet->setLanguage($language);
            $snippet->setFramework($data['framework'] ?? null);
            $snippet->setTags($data['tags'] ?? null);
            $snippet->setTheme($data['theme'] ?? Snippet::THEME_DEFAULT);
            $snippet->setLineNumbers($data['line_numbers'] ?? true);
            $snippet->setWordWrap($data['word_wrap'] ?? false);
            $snippet->setVisibility($data['visibility'] ?? Snippet::VISIBILITY_PRIVATE);
            $snippet->setAllowPublicIndexing($data['allow_public_indexing'] ?? false);
            $snippet->setContainsSensitiveData($containsSensitiveData);

            // Handle content encryption if requested or if sensitive data detected
            if ($encryptContent || ($containsSensitiveData && $data['auto_encrypt_sensitive'] ?? true)) {
                $encryptedContent = $this->encryptionService->encrypt($content);
                $snippet->setContent($encryptedContent);
                $snippet->setContentEncrypted(true);
            } else {
                $snippet->setContent($content);
            }

            // Handle sensitive data masking
            if ($containsSensitiveData && ($data['mask_sensitive'] ?? false)) {
                $maskedContent = $this->tokenMaskingService->maskSensitiveData($content);
                $snippet->setContent($maskedContent);
                $snippet->setSensitiveDataMasked(true);
                
                $detectedSecrets = $this->tokenMaskingService->getSensitiveDataStats($content);
                $snippet->setDetectedSecrets($detectedSecrets);
            }

            // Set expiration if provided
            if (!empty($data['expires_in_hours'])) {
                $expiresAt = new \DateTimeImmutable('+' . (int)$data['expires_in_hours'] . ' hours');
                $snippet->setAutoExpireAt($expiresAt);
            }

            // Validate snippet
            $errors = $this->validator->validate($snippet);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['error' => 'Validation failed', 'details' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            // Save snippet
            $this->entityManager->persist($snippet);
            $user->incrementMonthlySnippetsUsed();
            $this->entityManager->flush();

            $this->logger->info('Snippet created', [
                'snippet_id' => $snippet->getId(),
                'user_id' => $user->getId(),
                'title' => $snippet->getTitle(),
                'language' => $snippet->getLanguage(),
                'encrypted' => $snippet->isContentEncrypted(),
                'contains_sensitive' => $snippet->containsSensitiveData()
            ]);

            return $this->json([
                'message' => 'Snippet created successfully',
                'snippet' => $this->serializeSnippet($snippet, true)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create snippet', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to create snippet'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update an existing snippet
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $snippet = $this->snippetRepository->find($id);

            if (!$snippet) {
                return $this->json(['error' => 'Snippet not found'], Response::HTTP_NOT_FOUND);
            }

            if ($snippet->getUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            $createVersion = $data['create_version'] ?? false;
            $targetSnippet = $snippet;

            // Create new version if requested
            if ($createVersion) {
                $targetSnippet = $snippet->createVersion();
                $this->entityManager->persist($targetSnippet);
            }

            // Update fields
            if (isset($data['title'])) {
                $targetSnippet->setTitle($this->securityService->sanitizeInput($data['title']));
            }

            if (isset($data['description'])) {
                $targetSnippet->setDescription($this->securityService->sanitizeInput($data['description']));
            }

            if (isset($data['content'])) {
                $content = $data['content'];
                
                // Security checks
                $securityCheck = $this->securityService->detectSuspiciousContent($content);
                if ($securityCheck['suspicious'] && $securityCheck['risk_level'] === 'high') {
                    return $this->json([
                        'error' => 'Content contains suspicious elements',
                        'issues' => $securityCheck['issues']
                    ], Response::HTTP_BAD_REQUEST);
                }

                $containsSensitiveData = $this->tokenMaskingService->containsSensitiveData($content);
                $targetSnippet->setContainsSensitiveData($containsSensitiveData);

                // Handle encryption
                if ($data['encrypt'] ?? false) {
                    $encryptedContent = $this->encryptionService->encrypt($content);
                    $targetSnippet->setContent($encryptedContent);
                    $targetSnippet->setContentEncrypted(true);
                } elseif ($targetSnippet->isContentEncrypted() && ($data['decrypt'] ?? false)) {
                    $targetSnippet->setContent($content);
                    $targetSnippet->setContentEncrypted(false);
                } else {
                    $targetSnippet->setContent($content);
                }
            }

            if (isset($data['language']) && in_array(strtolower($data['language']), Snippet::SUPPORTED_LANGUAGES, true)) {
                $targetSnippet->setLanguage(strtolower($data['language']));
            }

            if (isset($data['framework'])) {
                $targetSnippet->setFramework($data['framework']);
            }

            if (isset($data['tags'])) {
                $targetSnippet->setTags($data['tags']);
            }

            if (isset($data['theme'])) {
                $targetSnippet->setTheme($data['theme']);
            }

            if (isset($data['line_numbers'])) {
                $targetSnippet->setLineNumbers((bool) $data['line_numbers']);
            }

            if (isset($data['word_wrap'])) {
                $targetSnippet->setWordWrap((bool) $data['word_wrap']);
            }

            if (isset($data['visibility'])) {
                $targetSnippet->setVisibility($data['visibility']);
            }

            if (isset($data['allow_public_indexing'])) {
                $targetSnippet->setAllowPublicIndexing((bool) $data['allow_public_indexing']);
            }

            // Validate snippet
            $errors = $this->validator->validate($targetSnippet);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['error' => 'Validation failed', 'details' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->flush();

            $this->logger->info('Snippet updated', [
                'snippet_id' => $targetSnippet->getId(),
                'user_id' => $user->getId(),
                'created_version' => $createVersion,
                'version' => $targetSnippet->getVersion()
            ]);

            return $this->json([
                'message' => 'Snippet updated successfully',
                'snippet' => $this->serializeSnippet($targetSnippet, true)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update snippet', [
                'error' => $e->getMessage(),
                'snippet_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to update snippet'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a snippet
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $snippet = $this->snippetRepository->find($id);

            if (!$snippet) {
                return $this->json(['error' => 'Snippet not found'], Response::HTTP_NOT_FOUND);
            }

            if ($snippet->getUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            // Soft delete by setting deletedAt
            $snippet->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('Snippet deleted', [
                'snippet_id' => $snippet->getId(),
                'user_id' => $user->getId(),
                'title' => $snippet->getTitle()
            ]);

            return $this->json(['message' => 'Snippet deleted successfully']);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete snippet', [
                'error' => $e->getMessage(),
                'snippet_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to delete snippet'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Fork a snippet
     */
    #[Route('/{id}/fork', name: 'fork', methods: ['POST'])]
    public function fork(string $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $originalSnippet = $this->snippetRepository->find($id);

            if (!$originalSnippet) {
                return $this->json(['error' => 'Snippet not found'], Response::HTTP_NOT_FOUND);
            }

            if (!$this->canAccessSnippet($originalSnippet, $user)) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            if (!$user->canCreateSnippet()) {
                return $this->json([
                    'error' => 'Monthly snippet limit exceeded',
                    'limit' => $user->getMonthlySnippetLimit(),
                    'used' => $user->getMonthlySnippetsUsed()
                ], Response::HTTP_FORBIDDEN);
            }

            // Create fork
            $fork = $originalSnippet->createFork($user);
            $this->entityManager->persist($fork);
            $user->incrementMonthlySnippetsUsed();
            $this->entityManager->flush();

            $this->logger->info('Snippet forked', [
                'original_snippet_id' => $originalSnippet->getId(),
                'fork_snippet_id' => $fork->getId(),
                'user_id' => $user->getId()
            ]);

            return $this->json([
                'message' => 'Snippet forked successfully',
                'snippet' => $this->serializeSnippet($fork, true)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fork snippet', [
                'error' => $e->getMessage(),
                'snippet_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to fork snippet'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get snippet versions
     */
    #[Route('/{id}/versions', name: 'versions', methods: ['GET'])]
    public function versions(string $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $snippet = $this->snippetRepository->find($id);

            if (!$snippet) {
                return $this->json(['error' => 'Snippet not found'], Response::HTTP_NOT_FOUND);
            }

            if ($snippet->getUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            // Find all versions (including this one and its children)
            $versions = $this->snippetRepository->createQueryBuilder('s')
                ->where('s.parentSnippet = :parent OR s.id = :id')
                ->setParameter('parent', $snippet)
                ->setParameter('id', $snippet->getId())
                ->orderBy('s.version', 'DESC')
                ->getQuery()
                ->getResult();

            $versionData = [];
            foreach ($versions as $version) {
                $versionData[] = [
                    'id' => $version->getId(),
                    'version' => $version->getVersion(),
                    'title' => $version->getTitle(),
                    'is_latest' => $version->isLatestVersion(),
                    'created_at' => $version->getCreatedAt()->format('c'),
                    'content_hash' => $version->getContentHash()
                ];
            }

            return $this->json(['versions' => $versionData]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve snippet versions', [
                'error' => $e->getMessage(),
                'snippet_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve versions'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search public snippets
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');
            $language = $request->query->get('language', '');
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(50, (int) $request->query->get('limit', 20)));

            if (strlen($query) < 3) {
                return $this->json(['error' => 'Search query must be at least 3 characters'], Response::HTTP_BAD_REQUEST);
            }

            $queryBuilder = $this->snippetRepository->createQueryBuilder('s')
                ->where('s.visibility = :visibility')
                ->andWhere('s.allowPublicIndexing = true')
                ->andWhere('s.deletedAt IS NULL')
                ->andWhere('s.autoExpireAt IS NULL OR s.autoExpireAt > :now')
                ->setParameter('visibility', Snippet::VISIBILITY_PUBLIC)
                ->setParameter('now', new \DateTimeImmutable())
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);

            // Add search conditions
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('s.title', ':search'),
                    $queryBuilder->expr()->like('s.description', ':search'),
                    $queryBuilder->expr()->like('s.contentSearchVector', ':search')
                )
            )->setParameter('search', '%' . $query . '%');

            if ($language) {
                $queryBuilder->andWhere('s.language = :language')
                    ->setParameter('language', $language);
            }

            $queryBuilder->orderBy('s.favoriteCount', 'DESC')
                ->addOrderBy('s.viewCount', 'DESC')
                ->addOrderBy('s.createdAt', 'DESC');

            $snippets = $queryBuilder->getQuery()->getResult();

            $snippetData = [];
            foreach ($snippets as $snippet) {
                $snippetData[] = $this->serializeSnippet($snippet, false);
            }

            return $this->json([
                'snippets' => $snippetData,
                'query' => $query,
                'language' => $language,
                'page' => $page,
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Search failed', [
                'error' => $e->getMessage(),
                'query' => $request->query->get('q', '')
            ]);

            return $this->json(['error' => 'Search failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get snippet statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $totalSnippets = $this->snippetRepository->count(['user' => $user, 'deletedAt' => null]);
            $publicSnippets = $this->snippetRepository->count(['user' => $user, 'visibility' => Snippet::VISIBILITY_PUBLIC, 'deletedAt' => null]);
            
            $languageStats = $this->snippetRepository->createQueryBuilder('s')
                ->select('s.language, COUNT(s.id) as count')
                ->where('s.user = :user')
                ->andWhere('s.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->groupBy('s.language')
                ->orderBy('count', 'DESC')
                ->getQuery()
                ->getResult();

            $totalViews = $this->snippetRepository->createQueryBuilder('s')
                ->select('SUM(s.viewCount)')
                ->where('s.user = :user')
                ->andWhere('s.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;

            $totalForks = $this->snippetRepository->createQueryBuilder('s')
                ->select('SUM(s.forkCount)')
                ->where('s.user = :user')
                ->andWhere('s.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;

            return $this->json([
                'total_snippets' => $totalSnippets,
                'public_snippets' => $publicSnippets,
                'private_snippets' => $totalSnippets - $publicSnippets,
                'total_views' => $totalViews,
                'total_forks' => $totalForks,
                'language_breakdown' => $languageStats,
                'monthly_usage' => [
                    'limit' => $user->getMonthlySnippetLimit(),
                    'used' => $user->getMonthlySnippetsUsed(),
                    'remaining' => $user->getMonthlySnippetLimit() === -1 ? -1 : $user->getMonthlySnippetLimit() - $user->getMonthlySnippetsUsed()
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve stats', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve statistics'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check if user can access a snippet
     */
    private function canAccessSnippet(Snippet $snippet, User $user): bool
    {
        // Owner can always access
        if ($snippet->getUser() === $user) {
            return true;
        }

        // Public snippets are accessible to all
        if ($snippet->getVisibility() === Snippet::VISIBILITY_PUBLIC) {
            return true;
        }

        // Shared snippets require additional checks (implement sharing logic)
        if ($snippet->getVisibility() === Snippet::VISIBILITY_SHARED) {
            // This would check sharing permissions
            return false; // For now, deny access
        }

        return false;
    }

    /**
     * Serialize snippet for API response
     */
    private function serializeSnippet(Snippet $snippet, bool $includeContent = false): array
    {
        $data = [
            'id' => $snippet->getId(),
            'title' => $snippet->getTitle(),
            'description' => $snippet->getDescription(),
            'language' => $snippet->getLanguage(),
            'framework' => $snippet->getFramework(),
            'tags' => $snippet->getTags(),
            'theme' => $snippet->getTheme(),
            'line_numbers' => $snippet->isLineNumbers(),
            'word_wrap' => $snippet->isWordWrap(),
            'version' => $snippet->getVersion(),
            'is_latest_version' => $snippet->isLatestVersion(),
            'visibility' => $snippet->getVisibility(),
            'allow_public_indexing' => $snippet->isAllowPublicIndexing(),
            'password_protected' => $snippet->isPasswordProtected(),
            'view_count' => $snippet->getViewCount(),
            'fork_count' => $snippet->getForkCount(),
            'favorite_count' => $snippet->getFavoriteCount(),
            'contains_sensitive_data' => $snippet->containsSensitiveData(),
            'sensitive_data_masked' => $snippet->isSensitiveDataMasked(),
            'content_encrypted' => $snippet->isContentEncrypted(),
            'created_at' => $snippet->getCreatedAt()->format('c'),
            'updated_at' => $snippet->getUpdatedAt()->format('c'),
            'last_accessed_at' => $snippet->getLastAccessedAt()->format('c'),
            'auto_expire_at' => $snippet->getAutoExpireAt()?->format('c'),
            'user' => [
                'id' => $snippet->getUser()->getId(),
                'username' => $snippet->getUser()->getUsernameField(),
                'first_name' => $snippet->getUser()->getFirstName(),
                'avatar_url' => $snippet->getUser()->getAvatarUrl()
            ]
        ];

        if ($includeContent) {
            $content = $snippet->getContent();
            
            // Decrypt content if encrypted
            if ($snippet->isContentEncrypted()) {
                try {
                    $content = $this->encryptionService->decrypt($content, $snippet->getEncryptionIv());
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to decrypt snippet content', [
                        'snippet_id' => $snippet->getId(),
                        'error' => $e->getMessage()
                    ]);
                    $content = '[Content could not be decrypted]';
                }
            }
            
            $data['content'] = $content;
            $data['content_hash'] = $snippet->getContentHash();
            
            if ($snippet->getDetectedSecrets()) {
                $data['detected_secrets'] = $snippet->getDetectedSecrets();
            }
        }

        return $data;
    }
}