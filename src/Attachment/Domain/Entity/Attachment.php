<?php

namespace App\Attachment\Domain\Entity;

use App\User\Domain\Entity\User;
use App\Snippet\Domain\Entity\Snippet;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Attachment\Infrastructure\Persistence\Doctrine\AttachmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Attachment entity for secure file sharing with snippets
 */
#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\Table(name: 'attachments')]
#[ORM\Index(columns: ['snippet_id'], name: 'idx_attachment_snippet')]
#[ORM\Index(columns: ['user_id'], name: 'idx_attachment_user')]
#[ORM\UniqueConstraint(columns: ['stored_filename'], name: 'idx_attachment_filename')]
#[ORM\Index(columns: ['file_hash'], name: 'idx_attachment_hash')]
#[ORM\Index(columns: ['virus_scan_status'], name: 'idx_attachment_scan_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_attachment_created')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['attachment:read:collection']],
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            normalizationContext: ['groups' => ['attachment:read']],
            security: "is_granted('ROLE_USER') and (object.getUser() == user or object.getSnippet().getUser() == user)"
        ),
        new Post(
            denormalizationContext: ['groups' => ['attachment:write']],
            validationContext: ['groups' => ['attachment:create']],
            security: "is_granted('ROLE_USER')"
        ),
        new Delete(
            security: "is_granted('ROLE_USER') and object.getUser() == user"
        )
    ],
    normalizationContext: ['groups' => ['attachment:read']],
    denormalizationContext: ['groups' => ['attachment:write']]
)]
class Attachment
{
    public const VIRUS_STATUS_PENDING = 'pending';
    public const VIRUS_STATUS_CLEAN = 'clean';
    public const VIRUS_STATUS_INFECTED = 'infected';
    public const VIRUS_STATUS_ERROR = 'error';

    public const MAX_FILE_SIZE = 52428800; // 50MB in bytes

    public const ALLOWED_MIME_TYPES = [
        'text/plain',
        'text/csv',
        'text/html',
        'text/css',
        'text/javascript',
        'application/json',
        'application/xml',
        'application/pdf',
        'application/zip',
        'application/x-tar',
        'application/gzip',
        'application/x-compressed',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['attachment:read', 'snippet:read:full'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Snippet::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['attachment:read', 'attachment:write'])]
    private Snippet $snippet;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['attachment:read'])]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(groups: ['attachment:create'])]
    #[Groups(['attachment:read'])]
    private string $originalFilename;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Groups(['attachment:read:admin'])]
    private string $storedFilename;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['attachment:read:admin'])]
    private string $filePath;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Choice(choices: self::ALLOWED_MIME_TYPES, groups: ['attachment:create'])]
    #[Groups(['attachment:read'])]
    private string $mimeType;

    #[ORM\Column(type: Types::BIGINT)]
    #[Assert\Range(min: 1, max: self::MAX_FILE_SIZE, groups: ['attachment:create'])]
    #[Groups(['attachment:read'])]
    private int $fileSize;

    #[ORM\Column(type: Types::STRING, length: 64)]
    #[Groups(['attachment:read:admin'])]
    private string $fileHash;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['attachment:read'])]
    private bool $isEncrypted = true;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['attachment:read:admin'])]
    private ?string $encryptionKeyId = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['attachment:read'])]
    private string $virusScanStatus = self::VIRUS_STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['attachment:read:admin'])]
    private ?\DateTimeImmutable $virusScanAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['attachment:read'])]
    private int $downloadCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['attachment:read:admin'])]
    private ?\DateTimeImmutable $lastDownloadedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['attachment:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['attachment:read:admin'])]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->storedFilename = $this->generateStoredFilename();
        $this->createdAt = new \DateTimeImmutable();
    }

    private function generateStoredFilename(): string
    {
        // Generate a UUID-based filename to avoid conflicts and enhance security
        return Uuid::v7()->toRfc4122() . '.encrypted';
    }

    #[ORM\PrePersist]
    public function generateFileHash(): void
    {
        // This will be set by the file upload service after the file is processed
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSnippet(): Snippet
    {
        return $this->snippet;
    }

    public function setSnippet(Snippet $snippet): static
    {
        $this->snippet = $snippet;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getFileSizeFormatted(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->fileSize;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFileHash(): string
    {
        return $this->fileHash;
    }

    public function setFileHash(string $fileHash): static
    {
        $this->fileHash = $fileHash;
        return $this;
    }

    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }

    public function setIsEncrypted(bool $isEncrypted): static
    {
        $this->isEncrypted = $isEncrypted;
        return $this;
    }

    public function getEncryptionKeyId(): ?string
    {
        return $this->encryptionKeyId;
    }

    public function setEncryptionKeyId(?string $encryptionKeyId): static
    {
        $this->encryptionKeyId = $encryptionKeyId;
        return $this;
    }

    public function getVirusScanStatus(): string
    {
        return $this->virusScanStatus;
    }

    public function setVirusScanStatus(string $virusScanStatus): static
    {
        $this->virusScanStatus = $virusScanStatus;
        $this->virusScanAt = new \DateTimeImmutable();
        return $this;
    }

    public function getVirusScanAt(): ?\DateTimeImmutable
    {
        return $this->virusScanAt;
    }

    public function isVirusScanPending(): bool
    {
        return $this->virusScanStatus === self::VIRUS_STATUS_PENDING;
    }

    public function isVirusScanClean(): bool
    {
        return $this->virusScanStatus === self::VIRUS_STATUS_CLEAN;
    }

    public function isVirusScanInfected(): bool
    {
        return $this->virusScanStatus === self::VIRUS_STATUS_INFECTED;
    }

    public function hasVirusScanError(): bool
    {
        return $this->virusScanStatus === self::VIRUS_STATUS_ERROR;
    }

    public function isSafeToDownload(): bool
    {
        return $this->isVirusScanClean() || $this->hasVirusScanError();
    }

    public function getDownloadCount(): int
    {
        return $this->downloadCount;
    }

    public function incrementDownloadCount(): static
    {
        $this->downloadCount++;
        $this->lastDownloadedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLastDownloadedAt(): ?\DateTimeImmutable
    {
        return $this->lastDownloadedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): static
    {
        $this->deletedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getFileExtension(): string
    {
        return strtolower(pathinfo($this->originalFilename, PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function isText(): bool
    {
        return str_starts_with($this->mimeType, 'text/') || 
               in_array($this->mimeType, ['application/json', 'application/xml']);
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    public function isArchive(): bool
    {
        return in_array($this->mimeType, [
            'application/zip',
            'application/x-tar',
            'application/gzip',
            'application/x-compressed'
        ]);
    }

    public function canPreview(): bool
    {
        return $this->isImage() || $this->isText() || $this->isPdf();
    }

    /**
     * Get the appropriate icon class for the file type
     */
    public function getIconClass(): string
    {
        if ($this->isImage()) {
            return 'fa-image';
        }
        
        if ($this->isText()) {
            return 'fa-file-text';
        }
        
        if ($this->isPdf()) {
            return 'fa-file-pdf';
        }
        
        if ($this->isArchive()) {
            return 'fa-file-archive';
        }

        return 'fa-file';
    }

    /**
     * Generate download URL
     */
    public function getDownloadUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/api/attachments/' . $this->getId() . '/download';
    }

    /**
     * Generate preview URL (if applicable)
     */
    public function getPreviewUrl(string $baseUrl): ?string
    {
        if (!$this->canPreview()) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/api/attachments/' . $this->getId() . '/preview';
    }

    /**
     * Check if file size is within limits for the user's plan
     */
    public function isWithinSizeLimit(User $user): bool
    {
        $maxSize = match($user->getSubscriptionPlan()) {
            User::PLAN_FREEMIUM => 5 * 1024 * 1024,    // 5MB
            User::PLAN_PRO => 25 * 1024 * 1024,        // 25MB
            User::PLAN_TEAM => 50 * 1024 * 1024,       // 50MB
            User::PLAN_ENTERPRISE => 100 * 1024 * 1024, // 100MB
            default => 5 * 1024 * 1024                  // Default to freemium
        };

        return $this->fileSize <= $maxSize;
    }

    /**
     * Get maximum allowed file size for a user plan
     */
    public static function getMaxFileSizeForPlan(string $plan): int
    {
        return match($plan) {
            User::PLAN_FREEMIUM => 5 * 1024 * 1024,    // 5MB
            User::PLAN_PRO => 25 * 1024 * 1024,        // 25MB
            User::PLAN_TEAM => 50 * 1024 * 1024,       // 50MB
            User::PLAN_ENTERPRISE => 100 * 1024 * 1024, // 100MB
            default => 5 * 1024 * 1024                  // Default to freemium
        };
    }

    /**
     * Get maximum allowed file size formatted for a user plan
     */
    public static function getMaxFileSizeFormattedForPlan(string $plan): string
    {
        $size = self::getMaxFileSizeForPlan($plan);
        return match($size) {
            5 * 1024 * 1024 => '5MB',
            25 * 1024 * 1024 => '25MB',
            50 * 1024 * 1024 => '50MB',
            100 * 1024 * 1024 => '100MB',
            default => '5MB'
        };
    }
}