#!/bin/bash

# Quick Fix Script for Virtual Environment Issues
# This script resolves the PEP 668 externally managed environment error

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
VENV_PATH="$PROJECT_ROOT/scripts/venv"

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

create_virtual_environment() {
    log_header "Setting Up Virtual Environment"
    
    # Remove existing venv if it exists
    if [[ -d "$VENV_PATH" ]]; then
        log_warning "Removing existing virtual environment..."
        rm -rf "$VENV_PATH"
    fi
    
    # Create new virtual environment
    log_info "Creating new virtual environment..."
    python3 -m venv "$VENV_PATH"
    
    # Upgrade pip
    log_info "Upgrading pip..."
    "$VENV_PATH/bin/python" -m pip install --upgrade pip
    
    log_info "Virtual environment created successfully"
}

install_dependencies() {
    log_header "Installing Python Dependencies"
    
    # Install from requirements.txt if it exists
    if [[ -f "$PROJECT_ROOT/scripts/requirements.txt" ]]; then
        log_info "Installing from requirements.txt..."
        "$VENV_PATH/bin/pip" install -r "$PROJECT_ROOT/scripts/requirements.txt"
    else
        log_warning "requirements.txt not found, installing basic dependencies..."
        "$VENV_PATH/bin/pip" install fastapi uvicorn httpx pydantic redis python-dotenv openai cachetools python-multipart
    fi
    
    log_info "Dependencies installed successfully"
}

test_virtual_environment() {
    log_header "Testing Virtual Environment"
    
    # Test Python imports
    log_info "Testing Python imports..."
    "$VENV_PATH/bin/python" -c "
import fastapi
import uvicorn
import httpx
import pydantic
print('✓ All required packages imported successfully')
"
    
    log_info "Virtual environment test passed"
}

show_next_steps() {
    log_header "Next Steps"
    
    echo "Virtual environment setup completed! Now you can:"
    echo ""
    echo "1. Install the service:"
    echo "   ${GREEN}sudo ./install-service.sh install${NC}"
    echo ""
    echo "2. Or test manually first:"
    echo "   ${GREEN}cd ../scripts${NC}"
    echo "   ${GREEN}./venv/bin/python medical_ai_service.py${NC}"
    echo ""
    echo "3. Check service status:"
    echo "   ${GREEN}./manage-service.sh status${NC}"
    echo ""
}

# Main execution
main() {
    log_header "Quick Fix for Virtual Environment"
    
    # Check if we're in the right directory
    if [[ ! -f "$SCRIPT_DIR/install-service.sh" ]]; then
        log_error "Please run this script from the scripts/startup directory"
        exit 1
    fi
    
    # Check if Python 3 is available
    if ! command -v python3 &> /dev/null; then
        log_error "Python 3 is not installed"
        exit 1
    fi
    
    create_virtual_environment
    install_dependencies
    test_virtual_environment
    show_next_steps
    
    log_info "Quick fix completed successfully!"
}

# Run the main function
main "$@" 