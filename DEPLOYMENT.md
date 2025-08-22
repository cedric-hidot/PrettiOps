# PrettiOps Platform.sh Deployment Guide

## Current Deployment Status
- **URL**: https://main-bvxea6i-neryyvaw4ajlu.fr-3.platformsh.site/
- **Platform**: Platform.sh (France region)
- **PHP Version**: 8.2
- **Database**: PostgreSQL 16
- **Cache**: Redis

## Quick Fix for Blank Page Issue

If you're seeing a blank page after deployment, follow these steps:

### 1. Set Required Environment Variables

Run these commands using Platform.sh CLI:

```bash
# Install Platform.sh CLI if not already installed
curl -fsS https://platform.sh/cli/installer | bash

# Login to Platform.sh
platform login

# Navigate to your project
cd /path/to/PrettiOps

# Set required environment variables
platform variable:create --level project --name APP_SECRET --value "$(openssl rand -hex 32)" --json false --enabled true --inheritable true
platform variable:create --level project --name ENCRYPTION_KEY --value "$(openssl rand -hex 16)" --json false --enabled true --inheritable true
platform variable:create --level project --name DATABASE_URL --value "postgresql://main:main@database.internal:5432/main?serverVersion=16&charset=utf8" --json false --enabled true --inheritable true
```

### 2. Redeploy the Application

```bash
# Trigger a new deployment
platform redeploy
```

### 3. Check Application Logs

```bash
# View application logs
platform logs app

# SSH into the container to debug
platform ssh
```

## Common Issues and Solutions

### Issue: Blank Page
**Cause**: Missing environment variables or Symfony configuration issues
**Solution**: 
1. Ensure all environment variables are set (see above)
2. Check that APP_ENV is set to 'prod'
3. Clear and warm cache: `platform ssh "php bin/console cache:clear --env=prod"`

### Issue: Database Connection Failed
**Cause**: Incorrect DATABASE_URL
**Solution**: Platform.sh automatically provides database credentials through relationships. The DATABASE_URL should use the relationship name.

### Issue: Assets Not Loading
**Cause**: Webpack build failed or assets not installed
**Solution**: 
1. Check build logs: `platform logs build`
2. Manually install assets: `platform ssh "php bin/console assets:install --env=prod"`

## Essential Commands

```bash
# View environment variables
platform ssh "env | grep -E 'APP_|DATABASE_|REDIS_'"

# Clear Symfony cache
platform ssh "php bin/console cache:clear --env=prod"

# Run database migrations
platform ssh "php bin/console doctrine:migrations:migrate --no-interaction"

# Check Symfony requirements
platform ssh "php bin/console about"

# View real-time logs
platform logs app --tail
```

## Environment Variables Reference

| Variable | Description | Example |
|----------|-------------|---------|
| APP_SECRET | Symfony secret key | Random 32-char hex string |
| ENCRYPTION_KEY | Data encryption key | Random 16-char hex string |
| DATABASE_URL | PostgreSQL connection | Auto-provided by Platform.sh |
| REDIS_URL | Redis connection | Auto-provided by Platform.sh |
| APP_URL | Application URL | https://your-domain.platformsh.site |
| MAILER_DSN | Email configuration | smtp://localhost or actual SMTP |
| JWT_PASSPHRASE | JWT token passphrase | Random secure string |

## Deployment Checklist

- [ ] Environment variables configured
- [ ] Database migrations run successfully
- [ ] Assets compiled (npm run build)
- [ ] Cache cleared and warmed
- [ ] Health check endpoint responding (/health)
- [ ] Homepage loading correctly
- [ ] Static assets loading (CSS/JS)
- [ ] Database connectivity verified

## Support

If issues persist after following this guide:
1. Check Platform.sh build logs for errors
2. SSH into the container and check Symfony logs
3. Verify all services are running: `platform ssh "php bin/console debug:container"`
4. Test database connectivity: `platform ssh "php bin/console dbal:run-sql 'SELECT 1'"`