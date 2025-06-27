# MSC Wound Portal - Security & Deployment Guide

## 🚨 Critical Security Fixes Required Before Production

### Immediate Actions (Day 1)

1. **Re-enable CSRF Protection**
   ```bash
   # Already fixed in bootstrap/app.php
   php artisan config:clear
   ```

2. **Remove Debug Routes**
   ```bash
   # Debug routes have been removed from routes/web.php
   # Verify no debug routes exist:
   grep -r "debug\|test" routes/
   ```

3. **Enable Session Encryption**
   ```bash
   # Update .env file:
   SESSION_ENCRYPT=true
   SESSION_LIFETIME=30
   SESSION_SECURE_COOKIE=true
   ```

4. **Configure Rate Limiting**
   ```bash
   # Rate limiting middleware has been added
   # Test rate limits:
   php artisan test --filter=RateLimitTest
   ```

### Security Configuration Checklist

- [ ] CSRF protection enabled
- [ ] Session encryption enabled
- [ ] Secure cookies in production
- [ ] Rate limiting on all public endpoints
- [ ] Webhook signature verification
- [ ] Security headers middleware applied
- [ ] PHI-safe logging implemented
- [ ] API authentication required
- [ ] Debug mode disabled in production

## 🏥 HIPAA Compliance Requirements

### PHI Data Protection

1. **Audit Logging**
   - All PHI access is logged to `storage/logs/phi-audit.log`
   - Logs retained for 6 years per HIPAA requirements
   - Use PhiSafeLogger for all logging operations

2. **Encryption**
   - Database: Azure SQL with TDE enabled
   - File Storage: AWS S3 with KMS encryption
   - Sessions: Encrypted by default
   - API: TLS 1.2+ required

3. **Access Controls**
   - Role-based permissions implemented
   - PHI access requires explicit permission
   - Automatic session timeout (30 minutes)
   - Multi-factor authentication recommended

## 🚀 Production Deployment Steps

### Prerequisites

```bash
# Required versions
PHP >= 8.3
Node.js >= 22 LTS
Composer >= 2.x
Redis Server
```

### Environment Setup

1. **Clone and Install**
   ```bash
   git clone <repository>
   cd msc-wound-portal
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   
   # Edit .env with production values:
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://app.mscwoundcare.com
   
   # Database
   DB_CONNECTION=sqlsrv
   DB_HOST=<azure-sql-server>
   DB_DATABASE=<database-name>
   DB_USERNAME=<username>
   DB_PASSWORD=<strong-password>
   
   # Redis
   REDIS_HOST=<redis-endpoint>
   REDIS_PASSWORD=<redis-password>
   
   # Session Security
   SESSION_DRIVER=redis
   SESSION_ENCRYPT=true
   SESSION_SECURE_COOKIE=true
   SESSION_LIFETIME=30
   
   # FHIR Configuration
   AZURE_FHIR_URL=https://<workspace>.fhir.azurehealthcareapis.com
   AZURE_FHIR_TENANT_ID=<tenant-id>
   AZURE_FHIR_CLIENT_ID=<client-id>
   AZURE_FHIR_CLIENT_SECRET=<client-secret>
   
   # AWS S3
   AWS_ACCESS_KEY_ID=<access-key>
   AWS_SECRET_ACCESS_KEY=<secret-key>
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=msc-wound-portal-docs
   AWS_KMS_KEY_ID=<kms-key-id>
   
   # DocuSeal
   DOCUSEAL_API_KEY=<api-key>
   DOCUSEAL_WEBHOOK_SECRET=<webhook-secret>
   ```

3. **Database Migration**
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=ProductionSeeder
   ```

4. **Optimize Application**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   php artisan optimize
   ```

5. **Set Permissions**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

### Web Server Configuration

#### Nginx Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name app.mscwoundcare.com;
    root /var/www/msc-wound-portal/public;

    ssl_certificate /etc/ssl/certs/mscwoundcare.crt;
    ssl_certificate_key /etc/ssl/private/mscwoundcare.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "DENY";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()";

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Queue Workers

```bash
# Supervisor configuration
[program:msc-wound-portal-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/msc-wound-portal/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/msc-wound-portal/storage/logs/worker.log
```

### Scheduled Tasks

```bash
# Add to crontab
* * * * * cd /var/www/msc-wound-portal && php artisan schedule:run >> /dev/null 2>&1
```

### Health Checks

```bash
# Application health check
curl https://app.mscwoundcare.com/api/health

# FHIR connection test
php artisan fhir:test-connection

# Queue health check
php artisan queue:monitor
```

## 🔍 Monitoring & Logging

### Application Performance Monitoring

1. **Configure Sentry**
   ```env
   SENTRY_LARAVEL_DSN=<sentry-dsn>
   SENTRY_ENVIRONMENT=production
   SENTRY_SEND_DEFAULT_PII=false
   ```

2. **CloudWatch Logs**
   ```bash
   # Install CloudWatch agent
   # Configure log groups for:
   # - /aws/elasticbeanstalk/msc-wound-portal/laravel.log
   # - /aws/elasticbeanstalk/msc-wound-portal/phi-audit.log
   # - /aws/elasticbeanstalk/msc-wound-portal/security.log
   ```

3. **Custom Metrics**
   ```php
   // Track critical business metrics
   app('metrics')->increment('episodes.created');
   app('metrics')->histogram('api.response_time', $duration);
   ```

### Security Monitoring

1. **Failed Login Attempts**
   ```bash
   grep "Failed login" storage/logs/security.log | tail -100
   ```

2. **API Abuse Detection**
   ```bash
   grep "API abuse detected" storage/logs/security.log
   ```

3. **PHI Access Audit**
   ```bash
   # Review PHI access patterns
   php artisan audit:phi-access --days=7
   ```

## 🔐 Security Best Practices

### Code Security

1. **Dependency Scanning**
   ```bash
   composer audit
   npm audit
   ```

2. **Static Analysis**
   ```bash
   ./vendor/bin/phpstan analyse
   ./vendor/bin/psalm
   ```

3. **Security Testing**
   ```bash
   php artisan test --testsuite=Security
   ```

### Infrastructure Security

1. **Azure SQL Database**
   - Enable Advanced Threat Protection
   - Configure firewall rules
   - Enable auditing
   - Use managed identities

2. **Azure Health Data Services**
   - Configure RBAC
   - Enable diagnostic logs
   - Set up alerts
   - Regular backups

3. **AWS S3**
   - Enable versioning
   - Configure lifecycle policies
   - Block public access
   - Enable CloudTrail

### Incident Response Plan

1. **PHI Breach Response**
   - Immediately disable affected accounts
   - Run `php artisan security:lock-phi`
   - Review audit logs
   - Notify compliance officer
   - Document incident

2. **Security Incident Checklist**
   - [ ] Isolate affected systems
   - [ ] Preserve evidence
   - [ ] Assess impact
   - [ ] Notify stakeholders
   - [ ] Implement fixes
   - [ ] Document lessons learned

## 📋 Deployment Checklist

### Pre-Deployment
- [ ] All tests passing
- [ ] Security scan completed
- [ ] Performance testing done
- [ ] Backup procedures tested
- [ ] Rollback plan documented

### Deployment
- [ ] Database migrations run
- [ ] Cache cleared and rebuilt
- [ ] Queue workers restarted
- [ ] SSL certificates valid
- [ ] Health checks passing

### Post-Deployment
- [ ] Monitor error rates
- [ ] Check performance metrics
- [ ] Verify PHI audit logging
- [ ] Test critical workflows
- [ ] Update documentation

## 🆘 Emergency Contacts

- **DevOps Lead**: devops@mscwoundcare.com
- **Security Team**: security@mscwoundcare.com
- **HIPAA Compliance**: compliance@mscwoundcare.com
- **On-Call Engineer**: +1-XXX-XXX-XXXX

## 📚 Additional Resources

- [Laravel Security Documentation](https://laravel.com/docs/security)
- [HIPAA Compliance Guide](https://www.hhs.gov/hipaa/for-professionals/security/index.html)
- [Azure Security Best Practices](https://docs.microsoft.com/en-us/azure/security/fundamentals/best-practices-and-patterns)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)

---

**Last Updated**: January 2024
**Document Version**: 1.0
**Next Review**: March 2024