#!/bin/bash

# Medical AI Service Update Script
# Run this after pushing code changes to restart the service

set -e

echo "🔄 Updating Medical AI Service..."

SERVICE_NAME="medical-ai-service"
PROJECT_PATH="/var/www/wound-care-react-laravel"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Change to project directory
cd "$PROJECT_PATH"

# Pull latest changes (if using git)
if [ -d ".git" ]; then
    print_status "Pulling latest changes..."
    git pull origin azure-dev
fi

# Update Python dependencies if requirements changed
cd scripts
if [ -f "requirements.txt" ]; then
    print_status "Updating Python dependencies..."
    source ai_service_env/bin/activate
    pip install -r requirements.txt
fi

# Restart the service
print_status "Restarting Medical AI Service..."
sudo systemctl restart $SERVICE_NAME

# Wait and check status
sleep 3
if sudo systemctl is-active --quiet $SERVICE_NAME; then
    print_status "Service restarted successfully!"
    
    # Test health endpoint
    echo "🏥 Testing service..."
    sleep 2
    if curl -s http://localhost:8080/health > /dev/null; then
        print_status "Service is healthy and responding"
    else
        print_warning "Service may still be starting up"
    fi
else
    echo "❌ Service failed to start. Check logs:"
    sudo journalctl -u $SERVICE_NAME --since "1 minute ago"
    exit 1
fi

print_status "Update complete!" 