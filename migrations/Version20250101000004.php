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
        // =============================================================================
        // USERS TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE UNIQUE INDEX CONCURRENTLY idx_users_email ON users(email) WHERE deleted_at IS NULL');
        $this->addSql('CREATE UNIQUE INDEX CONCURRENTLY idx_users_username ON users(username) WHERE deleted_at IS NULL AND username IS NOT NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_users_oauth ON users(oauth_provider, oauth_id) WHERE oauth_provider IS NOT NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_users_last_login ON users(last_login_at DESC) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_users_subscription ON users(subscription_plan, subscription_expires_at) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_users_usage_reset ON users(monthly_usage_reset_at) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_users_active ON users(id) WHERE deleted_at IS NULL');

        // =============================================================================
        // SNIPPETS TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_user_created ON snippets(user_id, created_at DESC) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_user_updated ON snippets(user_id, updated_at DESC) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_language ON snippets(language) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_visibility ON snippets(visibility, created_at DESC) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_public ON snippets(created_at DESC) WHERE visibility = \'public\' AND deleted_at IS NULL');
        
        // Full-text search indexes
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_search_vector ON snippets USING gin(content_search_vector)');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_title_search ON snippets USING gin(title gin_trgm_ops)');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_tags ON snippets USING gin(tags)');
        
        // Performance indexes
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_content_hash ON snippets(content_hash) WHERE content_hash IS NOT NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_popular ON snippets(view_count DESC, created_at DESC) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_active ON snippets(id) WHERE deleted_at IS NULL');

        // =============================================================================
        // SHARES TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE UNIQUE INDEX CONCURRENTLY idx_shares_token ON shares(share_token) WHERE revoked_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_shares_user_created ON shares(created_by_user_id, created_at DESC) WHERE revoked_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_shares_snippet ON shares(snippet_id, created_at DESC) WHERE revoked_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_shares_expires ON shares(expires_at) WHERE expires_at IS NOT NULL AND revoked_at IS NULL');

        // =============================================================================
        // ATTACHMENTS TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE INDEX CONCURRENTLY idx_attachments_snippet ON attachments(snippet_id, created_at) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_attachments_user ON attachments(user_id, created_at DESC) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_attachments_hash ON attachments(file_hash)');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_attachments_virus_scan ON attachments(virus_scan_status, virus_scan_at) WHERE virus_scan_status IN (\'pending\', \'infected\')');

        // =============================================================================
        // INTEGRATIONS TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE INDEX CONCURRENTLY idx_integrations_user_type ON integrations(user_id, type) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_integrations_active ON integrations(is_active, type) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_integrations_sync ON integrations(last_sync_at, sync_enabled) WHERE deleted_at IS NULL');

        // =============================================================================
        // USER_SESSIONS TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE UNIQUE INDEX CONCURRENTLY idx_sessions_token ON user_sessions(session_token) WHERE revoked_at IS NULL');
        $this->addSql('CREATE UNIQUE INDEX CONCURRENTLY idx_sessions_refresh_token ON user_sessions(refresh_token_hash) WHERE revoked_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_sessions_user ON user_sessions(user_id, last_used_at DESC) WHERE revoked_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_sessions_expires ON user_sessions(expires_at) WHERE revoked_at IS NULL');

        // =============================================================================
        // API_KEYS TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE UNIQUE INDEX CONCURRENTLY idx_api_keys_hash ON api_keys(key_hash) WHERE revoked_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_api_keys_user ON api_keys(user_id, created_at DESC) WHERE revoked_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_api_keys_active ON api_keys(is_active, last_used_at DESC) WHERE revoked_at IS NULL');

        // =============================================================================
        // RATE_LIMITS TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE INDEX CONCURRENTLY idx_rate_limits_lookup ON rate_limits(identifier, endpoint, window_start, window_duration)');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_rate_limits_cleanup ON rate_limits(created_at) WHERE created_at < NOW() - INTERVAL \'7 days\'');

        // =============================================================================
        // AUDIT_LOGS TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE INDEX CONCURRENTLY idx_audit_logs_user ON audit_logs(user_id, created_at DESC) WHERE user_id IS NOT NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_audit_logs_resource ON audit_logs(resource_type, resource_id, created_at DESC)');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_audit_logs_action ON audit_logs(action, created_at DESC)');

        // =============================================================================
        // FAVORITES TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE INDEX CONCURRENTLY idx_favorites_user ON favorites(user_id, created_at DESC)');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_favorites_snippet ON favorites(snippet_id, created_at)');

        // =============================================================================
        // NOTIFICATIONS TABLE INDEXES
        // =============================================================================
        $this->addSql('CREATE INDEX CONCURRENTLY idx_notifications_user_unread ON notifications(user_id, read_at, created_at DESC) WHERE archived_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_notifications_type ON notifications(type, created_at DESC)');

        // =============================================================================
        // COMPOSITE INDEXES FOR COMPLEX QUERIES
        // =============================================================================
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_user_dashboard ON snippets(user_id, visibility, updated_at DESC) WHERE deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_snippets_public_discovery ON snippets(visibility, language, created_at DESC) WHERE visibility = \'public\' AND deleted_at IS NULL');
        $this->addSql('CREATE INDEX CONCURRENTLY idx_shares_access_control ON shares(share_token, expires_at, max_views, current_views) WHERE revoked_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        // Drop composite indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_shares_access_control');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_public_discovery');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_user_dashboard');

        // Drop notifications indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_notifications_type');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_notifications_user_unread');

        // Drop favorites indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_favorites_snippet');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_favorites_user');

        // Drop audit logs indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_audit_logs_action');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_audit_logs_resource');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_audit_logs_user');

        // Drop rate limits indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_rate_limits_cleanup');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_rate_limits_lookup');

        // Drop API keys indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_api_keys_active');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_api_keys_user');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_api_keys_hash');

        // Drop sessions indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_sessions_expires');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_sessions_user');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_sessions_refresh_token');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_sessions_token');

        // Drop integrations indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_integrations_sync');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_integrations_active');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_integrations_user_type');

        // Drop attachments indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_attachments_virus_scan');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_attachments_hash');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_attachments_user');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_attachments_snippet');

        // Drop shares indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_shares_expires');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_shares_snippet');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_shares_user_created');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_shares_token');

        // Drop snippets indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_active');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_popular');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_content_hash');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_tags');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_title_search');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_search_vector');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_public');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_visibility');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_language');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_user_updated');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_snippets_user_created');

        // Drop users indexes
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_users_active');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_users_usage_reset');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_users_subscription');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_users_last_login');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_users_oauth');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_users_username');
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_users_email');
    }
}