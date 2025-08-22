<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\SnippetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Snippet entity with versioning, encryption, and full-text search
 */
#[ORM\Entity(repositoryClass: SnippetRepository::class)]
#[ORM\Table(name: 'snippets')]
#[ORM\Index(columns: ['user_id'], name: 'idx_snippet_user')]
#[ORM\Index(columns: ['language'], name: 'idx_snippet_language')]
#[ORM\Index(columns: ['visibility'], name: 'idx_snippet_visibility')]
#[ORM\Index(columns: ['created_at'], name: 'idx_snippet_created')]
#[ORM\Index(columns: ['parent_snippet_id', 'version'], name: 'idx_snippet_version')]
#[ORM\Index(columns: ['content_hash'], name: 'idx_snippet_content_hash')]
#[ORM\Index(columns: ['contains_sensitive_data'], name: 'idx_snippet_sensitive')]
#[ORM\Index(columns: ['auto_expire_at'], name: 'idx_snippet_auto_expire')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['snippet:read:collection']],
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            normalizationContext: ['groups' => ['snippet:read']],
            security: "is_granted('ROLE_USER') and (object.getUser() == user or object.getVisibility() == 'public')"
        ),
        new Post(
            denormalizationContext: ['groups' => ['snippet:write']],
            validationContext: ['groups' => ['snippet:create']],
            security: "is_granted('ROLE_USER')"
        ),
        new Patch(
            denormalizationContext: ['groups' => ['snippet:update']],
            security: "is_granted('ROLE_USER') and object.getUser() == user"
        ),
        new Delete(
            security: "is_granted('ROLE_USER') and object.getUser() == user"
        )
    ],
    normalizationContext: ['groups' => ['snippet:read']],
    denormalizationContext: ['groups' => ['snippet:write']]
)]
class Snippet
{
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_SHARED = 'shared';
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_TEAM = 'team';

    public const THEME_DEFAULT = 'default';
    public const THEME_DARK = 'dark';
    public const THEME_LIGHT = 'light';
    public const THEME_GITHUB = 'github';
    public const THEME_MONOKAI = 'monokai';
    public const THEME_SOLARIZED = 'solarized';

    public const SUPPORTED_LANGUAGES = [
        'javascript', 'typescript', 'php', 'python', 'java', 'csharp', 'cpp',
        'go', 'rust', 'ruby', 'kotlin', 'swift', 'scala', 'bash', 'sql',
        'html', 'css', 'scss', 'json', 'xml', 'yaml', 'markdown', 'text'
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['snippet:read', 'share:read', 'user:read:full'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'snippets')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['snippet:read', 'snippet:write'])]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(groups: ['snippet:create'])]
    #[Assert\Length(max: 255, groups: ['snippet:create', 'snippet:update'])]
    #[Groups(['snippet:read', 'snippet:write'])]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['snippet:read', 'snippet:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(groups: ['snippet:create'])]
    #[Assert\Choice(choices: self::SUPPORTED_LANGUAGES, groups: ['snippet:create', 'snippet:update'])]
    #[Groups(['snippet:read', 'snippet:write'])]
    private string $language;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['snippet:read', 'snippet:write'])]
    private ?string $framework = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['snippet:read', 'snippet:write'])]
    private ?array $tags = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(groups: ['snippet:create'])]
    #[Groups(['snippet:read', 'snippet:write'])]
    private string $content;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['snippet:read:admin'])]
    private bool $contentEncrypted = false;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Groups(['snippet:read:admin'])]
    private ?string $contentHash = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contentSearchVector = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(choices: [self::THEME_DEFAULT, self::THEME_DARK, self::THEME_LIGHT, self::THEME_GITHUB, self::THEME_MONOKAI, self::THEME_SOLARIZED])]
    #[Groups(['snippet:read', 'snippet:write'])]
    private string $theme = self::THEME_DEFAULT;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['snippet:read', 'snippet:write'])]
    private bool $lineNumbers = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['snippet:read', 'snippet:write'])]
    private bool $wordWrap = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['snippet:read:admin'])]
    private ?string $renderCache = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['snippet:read:admin'])]
    private ?\DateTimeImmutable $renderCacheExpiresAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['snippet:read'])]
    private int $version = 1;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['snippet:read:full'])]
    private ?self $parentSnippet = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['snippet:read:admin'])]
    private bool $isLatestVersion = true;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: 'snippet_visibility')]
    #[Groups(['snippet:read', 'snippet:write'])]
    private string $visibility = self::VISIBILITY_PRIVATE;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['snippet:read', 'snippet:write'])]
    private bool $allowPublicIndexing = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['snippet:read', 'snippet:write'])]
    private bool $passwordProtected = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $accessPasswordHash = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['snippet:read'])]
    private int $viewCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['snippet:read'])]
    private int $forkCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['snippet:read'])]
    private int $favoriteCount = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['snippet:read:admin'])]
    private bool $containsSensitiveData = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['snippet:read:admin'])]
    private bool $sensitiveDataMasked = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['snippet:read:admin'])]
    private ?array $detectedSecrets = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['snippet:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['snippet:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['snippet:read:admin'])]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['snippet:read', 'snippet:write'])]
    private ?\DateTimeImmutable $autoExpireAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['snippet:read'])]
    private \DateTimeImmutable $lastAccessedAt;

    /**
     * @var Collection<int, Share>
     */
    #[ORM\OneToMany(mappedBy: 'snippet', targetEntity: Share::class, orphanRemoval: true)]
    #[Groups(['snippet:read:full'])]
    private Collection $shares;

    /**
     * @var Collection<int, Attachment>
     */
    #[ORM\OneToMany(mappedBy: 'snippet', targetEntity: Attachment::class, orphanRemoval: true)]
    #[Groups(['snippet:read:full'])]
    private Collection $attachments;

    /**
     * @var Collection<int, Favorite>
     */
    #[ORM\OneToMany(mappedBy: 'snippet', targetEntity: Favorite::class, orphanRemoval: true)]
    private Collection $favorites;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastAccessedAt = new \DateTimeImmutable();
        $this->shares = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->favorites = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function generateContentHash(): void
    {
        $this->contentHash = hash('sha256', $this->content);
        $this->contentSearchVector = $this->generateSearchVector();
    }

    private function generateSearchVector(): string
    {
        // Combine title, description, and content for full-text search
        $searchableContent = implode(' ', array_filter([
            $this->title,
            $this->description,
            $this->content,
            implode(' ', $this->tags ?? []),
            $this->language,
            $this->framework
        ]));

        return strtolower($searchableContent);
    }

    public function getId(): Uuid
    {
        return $this->id;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getFramework(): ?string
    {
        return $this->framework;
    }

    public function setFramework(?string $framework): static
    {
        $this->framework = $framework;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function isContentEncrypted(): bool
    {
        return $this->contentEncrypted;
    }

    public function setContentEncrypted(bool $contentEncrypted): static
    {
        $this->contentEncrypted = $contentEncrypted;
        return $this;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function isLineNumbers(): bool
    {
        return $this->lineNumbers;
    }

    public function setLineNumbers(bool $lineNumbers): static
    {
        $this->lineNumbers = $lineNumbers;
        return $this;
    }

    public function isWordWrap(): bool
    {
        return $this->wordWrap;
    }

    public function setWordWrap(bool $wordWrap): static
    {
        $this->wordWrap = $wordWrap;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getParentSnippet(): ?self
    {
        return $this->parentSnippet;
    }

    public function setParentSnippet(?self $parentSnippet): static
    {
        $this->parentSnippet = $parentSnippet;
        return $this;
    }

    public function isLatestVersion(): bool
    {
        return $this->isLatestVersion;
    }

    public function setIsLatestVersion(bool $isLatestVersion): static
    {
        $this->isLatestVersion = $isLatestVersion;
        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): static
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function isAllowPublicIndexing(): bool
    {
        return $this->allowPublicIndexing;
    }

    public function setAllowPublicIndexing(bool $allowPublicIndexing): static
    {
        $this->allowPublicIndexing = $allowPublicIndexing;
        return $this;
    }

    public function isPasswordProtected(): bool
    {
        return $this->passwordProtected;
    }

    public function setPasswordProtected(bool $passwordProtected): static
    {
        $this->passwordProtected = $passwordProtected;
        return $this;
    }

    public function getAccessPasswordHash(): ?string
    {
        return $this->accessPasswordHash;
    }

    public function setAccessPasswordHash(?string $accessPasswordHash): static
    {
        $this->accessPasswordHash = $accessPasswordHash;
        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function incrementViewCount(): static
    {
        $this->viewCount++;
        $this->lastAccessedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getForkCount(): int
    {
        return $this->forkCount;
    }

    public function incrementForkCount(): static
    {
        $this->forkCount++;
        return $this;
    }

    public function getFavoriteCount(): int
    {
        return $this->favoriteCount;
    }

    public function incrementFavoriteCount(): static
    {
        $this->favoriteCount++;
        return $this;
    }

    public function decrementFavoriteCount(): static
    {
        $this->favoriteCount = max(0, $this->favoriteCount - 1);
        return $this;
    }

    public function containsSensitiveData(): bool
    {
        return $this->containsSensitiveData;
    }

    public function setContainsSensitiveData(bool $containsSensitiveData): static
    {
        $this->containsSensitiveData = $containsSensitiveData;
        return $this;
    }

    public function isSensitiveDataMasked(): bool
    {
        return $this->sensitiveDataMasked;
    }

    public function setSensitiveDataMasked(bool $sensitiveDataMasked): static
    {
        $this->sensitiveDataMasked = $sensitiveDataMasked;
        return $this;
    }

    public function getDetectedSecrets(): ?array
    {
        return $this->detectedSecrets;
    }

    public function setDetectedSecrets(?array $detectedSecrets): static
    {
        $this->detectedSecrets = $detectedSecrets;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getAutoExpireAt(): ?\DateTimeImmutable
    {
        return $this->autoExpireAt;
    }

    public function setAutoExpireAt(?\DateTimeImmutable $autoExpireAt): static
    {
        $this->autoExpireAt = $autoExpireAt;
        return $this;
    }

    public function getLastAccessedAt(): \DateTimeImmutable
    {
        return $this->lastAccessedAt;
    }

    public function isExpired(): bool
    {
        return $this->autoExpireAt !== null && $this->autoExpireAt <= new \DateTimeImmutable();
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function isShared(): bool
    {
        return $this->visibility === self::VISIBILITY_SHARED;
    }

    public function createFork(User $user): self
    {
        $fork = new self();
        $fork->setUser($user);
        $fork->setTitle($this->title . ' (Fork)');
        $fork->setDescription($this->description);
        $fork->setLanguage($this->language);
        $fork->setFramework($this->framework);
        $fork->setTags($this->tags);
        $fork->setContent($this->content);
        $fork->setTheme($this->theme);
        $fork->setLineNumbers($this->lineNumbers);
        $fork->setWordWrap($this->wordWrap);
        $fork->setParentSnippet($this);

        // Increment fork count on original
        $this->incrementForkCount();

        return $fork;
    }

    public function createVersion(): self
    {
        $newVersion = clone $this;
        $newVersion->id = Uuid::v7();
        $newVersion->version = $this->version + 1;
        $newVersion->parentSnippet = $this;
        $newVersion->createdAt = new \DateTimeImmutable();
        $newVersion->updatedAt = new \DateTimeImmutable();
        $newVersion->lastAccessedAt = new \DateTimeImmutable();

        // Mark previous version as not latest
        $this->setIsLatestVersion(false);

        return $newVersion;
    }

    /**
     * @return Collection<int, Share>
     */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function addShare(Share $share): static
    {
        if (!$this->shares->contains($share)) {
            $this->shares->add($share);
            $share->setSnippet($this);
        }

        return $this;
    }

    public function removeShare(Share $share): static
    {
        if ($this->shares->removeElement($share)) {
            // set the owning side to null (unless already changed)
            if ($share->getSnippet() === $this) {
                $share->setSnippet(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Attachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setSnippet($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): static
    {
        if ($this->attachments->removeElement($attachment)) {
            // set the owning side to null (unless already changed)
            if ($attachment->getSnippet() === $this) {
                $attachment->setSnippet(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Favorite>
     */
    public function getFavorites(): Collection
    {
        return $this->favorites;
    }
}