<?php

namespace App\Shared\Infrastructure\Security\Encryption;

/**
 * AES-256-GCM encryption service for sensitive data
 * Implements secure encryption for PrettiOps data
 */
class EncryptionService
{
    private const CIPHER = 'AES-256-GCM';
    private const IV_LENGTH = 12; // 96 bits for GCM
    private const TAG_LENGTH = 16; // 128 bits auth tag

    public function __construct(
        private readonly string $encryptionKey,
        private readonly string $cipher = self::CIPHER
    ) {
        if (strlen($this->encryptionKey) !== 32) {
            throw new \InvalidArgumentException('Encryption key must be exactly 32 bytes long');
        }

        if (!in_array($this->cipher, openssl_get_cipher_methods(), true)) {
            throw new \InvalidArgumentException(sprintf('Cipher %s is not supported', $this->cipher));
        }
    }

    /**
     * Encrypt data using AES-256-GCM
     */
    public function encrypt(string $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }

        // Generate random IV
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        if ($iv === false) {
            throw new \RuntimeException('Failed to generate initialization vector');
        }

        // Encrypt data
        $tag = '';
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'cipher' => $this->cipher
        ];
    }

    /**
     * Decrypt data using AES-256-GCM
     */
    public function decrypt(array $encryptedData): string
    {
        $requiredKeys = ['data', 'iv', 'tag', 'cipher'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $encryptedData)) {
                throw new \InvalidArgumentException(sprintf('Missing required key: %s', $key));
            }
        }

        if ($encryptedData['cipher'] !== $this->cipher) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported cipher: %s', $encryptedData['cipher'])
            );
        }

        $data = base64_decode($encryptedData['data']);
        $iv = base64_decode($encryptedData['iv']);
        $tag = base64_decode($encryptedData['tag']);

        if ($data === false || $iv === false || $tag === false) {
            throw new \InvalidArgumentException('Invalid base64 encoded data');
        }

        $decrypted = openssl_decrypt(
            $data,
            $this->cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * Encrypt a string and return as base64 encoded JSON
     */
    public function encryptToString(string $data): string
    {
        $encrypted = $this->encrypt($data);
        return base64_encode(json_encode($encrypted));
    }

    /**
     * Decrypt from base64 encoded JSON string
     */
    public function decryptFromString(string $encryptedString): string
    {
        $decoded = base64_decode($encryptedString);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 encoded string');
        }

        $encryptedData = json_decode($decoded, true);
        if ($encryptedData === null) {
            throw new \InvalidArgumentException('Invalid JSON data');
        }

        return $this->decrypt($encryptedData);
    }

    /**
     * Generate a secure random key for encryption
     */
    public static function generateKey(): string
    {
        return openssl_random_pseudo_bytes(32);
    }

    /**
     * Generate a secure random key as hex string
     */
    public static function generateKeyHex(): string
    {
        return bin2hex(self::generateKey());
    }

    /**
     * Hash data using SHA-256
     */
    public function hash(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * Verify hash
     */
    public function verifyHash(string $data, string $hash): bool
    {
        return hash_equals($hash, $this->hash($data));
    }

    /**
     * Generate HMAC for data integrity
     */
    public function generateHmac(string $data): string
    {
        return hash_hmac('sha256', $data, $this->encryptionKey);
    }

    /**
     * Verify HMAC
     */
    public function verifyHmac(string $data, string $hmac): bool
    {
        $calculated = $this->generateHmac($data);
        return hash_equals($hmac, $calculated);
    }

    /**
     * Encrypt file contents
     */
    public function encryptFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('File does not exist');
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read file');
        }

        return $this->encrypt($content);
    }

    /**
     * Decrypt and save to file
     */
    public function decryptToFile(array $encryptedData, string $outputPath): void
    {
        $decrypted = $this->decrypt($encryptedData);
        
        $result = file_put_contents($outputPath, $decrypted);
        if ($result === false) {
            throw new \RuntimeException('Failed to write decrypted file');
        }
    }

    /**
     * Encrypt large data in chunks (for large files)
     */
    public function encryptStream($inputStream, $outputStream, int $chunkSize = 8192): void
    {
        if (!is_resource($inputStream) || !is_resource($outputStream)) {
            throw new \InvalidArgumentException('Invalid stream resources');
        }

        // Generate random IV for the entire stream
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        if ($iv === false) {
            throw new \RuntimeException('Failed to generate initialization vector');
        }

        // Write IV to output stream
        fwrite($outputStream, $iv);

        // Initialize cipher context
        $ctx = openssl_cipher_iv_length($this->cipher);
        if ($ctx === false) {
            throw new \RuntimeException('Failed to initialize cipher context');
        }

        $tag = '';
        while (!feof($inputStream)) {
            $chunk = fread($inputStream, $chunkSize);
            if ($chunk === false) {
                throw new \RuntimeException('Failed to read input stream');
            }

            if (strlen($chunk) > 0) {
                $encrypted = openssl_encrypt(
                    $chunk,
                    $this->cipher,
                    $this->encryptionKey,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag
                );

                if ($encrypted === false) {
                    throw new \RuntimeException('Encryption failed');
                }

                fwrite($outputStream, $encrypted);
            }
        }

        // Write authentication tag
        fwrite($outputStream, $tag);
    }

    /**
     * Check if encryption is available on this system
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('openssl') && in_array(self::CIPHER, openssl_get_cipher_methods());
    }

    /**
     * Get cipher information
     */
    public function getCipherInfo(): array
    {
        return [
            'cipher' => $this->cipher,
            'iv_length' => self::IV_LENGTH,
            'tag_length' => self::TAG_LENGTH,
            'key_length' => strlen($this->encryptionKey),
            'available' => self::isAvailable()
        ];
    }

    /**
     * Securely wipe a string from memory (best effort)
     */
    public static function secureWipe(string &$data): void
    {
        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            $data[$i] = chr(random_int(0, 255));
        }
        $data = '';
    }

    /**
     * Generate a secure password
     */
    public static function generateSecurePassword(int $length = 32): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        $password = '';
        $charLength = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charLength)];
        }

        return $password;
    }

    /**
     * Key derivation using PBKDF2
     */
    public static function deriveKey(string $password, string $salt, int $iterations = 10000): string
    {
        return hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
    }

    /**
     * Generate a secure salt
     */
    public static function generateSalt(int $length = 32): string
    {
        return openssl_random_pseudo_bytes($length);
    }
}