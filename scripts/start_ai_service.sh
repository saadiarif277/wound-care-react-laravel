#!/bin/bash

# AI Service Startup Script
# This script loads environment variables from Laravel .env and starts the Python AI service

echo "🚀 Starting Medical AI Service..."

# Load environment variables from Laravel .env file
if [ -f "../.env" ]; then
    echo "📄 Loading environment variables from .env file..."
    set -a  # Export all variables
    source "../.env"
    set +a  # Stop exporting
else
    echo "⚠️  Warning: .env file not found, using system environment variables"
fi

# Verify required Azure OpenAI environment variables are set
if [ -z "$AZURE_OPENAI_ENDPOINT" ] || [ -z "$AZURE_OPENAI_API_KEY" ]; then
    echo "❌ Error: Azure OpenAI environment variables not set!"
    echo "   Please ensure AZURE_OPENAI_ENDPOINT and AZURE_OPENAI_API_KEY are in your .env file"
    exit 1
fi

# Set defaults for optional variables
export AZURE_OPENAI_DEPLOYMENT="${AZURE_OPENAI_DEPLOYMENT:-gpt-4o}"
export AZURE_OPENAI_API_VERSION="${AZURE_OPENAI_API_VERSION:-2024-02-15-preview}"

# Other service configuration
export ENABLE_LOCAL_FALLBACK="${ENABLE_LOCAL_FALLBACK:-true}"
export API_HOST="${API_HOST:-0.0.0.0}"
export API_PORT="${API_PORT:-8081}"
export CACHE_TTL="${CACHE_TTL:-3600}"

echo "✅ Azure OpenAI Configuration:"
echo "   Endpoint: $AZURE_OPENAI_ENDPOINT"
echo "   Deployment: $AZURE_OPENAI_DEPLOYMENT"
echo "   API Version: $AZURE_OPENAI_API_VERSION"

# Change to scripts directory
cd "$(dirname "$0")"

# Activate virtual environment
echo "🔧 Activating virtual environment..."
source ai_service_env/bin/activate

# Start the service
echo "🌟 Starting AI service on http://$API_HOST:$API_PORT"
python -m uvicorn medical_ai_service:app --host $API_HOST --port $API_PORT

# Deactivate virtual environment on exit
deactivate 