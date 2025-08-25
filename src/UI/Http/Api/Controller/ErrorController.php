<?php

namespace App\UI\Http\Api\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Domain\User\Entity\User;

/**
 * API Error Controller
 * Handles error reporting from frontend JavaScript
 */
#[Route('/api', name: 'api_')]
class ErrorController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Report JavaScript error from frontend
     * Note: This endpoint is public to allow error reporting even when not authenticated
     */
    #[Route('/errors', name: 'errors', methods: ['POST'])]
    public function reportError(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            $errorType = $data['type'] ?? 'unknown';
            $message = $data['message'] ?? 'No error message provided';
            $stack = $data['stack'] ?? null;
            $url = $data['url'] ?? $request->headers->get('Referer', 'unknown');
            $userAgent = $request->headers->get('User-Agent');
            $timestamp = $data['timestamp'] ?? time();

            // Validate error type
            $allowedTypes = ['javascript', 'network', 'syntax', 'reference', 'type', 'range', 'eval', 'uri', 'security'];
            if (!in_array($errorType, $allowedTypes)) {
                $errorType = 'unknown';
            }

            // Log the error with context
            $context = [
                'error_type' => $errorType,
                'message' => $message,
                'url' => $url,
                'user_agent' => $userAgent,
                'timestamp' => $timestamp,
                'user_id' => null,
                'user_email' => null,
                'ip_address' => $request->getClientIp(),
                'session_id' => $request->getSession()?->getId()
            ];

            if ($stack) {
                $context['stack_trace'] = $stack;
            }

            // Log with appropriate level based on error type
            $logLevel = match ($errorType) {
                'security' => 'critical',
                'syntax', 'reference', 'type' => 'error',
                'network' => 'warning',
                default => 'error'
            };

            $this->logger->log($logLevel, "Frontend error reported: {$message}", $context);

            // Check if this is a critical error that needs immediate attention
            $isCritical = in_array($errorType, ['security', 'syntax']) || 
                         str_contains(strtolower($message), 'csrf') ||
                         str_contains(strtolower($message), 'unauthorized');

            if ($isCritical) {
                $this->logger->critical('Critical frontend error detected', $context);
            }

            return $this->json([
                'message' => 'Error reported successfully',
                'error_id' => uniqid('err_', true),
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to process error report', [
                'error' => $e->getMessage(),
                'request_content' => $request->getContent()
            ]);

            return $this->json([
                'error' => 'Failed to process error report'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get error statistics for debugging (admin only)
     */
    #[Route('/errors/stats', name: 'error_stats', methods: ['GET'])]
    public function errorStats(#[CurrentUser] ?User $user): JsonResponse
    {
        // For now, return basic stats
        // In production, this would query an error tracking database
        
        if (!$user || !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'stats' => [
                'total_errors_today' => 0,
                'critical_errors_today' => 0,
                'most_common_errors' => [],
                'error_rate_trend' => 'stable',
                'last_updated' => (new \DateTime())->format('c')
            ]
        ]);
    }

    /**
     * Performance metrics endpoint
     * Note: This endpoint is public to allow performance reporting even when not authenticated
     */
    #[Route('/performance', name: 'performance', methods: ['POST'])]
    public function performance(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            // Log performance metrics
            $this->logger->info('Performance metrics reported', [
                'metrics' => $data,
                'user_id' => null,
                'url' => $request->headers->get('Referer'),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => time()
            ]);

            return $this->json([
                'message' => 'Performance metrics recorded',
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to process performance metrics', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to process performance metrics'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Health check endpoint
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => time(),
            'version' => '1.0.0',
            'environment' => $_ENV['APP_ENV'] ?? 'production'
        ]);
    }
}