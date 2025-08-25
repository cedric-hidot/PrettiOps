<?php

namespace App\User\Infrastructure\Security\OAuth2;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * JWT Token management service
 * Handles token generation, validation, and refresh token logic
 */
class JwtTokenService
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate access token for user
     */
    public function generateToken(User $user): string
    {
        $payload = [
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsernameField(),
            'roles' => $user->getRoles(),
            'subscription_plan' => $user->getSubscriptionPlan(),
            'iat' => time(),
            'exp' => time() + $this->getTokenTtl()
        ];

        $token = $this->jwtManager->createFromPayload($user, $payload);

        $this->logger->info('JWT token generated', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'expires_at' => date('Y-m-d H:i:s', $payload['exp'])
        ]);

        return $token;
    }

    /**
     * Generate token with custom payload
     */
    public function generateTokenWithPayload(User $user, array $customPayload = []): string
    {
        $defaultPayload = [
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsernameField(),
            'roles' => $user->getRoles(),
            'subscription_plan' => $user->getSubscriptionPlan(),
            'iat' => time(),
            'exp' => time() + $this->getTokenTtl()
        ];

        $payload = array_merge($defaultPayload, $customPayload);
        return $this->jwtManager->createFromPayload($user, $payload);
    }

    /**
     * Parse token and extract payload
     */
    public function parseToken(string $token): array
    {
        try {
            return $this->jwtManager->parse($token);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse JWT token', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 20) . '...'
            ]);
            
            return [];
        }
    }

    /**
     * Check if token is valid
     */
    public function isTokenValid(string $token): bool
    {
        try {
            $payload = $this->parseToken($token);
            
            if (empty($payload)) {
                return false;
            }

            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }

            // Check required fields
            if (!isset($payload['sub']) || !isset($payload['email'])) {
                return false;
            }

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Extract user ID from token
     */
    public function getUserIdFromToken(string $token): ?string
    {
        $payload = $this->parseToken($token);
        return $payload['sub'] ?? null;
    }

    /**
     * Extract user email from token
     */
    public function getEmailFromToken(string $token): ?string
    {
        $payload = $this->parseToken($token);
        return $payload['email'] ?? null;
    }

    /**
     * Get token expiration time
     */
    public function getTokenExpiration(string $token): ?\DateTimeImmutable
    {
        $payload = $this->parseToken($token);
        
        if (!isset($payload['exp'])) {
            return null;
        }

        return new \DateTimeImmutable('@' . $payload['exp']);
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(string $token): bool
    {
        $expiration = $this->getTokenExpiration($token);
        
        if (!$expiration) {
            return true;
        }

        return $expiration <= new \DateTimeImmutable();
    }

    /**
     * Get time until token expires (in seconds)
     */
    public function getTimeUntilExpiration(string $token): ?int
    {
        $expiration = $this->getTokenExpiration($token);
        
        if (!$expiration) {
            return null;
        }

        $now = new \DateTimeImmutable();
        if ($expiration <= $now) {
            return 0;
        }

        return $expiration->getTimestamp() - $now->getTimestamp();
    }

    /**
     * Generate refresh token
     */
    public function generateRefreshToken(User $user): string
    {
        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        
        $this->logger->info('Refresh token generated', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);

        return $token;
    }

    /**
     * Revoke token (add to blacklist)
     */
    public function revokeToken(string $token): void
    {
        // In a real implementation, you would store revoked tokens in cache/database
        // For now, we just log the revocation
        $this->logger->info('JWT token revoked', [
            'token' => substr($token, 0, 20) . '...',
            'revoked_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Check if token is revoked (blacklisted)
     */
    public function isTokenRevoked(string $token): bool
    {
        // In a real implementation, you would check against a cache/database
        // For now, always return false
        return false;
    }

    /**
     * Create token for password reset
     */
    public function generatePasswordResetToken(User $user): string
    {
        $payload = [
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'type' => 'password_reset',
            'iat' => time(),
            'exp' => time() + 3600 // 1 hour expiration
        ];

        return $this->jwtManager->createFromPayload($user, $payload);
    }

    /**
     * Create token for email verification
     */
    public function generateEmailVerificationToken(User $user): string
    {
        $payload = [
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'type' => 'email_verification',
            'iat' => time(),
            'exp' => time() + 86400 // 24 hours expiration
        ];

        return $this->jwtManager->createFromPayload($user, $payload);
    }

    /**
     * Validate password reset token
     */
    public function validatePasswordResetToken(string $token): array
    {
        $payload = $this->parseToken($token);
        
        if (empty($payload)) {
            return ['valid' => false, 'error' => 'Invalid token'];
        }

        if (!isset($payload['type']) || $payload['type'] !== 'password_reset') {
            return ['valid' => false, 'error' => 'Invalid token type'];
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return ['valid' => false, 'error' => 'Token expired'];
        }

        return [
            'valid' => true,
            'user_id' => $payload['sub'] ?? null,
            'email' => $payload['email'] ?? null
        ];
    }

    /**
     * Validate email verification token
     */
    public function validateEmailVerificationToken(string $token): array
    {
        $payload = $this->parseToken($token);
        
        if (empty($payload)) {
            return ['valid' => false, 'error' => 'Invalid token'];
        }

        if (!isset($payload['type']) || $payload['type'] !== 'email_verification') {
            return ['valid' => false, 'error' => 'Invalid token type'];
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return ['valid' => false, 'error' => 'Token expired'];
        }

        return [
            'valid' => true,
            'user_id' => $payload['sub'] ?? null,
            'email' => $payload['email'] ?? null
        ];
    }

    /**
     * Get default token TTL from configuration
     */
    private function getTokenTtl(): int
    {
        return (int) ($_ENV['JWT_TTL'] ?? 3600); // Default 1 hour
    }

    /**
     * Create API key token for service-to-service communication
     */
    public function generateApiKeyToken(string $apiKey, array $scopes = []): string
    {
        $payload = [
            'api_key' => hash('sha256', $apiKey),
            'type' => 'api_key',
            'scopes' => $scopes,
            'iat' => time(),
            'exp' => time() + 86400 // 24 hours for API keys
        ];

        // Create a dummy user for API key tokens
        $dummyUser = new User();
        $dummyUser->setEmail('api@prettiops.com');

        return $this->jwtManager->createFromPayload($dummyUser, $payload);
    }

    /**
     * Validate API key token
     */
    public function validateApiKeyToken(string $token): array
    {
        $payload = $this->parseToken($token);
        
        if (empty($payload)) {
            return ['valid' => false, 'error' => 'Invalid token'];
        }

        if (!isset($payload['type']) || $payload['type'] !== 'api_key') {
            return ['valid' => false, 'error' => 'Invalid token type'];
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return ['valid' => false, 'error' => 'Token expired'];
        }

        return [
            'valid' => true,
            'api_key_hash' => $payload['api_key'] ?? null,
            'scopes' => $payload['scopes'] ?? []
        ];
    }
}