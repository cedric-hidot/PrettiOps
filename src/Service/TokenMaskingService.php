<?php

namespace App\Service;

use Spatie\Regex\Regex;

/**
 * Service for detecting and masking sensitive tokens and credentials
 */
class TokenMaskingService
{
    /**
     * Patterns for detecting various types of sensitive data
     */
    private const PATTERNS = [
        // API Keys and Tokens
        'github_token' => '/ghp_[a-zA-Z0-9]{36}/',
        'github_classic' => '/github_pat_[a-zA-Z0-9_]{82}/',
        'slack_token' => '/xox[baprs]-([0-9a-zA-Z]{10,48})?/',
        'discord_token' => '/[MN][a-zA-Z\d]{23}\.[\w-]{6}\.[\w-]{38}/',
        'twitter_bearer' => '/AAAAAAAAAAAAAAAAAAAAA[a-zA-Z0-9%]{80,120}/',
        'stripe_key' => '/sk_live_[a-zA-Z0-9]{24}/',
        'stripe_test' => '/sk_test_[a-zA-Z0-9]{24}/',
        'paypal_client' => '/A[a-zA-Z0-9_-]{79}/',
        'aws_access_key' => '/AKIA[0-9A-Z]{16}/',
        'aws_secret' => '/[a-zA-Z0-9+\/]{40}/',
        'google_api' => '/AIza[0-9A-Za-z-_]{35}/',
        'firebase_key' => '/firebase[_-]?[a-zA-Z0-9]{32,}/',
        'sendgrid_key' => '/SG\.[a-zA-Z0-9_-]{22}\.[a-zA-Z0-9_-]{43}/',
        'mailgun_key' => '/key-[a-f0-9]{32}/',
        'twilio_sid' => '/AC[a-fA-F0-9]{32}/',
        'twilio_token' => '/SK[a-fA-F0-9]{32}/',
        'microsoft_key' => '/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/',
        
        // Database Connection Strings
        'mysql_connection' => '/mysql:\/\/[^:]+:[^@]+@[^\/]+\/[^\s]+/',
        'postgresql_connection' => '/postgresql:\/\/[^:]+:[^@]+@[^\/]+\/[^\s]+/',
        'mongodb_connection' => '/mongodb:\/\/[^:]+:[^@]+@[^\/]+\/[^\s]+/',
        'redis_connection' => '/redis:\/\/[^:]*:[^@]*@[^\/]+\/[^\s]*/i',
        
        // JWT Tokens
        'jwt_token' => '/eyJ[a-zA-Z0-9_-]+\.eyJ[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+/',
        
        // Generic API Keys
        'generic_api_key' => '/["\']?api[_-]?key["\']?\s*[:=]\s*["\']([a-zA-Z0-9_-]{20,})["\']?/i',
        'generic_secret' => '/["\']?secret["\']?\s*[:=]\s*["\']([a-zA-Z0-9_-]{20,})["\']?/i',
        'generic_token' => '/["\']?token["\']?\s*[:=]\s*["\']([a-zA-Z0-9_-]{20,})["\']?/i',
        'generic_password' => '/["\']?password["\']?\s*[:=]\s*["\']([^"\']{8,})["\']?/i',
        
        // Private Keys
        'rsa_private' => '/-----BEGIN (RSA )?PRIVATE KEY-----[\s\S]*?-----END (RSA )?PRIVATE KEY-----/',
        'ssh_private' => '/-----BEGIN OPENSSH PRIVATE KEY-----[\s\S]*?-----END OPENSSH PRIVATE KEY-----/',
        'ec_private' => '/-----BEGIN EC PRIVATE KEY-----[\s\S]*?-----END EC PRIVATE KEY-----/',
        
        // Certificates
        'certificate' => '/-----BEGIN CERTIFICATE-----[\s\S]*?-----END CERTIFICATE-----/',
        
        // Email addresses (in some contexts might be sensitive)
        'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        
        // Credit Card Numbers
        'credit_card' => '/(?:\d{4}[-\s]?){3}\d{4}/',
        
        // Social Security Numbers (US)
        'ssn' => '/\d{3}-?\d{2}-?\d{4}/',
        
        // Phone Numbers
        'phone' => '/\+?1?[-.\s]?\(?[0-9]{3}\)?[-.\s]?[0-9]{3}[-.\s]?[0-9]{4}/',
        
        // IP Addresses
        'ipv4' => '/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/',
        'ipv6' => '/([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}/',
        
        // URLs with credentials
        'url_with_creds' => '/https?:\/\/[^:]+:[^@]+@[^\s]+/',
        
        // Hash values (might be sensitive)
        'md5' => '/\b[a-f0-9]{32}\b/',
        'sha1' => '/\b[a-f0-9]{40}\b/',
        'sha256' => '/\b[a-f0-9]{64}\b/',
    ];

    /**
     * Common words that might indicate sensitive context
     */
    private const SENSITIVE_KEYWORDS = [
        'password', 'pass', 'pwd', 'secret', 'key', 'token', 'api', 'auth',
        'credential', 'private', 'confidential', 'sensitive', 'secure',
        'database', 'db', 'connection', 'config', 'env', 'environment'
    ];

    public function __construct(
        private array $customPatterns = [],
        private bool $strictMode = false
    ) {
    }

    /**
     * Detect sensitive data in content
     */
    public function detectSensitiveData(string $content): array
    {
        $detections = [];
        $allPatterns = array_merge(self::PATTERNS, $this->customPatterns);

        foreach ($allPatterns as $type => $pattern) {
            try {
                $matches = Regex::matchAll($pattern, $content);
                if ($matches->hasMatch()) {
                    foreach ($matches->results() as $match) {
                        $detections[] = [
                            'type' => $type,
                            'value' => $match->group(0),
                            'position' => $match->offset(),
                            'length' => strlen($match->group(0)),
                            'confidence' => $this->calculateConfidence($type, $match->group(0))
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Log error but continue with other patterns
                continue;
            }
        }

        // Additional context-based detection
        $contextDetections = $this->detectByContext($content);
        $detections = array_merge($detections, $contextDetections);

        // Sort by position
        usort($detections, fn($a, $b) => $a['position'] <=> $b['position']);

        return $detections;
    }

    /**
     * Mask sensitive data in content
     */
    public function maskSensitiveData(string $content, array $options = []): array
    {
        $maskChar = $options['mask_char'] ?? '*';
        $preserveLength = $options['preserve_length'] ?? true;
        $showFirst = $options['show_first'] ?? 3;
        $showLast = $options['show_last'] ?? 3;
        
        $detections = $this->detectSensitiveData($content);
        $maskedContent = $content;
        $offset = 0;

        foreach ($detections as $detection) {
            $originalValue = $detection['value'];
            $maskedValue = $this->maskValue(
                $originalValue,
                $maskChar,
                $preserveLength,
                $showFirst,
                $showLast,
                $detection['type']
            );

            $position = $detection['position'] + $offset;
            $length = $detection['length'];

            $maskedContent = substr_replace($maskedContent, $maskedValue, $position, $length);
            $offset += strlen($maskedValue) - $length;
        }

        return [
            'content' => $maskedContent,
            'detections' => $detections,
            'masked_count' => count($detections)
        ];
    }

    /**
     * Check if content contains sensitive data
     */
    public function containsSensitiveData(string $content): bool
    {
        $detections = $this->detectSensitiveData($content);
        return !empty($detections);
    }

    /**
     * Get statistics about detected sensitive data
     */
    public function getSensitiveDataStats(string $content): array
    {
        $detections = $this->detectSensitiveData($content);
        $stats = [
            'total_count' => count($detections),
            'types' => [],
            'high_confidence' => 0,
            'medium_confidence' => 0,
            'low_confidence' => 0
        ];

        foreach ($detections as $detection) {
            $type = $detection['type'];
            $confidence = $detection['confidence'];

            if (!isset($stats['types'][$type])) {
                $stats['types'][$type] = 0;
            }
            $stats['types'][$type]++;

            if ($confidence >= 0.8) {
                $stats['high_confidence']++;
            } elseif ($confidence >= 0.5) {
                $stats['medium_confidence']++;
            } else {
                $stats['low_confidence']++;
            }
        }

        return $stats;
    }

    /**
     * Sanitize content for safe display
     */
    public function sanitizeForDisplay(string $content, array $options = []): string
    {
        $result = $this->maskSensitiveData($content, $options);
        return $result['content'];
    }

    /**
     * Add custom pattern
     */
    public function addCustomPattern(string $name, string $pattern): void
    {
        $this->customPatterns[$name] = $pattern;
    }

    /**
     * Remove custom pattern
     */
    public function removeCustomPattern(string $name): void
    {
        unset($this->customPatterns[$name]);
    }

    /**
     * Get all available patterns
     */
    public function getAvailablePatterns(): array
    {
        return array_merge(self::PATTERNS, $this->customPatterns);
    }

    /**
     * Calculate confidence score for a detection
     */
    private function calculateConfidence(string $type, string $value): float
    {
        // Base confidence by type
        $baseConfidence = match($type) {
            'github_token', 'github_classic', 'stripe_key', 'aws_access_key' => 0.95,
            'jwt_token', 'rsa_private', 'ssh_private' => 0.9,
            'mysql_connection', 'postgresql_connection' => 0.85,
            'generic_api_key', 'generic_secret', 'generic_token' => 0.7,
            'email', 'ipv4' => 0.3,
            default => 0.6
        };

        // Adjust based on length and complexity
        $length = strlen($value);
        if ($length > 50) {
            $baseConfidence += 0.1;
        } elseif ($length < 20) {
            $baseConfidence -= 0.1;
        }

        // Check for randomness (entropy)
        $entropy = $this->calculateEntropy($value);
        if ($entropy > 4.0) {
            $baseConfidence += 0.1;
        } elseif ($entropy < 2.0) {
            $baseConfidence -= 0.2;
        }

        return max(0.1, min(1.0, $baseConfidence));
    }

    /**
     * Detect sensitive data by context
     */
    private function detectByContext(string $content): array
    {
        $detections = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            $lineOffset = array_sum(array_map('strlen', array_slice($lines, 0, $lineNumber))) + $lineNumber;
            
            // Look for assignment patterns with sensitive keywords
            foreach (self::SENSITIVE_KEYWORDS as $keyword) {
                $pattern = '/(' . preg_quote($keyword, '/') . ')\s*[:=]\s*["\']?([^"\'\s]+)["\']?/i';
                if (preg_match($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                    $value = $matches[2][0];
                    if (strlen($value) > 6) { // Only consider non-trivial values
                        $detections[] = [
                            'type' => 'context_' . $keyword,
                            'value' => $value,
                            'position' => $lineOffset + $matches[2][1],
                            'length' => strlen($value),
                            'confidence' => 0.4
                        ];
                    }
                }
            }
        }

        return $detections;
    }

    /**
     * Mask a single value
     */
    private function maskValue(
        string $value,
        string $maskChar,
        bool $preserveLength,
        int $showFirst,
        int $showLast,
        string $type
    ): string {
        $length = strlen($value);

        // Special handling for certain types
        if (in_array($type, ['rsa_private', 'ssh_private', 'ec_private', 'certificate'])) {
            return '[REDACTED_' . strtoupper($type) . ']';
        }

        if ($length <= ($showFirst + $showLast + 2)) {
            // Value too short to partially show
            return $preserveLength ? str_repeat($maskChar, $length) : '[REDACTED]';
        }

        $first = substr($value, 0, $showFirst);
        $last = substr($value, -$showLast);
        $middle = $preserveLength 
            ? str_repeat($maskChar, $length - $showFirst - $showLast)
            : str_repeat($maskChar, 4);

        return $first . $middle . $last;
    }

    /**
     * Calculate entropy of a string
     */
    private function calculateEntropy(string $string): float
    {
        $length = strlen($string);
        if ($length <= 1) {
            return 0;
        }

        $frequencies = array_count_values(str_split($string));
        $entropy = 0;

        foreach ($frequencies as $frequency) {
            $probability = $frequency / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    /**
     * Validate and sanitize regex pattern
     */
    private function validatePattern(string $pattern): bool
    {
        try {
            preg_match($pattern, '');
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get recommendations for detected sensitive data
     */
    public function getRecommendations(array $detections): array
    {
        $recommendations = [];

        foreach ($detections as $detection) {
            $type = $detection['type'];
            
            $recommendation = match($type) {
                'github_token', 'github_classic' => 'Store GitHub tokens in environment variables or secure vault',
                'aws_access_key' => 'Use AWS IAM roles or store keys in AWS Secrets Manager',
                'database_connection' => 'Use environment variables for database credentials',
                'jwt_token' => 'JWT tokens should not be hardcoded in source code',
                'private_key' => 'Private keys should be stored securely and never committed to code',
                'password' => 'Passwords should never be hardcoded. Use secure configuration management',
                default => 'Consider moving this sensitive data to environment variables or a secure vault'
            };

            if (!in_array($recommendation, $recommendations)) {
                $recommendations[] = $recommendation;
            }
        }

        return $recommendations;
    }
}