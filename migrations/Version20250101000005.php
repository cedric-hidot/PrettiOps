<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PrettiOps Core Schema Migration - Phase 5: Functions and Triggers
 * Creates database functions, triggers, and search capabilities
 */
final class Version20250101000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create database functions, triggers, and search capabilities';
    }

    public function up(Schema $schema): void
    {
        // =============================================================================
        // HELPER FUNCTIONS
        // =============================================================================
        
        // Function to update timestamp automatically
        $this->addSql('
            CREATE OR REPLACE FUNCTION update_timestamp()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at := NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Function to update snippet search vector
        $this->addSql('
            CREATE OR REPLACE FUNCTION update_snippet_search_vector()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.content_search_vector := to_tsvector(
                    \'english\', 
                    COALESCE(NEW.title, \'\') || \' \' || 
                    COALESCE(NEW.description, \'\') || \' \' || 
                    COALESCE(NEW.content, \'\')
                );
                
                NEW.content_hash := encode(digest(NEW.content, \'sha256\'), \'hex\');
                NEW.updated_at := NOW();
                
                IF TG_OP = \'UPDATE\' AND OLD.content != NEW.content THEN
                    NEW.version := OLD.version + 1;
                END IF;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Function to detect sensitive data
        $this->addSql('
            CREATE OR REPLACE FUNCTION detect_sensitive_data()
            RETURNS TRIGGER AS $$
            DECLARE
                content_lower TEXT;
                detected_secrets JSONB := \'[]\'::jsonb;
            BEGIN
                content_lower := LOWER(NEW.content);
                
                -- API Keys
                IF content_lower ~ \'api[_-]?key.*[a-z0-9]{20,}\' THEN
                    detected_secrets := detected_secrets || \'["api_key"]\'::jsonb;
                END IF;
                
                -- AWS Access Keys
                IF content_lower ~ \'aws[_-]?access[_-]?key|AKIA[0-9A-Z]{16}\' THEN
                    detected_secrets := detected_secrets || \'["aws_access_key"]\'::jsonb;
                END IF;
                
                -- Database URLs
                IF content_lower ~ \'postgres://|mysql://|mongodb://.*:[^@]*@\' THEN
                    detected_secrets := detected_secrets || \'["database_url"]\'::jsonb;
                END IF;
                
                -- JWT tokens
                IF content_lower ~ \'eyJ[A-Za-z0-9-_=]+\.[A-Za-z0-9-_=]+\.?[A-Za-z0-9-_.+/=]*\' THEN
                    detected_secrets := detected_secrets || \'["jwt_token"]\'::jsonb;
                END IF;
                
                -- Private keys
                IF content_lower ~ \'-----BEGIN (RSA |DSA |EC |OPENSSH )?PRIVATE KEY-----\' THEN
                    detected_secrets := detected_secrets || \'["private_key"]\'::jsonb;
                END IF;
                
                NEW.detected_secrets := detected_secrets;
                NEW.contains_sensitive_data := CASE 
                    WHEN jsonb_array_length(detected_secrets) > 0 THEN TRUE 
                    ELSE FALSE 
                END;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Function to set data retention
        $this->addSql('
            CREATE OR REPLACE FUNCTION set_data_retention()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.subscription_plan = \'freemium\' THEN
                    NEW.data_retention_expires_at := NOW() + INTERVAL \'2 years\';
                ELSIF NEW.subscription_plan IN (\'pro\', \'team\') THEN
                    NEW.data_retention_expires_at := NOW() + INTERVAL \'5 years\';
                ELSIF NEW.subscription_plan = \'enterprise\' THEN
                    NEW.data_retention_expires_at := NOW() + INTERVAL \'7 years\';
                END IF;
                
                IF TG_OP = \'INSERT\' THEN
                    NEW.monthly_usage_reset_at := DATE_TRUNC(\'month\', NOW()) + INTERVAL \'1 month\';
                END IF;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Audit logging function
        $this->addSql('
            CREATE OR REPLACE FUNCTION log_audit_event()
            RETURNS TRIGGER AS $$
            DECLARE
                action_name TEXT;
                old_vals JSONB;
                new_vals JSONB;
                resource_type_name TEXT;
                contains_pii_data BOOLEAN := FALSE;
            BEGIN
                action_name := LOWER(TG_OP);
                resource_type_name := TG_TABLE_NAME;
                
                IF TG_OP = \'DELETE\' THEN
                    old_vals := to_jsonb(OLD) - ARRAY[\'password_hash\', \'two_factor_secret\', \'backup_codes\', \'access_token\', \'refresh_token\', \'oauth_data\'];
                    new_vals := NULL;
                ELSIF TG_OP = \'INSERT\' THEN
                    old_vals := NULL;
                    new_vals := to_jsonb(NEW) - ARRAY[\'password_hash\', \'two_factor_secret\', \'backup_codes\', \'access_token\', \'refresh_token\', \'oauth_data\'];
                ELSE
                    old_vals := to_jsonb(OLD) - ARRAY[\'password_hash\', \'two_factor_secret\', \'backup_codes\', \'access_token\', \'refresh_token\', \'oauth_data\'];
                    new_vals := to_jsonb(NEW) - ARRAY[\'password_hash\', \'two_factor_secret\', \'backup_codes\', \'access_token\', \'refresh_token\', \'oauth_data\'];
                END IF;
                
                IF resource_type_name IN (\'users\', \'user_sessions\') THEN
                    contains_pii_data := TRUE;
                END IF;
                
                INSERT INTO audit_logs (
                    action, resource_type, resource_id, old_values, new_values,
                    contains_pii, retention_expires_at, created_at
                ) VALUES (
                    action_name, resource_type_name, COALESCE(NEW.id, OLD.id),
                    old_vals, new_vals, contains_pii_data,
                    NOW() + INTERVAL \'7 years\', NOW()
                );
                
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ');

        // =============================================================================
        // CLEANUP FUNCTIONS
        // =============================================================================
        
        $this->addSql('
            CREATE OR REPLACE FUNCTION cleanup_expired_tokens()
            RETURNS VOID AS $$
            BEGIN
                UPDATE user_sessions SET revoked_at = NOW() 
                WHERE expires_at < NOW() AND revoked_at IS NULL;
                
                UPDATE api_keys SET revoked_at = NOW() 
                WHERE expires_at IS NOT NULL AND expires_at < NOW() AND revoked_at IS NULL;
                
                UPDATE shares SET revoked_at = NOW() 
                WHERE expires_at IS NOT NULL AND expires_at < NOW() AND revoked_at IS NULL;
                
                DELETE FROM rate_limits 
                WHERE created_at < NOW() - INTERVAL \'1 week\';
                
                DELETE FROM notifications 
                WHERE expires_at IS NOT NULL AND expires_at < NOW();
            END;
            $$ LANGUAGE plpgsql;
        ');

        $this->addSql('
            CREATE OR REPLACE FUNCTION reset_monthly_usage()
            RETURNS VOID AS $$
            BEGIN
                UPDATE users 
                SET 
                    monthly_snippets_used = 0,
                    monthly_usage_reset_at = DATE_TRUNC(\'month\', NOW()) + INTERVAL \'1 month\'
                WHERE monthly_usage_reset_at <= NOW();
            END;
            $$ LANGUAGE plpgsql;
        ');

        // RGPD deletion function
        $this->addSql('
            CREATE OR REPLACE FUNCTION rgpd_delete_user_data(user_uuid UUID)
            RETURNS VOID AS $$
            BEGIN
                UPDATE users 
                SET 
                    email = \'deleted_\' || user_uuid || \'@anonymized.local\',
                    username = NULL,
                    first_name = \'[DELETED]\',
                    last_name = \'[DELETED]\',
                    avatar_url = NULL,
                    oauth_data = NULL,
                    password_hash = NULL,
                    two_factor_secret = NULL,
                    backup_codes = NULL,
                    deleted_at = NOW(),
                    gdpr_consent_at = NULL,
                    marketing_consent = FALSE
                WHERE id = user_uuid;
                
                UPDATE snippets SET deleted_at = NOW() 
                WHERE user_id = user_uuid AND deleted_at IS NULL;
                
                UPDATE shares SET revoked_at = NOW() 
                WHERE created_by_user_id = user_uuid AND revoked_at IS NULL;
                
                UPDATE attachments SET deleted_at = NOW() 
                WHERE user_id = user_uuid AND deleted_at IS NULL;
                
                UPDATE user_sessions SET revoked_at = NOW() 
                WHERE user_id = user_uuid AND revoked_at IS NULL;
                
                UPDATE api_keys SET revoked_at = NOW() 
                WHERE user_id = user_uuid AND revoked_at IS NULL;
                
                UPDATE integrations SET deleted_at = NOW() 
                WHERE user_id = user_uuid AND deleted_at IS NULL;
                
                DELETE FROM favorites WHERE user_id = user_uuid;
                
                UPDATE notifications SET archived_at = NOW() 
                WHERE user_id = user_uuid AND archived_at IS NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // =============================================================================
        // SEARCH FUNCTIONS
        // =============================================================================
        
        $this->addSql('
            CREATE OR REPLACE FUNCTION search_snippets(
                search_query TEXT,
                user_id_filter UUID DEFAULT NULL,
                language_filter VARCHAR DEFAULT NULL,
                visibility_filter snippet_visibility DEFAULT NULL,
                include_content BOOLEAN DEFAULT TRUE,
                limit_results INTEGER DEFAULT 20,
                offset_results INTEGER DEFAULT 0
            )
            RETURNS TABLE (
                snippet_id UUID, title VARCHAR, description TEXT, language VARCHAR,
                content TEXT, user_id UUID, created_at TIMESTAMPTZ,
                view_count INTEGER, favorite_count INTEGER, search_rank REAL
            ) AS $$
            DECLARE
                ts_query TSQUERY;
            BEGIN
                BEGIN
                    ts_query := plainto_tsquery(\'english\', search_query);
                EXCEPTION
                    WHEN OTHERS THEN
                        ts_query := plainto_tsquery(\'english\', regexp_replace(search_query, \'[^\\w\\s]\', \'\', \'g\'));
                END;
                
                IF ts_query IS NULL THEN
                    RETURN;
                END IF;
                
                RETURN QUERY
                SELECT 
                    s.id, s.title, s.description, s.language,
                    CASE WHEN include_content THEN s.content ELSE NULL END,
                    s.user_id, s.created_at, s.view_count, s.favorite_count,
                    (ts_rank(s.content_search_vector, ts_query) + 
                     COALESCE(ts_rank(to_tsvector(\'english\', s.title), ts_query) * 2.0, 0))::REAL
                FROM snippets s
                WHERE 
                    s.deleted_at IS NULL
                    AND s.content_search_vector @@ ts_query
                    AND (user_id_filter IS NULL OR s.user_id = user_id_filter)
                    AND (language_filter IS NULL OR s.language ILIKE language_filter)
                    AND (visibility_filter IS NULL OR s.visibility = visibility_filter)
                    AND (s.visibility = \'public\' OR (user_id_filter IS NOT NULL AND s.user_id = user_id_filter))
                ORDER BY 
                    (ts_rank(s.content_search_vector, ts_query) + 
                     COALESCE(ts_rank(to_tsvector(\'english\', s.title), ts_query) * 2.0, 0)) DESC,
                    s.created_at DESC
                LIMIT limit_results OFFSET offset_results;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // =============================================================================
        // CREATE TRIGGERS
        // =============================================================================
        
        // Timestamp triggers
        $this->addSql('CREATE TRIGGER trigger_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_timestamp()');
        $this->addSql('CREATE TRIGGER trigger_snippets_updated_at BEFORE UPDATE ON snippets FOR EACH ROW EXECUTE FUNCTION update_timestamp()');
        $this->addSql('CREATE TRIGGER trigger_integrations_updated_at BEFORE UPDATE ON integrations FOR EACH ROW EXECUTE FUNCTION update_timestamp()');
        
        // Snippet-specific triggers
        $this->addSql('CREATE TRIGGER trigger_snippet_search_vector BEFORE INSERT OR UPDATE ON snippets FOR EACH ROW EXECUTE FUNCTION update_snippet_search_vector()');
        $this->addSql('CREATE TRIGGER trigger_detect_sensitive_data BEFORE INSERT OR UPDATE ON snippets FOR EACH ROW EXECUTE FUNCTION detect_sensitive_data()');
        
        // User-specific triggers
        $this->addSql('CREATE TRIGGER trigger_user_data_retention BEFORE INSERT OR UPDATE ON users FOR EACH ROW EXECUTE FUNCTION set_data_retention()');
        
        // Audit triggers
        $this->addSql('CREATE TRIGGER trigger_audit_users AFTER INSERT OR UPDATE OR DELETE ON users FOR EACH ROW EXECUTE FUNCTION log_audit_event()');
        $this->addSql('CREATE TRIGGER trigger_audit_snippets AFTER INSERT OR UPDATE OR DELETE ON snippets FOR EACH ROW EXECUTE FUNCTION log_audit_event()');
        $this->addSql('CREATE TRIGGER trigger_audit_shares AFTER INSERT OR UPDATE OR DELETE ON shares FOR EACH ROW EXECUTE FUNCTION log_audit_event()');
    }

    public function down(Schema $schema): void
    {
        // Drop triggers
        $this->addSql('DROP TRIGGER IF EXISTS trigger_audit_shares ON shares');
        $this->addSql('DROP TRIGGER IF EXISTS trigger_audit_snippets ON snippets');
        $this->addSql('DROP TRIGGER IF EXISTS trigger_audit_users ON users');
        $this->addSql('DROP TRIGGER IF EXISTS trigger_user_data_retention ON users');
        $this->addSql('DROP TRIGGER IF EXISTS trigger_detect_sensitive_data ON snippets');
        $this->addSql('DROP TRIGGER IF EXISTS trigger_snippet_search_vector ON snippets');
        $this->addSql('DROP TRIGGER IF EXISTS trigger_integrations_updated_at ON integrations');
        $this->addSql('DROP TRIGGER IF EXISTS trigger_snippets_updated_at ON snippets');
        $this->addSql('DROP TRIGGER IF EXISTS trigger_users_updated_at ON users');

        // Drop functions
        $this->addSql('DROP FUNCTION IF EXISTS search_snippets');
        $this->addSql('DROP FUNCTION IF EXISTS rgpd_delete_user_data');
        $this->addSql('DROP FUNCTION IF EXISTS reset_monthly_usage');
        $this->addSql('DROP FUNCTION IF EXISTS cleanup_expired_tokens');
        $this->addSql('DROP FUNCTION IF EXISTS log_audit_event');
        $this->addSql('DROP FUNCTION IF EXISTS set_data_retention');
        $this->addSql('DROP FUNCTION IF EXISTS detect_sensitive_data');
        $this->addSql('DROP FUNCTION IF EXISTS update_snippet_search_vector');
        $this->addSql('DROP FUNCTION IF EXISTS update_timestamp');
    }
}