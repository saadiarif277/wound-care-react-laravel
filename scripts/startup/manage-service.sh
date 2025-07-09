#!/bin/bash

# Medical AI Service Management Script
# User-friendly script to manage the medical AI service

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

SERVICE_NAME="medical-ai-service"
API_PORT=8081

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

log_header() {
    echo -e "${BLUE}==== $1 ====${NC}"
}

check_service_status() {
    if systemctl is-active --quiet "$SERVICE_NAME"; then
        echo -e "${GREEN}✓ Service is running${NC}"
        return 0
    else
        echo -e "${RED}✗ Service is not running${NC}"
        return 1
    fi
}

check_api_health() {
    if curl -s -f http://localhost:$API_PORT/health > /dev/null 2>&1; then
        echo -e "${GREEN}✓ API is responding${NC}"
        return 0
    else
        echo -e "${RED}✗ API is not responding${NC}"
        return 1
    fi
}

show_status() {
    log_header "Service Status"
    
    # Check systemd service status
    if systemctl is-enabled --quiet "$SERVICE_NAME" 2>/dev/null; then
        echo -e "${GREEN}✓ Service is enabled for startup${NC}"
    else
        echo -e "${YELLOW}! Service is not enabled for startup${NC}"
    fi
    
    # Check if service is running
    check_service_status
    
    # Check API health
    if check_service_status; then
        check_api_health
    fi
    
    echo ""
    echo "Detailed Status:"
    systemctl status "$SERVICE_NAME" --no-pager --lines=5 2>/dev/null || echo "Service not found"
}

show_logs() {
    log_header "Recent Logs"
    
    if [[ "$1" == "-f" ]]; then
        log_info "Showing live logs (press Ctrl+C to stop)..."
        journalctl -u "$SERVICE_NAME" -f
    else
        journalctl -u "$SERVICE_NAME" --no-pager -n 50
    fi
}

test_api() {
    log_header "API Test"
    
    # Test health endpoint
    log_info "Testing health endpoint..."
    if curl -s -f http://localhost:$API_PORT/health; then
        echo -e "\n${GREEN}✓ Health check passed${NC}"
    else
        echo -e "\n${RED}✗ Health check failed${NC}"
        return 1
    fi
    
    # Test terminology stats
    log_info "Testing terminology stats..."
    if curl -s -f http://localhost:$API_PORT/terminology-stats | jq . > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Terminology stats endpoint working${NC}"
    else
        echo -e "${YELLOW}! Terminology stats endpoint may have issues${NC}"
    fi
    
    # Test manufacturers endpoint
    log_info "Testing manufacturers endpoint..."
    if curl -s -f http://localhost:$API_PORT/manufacturers | jq . > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Manufacturers endpoint working${NC}"
    else
        echo -e "${YELLOW}! Manufacturers endpoint may have issues${NC}"
    fi
}

start_service() {
    log_header "Starting Service"
    
    if check_service_status; then
        log_warning "Service is already running"
        return 0
    fi
    
    log_info "Starting service..."
    if sudo systemctl start "$SERVICE_NAME"; then
        sleep 3
        if check_service_status; then
            log_info "Service started successfully"
            check_api_health
        else
            log_error "Service failed to start properly"
            return 1
        fi
    else
        log_error "Failed to start service"
        return 1
    fi
}

stop_service() {
    log_header "Stopping Service"
    
    if ! check_service_status; then
        log_warning "Service is already stopped"
        return 0
    fi
    
    log_info "Stopping service..."
    if sudo systemctl stop "$SERVICE_NAME"; then
        sleep 2
        if ! check_service_status; then
            log_info "Service stopped successfully"
        else
            log_error "Service may not have stopped properly"
            return 1
        fi
    else
        log_error "Failed to stop service"
        return 1
    fi
}

restart_service() {
    log_header "Restarting Service"
    
    log_info "Restarting service..."
    if sudo systemctl restart "$SERVICE_NAME"; then
        sleep 3
        if check_service_status; then
            log_info "Service restarted successfully"
            check_api_health
        else
            log_error "Service failed to restart properly"
            return 1
        fi
    else
        log_error "Failed to restart service"
        return 1
    fi
}

enable_service() {
    log_header "Enabling Service"
    
    if systemctl is-enabled --quiet "$SERVICE_NAME" 2>/dev/null; then
        log_warning "Service is already enabled"
        return 0
    fi
    
    log_info "Enabling service for automatic startup..."
    if sudo systemctl enable "$SERVICE_NAME"; then
        log_info "Service enabled successfully"
    else
        log_error "Failed to enable service"
        return 1
    fi
}

disable_service() {
    log_header "Disabling Service"
    
    if ! systemctl is-enabled --quiet "$SERVICE_NAME" 2>/dev/null; then
        log_warning "Service is already disabled"
        return 0
    fi
    
    log_info "Disabling service from automatic startup..."
    if sudo systemctl disable "$SERVICE_NAME"; then
        log_info "Service disabled successfully"
    else
        log_error "Failed to disable service"
        return 1
    fi
}

quick_setup() {
    log_header "Quick Setup"
    
    # Check if service is installed
    if ! systemctl list-unit-files | grep -q "$SERVICE_NAME"; then
        log_error "Service is not installed. Please run: sudo ./install-service.sh install"
        return 1
    fi
    
    # Enable and start service
    enable_service
    start_service
    
    log_info "Quick setup completed!"
}

show_help() {
    echo "Medical AI Service Management"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  status    - Show service status and health"
    echo "  start     - Start the service"
    echo "  stop      - Stop the service"
    echo "  restart   - Restart the service"
    echo "  enable    - Enable service for automatic startup"
    echo "  disable   - Disable automatic startup"
    echo "  logs      - Show recent logs"
    echo "  logs -f   - Show live logs"
    echo "  test      - Test API endpoints"
    echo "  setup     - Quick setup (enable + start)"
    echo "  help      - Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 status        # Check if service is running"
    echo "  $0 logs -f       # Watch live logs"
    echo "  $0 restart       # Restart the service"
    echo "  $0 test          # Test API endpoints"
}

# Main script
case "${1:-status}" in
    status)
        show_status
        ;;
    start)
        start_service
        ;;
    stop)
        stop_service
        ;;
    restart)
        restart_service
        ;;
    enable)
        enable_service
        ;;
    disable)
        disable_service
        ;;
    logs)
        show_logs "$2"
        ;;
    test)
        test_api
        ;;
    setup)
        quick_setup
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo "Unknown command: $1"
        echo ""
        show_help
        exit 1
        ;;
esac 