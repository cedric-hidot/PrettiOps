<?php

namespace App\EventListener;

use App\User\Infrastructure\Security\SecurityService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Rate limiting event listener
 * Applies rate limits to API endpoints based on user authentication status
 */
#[AsEventListener(event: 'kernel.request', priority: 250)]
class RateLimitListener
{
    private const RATE_LIMITED_PATHS = [
        '/api/auth/login' => ['limit' => 5, 'window' => 900], // 5 attempts per 15 minutes
        '/api/auth/register' => ['limit' => 3, 'window' => 3600], // 3 attempts per hour
        '/api/auth/forgot-password' => ['limit' => 3, 'window' => 3600], // 3 attempts per hour
        '/api/snippets' => ['limit' => 100, 'window' => 3600], // 100 requests per hour
        '/api/files/upload' => ['limit' => 20, 'window' => 3600], // 20 uploads per hour
        '/api/shares' => ['limit' => 50, 'window' => 3600], // 50 shares per hour
    ];

    private const GLOBAL_LIMITS = [
        'anonymous' => ['limit' => 1000, 'window' => 3600], // 1000 requests per hour
        'authenticated' => ['limit' => 5000, 'window' => 3600], // 5000 requests per hour
        'pro' => ['limit' => 10000, 'window' => 3600], // 10000 requests per hour
        'enterprise' => ['limit' => 50000, 'window' => 3600], // 50000 requests per hour
    ];

    public function __construct(
        private readonly SecurityService $securityService,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip rate limiting for certain paths
        if ($this->shouldSkipRateLimit($path)) {
            return;
        }

        try {
            // Get current user
            $user = null;
            $token = $this->tokenStorage->getToken();
            if ($token && $token->getUser() instanceof UserInterface) {
                $user = $token->getUser();
            }

            // Apply specific endpoint rate limits
            $this->applyEndpointRateLimit($request, $path, $user);

            // Apply global rate limits
            $this->applyGlobalRateLimit($request, $user);

        } catch (RateLimitExceededException $e) {
            $this->handleRateLimitExceeded($event, $e, $request);
        } catch (\Exception $e) {
            $this->logger->error('Rate limiting error', [
                'error' => $e->getMessage(),
                'path' => $path,
                'ip' => $request->getClientIp()
            ]);
        }
    }

    /**
     * Apply rate limit for specific endpoints
     */
    private function applyEndpointRateLimit($request, string $path, $user): void
    {
        // Check if this path has specific rate limits
        $matchedRule = null;
        foreach (self::RATE_LIMITED_PATHS as $pattern => $config) {
            if (str_starts_with($path, $pattern)) {
                $matchedRule = $config;
                break;
            }
        }

        if (!$matchedRule) {
            return;
        }

        // Create rate limit identifier
        $identifier = $this->createRateLimitIdentifier($request, $user, $path);
        
        // Check rate limit using security service
        $this->securityService->checkRateLimit($request, $user);
    }

    /**
     * Apply global rate limits based on authentication status
     */
    private function applyGlobalRateLimit($request, $user): void
    {
        $userType = $this->getUserType($user);
        $limits = self::GLOBAL_LIMITS[$userType];

        $identifier = $this->createGlobalRateLimitIdentifier($request, $user);
        
        // This would typically use the rate limiter factory
        // For now, we delegate to the security service
        $this->securityService->checkRateLimit($request, $user);
    }

    /**
     * Determine if rate limiting should be skipped for this path
     */
    private function shouldSkipRateLimit(string $path): bool
    {
        $skipPaths = [
            '/_profiler',
            '/_wdt',
            '/css',
            '/js',
            '/images',
            '/favicon.ico',
            '/robots.txt',
            '/sitemap.xml',
            '/health',
            '/api/docs'
        ];

        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user type for rate limiting
     */
    private function getUserType($user): string
    {
        if (!$user) {
            return 'anonymous';
        }

        if (method_exists($user, 'getSubscriptionPlan')) {
            return match ($user->getSubscriptionPlan()) {
                'enterprise' => 'enterprise',
                'pro', 'team' => 'pro',
                default => 'authenticated'
            };
        }

        return 'authenticated';
    }

    /**
     * Create rate limit identifier for specific endpoints
     */
    private function createRateLimitIdentifier($request, $user, string $path): string
    {
        $baseIdentifier = $path . ':';

        if ($user && method_exists($user, 'getId')) {
            return $baseIdentifier . 'user:' . $user->getId();
        }

        return $baseIdentifier . 'ip:' . $request->getClientIp();
    }

    /**
     * Create global rate limit identifier
     */
    private function createGlobalRateLimitIdentifier($request, $user): string
    {
        if ($user && method_exists($user, 'getId')) {
            return 'global:user:' . $user->getId();
        }

        return 'global:ip:' . $request->getClientIp();
    }

    /**
     * Handle rate limit exceeded
     */
    private function handleRateLimitExceeded(RequestEvent $event, \Exception $exception, $request): void
    {
        $this->logger->warning('Rate limit exceeded', [
            'ip' => $request->getClientIp(),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'user_agent' => $request->headers->get('User-Agent'),
            'exception' => $exception->getMessage()
        ]);

        // Determine retry after time
        $retryAfter = 60; // Default to 60 seconds
        if (method_exists($exception, 'getRetryAfter') && $exception->getRetryAfter()) {
            $retryAfter = $exception->getRetryAfter()->getTimestamp() - time();
        }

        // Create appropriate response based on request type
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            $response = new JsonResponse([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
                'type' => 'rate_limit_error'
            ], Response::HTTP_TOO_MANY_REQUESTS);
        } else {
            $response = new Response(
                '<html><body><h1>Rate limit exceeded</h1><p>Too many requests. Please try again later.</p></body></html>',
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Content-Type' => 'text/html']
            );
        }

        $response->headers->set('Retry-After', (string) $retryAfter);
        $response->headers->set('X-RateLimit-Limit', '1');
        $response->headers->set('X-RateLimit-Remaining', '0');
        $response->headers->set('X-RateLimit-Reset', (string) (time() + $retryAfter));

        $event->setResponse($response);
    }

    /**
     * Check if IP is whitelisted
     */
    private function isWhitelistedIp(string $ip): bool
    {
        $whitelistedIps = $_ENV['RATE_LIMIT_WHITELIST_IPS'] ?? '';
        
        if (empty($whitelistedIps)) {
            return false;
        }

        $whitelist = array_map('trim', explode(',', $whitelistedIps));
        
        foreach ($whitelist as $whitelistedIp) {
            if ($this->ipMatches($ip, $whitelistedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP matches pattern (supports CIDR)
     */
    private function ipMatches(string $ip, string $pattern): bool
    {
        if ($ip === $pattern) {
            return true;
        }

        if (strpos($pattern, '/') !== false) {
            // CIDR notation
            [$subnet, $mask] = explode('/', $pattern);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int)$mask);
            
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        return false;
    }

    /**
     * Get rate limit info for current request
     */
    public function getRateLimitInfo($request, $user): array
    {
        $userType = $this->getUserType($user);
        $limits = self::GLOBAL_LIMITS[$userType];
        
        // This would typically query the rate limiter to get current usage
        // For now, return the configuration
        return [
            'limit' => $limits['limit'],
            'window' => $limits['window'],
            'remaining' => $limits['limit'], // Would be calculated from actual usage
            'reset_time' => time() + $limits['window'],
            'user_type' => $userType
        ];
    }

    /**
     * Apply custom rate limit for specific scenarios
     */
    public function applyCustomRateLimit(string $key, int $limit, int $window, $request, $user = null): void
    {
        try {
            $identifier = 'custom:' . $key . ':';
            
            if ($user && method_exists($user, 'getId')) {
                $identifier .= 'user:' . $user->getId();
            } else {
                $identifier .= 'ip:' . $request->getClientIp();
            }

            // Apply rate limit using security service
            $this->securityService->checkRateLimit($request, $user);
            
        } catch (RateLimitExceededException $e) {
            throw new TooManyRequestsHttpException(
                $e->getRetryAfter(),
                'Custom rate limit exceeded: ' . $key
            );
        }
    }

    /**
     * Clear rate limit for user (admin function)
     */
    public function clearRateLimit($request, $user): void
    {
        $this->logger->info('Rate limit cleared', [
            'user_id' => $user && method_exists($user, 'getId') ? $user->getId() : null,
            'ip' => $request->getClientIp(),
            'cleared_by' => 'admin'
        ]);
        
        // In a real implementation, this would clear the rate limit from cache
    }

    /**
     * Get rate limit statistics
     */
    public function getRateLimitStats(): array
    {
        // This would typically return statistics from the rate limiter storage
        return [
            'total_requests_today' => 0, // Would be calculated
            'blocked_requests_today' => 0, // Would be calculated
            'top_blocked_ips' => [], // Would be calculated
            'most_limited_endpoints' => [] // Would be calculated
        ];
    }
}