<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PrettiOps Core Schema Migration - Phase 3: Security and Session Tables
 * Creates tables for session management, API keys, and security features
 */
final class Version20250101000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create security tables (sessions, API keys, rate limits, audit logs)';
    }

    public function up(Schema $schema): void
    {
        // =============================================================================
        // USER_SESSIONS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE user_sessions (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            
            -- Session identification
            session_token VARCHAR(255) NOT NULL UNIQUE,
            refresh_token_hash VARCHAR(255) NOT NULL UNIQUE,
            
            -- Device and location
            user_agent TEXT,
            ip_address INET,
            device_fingerprint VARCHAR(255),
            location_country VARCHAR(2),
            location_city VARCHAR(100),
            
            -- Security
            is_trusted_device BOOLEAN DEFAULT FALSE,
            requires_2fa BOOLEAN DEFAULT FALSE,
            
            -- Lifecycle
            created_at TIMESTAMPTZ DEFAULT NOW(),
            last_used_at TIMESTAMPTZ DEFAULT NOW(),
            expires_at TIMESTAMPTZ NOT NULL,
            revoked_at TIMESTAMPTZ,
            
            CONSTRAINT valid_expiration CHECK (expires_at > created_at)
        )');

        // =============================================================================
        // API_KEYS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE api_keys (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            
            -- Key identification
            name VARCHAR(100) NOT NULL,
            key_prefix VARCHAR(10) NOT NULL,
            key_hash VARCHAR(255) NOT NULL UNIQUE,
            
            -- Permissions and limits
            scopes TEXT[] DEFAULT ARRAY[\'read\'],
            rate_limit_per_hour INTEGER DEFAULT 1000,
            rate_limit_per_day INTEGER DEFAULT 10000,
            
            -- Usage tracking
            total_requests BIGINT DEFAULT 0,
            last_used_at TIMESTAMPTZ,
            last_used_ip INET,
            
            -- Status
            is_active BOOLEAN DEFAULT TRUE,
            
            -- Lifecycle
            created_at TIMESTAMPTZ DEFAULT NOW(),
            expires_at TIMESTAMPTZ,
            revoked_at TIMESTAMPTZ,
            
            CONSTRAINT unique_user_key_name UNIQUE (user_id, name),
            CONSTRAINT valid_scopes CHECK (scopes <@ ARRAY[\'read\', \'write\', \'admin\'])
        )');

        // =============================================================================
        // RATE_LIMITS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE rate_limits (
            id BIGSERIAL PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            
            -- Time windows
            window_start TIMESTAMPTZ NOT NULL,
            window_duration INTERVAL NOT NULL,
            
            -- Counters
            request_count INTEGER DEFAULT 1,
            
            -- Tracking
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW(),
            
            CONSTRAINT unique_rate_limit_window UNIQUE (identifier, endpoint, window_start, window_duration)
        )');

        // =============================================================================
        // AUDIT_LOGS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE audit_logs (
            id BIGSERIAL PRIMARY KEY,
            
            -- Actor information
            user_id UUID REFERENCES users(id) ON DELETE SET NULL,
            session_id UUID REFERENCES user_sessions(id) ON DELETE SET NULL,
            api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
            
            -- Action details
            action VARCHAR(100) NOT NULL,
            resource_type VARCHAR(50) NOT NULL,
            resource_id UUID,
            
            -- Context
            ip_address INET,
            user_agent TEXT,
            endpoint VARCHAR(255),
            
            -- Changes (for data modifications)
            old_values JSONB,
            new_values JSONB,
            
            -- Privacy compliance
            contains_pii BOOLEAN DEFAULT FALSE,
            anonymized_at TIMESTAMPTZ,
            retention_expires_at TIMESTAMPTZ,
            
            -- Timing
            created_at TIMESTAMPTZ DEFAULT NOW(),
            
            CONSTRAINT valid_retention CHECK (
                retention_expires_at IS NULL OR retention_expires_at > created_at
            )
        )');

        // =============================================================================
        // FAVORITES TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE favorites (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            snippet_id UUID NOT NULL REFERENCES snippets(id) ON DELETE CASCADE,
            
            -- Organization
            folder_name VARCHAR(100),
            notes TEXT,
            
            -- Audit
            created_at TIMESTAMPTZ DEFAULT NOW(),
            
            CONSTRAINT unique_user_favorite UNIQUE (user_id, snippet_id)
        )');

        // =============================================================================
        // NOTIFICATIONS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE notifications (
            id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
            user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            
            -- Notification content
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            
            -- Context
            related_resource_type VARCHAR(50),
            related_resource_id UUID,
            action_url TEXT,
            
            -- Delivery
            delivery_method VARCHAR(20) DEFAULT \'in_app\',
            delivered_at TIMESTAMPTZ,
            
            -- Status
            read_at TIMESTAMPTZ,
            archived_at TIMESTAMPTZ,
            
            -- Audit
            created_at TIMESTAMPTZ DEFAULT NOW(),
            expires_at TIMESTAMPTZ,
            
            CONSTRAINT valid_delivery_method CHECK (delivery_method IN (\'in_app\', \'email\', \'sms\'))
        )');

        // =============================================================================
        // SYSTEM_SETTINGS TABLE
        // =============================================================================
        $this->addSql('CREATE TABLE system_settings (
            key VARCHAR(100) PRIMARY KEY,
            value JSONB NOT NULL,
            description TEXT,
            is_public BOOLEAN DEFAULT FALSE,
            
            -- Audit
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW(),
            updated_by_user_id UUID REFERENCES users(id)
        )');

        // =============================================================================
        // Search helper table for stopwords
        // =============================================================================
        $this->addSql('CREATE TABLE code_stopwords_list (
            word TEXT PRIMARY KEY
        )');
        
        // Insert common programming stopwords
        $this->addSql("INSERT INTO code_stopwords_list (word) VALUES 
            ('function'), ('var'), ('let'), ('const'), ('if'), ('else'), ('for'), ('while'),
            ('class'), ('public'), ('private'), ('protected'), ('static'), ('return'),
            ('import'), ('export'), ('from'), ('as'), ('default'), ('new'), ('this'),
            ('true'), ('false'), ('null'), ('undefined'), ('void'), ('int'), ('string'),
            ('boolean'), ('array'), ('object'), ('try'), ('catch'), ('finally'), ('throw'),
            ('async'), ('await'), ('promise'), ('then')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS code_stopwords_list');
        $this->addSql('DROP TABLE IF EXISTS system_settings');
        $this->addSql('DROP TABLE IF EXISTS notifications');
        $this->addSql('DROP TABLE IF EXISTS favorites');
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
        $this->addSql('DROP TABLE IF EXISTS rate_limits');
        $this->addSql('DROP TABLE IF EXISTS api_keys');
        $this->addSql('DROP TABLE IF EXISTS user_sessions');
    }
}