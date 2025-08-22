<?php

namespace App\Controller\Web;

use App\Repository\ShareRepository;
use App\Service\SecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Share View Controller
 * Handles public access to shared snippets via share tokens
 */
class ShareViewController extends AbstractController
{
    public function __construct(
        private readonly ShareRepository $shareRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityService $securityService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * View a shared snippet
     */
    #[Route('/share/{token}', name: 'app_share_view', methods: ['GET', 'POST'])]
    public function view(string $token, Request $request): Response
    {
        try {
            $share = $this->shareRepository->findOneBy(['shareToken' => $token]);

            if (!$share) {
                throw $this->createNotFoundException('Share not found');
            }

            // Get user email if authenticated
            $userEmail = null;
            $user = $this->getUser();
            if ($user && method_exists($user, 'getEmail')) {
                $userEmail = $user->getEmail();
            }

            // Check if share is accessible
            if (!$share->isAccessible($userEmail)) {
                if ($share->isRevoked()) {
                    throw $this->createNotFoundException('This share has been revoked');
                }
                if ($share->isExpired()) {
                    throw $this->createNotFoundException('This share has expired');
                }
                if ($share->hasReachedMaxViews()) {
                    throw $this->createNotFoundException('This share has reached its maximum view limit');
                }
                if ($userEmail && !$share->isEmailAllowed($userEmail)) {
                    return $this->render('share/access_denied.html.twig', [
                        'message' => 'Your email address is not allowed to access this share'
                    ]);
                }
                if ($userEmail && !$share->isDomainAllowed($userEmail)) {
                    return $this->render('share/access_denied.html.twig', [
                        'message' => 'Your email domain is not allowed to access this share'
                    ]);
                }
            }

            // Check authentication requirement
            if ($share->requiresAuthentication() && !$user) {
                $request->getSession()->set('_security.target_path', $request->getUri());
                return $this->redirectToRoute('app_login');
            }

            // Handle password protection
            if ($share->requiresPassword()) {
                $passwordProvided = false;
                $passwordError = null;

                if ($request->isMethod('POST')) {
                    $password = $request->request->get('password', '');
                    if ($share->verifyPassword($password)) {
                        $passwordProvided = true;
                        // Store password verification in session to avoid repeated prompts
                        $request->getSession()->set('share_password_' . $token, true);
                    } else {
                        $passwordError = 'Incorrect password';
                    }
                } elseif ($request->getSession()->get('share_password_' . $token)) {
                    $passwordProvided = true;
                }

                if (!$passwordProvided) {
                    return $this->render('share/password_required.html.twig', [
                        'share' => $share,
                        'error' => $passwordError
                    ]);
                }
            }

            // Get the snippet
            $snippet = $share->getSnippet();

            // Increment view count and update access tracking
            $share->incrementViews();
            $share->setLastAccessedBy(
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent')
            );

            $this->entityManager->flush();

            $this->logger->info('Shared snippet accessed', [
                'share_id' => $share->getId(),
                'snippet_id' => $snippet->getId(),
                'token' => $token,
                'user_email' => $userEmail,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);

            // Check if this is a view-only or interactive share
            $canEdit = $share->canEdit() && $user !== null;
            $canReview = $share->canReview() && $user !== null;

            return $this->render('share/view.html.twig', [
                'share' => $share,
                'snippet' => $snippet,
                'can_edit' => $canEdit,
                'can_review' => $canReview,
                'is_owner' => $user && $snippet->getUser() === $user,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Share view failed', [
                'error' => $e->getMessage(),
                'token' => $token,
                'ip' => $request->getClientIp()
            ]);

            throw $this->createNotFoundException('Share not available');
        }
    }

    /**
     * Download shared snippet
     */
    #[Route('/share/{token}/download', name: 'app_share_download', methods: ['GET'])]
    public function download(string $token, Request $request): Response
    {
        try {
            $share = $this->shareRepository->findOneBy(['shareToken' => $token]);

            if (!$share) {
                throw $this->createNotFoundException('Share not found');
            }

            if (!$share->isDownloadEnabled()) {
                throw $this->createAccessDeniedException('Downloads are not enabled for this share');
            }

            // Get user email if authenticated
            $userEmail = null;
            $user = $this->getUser();
            if ($user && method_exists($user, 'getEmail')) {
                $userEmail = $user->getEmail();
            }

            // Check if share is accessible
            if (!$share->isAccessible($userEmail)) {
                throw $this->createNotFoundException('Share not accessible');
            }

            // Check authentication requirement
            if ($share->requiresAuthentication() && !$user) {
                throw $this->createAccessDeniedException('Authentication required');
            }

            // Check password protection (check session)
            if ($share->requiresPassword() && !$request->getSession()->get('share_password_' . $token)) {
                throw $this->createAccessDeniedException('Password verification required');
            }

            $snippet = $share->getSnippet();
            
            // Create the file content
            $content = $snippet->getContent();
            $filename = $this->sanitizeFilename($snippet->getTitle()) . '.' . $this->getFileExtension($snippet->getLanguage());

            $this->logger->info('Shared snippet downloaded', [
                'share_id' => $share->getId(),
                'snippet_id' => $snippet->getId(),
                'token' => $token,
                'filename' => $filename,
                'user_email' => $userEmail,
                'ip' => $request->getClientIp()
            ]);

            return new Response(
                $content,
                200,
                [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Content-Length' => strlen($content),
                ]
            );

        } catch (\Exception $e) {
            $this->logger->error('Share download failed', [
                'error' => $e->getMessage(),
                'token' => $token,
                'ip' => $request->getClientIp()
            ]);

            throw $this->createNotFoundException('Download not available');
        }
    }

    /**
     * Raw view of shared snippet (for embedding)
     */
    #[Route('/share/{token}/raw', name: 'app_share_raw', methods: ['GET'])]
    public function raw(string $token, Request $request): Response
    {
        try {
            $share = $this->shareRepository->findOneBy(['shareToken' => $token]);

            if (!$share) {
                throw $this->createNotFoundException('Share not found');
            }

            // Get user email if authenticated
            $userEmail = null;
            $user = $this->getUser();
            if ($user && method_exists($user, 'getEmail')) {
                $userEmail = $user->getEmail();
            }

            // Check if share is accessible
            if (!$share->isAccessible($userEmail)) {
                throw $this->createNotFoundException('Share not accessible');
            }

            // Check authentication requirement
            if ($share->requiresAuthentication() && !$user) {
                return new Response('Authentication required', 401, ['Content-Type' => 'text/plain']);
            }

            // Check password protection
            if ($share->requiresPassword() && !$request->getSession()->get('share_password_' . $token)) {
                return new Response('Password verification required', 401, ['Content-Type' => 'text/plain']);
            }

            $snippet = $share->getSnippet();
            $content = $snippet->getContent();

            // Add watermark if enabled
            if ($share->isWatermarkEnabled()) {
                $watermark = "\n/* Shared via PrettiOps - " . $request->getSchemeAndHttpHost() . " */\n";
                $content = $watermark . $content . $watermark;
            }

            // Increment view count
            $share->incrementViews();
            $share->setLastAccessedBy(
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent')
            );

            $this->entityManager->flush();

            return new Response(
                $content,
                200,
                [
                    'Content-Type' => 'text/plain; charset=utf-8',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'ALLOWALL', // Allow embedding
                    'Access-Control-Allow-Origin' => '*', // Allow cross-origin requests
                ]
            );

        } catch (\Exception $e) {
            $this->logger->error('Share raw view failed', [
                'error' => $e->getMessage(),
                'token' => $token,
                'ip' => $request->getClientIp()
            ]);

            return new Response('Share not available', 404, ['Content-Type' => 'text/plain']);
        }
    }

    /**
     * Sanitize filename for download
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^\w\-_\.]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename);
        $filename = trim($filename, '_');
        
        return $filename ?: 'snippet';
    }

    /**
     * Get file extension based on language
     */
    private function getFileExtension(string $language): string
    {
        return match (strtolower($language)) {
            'javascript' => 'js',
            'typescript' => 'ts',
            'php' => 'php',
            'python' => 'py',
            'java' => 'java',
            'csharp' => 'cs',
            'cpp' => 'cpp',
            'go' => 'go',
            'rust' => 'rs',
            'ruby' => 'rb',
            'kotlin' => 'kt',
            'swift' => 'swift',
            'scala' => 'scala',
            'bash', 'shell' => 'sh',
            'sql' => 'sql',
            'html' => 'html',
            'css' => 'css',
            'scss' => 'scss',
            'json' => 'json',
            'xml' => 'xml',
            'yaml' => 'yml',
            'markdown' => 'md',
            default => 'txt'
        };
    }
}