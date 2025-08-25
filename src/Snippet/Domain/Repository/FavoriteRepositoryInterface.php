<?php

namespace App\Snippet\Domain\Repository;

use App\Snippet\Domain\Entity\Favorite;
use App\Snippet\Domain\Entity\Snippet;
use App\User\Domain\Entity\User;

/**
 * Favorite repository interface following DDD principles
 */
interface FavoriteRepositoryInterface
{
    
    public function findAll(): array;
    
    public function findByUser(User $user): array;
    
    public function findByUserAndSnippet(User $user, Snippet $snippet): ?Favorite;
    
    public function findBySnippet(Snippet $snippet): array;
    
    public function findByFolder(User $user, string $folderName): array;
    
    public function getFoldersByUser(User $user): array;
    
    public function getStatistics(): array;
    
    public function save(Favorite $favorite): void;
    
    public function delete(Favorite $favorite): void;
}