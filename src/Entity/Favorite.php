<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\FavoriteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Favorite entity for user bookmarks and favorites
 */
#[ORM\Entity(repositoryClass: FavoriteRepository::class)]
#[ORM\Table(name: 'favorites')]
#[ORM\Index(columns: ['user_id'], name: 'idx_favorite_user')]
#[ORM\Index(columns: ['snippet_id'], name: 'idx_favorite_snippet')]
#[ORM\Index(columns: ['created_at'], name: 'idx_favorite_created')]
#[ORM\UniqueConstraint(name: 'unique_user_favorite', columns: ['user_id', 'snippet_id'])]
#[UniqueEntity(fields: ['user', 'snippet'], message: 'This snippet is already in your favorites.')]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['favorite:read:collection']],
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            normalizationContext: ['groups' => ['favorite:read']],
            security: "is_granted('ROLE_USER') and object.getUser() == user"
        ),
        new Post(
            denormalizationContext: ['groups' => ['favorite:write']],
            validationContext: ['groups' => ['favorite:create']],
            security: "is_granted('ROLE_USER')"
        ),
        new Delete(
            security: "is_granted('ROLE_USER') and object.getUser() == user"
        )
    ],
    normalizationContext: ['groups' => ['favorite:read']],
    denormalizationContext: ['groups' => ['favorite:write']]
)]
class Favorite
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['favorite:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['favorite:read'])]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Snippet::class, inversedBy: 'favorites')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['favorite:read', 'favorite:write'])]
    private Snippet $snippet;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100, groups: ['favorite:create', 'favorite:update'])]
    #[Groups(['favorite:read', 'favorite:write'])]
    private ?string $folderName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, groups: ['favorite:create', 'favorite:update'])]
    #[Groups(['favorite:read', 'favorite:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['favorite:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getSnippet(): Snippet
    {
        return $this->snippet;
    }

    public function setSnippet(Snippet $snippet): static
    {
        $this->snippet = $snippet;
        return $this;
    }

    public function getFolderName(): ?string
    {
        return $this->folderName;
    }

    public function setFolderName(?string $folderName): static
    {
        $this->folderName = $folderName;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}