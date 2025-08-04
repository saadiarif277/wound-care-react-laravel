# System Administration Guide

## 🛠️ System Administration Overview

This guide provides comprehensive instructions for system administrators managing the MSC Wound Care Portal infrastructure, configurations, and maintenance tasks.

## 🏗️ System Architecture Overview

### Infrastructure Components
- **Application Servers**: Laravel application hosting
- **Database Servers**: MySQL primary and read replicas
- **Cache Layer**: Redis for sessions and application cache
- **File Storage**: Azure Blob Storage for documents and images
- **Load Balancer**: Azure Application Gateway
- **CDN**: Azure CDN for static assets
- **Monitoring**: Application Insights and custom monitoring

### Environment Tiers
- **Production**: Live production environment
- **Staging**: Pre-production testing environment
- **Development**: Development and testing environment
- **Local**: Developer local environments

## 🔧 Server Management

### Application Server Administration

#### Server Monitoring
```bash
# Check server health
sudo systemctl status php8.2-fpm
sudo systemctl status nginx
sudo systemctl status redis-server

# Monitor resource usage
top
htop
df -h
free -m

# Check logs
tail -f /var/log/nginx/error.log
tail -f /var/log/php8.2-fpm.log
tail -f /var/www/html/storage/logs/laravel.log
```

#### Performance Optimization
```bash
# PHP-FPM Configuration
sudo nano /etc/php/8.2/fpm/pool.d/www.conf

# Key settings:
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
pm.max_requests = 500

# Nginx Configuration
sudo nano /etc/nginx/sites-available/msc-portal

# Key settings for performance:
client_max_body_size 100M;
fastcgi_cache_valid 200 60m;
gzip on;
gzip_comp_level 6;
```

#### SSL Certificate Management
```bash
# Using Let's Encrypt with Certbot
sudo certbot --nginx -d portal.mscwoundcare.com

# Certificate renewal (automated via cron)
sudo certbot renew --dry-run

# Manual certificate installation
sudo cp certificate.crt /etc/ssl/certs/
sudo cp private.key /etc/ssl/private/
sudo chown root:ssl-cert /etc/ssl/private/private.key
sudo chmod 640 /etc/ssl/private/private.key
```

### Database Administration

#### MySQL Management
```sql
-- Monitor database performance
SHOW PROCESSLIST;
SHOW ENGINE INNODB STATUS\G
SHOW GLOBAL STATUS LIKE 'Threads_connected';

-- Database optimization
OPTIMIZE TABLE patients;
ANALYZE TABLE orders;

-- Index analysis
SHOW INDEX FROM patients;
EXPLAIN SELECT * FROM patients WHERE email = 'test@example.com';

-- Backup database
mysqldump -u root -p wound_care_portal > backup_$(date +%Y%m%d_%H%M%S).sql

-- Restore database
mysql -u root -p wound_care_portal < backup_20240115_143000.sql
```

#### Database Maintenance
```bash
# Automated backup script
#!/bin/bash
# /usr/local/bin/db_backup.sh

DB_NAME="wound_care_portal"
DB_USER="backup_user"
DB_PASS="secure_password"
BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/backup_$DATE.sql.gz

# Keep only last 30 days of backups
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete

# Upload to Azure Storage
az storage blob upload \
    --account-name mscbackupstorage \
    --container-name database-backups \
    --name backup_$DATE.sql.gz \
    --file $BACKUP_DIR/backup_$DATE.sql.gz
```

### Redis Administration
```bash
# Connect to Redis CLI
redis-cli

# Monitor Redis
redis-cli INFO
redis-cli MONITOR

# Check memory usage
redis-cli INFO memory

# Clear specific cache
redis-cli FLUSHDB

# Backup Redis data
redis-cli BGSAVE

# Common Redis commands for MSC Portal
redis-cli KEYS "msc_portal:*"
redis-cli GET "msc_portal:user:sessions:123"
redis-cli DEL "msc_portal:cache:orders"
```

## 🔒 Security Administration

### User Account Management
```bash
# Create system user for application
sudo useradd -m -s /bin/bash msc-portal
sudo usermod -aG www-data msc-portal

# Set up SSH key authentication
sudo mkdir -p /home/msc-portal/.ssh
sudo cp authorized_keys /home/msc-portal/.ssh/
sudo chown -R msc-portal:msc-portal /home/msc-portal/.ssh
sudo chmod 700 /home/msc-portal/.ssh
sudo chmod 600 /home/msc-portal/.ssh/authorized_keys
```

### Firewall Configuration
```bash
# UFW (Uncomplicated Firewall) setup
sudo ufw enable
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow necessary ports
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw allow from 10.0.0.0/8 to any port 3306  # MySQL (internal only)
sudo ufw allow from 10.0.0.0/8 to any port 6379  # Redis (internal only)

# Check status
sudo ufw status verbose
```

### Security Hardening
```bash
# Disable root login and password authentication
sudo nano /etc/ssh/sshd_config

# Key settings:
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
AllowUsers msc-portal

# Restart SSH service
sudo systemctl restart sshd

# Install and configure fail2ban
sudo apt install fail2ban
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
sudo nano /etc/fail2ban/jail.local

# Configure for web application
[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
```

## 📊 Monitoring & Alerting

### Application Monitoring
```php
// Laravel application monitoring
// config/logging.php
'channels' => [
    'monitoring' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
    ],
    
    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'MSC Portal Monitor',
        'emoji' => ':warning:',
        'level' => 'error',
    ],
],
```

### System Monitoring Scripts
```bash
#!/bin/bash
# /usr/local/bin/system_monitor.sh

# Check disk space
DISK_USAGE=$(df -h | grep -vE '^Filesystem|tmpfs|cdrom' | awk '{ print $5 " " $1 }' | grep -E '^[8-9][0-9]%|^100%')
if [ ! -z "$DISK_USAGE" ]; then
    echo "Disk usage alert: $DISK_USAGE" | mail -s "Disk Space Alert" admin@mscwoundcare.com
fi

# Check memory usage
MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.2f"), $3/$2 * 100.0}')
if (( $(echo "$MEMORY_USAGE > 90" | bc -l) )); then
    echo "Memory usage is at $MEMORY_USAGE%" | mail -s "Memory Alert" admin@mscwoundcare.com
fi

# Check application availability
HTTP_STATUS=$(curl -o /dev/null -s -w "%{http_code}\n" https://portal.mscwoundcare.com/health)
if [ $HTTP_STATUS -ne 200 ]; then
    echo "Application health check failed with status: $HTTP_STATUS" | mail -s "Application Alert" admin@mscwoundcare.com
fi
```

### Log Management
```bash
# Logrotate configuration for application logs
sudo nano /etc/logrotate.d/msc-portal

/var/www/html/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        sudo systemctl reload php8.2-fpm
    endscript
}

# Test logrotate configuration
sudo logrotate -d /etc/logrotate.d/msc-portal
```

## 🚀 Deployment Management

### Deployment Process
```bash
#!/bin/bash
# /usr/local/bin/deploy.sh

APP_DIR="/var/www/html"
BACKUP_DIR="/var/backups/deployments"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup
sudo tar -czf $BACKUP_DIR/backup_$DATE.tar.gz $APP_DIR

# Pull latest code
cd $APP_DIR
sudo git pull origin main

# Update dependencies
sudo composer install --no-dev --optimize-autoloader
sudo npm ci --production

# Build assets
sudo npm run production

# Run migrations
sudo php artisan migrate --force

# Clear caches
sudo php artisan cache:clear
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx

# Verify deployment
curl -f https://portal.mscwoundcare.com/health || {
    echo "Deployment verification failed, rolling back..."
    sudo tar -xzf $BACKUP_DIR/backup_$DATE.tar.gz -C /
    sudo systemctl restart php8.2-fpm
    exit 1
}

echo "Deployment completed successfully"
```

### Blue-Green Deployment
```bash
# Blue-green deployment script
#!/bin/bash

BLUE_DIR="/var/www/blue"
GREEN_DIR="/var/www/green"
CURRENT_LINK="/var/www/html"

# Determine current environment
if [ -L $CURRENT_LINK ]; then
    CURRENT_TARGET=$(readlink $CURRENT_LINK)
    if [ "$CURRENT_TARGET" = "$BLUE_DIR" ]; then
        NEW_ENV="green"
        NEW_DIR=$GREEN_DIR
    else
        NEW_ENV="blue"
        NEW_DIR=$BLUE_DIR
    fi
else
    NEW_ENV="blue"
    NEW_DIR=$BLUE_DIR
fi

# Deploy to new environment
echo "Deploying to $NEW_ENV environment..."
rsync -av --exclude='.git' --exclude='storage/logs' /tmp/deploy/ $NEW_DIR/

# Switch traffic
sudo ln -sfn $NEW_DIR $CURRENT_LINK
sudo systemctl reload nginx

echo "Switched to $NEW_ENV environment"
```

## 🔧 Configuration Management

### Environment Configuration
```bash
# Production environment settings
sudo nano /var/www/html/.env

# Critical production settings:
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-production-key

# Database settings
DB_CONNECTION=mysql
DB_HOST=prod-mysql-primary.internal
DB_PORT=3306
DB_DATABASE=wound_care_portal
DB_USERNAME=app_user
DB_PASSWORD=secure_production_password

# Redis settings
REDIS_HOST=prod-redis.internal
REDIS_PASSWORD=secure_redis_password

# Mail settings
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key

# Azure settings
AZURE_STORAGE_ACCOUNT=mscprodstorage
AZURE_STORAGE_KEY=your_production_key
```

### Application Configuration
```php
// config/app.php - Production optimizations
'debug' => env('APP_DEBUG', false),
'url' => env('APP_URL', 'https://portal.mscwoundcare.com'),

// config/session.php
'lifetime' => env('SESSION_LIFETIME', 480), // 8 hours
'secure' => env('SESSION_SECURE_COOKIE', true),
'http_only' => true,
'same_site' => 'strict',

// config/database.php - Production database config
'mysql' => [
    'read' => [
        'host' => [
            'prod-mysql-read-1.internal',
            'prod-mysql-read-2.internal',
        ],
    ],
    'write' => [
        'host' => ['prod-mysql-primary.internal'],
    ],
    'options' => [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
],
```

## 📈 Performance Tuning

### Application Performance
```bash
# Optimize Composer autoloader
composer dump-autoload --optimize --classmap-authoritative

# Enable OPcache
sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini

opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=0
opcache.validate_timestamps=0  # Production only
opcache.save_comments=1
opcache.fast_shutdown=1
```

### Database Performance Tuning
```sql
-- MySQL configuration optimization
-- /etc/mysql/mysql.conf.d/mysqld.cnf

[mysqld]
# Buffer pool size (75% of available RAM)
innodb_buffer_pool_size = 12G

# Log file size
innodb_log_file_size = 1G

# Query cache
query_cache_size = 256M
query_cache_type = 1

# Connection limits
max_connections = 200
max_user_connections = 180

# Slow query log
slow_query_log = 1
long_query_time = 2
slow_query_log_file = /var/log/mysql/slow.log
```

## 🔄 Backup & Recovery

### Automated Backup Strategy
```bash
#!/bin/bash
# Comprehensive backup script

BACKUP_DIR="/var/backups/msc-portal"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Database backup
mysqldump -u backup_user -p$DB_PASSWORD wound_care_portal | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Application files backup
tar -czf $BACKUP_DIR/app_$DATE.tar.gz /var/www/html --exclude='storage/logs' --exclude='node_modules'

# Configuration backup
tar -czf $BACKUP_DIR/config_$DATE.tar.gz /etc/nginx /etc/php /etc/mysql/mysql.conf.d

# Upload to Azure Storage
az storage blob upload-batch \
    --destination backups \
    --source $BACKUP_DIR \
    --account-name mscbackupstorage

# Clean old backups
find $BACKUP_DIR -name "*.gz" -mtime +$RETENTION_DAYS -delete
```

### Disaster Recovery Plan
```bash
#!/bin/bash
# Disaster recovery restoration script

BACKUP_DATE=$1
BACKUP_SOURCE="azure"  # or "local"

if [ -z "$BACKUP_DATE" ]; then
    echo "Usage: $0 <backup_date>"
    echo "Example: $0 20240115_143000"
    exit 1
fi

# Stop services
sudo systemctl stop nginx php8.2-fpm

# Download backups from Azure if needed
if [ "$BACKUP_SOURCE" = "azure" ]; then
    az storage blob download \
        --container-name backups \
        --name db_$BACKUP_DATE.sql.gz \
        --file /tmp/db_restore.sql.gz \
        --account-name mscbackupstorage
fi

# Restore database
gunzip < /tmp/db_restore.sql.gz | mysql -u root -p wound_care_portal

# Restore application files
tar -xzf /var/backups/msc-portal/app_$BACKUP_DATE.tar.gz -C /

# Restart services
sudo systemctl start php8.2-fpm nginx

echo "Recovery completed for backup: $BACKUP_DATE"
```

## 🚨 Incident Response

### Emergency Procedures
```bash
# Emergency maintenance mode
php artisan down --message="Emergency maintenance in progress"

# Quick service restart
sudo systemctl restart php8.2-fpm nginx mysql redis-server

# Emergency rollback
sudo git reset --hard HEAD~1
sudo systemctl restart php8.2-fpm

# Check for malware or security issues
sudo clamscan -r /var/www/html
sudo rkhunter --check
```

### Health Checks
```bash
#!/bin/bash
# Comprehensive health check script

echo "=== MSC Portal Health Check ==="

# Check services
services=("nginx" "php8.2-fpm" "mysql" "redis-server")
for service in "${services[@]}"; do
    if systemctl is-active --quiet $service; then
        echo "✓ $service is running"
    else
        echo "✗ $service is not running"
    fi
done

# Check application
HTTP_STATUS=$(curl -o /dev/null -s -w "%{http_code}\n" https://portal.mscwoundcare.com/health)
if [ $HTTP_STATUS -eq 200 ]; then
    echo "✓ Application is responding"
else
    echo "✗ Application health check failed (Status: $HTTP_STATUS)"
fi

# Check database connectivity
if mysql -u health_check -p$HEALTH_CHECK_PASSWORD -e "SELECT 1" > /dev/null 2>&1; then
    echo "✓ Database is accessible"
else
    echo "✗ Database connection failed"
fi

# Check disk space
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -lt 90 ]; then
    echo "✓ Disk usage is acceptable ($DISK_USAGE%)"
else
    echo "✗ Disk usage is high ($DISK_USAGE%)"
fi
```

## 📚 Documentation & Compliance

### Change Management
- **Change Requests**: Document all system changes
- **Approval Process**: Require approval for production changes
- **Rollback Plans**: Maintain rollback procedures for all changes
- **Testing**: Require testing in staging environment

### Audit Compliance
- **Access Logs**: Maintain comprehensive access logs
- **Change Logs**: Document all system modifications
- **Security Reviews**: Regular security assessments
- **Compliance Reports**: Generate compliance documentation

## 📞 Support Contacts

### Emergency Contacts
- **System Administrator**: admin@mscwoundcare.com
- **Database Administrator**: dba@mscwoundcare.com
- **Security Team**: security@mscwoundcare.com
- **24/7 Emergency**: 1-800-MSC-EMERGENCY

### Vendor Support
- **Azure Support**: Azure portal support requests
- **MySQL Support**: Enterprise support contact
- **SSL Certificate**: Certificate authority support

## 📖 Additional Resources

- [Azure Documentation](https://docs.microsoft.com/azure/)
- [Laravel Deployment Guide](https://laravel.com/docs/deployment)
- [MySQL Administration Guide](https://dev.mysql.com/doc/refman/8.0/en/server-administration.html)
- [Nginx Documentation](https://nginx.org/en/docs/)
