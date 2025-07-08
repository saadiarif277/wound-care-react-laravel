# Medical AI Service Automation

This directory contains scripts to automatically start the Python Medical AI Service whenever your server boots up.

## 🚀 Quick Start

### 1. Install the Service

```bash
# Make the scripts executable
chmod +x install-service.sh manage-service.sh

# Install and start the service (requires sudo)
sudo ./install-service.sh install
```

This will:
- Create a Python virtual environment
- Install Python dependencies in the virtual environment
- Create a systemd service
- Enable automatic startup
- Start the service
- Test that it's working

### 2. Verify Installation

```bash
# Check service status
./manage-service.sh status

# Test API endpoints
./manage-service.sh test
```

## 📋 Available Scripts

### `install-service.sh` (Requires sudo)
Main installation and management script for the systemd service.

```bash
sudo ./install-service.sh install      # Install and start the service
sudo ./install-service.sh uninstall    # Remove the service
sudo ./install-service.sh status       # Show detailed status
sudo ./install-service.sh restart      # Restart the service
sudo ./install-service.sh logs         # Show logs
```

### `manage-service.sh` (User-friendly)
Day-to-day management script that doesn't require constant sudo.

```bash
./manage-service.sh status      # Check service status
./manage-service.sh start       # Start the service
./manage-service.sh stop        # Stop the service
./manage-service.sh restart     # Restart the service
./manage-service.sh logs        # Show recent logs
./manage-service.sh logs -f     # Show live logs
./manage-service.sh test        # Test API endpoints
./manage-service.sh enable      # Enable auto-startup
./manage-service.sh disable     # Disable auto-startup
./manage-service.sh setup       # Quick enable + start
```

## 🔧 Service Configuration

The service runs with these settings:
- **Port**: 8080 (configurable via API_PORT environment variable)
- **User**: rvalen (your current user)
- **Auto-restart**: Yes (restarts if it crashes)
- **Startup**: Automatic (starts on server boot)
- **Logs**: Available via systemd journal

## 📊 Monitoring

### Check Service Status
```bash
./manage-service.sh status
```

### View Live Logs
```bash
./manage-service.sh logs -f
```

### Test API Health
```bash
./manage-service.sh test
```

### Manual API Testing
```bash
# Health check
curl http://localhost:8080/health

# Check terminology stats
curl http://localhost:8080/terminology-stats

# List manufacturers
curl http://localhost:8080/manufacturers
```

## 🛠️ Troubleshooting

### Service Won't Start
1. Check logs: `./manage-service.sh logs`
2. Verify Python dependencies: `../venv/bin/pip install -r ../requirements.txt`
3. Check virtual environment exists: `ls -la ../venv/`
4. Check environment variables in `.env` file
5. Ensure port 8080 is not in use: `sudo netstat -tlpn | grep 8080`

### Service Crashes
1. View crash logs: `./manage-service.sh logs`
2. Restart service: `./manage-service.sh restart`
3. Check for missing environment variables

### Permission Issues
1. Ensure user 'rvalen' exists and has proper permissions
2. Check file ownership: `ls -la ../../scripts/`
3. Reinstall service: `sudo ./install-service.sh uninstall && sudo ./install-service.sh install`

### API Not Responding
1. Check if service is running: `./manage-service.sh status`
2. Test network connectivity: `curl http://localhost:8080/health`
3. Check firewall settings (if applicable)

## 🔄 Manual Operations

### Start Service Manually (Development)
```bash
cd ../
# Using virtual environment
./venv/bin/python medical_ai_service.py
# OR activate virtual environment first
source venv/bin/activate
python medical_ai_service.py
```

### Stop Service
```bash
./manage-service.sh stop
```

### Restart After Code Changes
```bash
./manage-service.sh restart
```

### Disable Automatic Startup
```bash
./manage-service.sh disable
```

## 📁 Files Created

- `/etc/systemd/system/medical-ai-service.service` - Systemd service file
- Service logs in systemd journal (viewable with `journalctl`)

## 🔐 Security Notes

- Service runs as user 'rvalen' (non-root)
- Limited file system access
- Environment variables loaded from `.env` file
- Logs are accessible via systemd journal

## 🆘 Need Help?

### Common Commands Reference
```bash
# Installation
sudo ./install-service.sh install

# Daily management
./manage-service.sh status
./manage-service.sh restart
./manage-service.sh logs -f

# Testing
./manage-service.sh test
curl http://localhost:8080/health

# Cleanup
sudo ./install-service.sh uninstall
```

### Service Integration with Laravel

The medical AI service integrates with your Laravel application through the `DynamicFieldMappingService`. Once the service is running:

1. Laravel will automatically use it for DocuSeal field mapping
2. Check Laravel logs for integration status
3. Test integration: `php artisan command:test-dynamic-mapping`

### Environment Variables

Key environment variables (set in `.env`):
- `AZURE_OPENAI_ENDPOINT` - Azure OpenAI endpoint
- `AZURE_OPENAI_API_KEY` - Azure OpenAI API key
- `DOCUSEAL_API_KEY` - DocuSeal API key
- `API_HOST` - Service host (default: 0.0.0.0)
- `API_PORT` - Service port (default: 8080) 