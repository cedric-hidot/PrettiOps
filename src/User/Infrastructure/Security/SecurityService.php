<?php

namespace App\User\Infrastructure\Security;

use App\User\Domain\Entity\User;
use App\Shared\Infrastructure\Security\Encryption\EncryptionService;
use App\Shared\Infrastructure\Security\TokenMaskingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Core security service for PrettiOps
 * Handles security validations, rate limiting, and threat detection
 */
class SecurityService
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = '15 minutes';
    private const SUSPICIOUS_PATTERNS = [
        '/\b(union|select|insert|update|delete|drop|create|alter)\b/i',
        '/<script[^>]*>.*?<\/script>/i',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/\beval\s*\(/i',
        '/\bexec\s*\(/i'
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EncryptionService $encryptionService,
        private readonly TokenMaskingService $tokenMaskingService,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $anonymousApiLimiter,
        private readonly RateLimiterFactory $authenticatedApiLimiter,
        private readonly RateLimiterFactory $apiKeyLimiter
    ) {
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        // Check for common patterns
        if (preg_match('/(.)\1{3,}/', $password)) {
            $errors[] = 'Password cannot contain more than 3 consecutive identical characters';
        }
        
        // Check against common passwords
        $commonPasswords = [
            'password', 'password123', '123456', '123456789', 'qwerty',
            'abc123', 'password1', 'admin', 'letmein', 'welcome'
        ];
        
        if (in_array(strtolower($password), $commonPasswords, true)) {
            $errors[] = 'Password is too common. Please choose a more secure password';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $this->calculatePasswordStrength($password)
        ];
    }

    /**
     * Handle failed login attempt
     */
    public function handleFailedLogin(User $user, Request $request): void
    {
        $user->setFailedLoginAttempts($user->getFailedLoginAttempts() + 1);
        
        $this->logger->warning('Failed login attempt', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'attempts' => $user->getFailedLoginAttempts()
        ]);

        if ($user->getFailedLoginAttempts() >= self::MAX_LOGIN_ATTEMPTS) {
            $lockoutTime = new \DateTimeImmutable('+' . self::LOCKOUT_DURATION);
            $user->setLockedUntil($lockoutTime);
            
            $this->logger->critical('User account locked due to excessive failed login attempts', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'ip' => $request->getClientIp(),
                'locked_until' => $lockoutTime->format('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Handle successful login
     */
    public function handleSuccessfulLogin(User $user, Request $request): void
    {
        // Reset failed attempts
        $user->setFailedLoginAttempts(0);
        $user->setLockedUntil(null);
        $user->setLastLoginAt(new \DateTimeImmutable());
        $user->setLastLoginIp($request->getClientIp());

        $this->logger->info('Successful login', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent')
        ]);
    }

    /**
     * Check if user is locked out
     */
    public function isUserLockedOut(User $user): bool
    {
        if ($user->getLockedUntil() === null) {
            return false;
        }

        if ($user->getLockedUntil() <= new \DateTimeImmutable()) {
            // Lockout has expired, clear it
            $user->setLockedUntil(null);
            $user->setFailedLoginAttempts(0);
            return false;
        }

        return true;
    }

    /**
     * Validate and sanitize user input
     */
    public function sanitizeInput(string $input, array $options = []): string
    {
        $maxLength = $options['max_length'] ?? 1000;
        $allowHtml = $options['allow_html'] ?? false;
        $strict = $options['strict'] ?? false;

        // Truncate if too long
        if (strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }

        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Normalize line endings
        $input = str_replace(["\r\n", "\r"], "\n", $input);

        if (!$allowHtml) {
            $input = strip_tags($input);
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if ($strict) {
            // Remove any potentially dangerous characters
            $input = preg_replace('/[^\p{L}\p{N}\s\-_.@]/u', '', $input);
        }

        return trim($input);
    }

    /**
     * Detect suspicious content
     */
    public function detectSuspiciousContent(string $content): array
    {
        $issues = [];

        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $issues[] = 'Potentially malicious content detected';
                break;
            }
        }

        // Check for excessive length
        if (strlen($content) > 50000) {
            $issues[] = 'Content exceeds maximum allowed length';
        }

        // Check for sensitive data
        if ($this->tokenMaskingService->containsSensitiveData($content)) {
            $stats = $this->tokenMaskingService->getSensitiveDataStats($content);
            if ($stats['high_confidence'] > 0) {
                $issues[] = 'Content contains sensitive data with high confidence';
            }
        }

        // Check for binary content
        if ($this->containsBinaryData($content)) {
            $issues[] = 'Content appears to contain binary data';
        }

        return [
            'suspicious' => !empty($issues),
            'issues' => $issues,
            'risk_level' => $this->calculateRiskLevel($issues)
        ];
    }

    /**
     * Apply rate limiting
     */
    public function checkRateLimit(Request $request, ?User $user = null, ?string $apiKey = null): bool
    {
        $identifier = $this->getRateLimitIdentifier($request, $user, $apiKey);
        
        if ($apiKey) {
            $limiter = $this->apiKeyLimiter->create($identifier);
        } elseif ($user) {
            $limiter = $this->authenticatedApiLimiter->create($identifier);
        } else {
            $limiter = $this->anonymousApiLimiter->create($identifier);
        }

        $limit = $limiter->consume();
        
        if (!$limit->isAccepted()) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'ip' => $request->getClientIp(),
                'user_id' => $user?->getId(),
                'retry_after' => $limit->getRetryAfter()->format('Y-m-d H:i:s')
            ]);
            
            throw new TooManyLoginAttemptsAuthenticationException($limit->getRetryAfter()->getTimestamp());
        }

        return true;
    }

    /**
     * Validate IP address
     */
    public function validateIpAddress(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Block private networks in production
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
            return true;
        }

        // Allow private networks in development
        return $_ENV['APP_ENV'] !== 'prod';
    }

    /**
     * Generate secure token
     */
    public function generateSecureToken(int $length = 32): string
    {
        $bytes = random_bytes($length);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Hash password securely
     */
    public function hashPassword(User $user, string $plainPassword): string
    {
        return $this->passwordHasher->hashPassword($user, $plainPassword);
    }

    /**
     * Verify password
     */
    public function verifyPassword(User $user, string $plainPassword): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }

    /**
     * Check if request comes from trusted source
     */
    public function isTrustedRequest(Request $request): bool
    {
        $trustedIps = $_ENV['TRUSTED_IPS'] ?? '';
        if (empty($trustedIps)) {
            return false;
        }

        $clientIp = $request->getClientIp();
        $trustedIpList = array_map('trim', explode(',', $trustedIps));

        foreach ($trustedIpList as $trustedIp) {
            if ($this->ipMatches($clientIp, $trustedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate CSRF token
     */
    public function generateCsrfToken(): string
    {
        return $this->generateSecureToken(40);
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(string $token, string $expected): bool
    {
        return hash_equals($expected, $token);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        $this->logger->warning($event, array_merge([
            'timestamp' => new \DateTimeImmutable(),
            'event_type' => 'security'
        ], $context));
    }

    /**
     * Calculate password strength score
     */
    private function calculatePasswordStrength(string $password): int
    {
        $score = 0;
        $length = strlen($password);

        // Length bonus
        $score += min($length * 2, 20);

        // Character variety
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 10;
        if (preg_match('/[0-9]/', $password)) $score += 10;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 15;

        // Patterns
        if ($length >= 12) $score += 10;
        if ($length >= 16) $score += 10;

        // Penalties
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 10;
        if (preg_match('/123|abc|qwe/i', $password)) $score -= 15;

        return max(0, min(100, $score));
    }

    /**
     * Check if content contains binary data
     */
    private function containsBinaryData(string $content): bool
    {
        return !ctype_print($content) && !preg_match('//u', $content);
    }

    /**
     * Calculate risk level based on issues
     */
    private function calculateRiskLevel(array $issues): string
    {
        $count = count($issues);
        
        if ($count === 0) return 'low';
        if ($count <= 2) return 'medium';
        return 'high';
    }

    /**
     * Get rate limit identifier
     */
    private function getRateLimitIdentifier(Request $request, ?User $user, ?string $apiKey): string
    {
        if ($apiKey) {
            return 'api_key:' . hash('sha256', $apiKey);
        }

        if ($user) {
            return 'user:' . $user->getId();
        }

        return 'ip:' . $request->getClientIp();
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
            list($subnet, $mask) = explode('/', $pattern);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int)$mask);
            
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        return false;
    }

    /**
     * Generate secure session ID
     */
    public function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate session ID format
     */
    public function validateSessionId(string $sessionId): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $sessionId) === 1;
    }

    /**
     * Check if user has required permissions
     */
    public function hasPermission(User $user, string $permission): bool
    {
        $userRoles = $user->getRoles();
        
        $permissions = [
            'SNIPPET_CREATE' => ['ROLE_USER'],
            'SNIPPET_EDIT' => ['ROLE_USER'],
            'SNIPPET_DELETE' => ['ROLE_USER'],
            'SNIPPET_SHARE' => ['ROLE_USER'],
            'ATTACHMENT_UPLOAD' => ['ROLE_USER'],
            'ADMIN_ACCESS' => ['ROLE_ADMIN'],
            'SYSTEM_SETTINGS' => ['ROLE_SUPER_ADMIN']
        ];

        if (!isset($permissions[$permission])) {
            return false;
        }

        $requiredRoles = $permissions[$permission];
        return !empty(array_intersect($userRoles, $requiredRoles));
    }
}