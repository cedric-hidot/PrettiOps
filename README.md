# PrettiOps - Beautiful Code Emails

**Enterprise-grade platform for transforming technical communication with professional code snippets, secure sharing, and seamless integrations.**

## 🚀 Quick Start

### Requirements
- PHP 8.2+
- Node.js 18+
- PostgreSQL 16+
- Redis 7+
- Composer 2.x

### Local Development Setup

```bash
# 1. Install dependencies
composer install
cd frontend && npm install && cd ..

# 2. Configure environment
cp .env.example .env
# Edit .env with your database credentials

# 3. Setup database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 4. Generate JWT keys
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

# 5. Build frontend assets
cd frontend && npm run dev && cd ..

# 6. Start development server
symfony serve -d
```

Visit: http://localhost:8000

## 🏗 Architecture

### Backend
- **Framework**: Symfony 7.1 LTS
- **API**: API Platform 3.x with OpenAPI documentation
- **Database**: PostgreSQL with optimized full-text search
- **Authentication**: JWT with refresh tokens
- **Security**: AES-256 encryption, automatic token masking, GDPR compliance

### Frontend
- **Framework**: Symfony UX (Turbo + Stimulus)
- **Styling**: Tailwind CSS with custom design system
- **Build**: Webpack Encore with TypeScript support
- **Components**: Modern interactive components with accessibility

### Infrastructure
- **Deployment**: Platform.sh with auto-scaling
- **Cache**: Redis for sessions and API rate limiting
- **Storage**: Secure file uploads with virus scanning
- **Monitoring**: Built-in health checks and performance metrics

## 🔐 Security Features

- **Data Encryption**: AES-256 encryption for sensitive data
- **Token Masking**: Automatic detection and masking of API keys/tokens
- **Access Control**: Role-based permissions with audit trails
- **GDPR Compliance**: Right to erasure, data portability, consent management
- **Rate Limiting**: API and form submission protection
- **Security Headers**: CSP, HSTS, and XSS protection

## 📊 Performance

- **Database**: Optimized indexes for sub-50ms search queries
- **Frontend**: Lazy loading, code splitting, optimized assets
- **API**: Efficient pagination and filtering
- **Caching**: Multi-layer caching strategy with Redis
- **CDN**: Static asset optimization and global delivery

## 🔧 Development

### Code Quality
```bash
# Run tests
composer test
npm test

# Code analysis
composer phpstan
npm run lint

# Format code
composer cs-fix
npm run format
```

### API Documentation
Visit `/api/docs` for interactive API documentation with examples.

## 🚀 Deployment

### Platform.sh
```bash
# Deploy to Platform.sh
platform push

# Set environment variables
platform variable:create --level project --name APP_SECRET --value "your-secret"
```

### Environment Variables
```bash
# Required for production
APP_ENV=prod
APP_SECRET=your-app-secret
DATABASE_URL=postgresql://user:pass@host:port/db
REDIS_URL=redis://host:port
JWT_PASSPHRASE=your-jwt-passphrase
ENCRYPTION_KEY=32-character-key
```

## 📝 API Overview

### Authentication
```bash
POST /api/auth/login
POST /api/auth/register
POST /api/auth/refresh
```

### Snippets
```bash
GET    /api/snippets          # List snippets
POST   /api/snippets          # Create snippet
GET    /api/snippets/{id}     # Get snippet
PUT    /api/snippets/{id}     # Update snippet
DELETE /api/snippets/{id}     # Delete snippet
```

### Sharing
```bash
POST   /api/snippets/{id}/share    # Create share link
GET    /api/shares/{token}         # Access shared snippet
```

## 🏢 Enterprise Features

- **SSO Integration**: OAuth2 with Google, GitHub, and custom providers
- **Team Management**: Organization accounts with role-based access
- **Analytics**: Usage statistics and performance metrics
- **White-label**: Custom branding and domain configuration
- **Compliance**: SOC 2, GDPR, and enterprise security standards

## 📞 Support

- **Documentation**: `/help` - Comprehensive user guides
- **API Reference**: `/docs` - Interactive API documentation
- **Security**: Report issues to security@prettiops.com
- **General Support**: support@prettiops.com

## 📄 License

Proprietary - All rights reserved.

---

**Built with ❤️ for developers who care about beautiful technical communication.**