<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PrettiOps Core Schema Migration - Phase 1: Extensions and Types
 * Creates PostgreSQL extensions and custom types required for the application
 */
final class Version20250101000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create PostgreSQL extensions and custom types for PrettiOps';
    }

    public function up(Schema $schema): void
    {
        // Enable required PostgreSQL extensions
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "btree_gin"');

        // Create custom ENUM types for type safety
        $this->addSql("CREATE TYPE user_status AS ENUM ('active', 'inactive', 'suspended', 'deleted')");
        $this->addSql("CREATE TYPE subscription_plan AS ENUM ('freemium', 'pro', 'team', 'enterprise')");
        $this->addSql("CREATE TYPE snippet_visibility AS ENUM ('private', 'shared', 'public', 'team')");
        $this->addSql("CREATE TYPE integration_type AS ENUM ('github', 'gitlab', 'vscode', 'phpstorm', 'postman', 'jira', 'linear')");
        $this->addSql("CREATE TYPE share_type AS ENUM ('view', 'edit', 'review')");
    }

    public function down(Schema $schema): void
    {
        // Drop custom types
        $this->addSql('DROP TYPE IF EXISTS share_type');
        $this->addSql('DROP TYPE IF EXISTS integration_type');
        $this->addSql('DROP TYPE IF EXISTS snippet_visibility');
        $this->addSql('DROP TYPE IF EXISTS subscription_plan');
        $this->addSql('DROP TYPE IF EXISTS user_status');

        // Note: Extensions are not dropped to avoid potential issues with other databases
        $this->addSql('-- Extensions left in place for safety');
    }
}