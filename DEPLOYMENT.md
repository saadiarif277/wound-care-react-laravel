# Medical AI Service Deployment Guide

This guide covers deploying the Medical AI Service to your dev server with multiple deployment options.

## 📋 Prerequisites

- Ubuntu/Debian server with sudo access
- Python 3.8+ installed
- Docker (optional, for containerized deployment)
- Network access to Azure OpenAI (if using AI features)

## 🚀 Deployment Options

### Option 1: Systemd Service (Recommended)

**Best for**: Production-like deployments with automatic startup and process management.

1. **Initial Deployment:**
   ```bash
   # On your dev server
   cd /var/www/wound-care-react-laravel
   ./scripts/deploy_ai_service.sh
   ```

2. **After Code Updates:**
   ```bash
   # Quick restart after pushing changes
   ./scripts/update_ai_service.sh
   ```

3. **Service Management:**
   ```bash
   # Check status
   sudo systemctl status medical-ai-service
   
   # View logs
   sudo journalctl -u medical-ai-service -f
   
   # Restart service
   sudo systemctl restart medical-ai-service
   
   # Stop service
   sudo systemctl stop medical-ai-service
   ```

### Option 2: Docker Deployment

**Best for**: Containerized environments with easy scaling and isolation.

1. **Build and Start:**
   ```bash
   cd /var/www/wound-care-react-laravel/scripts
   
   # Create environment file
   cat > .env <<EOF
   AZURE_OPENAI_API_KEY=your_api_key_here
   AZURE_OPENAI_ENDPOINT=https://msc-ai-services.openai.azure.com/
   AZURE_OPENAI_DEPLOYMENT=gpt-4o
   ENABLE_LOCAL_FALLBACK=true
   EOF
   
   # Start with Docker Compose
   docker-compose up -d
   ```

2. **Management Commands:**
   ```bash
   # Check status
   docker-compose ps
   
   # View logs
   docker-compose logs -f medical-ai-service
   
   # Restart after updates
   docker-compose down && docker-compose up -d --build
   
   # Stop services
   docker-compose down
   ```

### Option 3: Manual Process (Development)

**Best for**: Development and testing environments.

```bash
# Navigate to scripts directory
cd /var/www/wound-care-react-laravel/scripts

# Activate virtual environment
source ai_service_env/bin/activate

# Set environment variables
export AZURE_OPENAI_ENDPOINT="https://msc-ai-services.openai.azure.com/"
export AZURE_OPENAI_API_KEY="your_api_key"
export ENABLE_LOCAL_FALLBACK="true"

# Start service
python -m uvicorn medical_ai_service:app --host 0.0.0.0 --port 8080
```

## 🔧 Configuration

### Environment Variables

| Variable | Description | Default | Required |
|----------|-------------|---------|----------|
| `AZURE_OPENAI_ENDPOINT` | Azure OpenAI service endpoint | None | No* |
| `AZURE_OPENAI_API_KEY` | Azure OpenAI API key | None | No* |
| `AZURE_OPENAI_DEPLOYMENT` | Model deployment name | `gpt-4o` | No |
| `AZURE_OPENAI_API_VERSION` | API version | `2024-02-15-preview` | No |
| `ENABLE_LOCAL_FALLBACK` | Enable local processing when AI unavailable | `true` | No |
| `API_HOST` | Service bind address | `0.0.0.0` | No |
| `API_PORT` | Service port | `8080` | No |
| `CACHE_TTL` | Response cache TTL in seconds | `3600` | No |

*Not required if `ENABLE_LOCAL_FALLBACK=true`

### Server Configuration

**For Systemd deployment**, update `/etc/systemd/system/medical-ai-service.service`:

```ini
[Service]
Environment=AZURE_OPENAI_API_KEY=your_new_api_key
Environment=ENABLE_LOCAL_FALLBACK=true
```

Then reload: `sudo systemctl daemon-reload && sudo systemctl restart medical-ai-service`

## 🔍 Health Monitoring

### Health Check Endpoints

- **Health Status**: `GET /health`
- **Service Status**: `GET /manufacturers` 
- **API Documentation**: `GET /docs`

### Example Health Check

```bash
# Basic health check
curl http://localhost:8080/health

# Expected response
{
  "status": "healthy",
  "timestamp": "2024-01-15T10:30:00",
  "azure_ai_status": "available",
  "local_fallback_enabled": true,
  "services": {
    "ai_agent": "running",
    "knowledge_base": "loaded",
    "manufacturer_configs": 11
  }
}
```

### Automated Monitoring

Add to your monitoring system:

```bash
#!/bin/bash
# Health check script for monitoring
HEALTH_URL="http://localhost:8080/health"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" $HEALTH_URL)

if [ $RESPONSE -eq 200 ]; then
    echo "✅ Medical AI Service is healthy"
    exit 0
else
    echo "❌ Medical AI Service is unhealthy (HTTP $RESPONSE)"
    exit 1
fi
```

## 📊 Performance & Scaling

### Resource Requirements

- **Minimum**: 1 CPU, 2GB RAM
- **Recommended**: 2 CPU, 4GB RAM
- **Storage**: 1GB for service + logs

### Scaling Options

1. **Horizontal Scaling** (Multiple instances):
   ```bash
   # Run on different ports
   docker-compose -f docker-compose.yml -f docker-compose.scale.yml up -d --scale medical-ai-service=3
   ```

2. **Load Balancing** (Nginx example):
   ```nginx
   upstream medical_ai {
       server localhost:8080;
       server localhost:8081;
       server localhost:8082;
   }
   
   server {
       listen 80;
       location / {
           proxy_pass http://medical_ai;
       }
   }
   ```

## 🐛 Troubleshooting

### Common Issues

1. **Service Won't Start**
   ```bash
   # Check service status
   sudo systemctl status medical-ai-service
   
   # Check detailed logs
   sudo journalctl -u medical-ai-service --since "5 minutes ago"
   
   # Check port availability
   sudo netstat -tulpn | grep :8080
   ```

2. **Azure AI Connection Issues**
   ```bash
   # Test Azure OpenAI connectivity
   curl -H "api-key: $AZURE_OPENAI_API_KEY" \
        "$AZURE_OPENAI_ENDPOINT/openai/deployments/gpt-4o/chat/completions?api-version=2024-02-15-preview" \
        -d '{"messages":[{"role":"user","content":"test"}],"max_tokens":1}'
   ```

3. **Configuration Issues**
   ```bash
   # Verify environment variables
   sudo systemctl show medical-ai-service --property=Environment
   
   # Test service import
   cd /var/www/wound-care-react-laravel/scripts
   source ai_service_env/bin/activate
   python -c "from medical_ai_service import app; print('✅ Import successful')"
   ```

### Log Locations

- **Systemd**: `sudo journalctl -u medical-ai-service`
- **Docker**: `docker-compose logs medical-ai-service`
- **Manual**: Service outputs to stdout/stderr

## 🔄 CI/CD Integration

### GitHub Actions Example

```yaml
name: Deploy Medical AI Service

on:
  push:
    branches: [ azure-dev ]
    paths: [ 'scripts/**' ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v3
    
    - name: Deploy to Dev Server
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.DEV_SERVER_HOST }}
        username: ${{ secrets.DEV_SERVER_USER }}
        key: ${{ secrets.DEV_SERVER_SSH_KEY }}
        script: |
          cd /var/www/wound-care-react-laravel
          git pull origin azure-dev
          ./scripts/update_ai_service.sh
```

### Manual Deployment After Git Push

```bash
# On your dev server
cd /var/www/wound-care-react-laravel
git pull origin azure-dev
./scripts/update_ai_service.sh
```

## 📞 Support

### Service Endpoints
- **Health**: http://your-server:8080/health
- **Manufacturers**: http://your-server:8080/manufacturers  
- **API Docs**: http://your-server:8080/docs

### Key Commands Reference
```bash
# Service management
sudo systemctl {start|stop|restart|status} medical-ai-service

# Logs
sudo journalctl -u medical-ai-service -f

# Health check
curl http://localhost:8080/health

# Update after code changes
./scripts/update_ai_service.sh
```

---

🏥 **Medical AI Service** - Intelligent field mapping and medical terminology validation for wound care workflows. 