#!/bin/bash

# AI Service Startup Script
# This script sets up Azure OpenAI environment variables and starts the Python AI service

echo "🚀 Starting Medical AI Service..."

# Source .env file to get the correct API key
if [ -f "../.env" ]; then
    set -a
    source ../.env
    set +a
elif [ -f ".env" ]; then
    set -a
    source .env
    set +a
fi

# Export Azure OpenAI environment variables (use .env values, fallback to defaults)
export AZURE_OPENAI_ENDPOINT="${AZURE_OPENAI_ENDPOINT:-https://msc-ai-services.openai.azure.com/}"
export AZURE_OPENAI_API_KEY="${AZURE_OPENAI_API_KEY}"
export AZURE_OPENAI_DEPLOYMENT="${AZURE_OPENAI_DEPLOYMENT:-gpt-4o}"
export AZURE_OPENAI_API_VERSION="${AZURE_OPENAI_API_VERSION:-2024-02-15-preview}"

# Other service configuration
export ENABLE_LOCAL_FALLBACK="true"
export API_HOST="0.0.0.0"
export API_PORT="8080"
export CACHE_TTL="3600"

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