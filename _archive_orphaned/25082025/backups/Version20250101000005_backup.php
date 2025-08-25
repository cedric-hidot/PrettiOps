<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * BACKUP of complex migration - temporarily disabled
 * This migration was causing 30-second timeouts due to complex database operations
 */
final class Version20250101000005_backup extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DISABLED: Complex database functions and triggers (causing timeout)';
    }

    public function up(Schema $schema): void
    {
        // Migration temporarily disabled due to timeout issues
        // Complex functions and triggers moved to separate migration
    }

    public function down(Schema $schema): void
    {
        // Nothing to rollback
    }
}