<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ShareRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Share entity for temporary and permanent sharing with access control
 */
#[ORM\Entity(repositoryClass: ShareRepository::class)]
#[ORM\Table(name: 'shares')]
#[ORM\Index(columns: ['snippet_id'], name: 'idx_share_snippet')]
#[ORM\Index(columns: ['created_by_user_id'], name: 'idx_share_created_by')]
#[ORM\Index(columns: ['share_token'], name: 'idx_share_token', unique: true)]
#[ORM\Index(columns: ['expires_at'], name: 'idx_share_expires')]
#[ORM\Index(columns: ['created_at'], name: 'idx_share_created')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['share:read:collection']],
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            normalizationContext: ['groups' => ['share:read']],
            security: "is_granted('ROLE_USER') and (object.getCreatedByUser() == user or is_granted('ROLE_ADMIN'))"
        ),
        new Post(
            denormalizationContext: ['groups' => ['share:write']],
            validationContext: ['groups' => ['share:create']],
            security: "is_granted('ROLE_USER')"
        ),
        new Patch(
            denormalizationContext: ['groups' => ['share:update']],
            security: "is_granted('ROLE_USER') and object.getCreatedByUser() == user"
        ),
        new Delete(
            security: "is_granted('ROLE_USER') and object.getCreatedByUser() == user"
        )
    ],
    normalizationContext: ['groups' => ['share:read']],
    denormalizationContext: ['groups' => ['share:write']]
)]
class Share
{
    public const TYPE_VIEW = 'view';
    public const TYPE_EDIT = 'edit';
    public const TYPE_REVIEW = 'review';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['share:read', 'snippet:read:full'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Snippet::class, inversedBy: 'shares')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['share:read', 'share:write'])]
    private Snippet $snippet;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'shares')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['share:read'])]
    private User $createdByUser;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    #[Groups(['share:read'])]
    private string $shareToken;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: 'share_type')]
    #[Assert\Choice(choices: [self::TYPE_VIEW, self::TYPE_EDIT, self::TYPE_REVIEW])]
    #[Groups(['share:read', 'share:write'])]
    private string $shareType = self::TYPE_VIEW;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['share:read', 'share:write'])]
    private ?array $allowedEmails = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['share:read', 'share:write'])]
    private ?array $allowedDomains = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['share:read', 'share:write'])]
    private bool $requireAuthentication = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['share:read', 'share:write'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(min: 1, max: 10000, groups: ['share:create', 'share:update'])]
    #[Groups(['share:read', 'share:write'])]
    private ?int $maxViews = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['share:read'])]
    private int $currentViews = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['share:read', 'share:write'])]
    private bool $requirePassword = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['share:read', 'share:write'])]
    private bool $watermarkEnabled = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['share:read', 'share:write'])]
    private bool $downloadEnabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['share:read:admin'])]
    private ?\DateTimeImmutable $lastAccessedAt = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    #[Groups(['share:read:admin'])]
    private ?string $lastAccessedByIp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['share:read:admin'])]
    private ?string $lastAccessedByUserAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['share:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['share:read:admin'])]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['share:read:admin'])]
    private ?User $revokedByUser = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->shareToken = $this->generateShareToken();
        $this->createdAt = new \DateTimeImmutable();
    }

    private function generateShareToken(): string
    {
        // Generate a cryptographically secure random token
        return bin2hex(random_bytes(32));
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

    public function getCreatedByUser(): User
    {
        return $this->createdByUser;
    }

    public function setCreatedByUser(User $createdByUser): static
    {
        $this->createdByUser = $createdByUser;
        return $this;
    }

    public function getShareToken(): string
    {
        return $this->shareToken;
    }

    public function regenerateShareToken(): static
    {
        $this->shareToken = $this->generateShareToken();
        return $this;
    }

    public function getShareType(): string
    {
        return $this->shareType;
    }

    public function setShareType(string $shareType): static
    {
        $this->shareType = $shareType;
        return $this;
    }

    public function getAllowedEmails(): ?array
    {
        return $this->allowedEmails;
    }

    public function setAllowedEmails(?array $allowedEmails): static
    {
        $this->allowedEmails = $allowedEmails;
        return $this;
    }

    public function isEmailAllowed(string $email): bool
    {
        if ($this->allowedEmails === null) {
            return true; // No restriction
        }

        return in_array(strtolower($email), array_map('strtolower', $this->allowedEmails));
    }

    public function getAllowedDomains(): ?array
    {
        return $this->allowedDomains;
    }

    public function setAllowedDomains(?array $allowedDomains): static
    {
        $this->allowedDomains = $allowedDomains;
        return $this;
    }

    public function isDomainAllowed(string $email): bool
    {
        if ($this->allowedDomains === null) {
            return true; // No restriction
        }

        $domain = strtolower(substr(strrchr($email, "@"), 1));
        return in_array($domain, array_map('strtolower', $this->allowedDomains));
    }

    public function requiresAuthentication(): bool
    {
        return $this->requireAuthentication;
    }

    public function setRequireAuthentication(bool $requireAuthentication): static
    {
        $this->requireAuthentication = $requireAuthentication;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= new \DateTimeImmutable();
    }

    public function getMaxViews(): ?int
    {
        return $this->maxViews;
    }

    public function setMaxViews(?int $maxViews): static
    {
        $this->maxViews = $maxViews;
        return $this;
    }

    public function getCurrentViews(): int
    {
        return $this->currentViews;
    }

    public function incrementViews(): static
    {
        $this->currentViews++;
        $this->lastAccessedAt = new \DateTimeImmutable();
        return $this;
    }

    public function hasReachedMaxViews(): bool
    {
        return $this->maxViews !== null && $this->currentViews >= $this->maxViews;
    }

    public function requiresPassword(): bool
    {
        return $this->requirePassword;
    }

    public function setRequirePassword(bool $requirePassword): static
    {
        $this->requirePassword = $requirePassword;
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function verifyPassword(string $password): bool
    {
        if (!$this->requiresPassword() || $this->passwordHash === null) {
            return true;
        }

        return password_verify($password, $this->passwordHash);
    }

    public function setPassword(string $password): static
    {
        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $this->requirePassword = true;
        return $this;
    }

    public function isWatermarkEnabled(): bool
    {
        return $this->watermarkEnabled;
    }

    public function setWatermarkEnabled(bool $watermarkEnabled): static
    {
        $this->watermarkEnabled = $watermarkEnabled;
        return $this;
    }

    public function isDownloadEnabled(): bool
    {
        return $this->downloadEnabled;
    }

    public function setDownloadEnabled(bool $downloadEnabled): static
    {
        $this->downloadEnabled = $downloadEnabled;
        return $this;
    }

    public function getLastAccessedAt(): ?\DateTimeImmutable
    {
        return $this->lastAccessedAt;
    }

    public function setLastAccessedBy(string $ip, ?string $userAgent = null): static
    {
        $this->lastAccessedAt = new \DateTimeImmutable();
        $this->lastAccessedByIp = $ip;
        $this->lastAccessedByUserAgent = $userAgent;
        return $this;
    }

    public function getLastAccessedByIp(): ?string
    {
        return $this->lastAccessedByIp;
    }

    public function getLastAccessedByUserAgent(): ?string
    {
        return $this->lastAccessedByUserAgent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function revoke(?User $revokedBy = null): static
    {
        $this->revokedAt = new \DateTimeImmutable();
        $this->revokedByUser = $revokedBy;
        return $this;
    }

    public function getRevokedByUser(): ?User
    {
        return $this->revokedByUser;
    }

    public function isAccessible(?string $email = null): bool
    {
        // Check if share is revoked
        if ($this->isRevoked()) {
            return false;
        }

        // Check expiration
        if ($this->isExpired()) {
            return false;
        }

        // Check view limit
        if ($this->hasReachedMaxViews()) {
            return false;
        }

        // Check email restrictions if email is provided
        if ($email !== null) {
            if (!$this->isEmailAllowed($email)) {
                return false;
            }

            if (!$this->isDomainAllowed($email)) {
                return false;
            }
        }

        return true;
    }

    public function canEdit(): bool
    {
        return $this->shareType === self::TYPE_EDIT && $this->isAccessible();
    }

    public function canView(): bool
    {
        return in_array($this->shareType, [self::TYPE_VIEW, self::TYPE_EDIT, self::TYPE_REVIEW]) && $this->isAccessible();
    }

    public function canReview(): bool
    {
        return $this->shareType === self::TYPE_REVIEW && $this->isAccessible();
    }

    /**
     * Generate a public URL for this share
     */
    public function getPublicUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/share/' . $this->shareToken;
    }

    /**
     * Set expiration based on duration from now
     */
    public function setExpirationInHours(int $hours): static
    {
        $this->expiresAt = new \DateTimeImmutable("+{$hours} hours");
        return $this;
    }

    public function setExpirationInDays(int $days): static
    {
        $this->expiresAt = new \DateTimeImmutable("+{$days} days");
        return $this;
    }

    public function removeExpiration(): static
    {
        $this->expiresAt = null;
        return $this;
    }

    /**
     * Get remaining time until expiration
     */
    public function getRemainingTime(): ?string
    {
        if ($this->expiresAt === null) {
            return null;
        }

        $now = new \DateTimeImmutable();
        if ($this->expiresAt <= $now) {
            return 'Expired';
        }

        $diff = $now->diff($this->expiresAt);

        if ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '');
        }

        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }

        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }

        return 'Less than a minute';
    }

    /**
     * Get remaining views before limit is reached
     */
    public function getRemainingViews(): ?int
    {
        if ($this->maxViews === null) {
            return null;
        }

        return max(0, $this->maxViews - $this->currentViews);
    }
}