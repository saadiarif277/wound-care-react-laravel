#!/bin/bash

# Medical AI Service Installation Script
# This script installs and configures the medical AI service for automatic startup

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
SERVICE_NAME="medical-ai-service"
SERVICE_FILE="medical-ai-service.service"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
SERVICE_PATH="/etc/systemd/system/${SERVICE_NAME}.service"

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_requirements() {
    log_info "Checking requirements..."
    
    # Check if running as root for systemd operations
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
    
    # Check if Python 3 is installed
    if ! command -v python3 &> /dev/null; then
        log_error "Python 3 is not installed"
        exit 1
    fi
    
    # Check if systemd is available
    if ! command -v systemctl &> /dev/null; then
        log_error "systemd is not available on this system"
        exit 1
    fi
    
    # Check if service file exists
    if [[ ! -f "$SCRIPT_DIR/$SERVICE_FILE" ]]; then
        log_error "Service file not found: $SCRIPT_DIR/$SERVICE_FILE"
        exit 1
    fi
    
    # Check if medical AI service exists
    if [[ ! -f "$PROJECT_ROOT/scripts/medical_ai_service.py" ]]; then
        log_error "Medical AI service script not found: $PROJECT_ROOT/scripts/medical_ai_service.py"
        exit 1
    fi
    
    log_info "Requirements check passed"
}

install_python_dependencies() {
    log_info "Installing Python dependencies..."
    
    # Create virtual environment if it doesn't exist
    VENV_PATH="$PROJECT_ROOT/scripts/venv"
    if [[ ! -d "$VENV_PATH" ]]; then
        log_info "Creating virtual environment..."
        python3 -m venv "$VENV_PATH"
    fi
    
    # Install/upgrade pip in virtual environment
    log_info "Updating pip in virtual environment..."
    "$VENV_PATH/bin/python" -m pip install --upgrade pip
    
    # Install required Python packages in virtual environment
    if [[ -f "$PROJECT_ROOT/scripts/requirements.txt" ]]; then
        log_info "Installing from requirements.txt..."
        "$VENV_PATH/bin/pip" install -r "$PROJECT_ROOT/scripts/requirements.txt"
    else
        log_warning "requirements.txt not found, installing basic dependencies..."
        "$VENV_PATH/bin/pip" install fastapi uvicorn httpx pydantic redis python-dotenv openai cachetools python-multipart
    fi
    
    log_info "Python dependencies installed in virtual environment"
}

install_service() {
    log_info "Installing systemd service..."
    
    # Stop service if it's running
    if systemctl is-active --quiet "$SERVICE_NAME"; then
        log_info "Stopping existing service..."
        systemctl stop "$SERVICE_NAME"
    fi
    
    # Disable service if it's enabled
    if systemctl is-enabled --quiet "$SERVICE_NAME"; then
        log_info "Disabling existing service..."
        systemctl disable "$SERVICE_NAME"
    fi
    
    # Copy service file
    cp "$SCRIPT_DIR/$SERVICE_FILE" "$SERVICE_PATH"
    
    # Update working directory, virtual environment path, and user in service file
    sed -i "s|WorkingDirectory=.*|WorkingDirectory=$PROJECT_ROOT/scripts|g" "$SERVICE_PATH"
    sed -i "s|ExecStart=.*|ExecStart=$PROJECT_ROOT/scripts/venv/bin/python medical_ai_service.py|g" "$SERVICE_PATH"
    sed -i "s|EnvironmentFile=.*|EnvironmentFile=-$PROJECT_ROOT/.env|g" "$SERVICE_PATH"
    sed -i "s|ReadWritePaths=.*|ReadWritePaths=$PROJECT_ROOT/storage/logs|g" "$SERVICE_PATH"
    
    # Set proper permissions
    chmod 644 "$SERVICE_PATH"
    
    # Reload systemd
    systemctl daemon-reload
    
    log_info "Service installed successfully"
}

configure_service() {
    log_info "Configuring service..."
    
    # Create logs directory if it doesn't exist
    mkdir -p "$PROJECT_ROOT/storage/logs"
    
    # Set proper permissions on project directory
    chown -R rvalen:rvalen "$PROJECT_ROOT/scripts"
    chown -R rvalen:rvalen "$PROJECT_ROOT/storage"
    
    # Make Python script executable
    chmod +x "$PROJECT_ROOT/scripts/medical_ai_service.py"
    
    log_info "Service configured successfully"
}

start_service() {
    log_info "Starting and enabling service..."
    
    # Enable service for automatic startup
    systemctl enable "$SERVICE_NAME"
    
    # Start service
    systemctl start "$SERVICE_NAME"
    
    # Check if service started successfully
    sleep 2
    if systemctl is-active --quiet "$SERVICE_NAME"; then
        log_info "Service started successfully"
    else
        log_error "Service failed to start"
        show_service_status
        exit 1
    fi
}

show_service_status() {
    log_info "Service status:"
    systemctl status "$SERVICE_NAME" --no-pager
    
    log_info "Recent logs:"
    journalctl -u "$SERVICE_NAME" --no-pager -n 20
}

test_service() {
    log_info "Testing service..."
    
    # Wait for service to be ready
    sleep 5
    
    # Test health endpoint
    if curl -s -f http://localhost:8080/health > /dev/null; then
        log_info "Service is responding to health checks"
    else
        log_warning "Service may not be ready yet, check logs with: journalctl -u $SERVICE_NAME -f"
    fi
}

uninstall_service() {
    log_info "Uninstalling service..."
    
    # Stop and disable service
    systemctl stop "$SERVICE_NAME" 2>/dev/null || true
    systemctl disable "$SERVICE_NAME" 2>/dev/null || true
    
    # Remove service file
    rm -f "$SERVICE_PATH"
    
    # Reload systemd
    systemctl daemon-reload
    
    log_info "Service uninstalled successfully"
}

# Main script
case "${1:-install}" in
    install)
        log_info "Installing Medical AI Service..."
        check_requirements
        install_python_dependencies
        install_service
        configure_service
        start_service
        test_service
        log_info "Installation completed successfully!"
        log_info "Service is now running and will start automatically on boot"
        log_info "Use 'sudo systemctl status $SERVICE_NAME' to check status"
        log_info "Use 'sudo journalctl -u $SERVICE_NAME -f' to view logs"
        ;;
    uninstall)
        log_info "Uninstalling Medical AI Service..."
        check_requirements
        uninstall_service
        log_info "Uninstallation completed"
        ;;
    status)
        show_service_status
        ;;
    restart)
        log_info "Restarting service..."
        systemctl restart "$SERVICE_NAME"
        test_service
        ;;
    logs)
        journalctl -u "$SERVICE_NAME" -f
        ;;
    *)
        echo "Usage: $0 {install|uninstall|status|restart|logs}"
        echo ""
        echo "Commands:"
        echo "  install   - Install and start the service"
        echo "  uninstall - Stop and remove the service"
        echo "  status    - Show service status"
        echo "  restart   - Restart the service"
        echo "  logs      - Show real-time logs"
        exit 1
        ;;
esac 