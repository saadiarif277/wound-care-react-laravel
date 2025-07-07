# Hybrid AI Environment Setup

Add the following environment variables to your `.env` file:

```env
# Azure OpenAI Configuration (for both text and voice)
AZURE_OPENAI_ENDPOINT=https://your-resource.cognitiveservices.azure.com/
AZURE_OPENAI_API_KEY=your-azure-openai-api-key

# Text/Document Processing Deployment
AZURE_OPENAI_DEPLOYMENT_NAME=gpt-4o
AZURE_OPENAI_API_VERSION=2024-02-15-preview

# Realtime Voice Deployment (Preview)
AZURE_OPENAI_REALTIME_DEPLOYMENT=gpt-4o-mini-realtime-preview
AZURE_OPENAI_REALTIME_API_VERSION=2024-10-01-preview

# Azure Speech Services (for natural text-to-speech in text mode)
AZURE_SPEECH_KEY=your-azure-speech-key
AZURE_SPEECH_REGION=eastus
# Optional: Custom endpoint if not using default
# AZURE_SPEECH_ENDPOINT=https://eastus.tts.speech.microsoft.com/cognitiveservices/v1

# Optional: Voice Configuration
AI_DEFAULT_VOICE=en-US-JennyNeural  # Azure Neural voice for text mode
AI_REALTIME_VOICE=alloy              # Voice option for realtime mode
```

## Getting the API Keys

### 1. Azure OpenAI
1. Go to [Azure Portal](https://portal.azure.com)
2. Create or navigate to your Azure OpenAI resource
3. Deploy the following models:
   - `gpt-4o` for text/document processing
   - `gpt-4o-mini-realtime-preview` for voice (if available in your region)
4. Go to "Keys and Endpoint"
5. Copy Key 1 or Key 2 and the endpoint (should end with `.cognitiveservices.azure.com`)

### 2. Azure Speech Services
1. Go to [Azure Portal](https://portal.azure.com)
2. Create a Speech resource
3. Go to "Keys and Endpoint"
4. Copy Key 1 or Key 2 and note the region

## Verifying Configuration

Run the following artisan command to test your configuration:

```bash
php artisan ai:test-services
```

This will verify:
- ✓ Azure OpenAI text endpoint connection
- ✓ Azure OpenAI Realtime API access (preview)
- ✓ Azure Speech Services connection

## Important Notes

1. **Realtime API Preview**: The Azure Realtime API is in preview and may not be available in all regions
2. **Endpoint Format**: Your endpoint should be `https://YOUR-RESOURCE.cognitiveservices.azure.com/`
3. **API Versions**: Use the preview API version (`2024-10-01-preview`) for Realtime features 