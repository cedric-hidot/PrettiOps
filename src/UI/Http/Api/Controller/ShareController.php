<?php

namespace App\Shared\UI\Http\Api\Controller;

use App\Sharing\Domain\Entity\Share;
use App\Snippet\Domain\Entity\Snippet;
use App\User\Domain\Entity\User;
use App\Sharing\Infrastructure\Persistence\Doctrine\ShareRepository;
use App\Snippet\Infrastructure\Persistence\Doctrine\SnippetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API Share Controller
 * Handles snippet sharing, access control, and share management
 */
#[Route('/api/shares', name: 'api_shares_')]
class ShareController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShareRepository $shareRepository,
        private readonly SnippetRepository $snippetRepository,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create a new share for a snippet
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            if (empty($data['snippet_id'])) {
                return $this->json(['error' => 'Snippet ID is required'], Response::HTTP_BAD_REQUEST);
            }

            $snippet = $this->snippetRepository->find($data['snippet_id']);
            if (!$snippet) {
                return $this->json(['error' => 'Snippet not found'], Response::HTTP_NOT_FOUND);
            }

            // Check if user can share this snippet
            if ($snippet->getUser() !== $user) {
                return $this->json(['error' => 'You can only share your own snippets'], Response::HTTP_FORBIDDEN);
            }

            // Create share
            $share = new Share();
            $share->setSnippet($snippet);
            $share->setCreatedByUser($user);

            // Set share type
            $shareType = $data['share_type'] ?? Share::TYPE_VIEW;
            if (!in_array($shareType, [Share::TYPE_VIEW, Share::TYPE_EDIT, Share::TYPE_REVIEW], true)) {
                return $this->json(['error' => 'Invalid share type'], Response::HTTP_BAD_REQUEST);
            }
            $share->setShareType($shareType);

            // Set optional parameters
            if (isset($data['allowed_emails']) && is_array($data['allowed_emails'])) {
                $emails = array_filter($data['allowed_emails'], 'filter_var', FILTER_VALIDATE_EMAIL);
                if (count($emails) !== count($data['allowed_emails'])) {
                    return $this->json(['error' => 'Some email addresses are invalid'], Response::HTTP_BAD_REQUEST);
                }
                $share->setAllowedEmails($emails);
            }

            if (isset($data['allowed_domains']) && is_array($data['allowed_domains'])) {
                $share->setAllowedDomains($data['allowed_domains']);
            }

            if (isset($data['require_authentication'])) {
                $share->setRequireAuthentication((bool) $data['require_authentication']);
            }

            if (isset($data['expires_in_hours']) && is_numeric($data['expires_in_hours']) && $data['expires_in_hours'] > 0) {
                $share->setExpirationInHours((int) $data['expires_in_hours']);
            } elseif (isset($data['expires_in_days']) && is_numeric($data['expires_in_days']) && $data['expires_in_days'] > 0) {
                $share->setExpirationInDays((int) $data['expires_in_days']);
            }

            if (isset($data['max_views']) && is_numeric($data['max_views']) && $data['max_views'] > 0) {
                $share->setMaxViews((int) $data['max_views']);
            }

            if (isset($data['password']) && !empty($data['password'])) {
                $share->setPassword($data['password']);
            }

            if (isset($data['watermark_enabled'])) {
                $share->setWatermarkEnabled((bool) $data['watermark_enabled']);
            }

            if (isset($data['download_enabled'])) {
                $share->setDownloadEnabled((bool) $data['download_enabled']);
            }

            // Validate share
            $errors = $this->validator->validate($share);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['error' => 'Validation failed', 'details' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            // Save share
            $this->entityManager->persist($share);
            $this->entityManager->flush();

            $this->logger->info('Share created', [
                'share_id' => $share->getId(),
                'snippet_id' => $snippet->getId(),
                'user_id' => $user->getId(),
                'share_type' => $share->getShareType(),
                'expires_at' => $share->getExpiresAt()?->format('c')
            ]);

            $baseUrl = $this->getParameter('app.url') ?? $request->getSchemeAndHttpHost();

            return $this->json([
                'message' => 'Share created successfully',
                'share' => [
                    'id' => $share->getId(),
                    'token' => $share->getShareToken(),
                    'url' => $share->getPublicUrl($baseUrl),
                    'share_type' => $share->getShareType(),
                    'expires_at' => $share->getExpiresAt()?->format('c'),
                    'max_views' => $share->getMaxViews(),
                    'current_views' => $share->getCurrentViews(),
                    'watermark_enabled' => $share->isWatermarkEnabled(),
                    'download_enabled' => $share->isDownloadEnabled(),
                    'created_at' => $share->getCreatedAt()->format('c')
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create share', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to create share'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all shares for the authenticated user
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 30)));
            $snippetId = $request->query->get('snippet_id');

            $queryBuilder = $this->shareRepository->createQueryBuilder('s')
                ->where('s.createdByUser = :user')
                ->setParameter('user', $user)
                ->orderBy('s.createdAt', 'DESC')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);

            if ($snippetId) {
                $queryBuilder->andWhere('s.snippet = :snippet')
                    ->setParameter('snippet', $snippetId);
            }

            $shares = $queryBuilder->getQuery()->getResult();

            $totalQuery = $this->shareRepository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.createdByUser = :user')
                ->setParameter('user', $user);

            if ($snippetId) {
                $totalQuery->andWhere('s.snippet = :snippet')
                    ->setParameter('snippet', $snippetId);
            }

            $total = $totalQuery->getQuery()->getSingleScalarResult();

            $baseUrl = $this->getParameter('app.url') ?? $request->getSchemeAndHttpHost();
            $shareData = [];

            foreach ($shares as $share) {
                $shareData[] = [
                    'id' => $share->getId(),
                    'token' => $share->getShareToken(),
                    'url' => $share->getPublicUrl($baseUrl),
                    'share_type' => $share->getShareType(),
                    'snippet' => [
                        'id' => $share->getSnippet()->getId(),
                        'title' => $share->getSnippet()->getTitle(),
                        'language' => $share->getSnippet()->getLanguage()
                    ],
                    'expires_at' => $share->getExpiresAt()?->format('c'),
                    'remaining_time' => $share->getRemainingTime(),
                    'max_views' => $share->getMaxViews(),
                    'current_views' => $share->getCurrentViews(),
                    'remaining_views' => $share->getRemainingViews(),
                    'is_expired' => $share->isExpired(),
                    'is_revoked' => $share->isRevoked(),
                    'watermark_enabled' => $share->isWatermarkEnabled(),
                    'download_enabled' => $share->isDownloadEnabled(),
                    'last_accessed_at' => $share->getLastAccessedAt()?->format('c'),
                    'created_at' => $share->getCreatedAt()->format('c')
                ];
            }

            return $this->json([
                'shares' => $shareData,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to list shares', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve shares'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific share
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $share = $this->shareRepository->find($id);

            if (!$share) {
                return $this->json(['error' => 'Share not found'], Response::HTTP_NOT_FOUND);
            }

            if ($share->getCreatedByUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $baseUrl = $this->getParameter('app.url') ?? $this->get('request_stack')->getCurrentRequest()->getSchemeAndHttpHost();

            return $this->json([
                'share' => [
                    'id' => $share->getId(),
                    'token' => $share->getShareToken(),
                    'url' => $share->getPublicUrl($baseUrl),
                    'share_type' => $share->getShareType(),
                    'snippet' => [
                        'id' => $share->getSnippet()->getId(),
                        'title' => $share->getSnippet()->getTitle(),
                        'language' => $share->getSnippet()->getLanguage(),
                        'description' => $share->getSnippet()->getDescription()
                    ],
                    'allowed_emails' => $share->getAllowedEmails(),
                    'allowed_domains' => $share->getAllowedDomains(),
                    'require_authentication' => $share->requiresAuthentication(),
                    'expires_at' => $share->getExpiresAt()?->format('c'),
                    'remaining_time' => $share->getRemainingTime(),
                    'max_views' => $share->getMaxViews(),
                    'current_views' => $share->getCurrentViews(),
                    'remaining_views' => $share->getRemainingViews(),
                    'require_password' => $share->requiresPassword(),
                    'is_expired' => $share->isExpired(),
                    'is_revoked' => $share->isRevoked(),
                    'watermark_enabled' => $share->isWatermarkEnabled(),
                    'download_enabled' => $share->isDownloadEnabled(),
                    'last_accessed_at' => $share->getLastAccessedAt()?->format('c'),
                    'created_at' => $share->getCreatedAt()->format('c')
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve share', [
                'error' => $e->getMessage(),
                'share_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve share'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a share
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $share = $this->shareRepository->find($id);

            if (!$share) {
                return $this->json(['error' => 'Share not found'], Response::HTTP_NOT_FOUND);
            }

            if ($share->getCreatedByUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            // Update allowed fields
            if (isset($data['share_type'])) {
                if (!in_array($data['share_type'], [Share::TYPE_VIEW, Share::TYPE_EDIT, Share::TYPE_REVIEW], true)) {
                    return $this->json(['error' => 'Invalid share type'], Response::HTTP_BAD_REQUEST);
                }
                $share->setShareType($data['share_type']);
            }

            if (isset($data['allowed_emails'])) {
                if ($data['allowed_emails'] === null) {
                    $share->setAllowedEmails(null);
                } elseif (is_array($data['allowed_emails'])) {
                    $emails = array_filter($data['allowed_emails'], 'filter_var', FILTER_VALIDATE_EMAIL);
                    if (count($emails) !== count($data['allowed_emails'])) {
                        return $this->json(['error' => 'Some email addresses are invalid'], Response::HTTP_BAD_REQUEST);
                    }
                    $share->setAllowedEmails($emails);
                }
            }

            if (isset($data['allowed_domains'])) {
                $share->setAllowedDomains(is_array($data['allowed_domains']) ? $data['allowed_domains'] : null);
            }

            if (isset($data['require_authentication'])) {
                $share->setRequireAuthentication((bool) $data['require_authentication']);
            }

            if (isset($data['expires_in_hours']) && is_numeric($data['expires_in_hours'])) {
                if ($data['expires_in_hours'] > 0) {
                    $share->setExpirationInHours((int) $data['expires_in_hours']);
                } else {
                    $share->removeExpiration();
                }
            } elseif (isset($data['expires_in_days']) && is_numeric($data['expires_in_days'])) {
                if ($data['expires_in_days'] > 0) {
                    $share->setExpirationInDays((int) $data['expires_in_days']);
                } else {
                    $share->removeExpiration();
                }
            } elseif (isset($data['remove_expiration']) && $data['remove_expiration']) {
                $share->removeExpiration();
            }

            if (isset($data['max_views'])) {
                $share->setMaxViews(is_numeric($data['max_views']) && $data['max_views'] > 0 ? (int) $data['max_views'] : null);
            }

            if (isset($data['password'])) {
                if (!empty($data['password'])) {
                    $share->setPassword($data['password']);
                } else {
                    $share->setRequirePassword(false);
                    $share->setPasswordHash(null);
                }
            }

            if (isset($data['watermark_enabled'])) {
                $share->setWatermarkEnabled((bool) $data['watermark_enabled']);
            }

            if (isset($data['download_enabled'])) {
                $share->setDownloadEnabled((bool) $data['download_enabled']);
            }

            // Regenerate token if requested
            if (isset($data['regenerate_token']) && $data['regenerate_token']) {
                $share->regenerateShareToken();
            }

            // Validate share
            $errors = $this->validator->validate($share);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['error' => 'Validation failed', 'details' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->flush();

            $this->logger->info('Share updated', [
                'share_id' => $share->getId(),
                'user_id' => $user->getId()
            ]);

            $baseUrl = $this->getParameter('app.url') ?? $request->getSchemeAndHttpHost();

            return $this->json([
                'message' => 'Share updated successfully',
                'share' => [
                    'id' => $share->getId(),
                    'token' => $share->getShareToken(),
                    'url' => $share->getPublicUrl($baseUrl),
                    'share_type' => $share->getShareType(),
                    'expires_at' => $share->getExpiresAt()?->format('c'),
                    'max_views' => $share->getMaxViews(),
                    'watermark_enabled' => $share->isWatermarkEnabled(),
                    'download_enabled' => $share->isDownloadEnabled()
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update share', [
                'error' => $e->getMessage(),
                'share_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to update share'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Revoke a share
     */
    #[Route('/{id}/revoke', name: 'revoke', methods: ['POST'])]
    public function revoke(string $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $share = $this->shareRepository->find($id);

            if (!$share) {
                return $this->json(['error' => 'Share not found'], Response::HTTP_NOT_FOUND);
            }

            if ($share->getCreatedByUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $share->revoke($user);
            $this->entityManager->flush();

            $this->logger->info('Share revoked', [
                'share_id' => $share->getId(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['message' => 'Share revoked successfully']);

        } catch (\Exception $e) {
            $this->logger->error('Failed to revoke share', [
                'error' => $e->getMessage(),
                'share_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to revoke share'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a share
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $share = $this->shareRepository->find($id);

            if (!$share) {
                return $this->json(['error' => 'Share not found'], Response::HTTP_NOT_FOUND);
            }

            if ($share->getCreatedByUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $this->entityManager->remove($share);
            $this->entityManager->flush();

            $this->logger->info('Share deleted', [
                'share_id' => $share->getId(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['message' => 'Share deleted successfully']);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete share', [
                'error' => $e->getMessage(),
                'share_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to delete share'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}