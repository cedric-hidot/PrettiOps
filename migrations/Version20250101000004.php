<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PrettiOps Core Schema Migration - Phase 4: Performance Indexes
 * Creates all performance indexes for optimal query performance
 */
final class Version20250101000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create performance indexes for all tables';
    }

    public function up(Schema $schema): void
    {
        // Simplified indexes to get the system running
        // Basic performance indexes without complex WHERE conditions
        
        // Users indexes
        $this->addSql('CREATE INDEX idx_users_email ON users(email)');
        $this->addSql('CREATE INDEX idx_users_username ON users(username)');
        $this->addSql('CREATE INDEX idx_users_oauth ON users(oauth_provider, oauth_id)');
        
        // Snippets indexes
        $this->addSql('CREATE INDEX idx_snippets_user ON snippets(user_id)');
        $this->addSql('CREATE INDEX idx_snippets_created ON snippets(created_at)');
        $this->addSql('CREATE INDEX idx_snippets_language ON snippets(language)');
        $this->addSql('CREATE INDEX idx_snippets_visibility ON snippets(visibility)');
        
        // Shares indexes
        $this->addSql('CREATE INDEX idx_shares_snippet ON shares(snippet_id)');
        $this->addSql('CREATE INDEX idx_shares_user ON shares(created_by_user_id)');
        
        // Basic indexes for other tables
        $this->addSql('CREATE INDEX idx_attachments_snippet ON attachments(snippet_id)');
        $this->addSql('CREATE INDEX idx_integrations_user ON integrations(user_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes in reverse order
        $this->addSql('DROP INDEX IF EXISTS idx_integrations_user');
        $this->addSql('DROP INDEX IF EXISTS idx_attachments_snippet');
        $this->addSql('DROP INDEX IF EXISTS idx_shares_user');
        $this->addSql('DROP INDEX IF EXISTS idx_shares_snippet');
        $this->addSql('DROP INDEX IF EXISTS idx_snippets_visibility');
        $this->addSql('DROP INDEX IF EXISTS idx_snippets_language');
        $this->addSql('DROP INDEX IF EXISTS idx_snippets_created');
        $this->addSql('DROP INDEX IF EXISTS idx_snippets_user');
        $this->addSql('DROP INDEX IF EXISTS idx_users_oauth');
        $this->addSql('DROP INDEX IF EXISTS idx_users_username');
        $this->addSql('DROP INDEX IF EXISTS idx_users_email');
    }
}