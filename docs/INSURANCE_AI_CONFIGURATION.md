# Insurance AI Assistant Configuration Guide

## 🔧 Quick Setup

The Insurance AI Assistant needs specific environment variables to connect your Microsoft AI agent with the existing ML ensemble system. Here's how to configure everything:

## 📋 Required Environment Variables

Add these to your `.env` file:

### 1. Microsoft AI Agent (REQUIRED)
```env
# Your trained Microsoft AI agent ID from Azure AI Studio
AZURE_INSURANCE_ASSISTANT_ID=your-microsoft-ai-agent-id-here
```

### 2. Azure OpenAI (REQUIRED)
```env
# Your Azure OpenAI endpoint and credentials
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com/
AZURE_OPENAI_KEY=your-azure-openai-api-key-here
AZURE_OPENAI_DEPLOYMENT_NAME=gpt-4
```

### 3. DocuSeal API (REQUIRED for form assistance)
```env
# Your DocuSeal API key for form template access
DOCUSEAL_API_KEY=your-docuseal-api-key-here
```

## ⚙️ Optional Configuration

### Voice Interaction
```env
# Enable voice features with Azure Speech Services
AZURE_INSURANCE_ASSISTANT_VOICE_ENABLED=true
AZURE_SPEECH_KEY=your-azure-speech-key-here
AZURE_SPEECH_REGION=your-azure-speech-region
```

### ML Enhancement Settings
```env
# Enable ML enhancement with existing 286+ field mappings
AZURE_INSURANCE_ASSISTANT_ML_ENHANCEMENT=true

# Enable behavioral tracking for personalized recommendations  
AZURE_INSURANCE_ASSISTANT_BEHAVIORAL_TRACKING=true

# Enable continuous learning from user interactions
AZURE_INSURANCE_ASSISTANT_CONTINUOUS_LEARNING=true
```

### Performance Settings
```env
# Cache timeout for ML recommendations (seconds)
INSURANCE_AI_CACHE_TIMEOUT=3600

# Maximum conversation context length
INSURANCE_AI_MAX_CONTEXT_LENGTH=4000

# Enable debug logging for troubleshooting
INSURANCE_AI_DEBUG_LOGGING=false
```

## 🧪 Testing Your Configuration

After setting up your environment variables, test the integration:

```bash
php artisan test:insurance-ai-assistant
```

This command will:
- ✅ Verify all required environment variables are set
- ✅ Test connection to your Microsoft AI agent
- ✅ Check ML ensemble integration (286+ field mappings)
- ✅ Validate DocuSeal API connectivity
- ✅ Test voice mode (if enabled)
- ✅ Report overall system health

## 🔍 Configuration Check

The test command will guide you through any missing configuration with helpful error messages:

```bash
❌ AZURE_INSURANCE_ASSISTANT_ID not set in .env
   Add your Microsoft AI agent ID to .env file

⚠️  DocuSeal API key not configured
   Set DOCUSEAL_API_KEY for form assistance testing

✅ Azure OpenAI configured
```

## 🚀 Getting Your API Keys

### Microsoft AI Agent ID
1. Go to [Azure AI Studio](https://ai.azure.com/)
2. Find your trained insurance assistant
3. Copy the Agent ID from the assistant details

### Azure OpenAI Credentials
1. Go to [Azure Portal](https://portal.azure.com/)
2. Navigate to your Azure OpenAI resource
3. Copy the endpoint URL and API key from "Keys and Endpoint"

### DocuSeal API Key
1. Log in to your [DocuSeal account](https://www.docuseal.co/)
2. Go to Settings → API Keys
3. Generate or copy your existing API key

### Azure Speech Services (Optional)
1. Go to [Azure Portal](https://portal.azure.com/)
2. Create or navigate to Azure Speech Services resource
3. Copy the key and region from "Keys and Endpoint"

## ⚡ Quick Start Template

Copy this template to your `.env` file and replace the placeholder values:

```env
# REQUIRED - Microsoft AI Agent
AZURE_INSURANCE_ASSISTANT_ID=your-agent-id

# REQUIRED - Azure OpenAI  
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com/
AZURE_OPENAI_KEY=your-api-key
AZURE_OPENAI_DEPLOYMENT_NAME=gpt-4

# REQUIRED - DocuSeal
DOCUSEAL_API_KEY=your-docuseal-key

# OPTIONAL - Voice features
AZURE_INSURANCE_ASSISTANT_VOICE_ENABLED=true
AZURE_SPEECH_KEY=your-speech-key
AZURE_SPEECH_REGION=eastus

# OPTIONAL - ML Enhancement (recommended)
AZURE_INSURANCE_ASSISTANT_ML_ENHANCEMENT=true
AZURE_INSURANCE_ASSISTANT_BEHAVIORAL_TRACKING=true
AZURE_INSURANCE_ASSISTANT_CONTINUOUS_LEARNING=true
```

## 🐛 Troubleshooting

### Common Issues

**"cURL error 28: Resolving timed out"**
- Check your Azure OpenAI endpoint URL
- Verify network connectivity
- Ensure API key is valid

**"DocuSeal API error: 404"**
- Verify your DocuSeal API key
- Check if the template ID exists in your account
- Ensure proper API permissions

**"Typed property $threadId must not be accessed before initialization"**
- This was fixed in the latest version
- Make sure you're using the updated InsuranceAIAssistantService

### Getting Help

If you encounter issues:
1. Run `php artisan test:insurance-ai-assistant` for detailed diagnostics
2. Check the Laravel logs: `tail -f storage/logs/laravel.log`
3. Verify your `.env` file has all required variables
4. Test individual services in the configuration check

## ✅ Next Steps

Once configured, you can:
1. Use the React component: `<InsuranceAssistant />`
2. Make API calls to `/api/v1/insurance-ai/`
3. Access ML-enhanced responses with 286+ field mappings
4. Get personalized recommendations based on user behavior
5. Use voice interaction for natural conversations

Your Microsoft AI agent is now supercharged with the ML ensemble system! 🚀 