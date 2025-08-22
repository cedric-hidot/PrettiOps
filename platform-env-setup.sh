#!/bin/bash
# Platform.sh Environment Variables Setup Script
# Run this script to configure required environment variables for PrettiOps

echo "Setting up Platform.sh environment variables for PrettiOps..."

# Generate a secure random key for encryption (32 characters)
ENCRYPTION_KEY=$(openssl rand -hex 16)

# Set environment variables using Platform.sh CLI
# Note: You need to have Platform.sh CLI installed and be logged in

platform variable:create --level project --name ENCRYPTION_KEY --value "$ENCRYPTION_KEY" --json false --enabled true --inheritable true
platform variable:create --level project --name APP_URL --value "https://main-bvxea6i-neryyvaw4ajlu.fr-3.platformsh.site" --json false --enabled true --inheritable true
platform variable:create --level project --name MAILER_DSN --value "smtp://localhost" --json false --enabled true --inheritable true
platform variable:create --level project --name MAX_FILE_SIZE --value "10485760" --json false --enabled true --inheritable true
platform variable:create --level project --name ALLOWED_EXTENSIONS --value "txt,md,json,xml,yml,yaml,js,ts,php,py,java,cpp,c,go,rs,rb" --json false --enabled true --inheritable true
platform variable:create --level project --name CORS_ALLOW_ORIGIN --value "^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$" --json false --enabled true --inheritable true
platform variable:create --level project --name RATE_LIMIT_API --value "100" --json false --enabled true --inheritable true
platform variable:create --level project --name RATE_LIMIT_LOGIN --value "5" --json false --enabled true --inheritable true
platform variable:create --level project --name RATE_LIMIT_REGISTER --value "3" --json false --enabled true --inheritable true
platform variable:create --level project --name AES_CIPHER --value "AES-256-CBC" --json false --enabled true --inheritable true
platform variable:create --level project --name JWT_PASSPHRASE --value "$(openssl rand -hex 32)" --json false --enabled true --inheritable true

echo "Environment variables have been set!"
echo "Note: You may need to redeploy for changes to take effect"
echo ""
echo "IMPORTANT: Save this encryption key securely: $ENCRYPTION_KEY"
echo ""
echo "To redeploy, run: platform redeploy"