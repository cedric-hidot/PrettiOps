<?php

namespace App\UI\Http\Api\Controller;

use App\Domain\Snippet\Entity\Attachment;
use App\Domain\User\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Repository\AttachmentRepository;
use App\Infrastructure\Storage\FileUploadService;
use App\Infrastructure\Security\SecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * API File Controller
 * Handles file uploads, downloads, and attachment management
 */
#[Route('/api/files', name: 'api_files_')]
class FileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AttachmentRepository $attachmentRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly SecurityService $securityService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Upload a file
     */
    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('file');
            
            if (!$uploadedFile) {
                return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
            }

            // Get additional parameters
            $customFilename = $request->request->get('filename');
            $encrypt = $request->request->getBoolean('encrypt', false);
            $description = $request->request->get('description');

            // Security check - apply rate limiting
            $this->securityService->checkRateLimit($request, $user);

            // Upload file
            $attachment = $this->fileUploadService->uploadFile(
                $uploadedFile,
                $user,
                $customFilename,
                $encrypt
            );

            // Set additional properties
            if ($description) {
                $attachment->setDescription($this->securityService->sanitizeInput($description));
            }

            // Save attachment
            $this->entityManager->persist($attachment);
            $this->entityManager->flush();

            $this->logger->info('File uploaded successfully', [
                'attachment_id' => $attachment->getId(),
                'user_id' => $user->getId(),
                'filename' => $attachment->getFilename(),
                'size' => $attachment->getFileSize(),
                'encrypted' => $attachment->isEncrypted()
            ]);

            return $this->json([
                'message' => 'File uploaded successfully',
                'attachment' => $this->serializeAttachment($attachment)
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            $this->logger->error('File upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);
            
            return $this->json(['error' => 'File upload failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during file upload', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Upload failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get user's files
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, min(100, (int) $request->query->get('limit', 30)));
            $search = $request->query->get('search', '');
            $mimeType = $request->query->get('mime_type', '');
            $sortBy = $request->query->get('sort_by', 'uploaded_at');
            $sortOrder = $request->query->get('sort_order', 'DESC');

            $queryBuilder = $this->attachmentRepository->createQueryBuilder('a')
                ->where('a.user = :user')
                ->andWhere('a.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->orderBy("a.$sortBy", $sortOrder)
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);

            // Apply filters
            if ($search) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->like('a.originalFilename', ':search'),
                        $queryBuilder->expr()->like('a.description', ':search')
                    )
                )->setParameter('search', '%' . $search . '%');
            }

            if ($mimeType) {
                if (str_contains($mimeType, '/')) {
                    // Exact MIME type
                    $queryBuilder->andWhere('a.mimeType = :mimeType')
                        ->setParameter('mimeType', $mimeType);
                } else {
                    // MIME type category (e.g., "image", "video")
                    $queryBuilder->andWhere('a.mimeType LIKE :mimeType')
                        ->setParameter('mimeType', $mimeType . '/%');
                }
            }

            $attachments = $queryBuilder->getQuery()->getResult();

            // Get total count for pagination
            $totalQuery = $this->attachmentRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.user = :user')
                ->andWhere('a.deletedAt IS NULL')
                ->setParameter('user', $user);

            if ($search) {
                $totalQuery->andWhere(
                    $totalQuery->expr()->orX(
                        $totalQuery->expr()->like('a.originalFilename', ':search'),
                        $totalQuery->expr()->like('a.description', ':search')
                    )
                )->setParameter('search', '%' . $search . '%');
            }

            if ($mimeType) {
                if (str_contains($mimeType, '/')) {
                    $totalQuery->andWhere('a.mimeType = :mimeType')
                        ->setParameter('mimeType', $mimeType);
                } else {
                    $totalQuery->andWhere('a.mimeType LIKE :mimeType')
                        ->setParameter('mimeType', $mimeType . '/%');
                }
            }

            $total = $totalQuery->getQuery()->getSingleScalarResult();

            $attachmentData = [];
            foreach ($attachments as $attachment) {
                $attachmentData[] = $this->serializeAttachment($attachment);
            }

            return $this->json([
                'attachments' => $attachmentData,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to list files', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve files'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific file's metadata
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $attachment = $this->attachmentRepository->find($id);

            if (!$attachment) {
                return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
            }

            if ($attachment->getUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $metadata = $this->fileUploadService->getFileMetadata($attachment);

            return $this->json([
                'attachment' => $this->serializeAttachment($attachment, true),
                'metadata' => $metadata
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve file', [
                'error' => $e->getMessage(),
                'attachment_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Download a file
     */
    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function download(string $id, #[CurrentUser] User $user): Response
    {
        try {
            $attachment = $this->attachmentRepository->find($id);

            if (!$attachment) {
                return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
            }

            if ($attachment->getUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            if (!$this->fileUploadService->isFileAccessible($attachment)) {
                return $this->json(['error' => 'File not accessible'], Response::HTTP_NOT_FOUND);
            }

            // Get file content
            $content = $this->fileUploadService->getFileContent($attachment);

            // Create streamed response for better memory usage with large files
            $response = new StreamedResponse(function() use ($content) {
                echo $content;
            });

            // Set headers
            $response->headers->set('Content-Type', $attachment->getMimeType());
            $response->headers->set('Content-Length', (string) strlen($content));
            
            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $attachment->getOriginalFilename()
            );
            $response->headers->set('Content-Disposition', $disposition);

            // Update download count/last accessed
            $attachment->setLastDownloadedAt(new \DateTimeImmutable());
            $attachment->setDownloadCount($attachment->getDownloadCount() + 1);
            $this->entityManager->flush();

            $this->logger->info('File downloaded', [
                'attachment_id' => $attachment->getId(),
                'user_id' => $user->getId(),
                'filename' => $attachment->getOriginalFilename()
            ]);

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('File download failed', [
                'error' => $e->getMessage(),
                'attachment_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Download failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update file metadata
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $attachment = $this->attachmentRepository->find($id);

            if (!$attachment) {
                return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
            }

            if ($attachment->getUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            $updated = false;

            if (isset($data['description'])) {
                $description = $this->securityService->sanitizeInput($data['description']);
                if ($description !== $attachment->getDescription()) {
                    $attachment->setDescription($description);
                    $updated = true;
                }
            }

            if (isset($data['tags'])) {
                $tags = is_array($data['tags']) ? $data['tags'] : null;
                if ($tags !== $attachment->getTags()) {
                    $attachment->setTags($tags);
                    $updated = true;
                }
            }

            if ($updated) {
                $this->entityManager->flush();

                $this->logger->info('File metadata updated', [
                    'attachment_id' => $attachment->getId(),
                    'user_id' => $user->getId()
                ]);
            }

            return $this->json([
                'message' => $updated ? 'File updated successfully' : 'No changes detected',
                'attachment' => $this->serializeAttachment($attachment)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update file', [
                'error' => $e->getMessage(),
                'attachment_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to update file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a file
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $attachment = $this->attachmentRepository->find($id);

            if (!$attachment) {
                return $this->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
            }

            if ($attachment->getUser() !== $user) {
                return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
            }

            // Delete physical file
            $fileDeleted = $this->fileUploadService->deleteFile($attachment);
            
            if (!$fileDeleted) {
                $this->logger->warning('Physical file deletion failed, but continuing with database cleanup', [
                    'attachment_id' => $attachment->getId(),
                    'filename' => $attachment->getFilename()
                ]);
            }

            // Soft delete in database
            $attachment->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('File deleted', [
                'attachment_id' => $attachment->getId(),
                'user_id' => $user->getId(),
                'filename' => $attachment->getOriginalFilename(),
                'physical_file_deleted' => $fileDeleted
            ]);

            return $this->json(['message' => 'File deleted successfully']);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete file', [
                'error' => $e->getMessage(),
                'attachment_id' => $id,
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to delete file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get file upload limits and settings
     */
    #[Route('/limits', name: 'limits', methods: ['GET'])]
    public function limits(#[CurrentUser] User $user): JsonResponse
    {
        $maxFileSize = (int) ($_ENV['MAX_FILE_SIZE'] ?? 10485760); // 10MB default
        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'pdf', 'doc', 'docx', 'txt', 'md', 'rtf',
            'xls', 'xlsx', 'csv', 'ods',
            'ppt', 'pptx', 'odp',
            'zip', 'tar', 'gz', '7z',
            'json', 'xml', 'yaml', 'yml', 'sql'
        ];

        return $this->json([
            'limits' => [
                'max_file_size' => $maxFileSize,
                'max_file_size_formatted' => $this->fileUploadService->formatFileSize($maxFileSize),
                'allowed_extensions' => $allowedExtensions,
                'max_files_per_upload' => 1, // Single file upload for now
                'total_storage_used' => $this->getTotalStorageUsed($user),
                'encryption_available' => true,
                'virus_scanning' => true
            ]
        ]);
    }

    /**
     * Get file statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $totalFiles = $this->attachmentRepository->count(['user' => $user, 'deletedAt' => null]);
            
            $totalSize = $this->attachmentRepository->createQueryBuilder('a')
                ->select('SUM(a.fileSize)')
                ->where('a.user = :user')
                ->andWhere('a.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;

            $totalDownloads = $this->attachmentRepository->createQueryBuilder('a')
                ->select('SUM(a.downloadCount)')
                ->where('a.user = :user')
                ->andWhere('a.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;

            $filesByType = $this->attachmentRepository->createQueryBuilder('a')
                ->select('a.mimeType, COUNT(a.id) as count, SUM(a.fileSize) as total_size')
                ->where('a.user = :user')
                ->andWhere('a.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->groupBy('a.mimeType')
                ->orderBy('count', 'DESC')
                ->getQuery()
                ->getResult();

            return $this->json([
                'stats' => [
                    'total_files' => $totalFiles,
                    'total_size' => $totalSize,
                    'total_size_formatted' => $this->fileUploadService->formatFileSize($totalSize),
                    'total_downloads' => $totalDownloads,
                    'files_by_type' => array_map(function($item) {
                        return [
                            'mime_type' => $item['mimeType'],
                            'count' => $item['count'],
                            'total_size' => $item['total_size'],
                            'total_size_formatted' => $this->fileUploadService->formatFileSize($item['total_size']),
                            'icon' => $this->fileUploadService->getFileIcon($item['mimeType'])
                        ];
                    }, $filesByType)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve file stats', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json(['error' => 'Failed to retrieve statistics'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Serialize attachment for API response
     */
    private function serializeAttachment(Attachment $attachment, bool $includeExtendedInfo = false): array
    {
        $data = [
            'id' => $attachment->getId(),
            'original_filename' => $attachment->getOriginalFilename(),
            'filename' => $attachment->getFilename(),
            'mime_type' => $attachment->getMimeType(),
            'file_size' => $attachment->getFileSize(),
            'file_size_formatted' => $this->fileUploadService->formatFileSize($attachment->getFileSize()),
            'description' => $attachment->getDescription(),
            'tags' => $attachment->getTags(),
            'encrypted' => $attachment->isEncrypted(),
            'download_count' => $attachment->getDownloadCount(),
            'uploaded_at' => $attachment->getUploadedAt()->format('c'),
            'last_downloaded_at' => $attachment->getLastDownloadedAt()?->format('c'),
            'icon' => $this->fileUploadService->getFileIcon($attachment->getMimeType())
        ];

        if ($includeExtendedInfo) {
            $data = array_merge($data, [
                'file_hash' => $attachment->getFileHash(),
                'virus_scan_result' => $attachment->getVirusScanResult(),
                'security_scan_result' => $attachment->getSecurityScanResult(),
                'encryption_iv' => $attachment->isEncrypted() ? '[ENCRYPTED]' : null
            ]);
        }

        return $data;
    }

    /**
     * Get total storage used by user
     */
    private function getTotalStorageUsed(User $user): int
    {
        return (int) $this->attachmentRepository->createQueryBuilder('a')
            ->select('COALESCE(SUM(a.fileSize), 0)')
            ->where('a.user = :user')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}