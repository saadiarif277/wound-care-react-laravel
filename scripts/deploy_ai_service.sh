#!/bin/bash

# Medical AI Service Deployment Script
# Run this script on your dev server after pushing code

set -e  # Exit on any error

echo "🚀 Starting Medical AI Service Deployment..."

# Configuration
SERVICE_NAME="medical-ai-service"
PROJECT_PATH="/var/www/wound-care-react-laravel"
SCRIPTS_PATH="$PROJECT_PATH/scripts"
PYTHON_ENV_PATH="$SCRIPTS_PATH/ai_service_env"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root"
   exit 1
fi

# 1. Update system packages
print_status "Updating system packages..."
sudo apt update && sudo apt upgrade -y

# 2. Install Python dependencies if not present
print_status "Installing Python and pip..."
sudo apt install -y python3 python3-pip python3-venv

# 3. Navigate to project directory
if [ ! -d "$PROJECT_PATH" ]; then
    print_error "Project directory not found: $PROJECT_PATH"
    exit 1
fi

cd "$PROJECT_PATH"
print_status "Changed to project directory: $PROJECT_PATH"

# 4. Setup Python virtual environment
print_status "Setting up Python virtual environment..."
cd scripts

if [ ! -d "ai_service_env" ]; then
    python3 -m venv ai_service_env
    print_status "Created virtual environment"
else
    print_warning "Virtual environment already exists"
fi

# 5. Activate environment and install dependencies
print_status "Installing Python dependencies..."
source ai_service_env/bin/activate

# Install required packages
pip install --upgrade pip
pip install fastapi uvicorn python-multipart
pip install openai azure-identity cachetools redis
pip install httpx

print_status "Python dependencies installed"

# 6. Test the service
print_status "Testing service configuration..."
python3 -c "
import sys
sys.path.append('.')
from medical_ai_service import app
print('✅ Service imports successfully')
"

# 7. Setup systemd service
print_status "Setting up systemd service..."

# Copy service file to systemd directory
sudo cp medical-ai-service.service /etc/systemd/system/

# Update the working directory in service file to match current path
sudo sed -i "s|/var/www/wound-care-react-laravel|$PROJECT_PATH|g" /etc/systemd/system/medical-ai-service.service

# Set correct permissions
sudo chown root:root /etc/systemd/system/medical-ai-service.service
sudo chmod 644 /etc/systemd/system/medical-ai-service.service

# Reload systemd and enable service
sudo systemctl daemon-reload
sudo systemctl enable $SERVICE_NAME

print_status "Systemd service configured"

# 8. Start the service
print_status "Starting Medical AI Service..."

# Stop service if already running
if sudo systemctl is-active --quiet $SERVICE_NAME; then
    print_warning "Service is already running, restarting..."
    sudo systemctl restart $SERVICE_NAME
else
    sudo systemctl start $SERVICE_NAME
fi

# 9. Check service status
sleep 3
if sudo systemctl is-active --quiet $SERVICE_NAME; then
    print_status "Service started successfully!"
    
    # Show service status
    echo ""
    echo "📊 Service Status:"
    sudo systemctl status $SERVICE_NAME --no-pager
    
    # Test the health endpoint
    echo ""
    echo "🏥 Testing health endpoint..."
    sleep 2
    if curl -s http://localhost:8080/health > /dev/null; then
        print_status "Health endpoint responding"
        echo "Response:"
        curl -s http://localhost:8080/health | python3 -m json.tool || echo "Service is running but response is not JSON"
    else
        print_warning "Health endpoint not responding yet (may still be starting up)"
    fi
    
else
    print_error "Failed to start service"
    echo "Check logs with: sudo journalctl -u $SERVICE_NAME -f"
    exit 1
fi

# 10. Setup log rotation
print_status "Setting up log rotation..."
sudo tee /etc/logrotate.d/medical-ai-service > /dev/null <<EOF
/var/log/medical-ai-service.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload medical-ai-service
    endscript
}
EOF

print_status "Log rotation configured"

# 11. Display useful commands
echo ""
echo "🎉 Deployment Complete!"
echo ""
echo "📋 Useful Commands:"
echo "   Start service:    sudo systemctl start $SERVICE_NAME"
echo "   Stop service:     sudo systemctl stop $SERVICE_NAME"
echo "   Restart service:  sudo systemctl restart $SERVICE_NAME"
echo "   Check status:     sudo systemctl status $SERVICE_NAME"
echo "   View logs:        sudo journalctl -u $SERVICE_NAME -f"
echo "   Health check:     curl http://localhost:8080/health"
echo ""
echo "🌐 Service Endpoints:"
echo "   Health:           http://localhost:8080/health"
echo "   Manufacturers:    http://localhost:8080/manufacturers"
echo "   API Documentation: http://localhost:8080/docs"
echo ""
print_status "Medical AI Service is now running on your dev server!" 