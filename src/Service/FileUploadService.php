<?php

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * File upload service for handling attachments
 * Supports validation, virus scanning, and secure storage
 */
class FileUploadService
{
    private const ALLOWED_EXTENSIONS = [
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        // Documents
        'pdf', 'doc', 'docx', 'txt', 'md', 'rtf',
        // Spreadsheets
        'xls', 'xlsx', 'csv', 'ods',
        // Presentations
        'ppt', 'pptx', 'odp',
        // Archives
        'zip', 'tar', 'gz', '7z', 'rar',
        // Code files
        'json', 'xml', 'yaml', 'yml', 'sql',
        // Other
        'log'
    ];

    private const MIME_TYPES = [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documents
        'application/pdf', 'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain', 'text/markdown', 'application/rtf',
        // Spreadsheets
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv', 'application/vnd.oasis.opendocument.spreadsheet',
        // Presentations
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.presentation',
        // Archives
        'application/zip', 'application/x-tar', 'application/gzip',
        'application/x-7z-compressed', 'application/x-rar-compressed',
        // Code files
        'application/json', 'application/xml', 'text/xml',
        'application/x-yaml', 'text/yaml', 'application/sql',
        // Other
        'text/x-log'
    ];

    private const DANGEROUS_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar',
        'php', 'asp', 'aspx', 'jsp', 'pl', 'py', 'rb', 'sh', 'ps1'
    ];

    public function __construct(
        private readonly string $uploadDirectory,
        private readonly int $maxFileSize,
        private readonly SluggerInterface $slugger,
        private readonly SecurityService $securityService,
        private readonly EncryptionService $encryptionService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Upload and process file
     */
    public function uploadFile(
        UploadedFile $uploadedFile,
        User $user,
        ?string $customFilename = null,
        bool $encrypt = false
    ): Attachment {
        // Validate file
        $this->validateFile($uploadedFile);

        // Generate secure filename
        $filename = $this->generateSecureFilename($uploadedFile, $customFilename);
        
        // Create attachment entity
        $attachment = new Attachment();
        $attachment->setUser($user);
        $attachment->setOriginalFilename($uploadedFile->getClientOriginalName());
        $attachment->setFilename($filename);
        $attachment->setMimeType($uploadedFile->getMimeType());
        $attachment->setFileSize($uploadedFile->getSize());
        $attachment->setEncrypted($encrypt);
        
        // Calculate file hash before upload
        $fileContent = file_get_contents($uploadedFile->getPathname());
        $attachment->setFileHash(hash('sha256', $fileContent));

        // Scan file for security issues
        $this->scanFileForThreats($uploadedFile, $attachment);

        try {
            // Create upload directory if it doesn't exist
            $uploadPath = $this->getUploadPath($user);
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $fullPath = $uploadPath . DIRECTORY_SEPARATOR . $filename;

            // Encrypt file content if requested
            if ($encrypt) {
                $encryptedContent = $this->encryptionService->encrypt($fileContent);
                file_put_contents($fullPath, $encryptedContent);
                $attachment->setEncryptionIv($this->encryptionService->getLastIv());
            } else {
                // Move file to final destination
                $uploadedFile->move($uploadPath, $filename);
            }

            $attachment->setFilePath($fullPath);
            $attachment->setUploadedAt(new \DateTimeImmutable());
            
            $this->logger->info('File uploaded successfully', [
                'user_id' => $user->getId(),
                'filename' => $filename,
                'original_filename' => $uploadedFile->getClientOriginalName(),
                'file_size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType(),
                'encrypted' => $encrypt
            ]);

            return $attachment;

        } catch (FileException $e) {
            $this->logger->error('File upload failed', [
                'user_id' => $user->getId(),
                'filename' => $uploadedFile->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('File upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete file from storage
     */
    public function deleteFile(Attachment $attachment): bool
    {
        try {
            $filePath = $attachment->getFilePath();
            
            if (file_exists($filePath)) {
                // Secure deletion - overwrite file before deletion
                $this->secureDeleteFile($filePath);
            }

            $this->logger->info('File deleted successfully', [
                'attachment_id' => $attachment->getId(),
                'filename' => $attachment->getFilename(),
                'user_id' => $attachment->getUser()->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('File deletion failed', [
                'attachment_id' => $attachment->getId(),
                'filename' => $attachment->getFilename(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get file content (decrypt if necessary)
     */
    public function getFileContent(Attachment $attachment): string
    {
        if (!file_exists($attachment->getFilePath())) {
            throw new \RuntimeException('File not found: ' . $attachment->getFilename());
        }

        $content = file_get_contents($attachment->getFilePath());

        if ($attachment->isEncrypted()) {
            try {
                $iv = $attachment->getEncryptionIv();
                $content = $this->encryptionService->decrypt($content, $iv);
            } catch (\Exception $e) {
                $this->logger->error('File decryption failed', [
                    'attachment_id' => $attachment->getId(),
                    'filename' => $attachment->getFilename(),
                    'error' => $e->getMessage()
                ]);
                
                throw new \RuntimeException('Failed to decrypt file');
            }
        }

        return $content;
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $uploadedFile): void
    {
        // Check if file was uploaded successfully
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException('File upload failed: ' . $uploadedFile->getErrorMessage());
        }

        // Check file size
        if ($uploadedFile->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException(
                sprintf('File size (%d bytes) exceeds maximum allowed size (%d bytes)', 
                    $uploadedFile->getSize(), 
                    $this->maxFileSize
                )
            );
        }

        // Check file extension
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('File type not allowed: ' . $extension);
        }

        // Check for dangerous extensions
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Dangerous file type detected: ' . $extension);
        }

        // Check MIME type
        $mimeType = $uploadedFile->getMimeType();
        if (!in_array($mimeType, self::MIME_TYPES, true)) {
            throw new \InvalidArgumentException('MIME type not allowed: ' . $mimeType);
        }

        // Verify file extension matches MIME type
        if (!$this->verifyMimeTypeExtensionMatch($mimeType, $extension)) {
            throw new \InvalidArgumentException('File extension does not match MIME type');
        }
    }

    /**
     * Generate secure filename
     */
    private function generateSecureFilename(UploadedFile $uploadedFile, ?string $customFilename = null): string
    {
        $extension = $uploadedFile->getClientOriginalExtension();
        
        if ($customFilename) {
            $safeFilename = $this->slugger->slug($customFilename);
        } else {
            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
        }

        // Add timestamp and random component for uniqueness
        $timestamp = (new \DateTimeImmutable())->format('YmdHis');
        $random = bin2hex(random_bytes(4));
        
        return sprintf('%s_%s_%s.%s', $safeFilename, $timestamp, $random, $extension);
    }

    /**
     * Get upload path for user
     */
    private function getUploadPath(User $user): string
    {
        // Organize files by user ID and year/month
        $date = new \DateTimeImmutable();
        $userIdHash = substr(hash('sha256', $user->getId()), 0, 8);
        
        return sprintf('%s/%s/%s/%s', 
            rtrim($this->uploadDirectory, '/'),
            $userIdHash,
            $date->format('Y'),
            $date->format('m')
        );
    }

    /**
     * Scan file for security threats
     */
    private function scanFileForThreats(UploadedFile $uploadedFile, Attachment $attachment): void
    {
        $filePath = $uploadedFile->getPathname();
        
        // Check for embedded scripts in images
        if (str_starts_with($uploadedFile->getMimeType(), 'image/')) {
            $this->scanImageForScripts($filePath, $attachment);
        }

        // Check for suspicious content
        if ($uploadedFile->getSize() < 1024 * 1024) { // Only scan files < 1MB for performance
            $content = file_get_contents($filePath);
            $securityCheck = $this->securityService->detectSuspiciousContent($content);
            
            if ($securityCheck['suspicious']) {
                $attachment->setSecurityScanResult($securityCheck);
                
                $this->logger->warning('Suspicious file content detected', [
                    'filename' => $uploadedFile->getClientOriginalName(),
                    'issues' => $securityCheck['issues'],
                    'risk_level' => $securityCheck['risk_level']
                ]);
            }
        }
    }

    /**
     * Scan image files for embedded scripts
     */
    private function scanImageForScripts(string $filePath, Attachment $attachment): void
    {
        $content = file_get_contents($filePath);
        
        // Check for common script patterns in images
        $scriptPatterns = [
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/eval\s*\(/i',
            '/base64,/i'
        ];

        foreach ($scriptPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $attachment->setSecurityScanResult([
                    'suspicious' => true,
                    'issues' => ['Script content detected in image file'],
                    'risk_level' => 'high'
                ]);
                
                $this->logger->warning('Script content detected in uploaded image', [
                    'filename' => $attachment->getFilename()
                ]);
                break;
            }
        }
    }

    /**
     * Verify MIME type matches file extension
     */
    private function verifyMimeTypeExtensionMatch(string $mimeType, string $extension): bool
    {
        $mimeExtensionMap = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'image/svg+xml' => ['svg'],
            'application/pdf' => ['pdf'],
            'text/plain' => ['txt'],
            'text/markdown' => ['md'],
            'application/json' => ['json'],
            'application/xml' => ['xml'],
            'text/xml' => ['xml'],
            'application/zip' => ['zip'],
            'text/csv' => ['csv']
        ];

        if (!isset($mimeExtensionMap[$mimeType])) {
            return true; // Allow if not in map (conservative approach)
        }

        return in_array($extension, $mimeExtensionMap[$mimeType], true);
    }

    /**
     * Securely delete file (overwrite before deletion)
     */
    private function secureDeleteFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $fileSize = filesize($filePath);
        
        // Overwrite file with random data
        $handle = fopen($filePath, 'r+b');
        if ($handle) {
            for ($i = 0; $i < 3; $i++) {
                fseek($handle, 0);
                fwrite($handle, random_bytes($fileSize));
                fflush($handle);
            }
            fclose($handle);
        }

        // Finally delete the file
        unlink($filePath);
    }

    /**
     * Get file icon based on MIME type
     */
    public function getFileIcon(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            $mimeType === 'application/pdf' => 'pdf',
            str_contains($mimeType, 'word') => 'word',
            str_contains($mimeType, 'excel') || str_contains($mimeType, 'spreadsheet') => 'excel',
            str_contains($mimeType, 'powerpoint') || str_contains($mimeType, 'presentation') => 'powerpoint',
            str_contains($mimeType, 'zip') || str_contains($mimeType, 'rar') || str_contains($mimeType, 'tar') => 'archive',
            str_starts_with($mimeType, 'text/') => 'text',
            default => 'file'
        };
    }

    /**
     * Get human-readable file size
     */
    public function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Check if file exists and is readable
     */
    public function isFileAccessible(Attachment $attachment): bool
    {
        $filePath = $attachment->getFilePath();
        return file_exists($filePath) && is_readable($filePath);
    }

    /**
     * Get file metadata
     */
    public function getFileMetadata(Attachment $attachment): array
    {
        if (!$this->isFileAccessible($attachment)) {
            return ['error' => 'File not accessible'];
        }

        $filePath = $attachment->getFilePath();
        $stat = stat($filePath);

        return [
            'size' => $stat['size'],
            'size_formatted' => $this->formatFileSize($stat['size']),
            'created' => new \DateTimeImmutable('@' . $stat['ctime']),
            'modified' => new \DateTimeImmutable('@' . $stat['mtime']),
            'accessed' => new \DateTimeImmutable('@' . $stat['atime']),
            'permissions' => substr(sprintf('%o', $stat['mode']), -4),
            'icon' => $this->getFileIcon($attachment->getMimeType())
        ];
    }
}