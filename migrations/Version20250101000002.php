<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PrettiOps Core Schema Migration - Phase 2: Core Tables
 * Creates the main tables: users, snippets, shares, attachments, integrations
 */
final class Version20250101000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create core tables for PrettiOps (users, snippets, shares, attachments, integrations)';
    }

    public function up(Schema $schema): void
    {
        // =============================================================================
        // USERS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE users (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            email VARCHAR(255) NOT NULL UNIQUE,
            email_verified_at TIMESTAMPTZ,
            
            -- User profile
            username VARCHAR(50) UNIQUE,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            avatar_url TEXT,
            timezone VARCHAR(50) DEFAULT \'UTC\',
            locale VARCHAR(10) DEFAULT \'en\',
            
            -- Authentication
            password_hash VARCHAR(255),
            oauth_provider VARCHAR(50),
            oauth_id VARCHAR(255),
            oauth_data JSONB,
            
            -- Security
            two_factor_enabled BOOLEAN DEFAULT FALSE,
            two_factor_secret VARCHAR(255),
            backup_codes TEXT[],
            last_login_at TIMESTAMPTZ,
            last_login_ip INET,
            failed_login_attempts INTEGER DEFAULT 0,
            locked_until TIMESTAMPTZ,
            
            -- Subscription and limits
            status user_status DEFAULT \'active\',
            subscription_plan subscription_plan DEFAULT \'freemium\',
            subscription_expires_at TIMESTAMPTZ,
            monthly_snippet_limit INTEGER DEFAULT 10,
            monthly_snippets_used INTEGER DEFAULT 0,
            monthly_usage_reset_at TIMESTAMPTZ DEFAULT NOW(),
            
            -- RGPD compliance
            gdpr_consent_at TIMESTAMPTZ,
            marketing_consent BOOLEAN DEFAULT FALSE,
            data_retention_expires_at TIMESTAMPTZ,
            
            -- Audit fields
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW(),
            deleted_at TIMESTAMPTZ,
            
            CONSTRAINT valid_email CHECK (email ~* \'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$\'),
            CONSTRAINT valid_username CHECK (username ~* \'^[a-zA-Z0-9_-]{3,50}$\'),
            CONSTRAINT oauth_consistency CHECK (
                (oauth_provider IS NOT NULL AND oauth_id IS NOT NULL) OR 
                (oauth_provider IS NULL AND oauth_id IS NULL)
            )
        )');

        // =============================================================================
        // SNIPPETS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE snippets (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            
            -- Snippet metadata
            title VARCHAR(255) NOT NULL,
            description TEXT,
            language VARCHAR(50) NOT NULL,
            framework VARCHAR(100),
            tags TEXT[],
            
            -- Content storage
            content TEXT NOT NULL,
            content_encrypted BOOLEAN DEFAULT FALSE,
            content_hash VARCHAR(64),
            content_search_vector TSVECTOR,
            
            -- Rendering and display
            theme VARCHAR(50) DEFAULT \'default\',
            line_numbers BOOLEAN DEFAULT TRUE,
            word_wrap BOOLEAN DEFAULT FALSE,
            render_cache TEXT,
            render_cache_expires_at TIMESTAMPTZ,
            
            -- Versioning
            version INTEGER DEFAULT 1,
            parent_snippet_id UUID REFERENCES snippets(id) ON DELETE SET NULL,
            is_latest_version BOOLEAN DEFAULT TRUE,
            
            -- Privacy and sharing
            visibility snippet_visibility DEFAULT \'private\',
            allow_public_indexing BOOLEAN DEFAULT FALSE,
            password_protected BOOLEAN DEFAULT FALSE,
            access_password_hash VARCHAR(255),
            
            -- Statistics
            view_count INTEGER DEFAULT 0,
            fork_count INTEGER DEFAULT 0,
            favorite_count INTEGER DEFAULT 0,
            
            -- Data sensitivity detection
            contains_sensitive_data BOOLEAN DEFAULT FALSE,
            sensitive_data_masked BOOLEAN DEFAULT FALSE,
            detected_secrets JSONB,
            
            -- Audit and compliance
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW(),
            deleted_at TIMESTAMPTZ,
            
            -- RGPD and data retention
            auto_expire_at TIMESTAMPTZ,
            last_accessed_at TIMESTAMPTZ DEFAULT NOW(),
            
            CONSTRAINT valid_version CHECK (version > 0),
            CONSTRAINT valid_theme CHECK (theme IN (\'default\', \'dark\', \'light\', \'github\', \'monokai\', \'solarized\')),
            CONSTRAINT no_self_parent CHECK (parent_snippet_id != id)
        )');

        // =============================================================================
        // SHARES TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE shares (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            snippet_id UUID NOT NULL REFERENCES snippets(id) ON DELETE CASCADE,
            created_by_user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            
            -- Share configuration
            share_token VARCHAR(64) UNIQUE NOT NULL,
            share_type share_type DEFAULT \'view\',
            
            -- Access control
            allowed_emails TEXT[],
            allowed_domains TEXT[],
            require_authentication BOOLEAN DEFAULT FALSE,
            
            -- Time-based access control
            expires_at TIMESTAMPTZ,
            max_views INTEGER,
            current_views INTEGER DEFAULT 0,
            
            -- Security options
            require_password BOOLEAN DEFAULT FALSE,
            password_hash VARCHAR(255),
            watermark_enabled BOOLEAN DEFAULT FALSE,
            download_enabled BOOLEAN DEFAULT TRUE,
            
            -- Tracking
            last_accessed_at TIMESTAMPTZ,
            last_accessed_by_ip INET,
            last_accessed_by_user_agent TEXT,
            
            -- Audit
            created_at TIMESTAMPTZ DEFAULT NOW(),
            revoked_at TIMESTAMPTZ,
            revoked_by_user_id UUID REFERENCES users(id),
            
            CONSTRAINT valid_max_views CHECK (max_views IS NULL OR max_views > 0),
            CONSTRAINT valid_current_views CHECK (current_views >= 0),
            CONSTRAINT expired_or_active CHECK (
                (expires_at IS NULL OR expires_at > NOW()) OR revoked_at IS NOT NULL
            )
        )');

        // =============================================================================
        // ATTACHMENTS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE attachments (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            snippet_id UUID NOT NULL REFERENCES snippets(id) ON DELETE CASCADE,
            user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            
            -- File metadata
            original_filename VARCHAR(255) NOT NULL,
            stored_filename VARCHAR(255) NOT NULL UNIQUE,
            file_path TEXT NOT NULL,
            mime_type VARCHAR(255) NOT NULL,
            file_size BIGINT NOT NULL,
            file_hash VARCHAR(64) NOT NULL,
            
            -- Security
            is_encrypted BOOLEAN DEFAULT TRUE,
            encryption_key_id VARCHAR(255),
            virus_scan_status VARCHAR(20) DEFAULT \'pending\',
            virus_scan_at TIMESTAMPTZ,
            
            -- Access tracking
            download_count INTEGER DEFAULT 0,
            last_downloaded_at TIMESTAMPTZ,
            
            -- Audit
            created_at TIMESTAMPTZ DEFAULT NOW(),
            deleted_at TIMESTAMPTZ,
            
            CONSTRAINT valid_file_size CHECK (file_size > 0 AND file_size <= 52428800),
            CONSTRAINT valid_virus_status CHECK (virus_scan_status IN (\'pending\', \'clean\', \'infected\', \'error\'))
        )');

        // =============================================================================
        // INTEGRATIONS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE integrations (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            
            -- Integration details
            type integration_type NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            
            -- Connection data
            external_id VARCHAR(255),
            external_username VARCHAR(255),
            
            -- OAuth credentials (encrypted)
            access_token TEXT,
            refresh_token TEXT,
            token_expires_at TIMESTAMPTZ,
            
            -- Configuration
            settings JSONB DEFAULT \'{}\',
            sync_enabled BOOLEAN DEFAULT TRUE,
            last_sync_at TIMESTAMPTZ,
            sync_error_count INTEGER DEFAULT 0,
            last_sync_error TEXT,
            
            -- Status
            is_active BOOLEAN DEFAULT TRUE,
            connection_verified BOOLEAN DEFAULT FALSE,
            last_verified_at TIMESTAMPTZ,
            
            -- Audit
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW(),
            deleted_at TIMESTAMPTZ,
            
            CONSTRAINT unique_user_integration UNIQUE (user_id, type, external_id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS integrations');
        $this->addSql('DROP TABLE IF EXISTS attachments');
        $this->addSql('DROP TABLE IF EXISTS shares');
        $this->addSql('DROP TABLE IF EXISTS snippets');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}