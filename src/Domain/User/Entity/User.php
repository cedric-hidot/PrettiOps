<?php

namespace App\Domain\User\Entity;

use App\Domain\Snippet\Entity\Snippet;
use App\Domain\Snippet\Entity\Attachment;
use App\Domain\Share\Entity\Share;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Infrastructure\Persistence\Doctrine\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * User entity with OAuth2 support and subscription management
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'], name: 'idx_user_email')]
#[ORM\Index(columns: ['username'], name: 'idx_user_username')]
#[ORM\Index(columns: ['oauth_provider', 'oauth_id'], name: 'idx_user_oauth')]
#[ORM\Index(columns: ['status'], name: 'idx_user_status')]
#[ORM\Index(columns: ['subscription_plan'], name: 'idx_user_subscription')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'This email is already registered.')]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken.')]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['user:read:collection']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Get(
            normalizationContext: ['groups' => ['user:read']],
            security: "is_granted('ROLE_USER') and object == user"
        ),
        new Post(
            denormalizationContext: ['groups' => ['user:write']],
            validationContext: ['groups' => ['user:create']],
            security: "is_granted('PUBLIC_ACCESS')"
        ),
        new Patch(
            denormalizationContext: ['groups' => ['user:update']],
            security: "is_granted('ROLE_USER') and object == user"
        ),
        new Delete(
            security: "is_granted('ROLE_USER') and object == user"
        )
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_DELETED = 'deleted';

    public const PLAN_FREEMIUM = 'freemium';
    public const PLAN_PRO = 'pro';
    public const PLAN_TEAM = 'team';
    public const PLAN_ENTERPRISE = 'enterprise';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['user:read', 'snippet:read', 'share:read'])]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Email(groups: ['user:create', 'user:update'])]
    #[Groups(['user:read', 'user:write', 'snippet:read'])]
    private string $email;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['user:read:admin'])]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]{3,50}$/', groups: ['user:create', 'user:update'])]
    #[Groups(['user:read', 'user:write', 'snippet:read'])]
    private ?string $username = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $firstName = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $avatarUrl = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Groups(['user:read', 'user:write'])]
    private string $timezone = 'UTC';

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Groups(['user:read', 'user:write'])]
    private string $locale = 'en';

    /**
     * @var string The hashed password
     */
    #[ORM\Column(name: 'password_hash', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['user:write'])]
    private ?string $password = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['user:read:admin'])]
    private ?string $oauthProvider = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['user:read:admin'])]
    private ?string $oauthId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['user:read:admin'])]
    private ?array $oauthData = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['user:read', 'user:write'])]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $twoFactorSecret = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $backupCodes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['user:read:admin'])]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    #[Groups(['user:read:admin'])]
    private ?string $lastLoginIp = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $failedLoginAttempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lockedUntil = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['user:read:admin'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['user:read'])]
    private string $subscriptionPlan = self::PLAN_FREEMIUM;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $subscriptionExpiresAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['user:read'])]
    private int $monthlySnippetLimit = 10;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['user:read'])]
    private int $monthlySnippetsUsed = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $monthlyUsageResetAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['user:read:admin'])]
    private ?\DateTimeImmutable $gdprConsentAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['user:read', 'user:write'])]
    private bool $marketingConsent = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['user:read:admin'])]
    private ?\DateTimeImmutable $dataRetentionExpiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * @var Collection<int, Snippet>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: \App\Domain\Snippet\Entity\Snippet::class, orphanRemoval: true)]
    #[Groups(['user:read:full'])]
    private Collection $snippets;

    /**
     * @var Collection<int, \App\Domain\Share\Entity\Share>
     */
    #[ORM\OneToMany(mappedBy: 'createdByUser', targetEntity: \App\Domain\Share\Entity\Share::class, orphanRemoval: true)]
    private Collection $shares;

    /**
     * @var Collection<int, \App\Domain\Snippet\Entity\Attachment>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: \App\Domain\Snippet\Entity\Attachment::class, orphanRemoval: true)]
    private Collection $attachments;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->monthlyUsageResetAt = new \DateTimeImmutable();
        $this->snippets = new ArrayCollection();
        $this->shares = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->subscriptionPlan === self::PLAN_ENTERPRISE) {
            $roles[] = 'ROLE_ENTERPRISE';
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        // This method is required by UserInterface but not used in this implementation
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    // Getters and setters for all properties...

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    public function getUsernameField(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    /**
     * Get the user's initials
     */
    public function getInitials(): string
    {
        $initials = '';
        if ($this->firstName) {
            $initials .= strtoupper(substr($this->firstName, 0, 1));
        }
        if ($this->lastName) {
            $initials .= strtoupper(substr($this->lastName, 0, 1));
        }
        if (!$initials && $this->email) {
            $initials = strtoupper(substr($this->email, 0, 1));
        }
        return $initials;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;
        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    public function getOauthProvider(): ?string
    {
        return $this->oauthProvider;
    }

    public function setOauthProvider(?string $oauthProvider): static
    {
        $this->oauthProvider = $oauthProvider;
        return $this;
    }

    public function getOauthId(): ?string
    {
        return $this->oauthId;
    }

    public function setOauthId(?string $oauthId): static
    {
        $this->oauthId = $oauthId;
        return $this;
    }

    public function getOauthData(): ?array
    {
        return $this->oauthData;
    }

    public function setOauthData(?array $oauthData): static
    {
        $this->oauthData = $oauthData;
        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $twoFactorEnabled): static
    {
        $this->twoFactorEnabled = $twoFactorEnabled;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getSubscriptionPlan(): string
    {
        return $this->subscriptionPlan;
    }

    public function setSubscriptionPlan(string $subscriptionPlan): static
    {
        $this->subscriptionPlan = $subscriptionPlan;
        
        // Update limits based on plan
        match($subscriptionPlan) {
            self::PLAN_FREEMIUM => $this->monthlySnippetLimit = 10,
            self::PLAN_PRO => $this->monthlySnippetLimit = -1, // Unlimited
            self::PLAN_TEAM => $this->monthlySnippetLimit = -1, // Unlimited
            self::PLAN_ENTERPRISE => $this->monthlySnippetLimit = -1, // Unlimited
        };
        
        return $this;
    }

    public function getMonthlySnippetLimit(): int
    {
        return $this->monthlySnippetLimit;
    }

    public function getMonthlySnippetsUsed(): int
    {
        return $this->monthlySnippetsUsed;
    }

    public function incrementMonthlySnippetsUsed(): static
    {
        $this->monthlySnippetsUsed++;
        return $this;
    }

    public function canCreateSnippet(): bool
    {
        return $this->monthlySnippetLimit === -1 || $this->monthlySnippetsUsed < $this->monthlySnippetLimit;
    }

    public function isMarketingConsentGiven(): bool
    {
        return $this->marketingConsent;
    }

    public function setMarketingConsent(bool $marketingConsent): static
    {
        $this->marketingConsent = $marketingConsent;
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * @return Collection<int, Snippet>
     */
    public function getSnippets(): Collection
    {
        return $this->snippets;
    }

    public function addSnippet(\App\Domain\Snippet\Entity\Snippet $snippet): static
    {
        if (!$this->snippets->contains($snippet)) {
            $this->snippets->add($snippet);
            $snippet->setUser($this);
        }

        return $this;
    }

    public function removeSnippet(\App\Domain\Snippet\Entity\Snippet $snippet): static
    {
        if ($this->snippets->removeElement($snippet)) {
            // set the owning side to null (unless already changed)
            if ($snippet->getUser() === $this) {
                $snippet->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, \App\Domain\Share\Entity\Share>
     */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    /**
     * @return Collection<int, \App\Domain\Snippet\Entity\Attachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getMonthlyUsageResetAt(): \DateTimeImmutable
    {
        return $this->monthlyUsageResetAt;
    }

    public function setMonthlyUsageResetAt(\DateTimeImmutable $monthlyUsageResetAt): static
    {
        $this->monthlyUsageResetAt = $monthlyUsageResetAt;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function setLastLoginIp(?string $lastLoginIp): static
    {
        $this->lastLoginIp = $lastLoginIp;
        return $this;
    }

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function setFailedLoginAttempts(int $failedLoginAttempts): static
    {
        $this->failedLoginAttempts = $failedLoginAttempts;
        return $this;
    }

    public function getLockedUntil(): ?\DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeImmutable $lockedUntil): static
    {
        $this->lockedUntil = $lockedUntil;
        return $this;
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function setTwoFactorSecret(?string $twoFactorSecret): static
    {
        $this->twoFactorSecret = $twoFactorSecret;
        return $this;
    }

    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?array $backupCodes): static
    {
        $this->backupCodes = $backupCodes;
        return $this;
    }

    public function getSubscriptionExpiresAt(): ?\DateTimeImmutable
    {
        return $this->subscriptionExpiresAt;
    }

    public function setSubscriptionExpiresAt(?\DateTimeImmutable $subscriptionExpiresAt): static
    {
        $this->subscriptionExpiresAt = $subscriptionExpiresAt;
        return $this;
    }

    public function setMonthlySnippetLimit(int $monthlySnippetLimit): static
    {
        $this->monthlySnippetLimit = $monthlySnippetLimit;
        return $this;
    }

    public function setMonthlySnippetsUsed(int $monthlySnippetsUsed): static
    {
        $this->monthlySnippetsUsed = $monthlySnippetsUsed;
        return $this;
    }

    public function getGdprConsentAt(): ?\DateTimeImmutable
    {
        return $this->gdprConsentAt;
    }

    public function setGdprConsentAt(?\DateTimeImmutable $gdprConsentAt): static
    {
        $this->gdprConsentAt = $gdprConsentAt;
        return $this;
    }

    public function getDataRetentionExpiresAt(): ?\DateTimeImmutable
    {
        return $this->dataRetentionExpiresAt;
    }

    public function setDataRetentionExpiresAt(?\DateTimeImmutable $dataRetentionExpiresAt): static
    {
        $this->dataRetentionExpiresAt = $dataRetentionExpiresAt;
        return $this;
    }
}