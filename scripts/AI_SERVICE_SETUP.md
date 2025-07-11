# Medical AI Service Setup Guide

## Overview
The Medical AI Service provides intelligent field mapping and enhancement for DocuSeal form submissions. It uses Azure OpenAI to analyze form data and map it to the correct DocuSeal template fields.

## Architecture

```
Frontend (Step7DocusealIVR.tsx)
    ↓
Laravel Backend (DocusealController.php)
    ↓
Medical AI Service (Python FastAPI)
    ↓
DocuSeal API
```

## Installation

### 1. Install Python Dependencies

```bash
cd scripts
python -m venv .venv
source .venv/bin/activate  # On Windows: .venv\Scripts\activate
pip install -r requirements.txt
```

### 2. Configure Environment Variables

The service uses environment variables from `.venv/.env` (already configured):
- `AZURE_OPENAI_ENDPOINT`: Azure OpenAI endpoint
- `AZURE_OPENAI_API_KEY`: Azure OpenAI API key
- `AZURE_OPENAI_DEPLOYMENT_NAME`: Model deployment name (gpt-4o)
- `DOCUSEAL_API_KEY`: DocuSeal API key
- `DOCUSEAL_API_URL`: DocuSeal base URL

### 3. Start the Service

#### Development Mode
```bash
cd scripts
./start_ai_service.sh
# Or manually:
source .venv/bin/activate
python medical_ai_service.py
```

#### Production Mode (systemd)
```bash
# Copy the service file
sudo cp medical-ai-service.service /etc/systemd/system/

# Enable and start the service
sudo systemctl daemon-reload
sudo systemctl enable medical-ai-service
sudo systemctl start medical-ai-service

# Check status
sudo systemctl status medical-ai-service
```

## Testing

### 1. Basic Health Check
```bash
curl http://localhost:8081/health
```

### 2. Run Integration Tests
```bash
cd scripts
./test_ai_docuseal_integration.py
```

### 3. Debug Service
```bash
cd scripts
./debug_ai_service.py
```

## API Endpoints

### Health Check
- **GET** `/health`
- Returns service status and configuration

### Field Enhancement
- **POST** `/api/v1/enhance-mapping`
- Enhances form fields using AI
- Rate limited: 10 requests per minute

### DocuSeal Integration
- **GET** `/api/v1/docuseal/template/{template_id}/fields`
- **POST** `/api/v1/docuseal/submissions`
- **POST** `/api/v1/docuseal/enhance-and-submit`

## Laravel Integration

The Laravel backend integrates with the AI service in `DocusealController.php`:

1. When `episode_id` is provided, it uses the QuickRequestOrchestrator to prepare comprehensive data
2. AI enhancement is attempted if enabled in config
3. Falls back to static mapping if AI fails

### Configuration
In `config/services.php`:
```php
'medical_ai' => [
    'url' => env('MEDICAL_AI_SERVICE_URL', 'http://localhost:8081'),
    'timeout' => env('MEDICAL_AI_SERVICE_TIMEOUT', 30),
    'enabled' => env('MEDICAL_AI_SERVICE_ENABLED', true),
    'use_for_docuseal' => env('MEDICAL_AI_USE_FOR_DOCUSEAL', true),
],
```

## Field Mapping Flow

1. **Frontend collects form data** (patient info, clinical data, insurance, etc.)
2. **Laravel receives the request** at `/quick-requests/docuseal/generate-submission-slug`
3. **If episode_id exists**:
   - QuickRequestOrchestrator loads comprehensive data from the database
   - AI service enhances the mapping if enabled
4. **If no episode_id**:
   - Form data is sent directly to AI service for enhancement
5. **AI Service**:
   - Analyzes the data structure
   - Maps fields to DocuSeal template fields
   - Returns enhanced mapping with confidence score
6. **DocuSeal submission** is created with the mapped data

## Troubleshooting

### Service Won't Start
- Check Python version: `python --version` (needs 3.8+)
- Verify virtual environment: `which python` should show `.venv/bin/python`
- Check port 8081 is available: `sudo lsof -i :8081`

### AI Enhancement Failing
- Verify Azure OpenAI credentials in `.venv/.env`
- Check API quota and rate limits
- Review logs: `tail -f /var/log/medical-ai-service.log`

### DocuSeal Integration Issues
- Verify DocuSeal API key is correct
- Check template IDs match between Laravel and DocuSeal
- Use test script to validate connection

### Laravel Not Connecting
- Ensure AI service is running on port 8081
- Check Laravel logs: `tail -f storage/logs/laravel.log`
- Verify firewall allows local connections

## Performance Optimization

1. **Caching**: Results are cached for 5 minutes (configurable)
2. **Rate Limiting**: 10 requests per minute per IP
3. **Request Logging**: All requests are logged with timing
4. **Fallback Mode**: Service works without Azure OpenAI using static mappings

## Security Considerations

1. **API Keys**: Store in environment variables, never commit to git
2. **CORS**: Configure allowed origins in production
3. **Rate Limiting**: Prevents abuse
4. **Input Validation**: All inputs are validated using Pydantic
5. **Error Handling**: Sensitive errors are logged, not exposed

## Monitoring

### Health Checks
- Monitor `/health` endpoint
- Set up alerts for service downtime
- Track Azure OpenAI usage and costs

### Logs
- Application logs: `/var/log/medical-ai-service.log`
- Error logs: `/var/log/medical-ai-service.error.log`
- Laravel logs: `storage/logs/laravel.log`

### Metrics to Track
- Response times
- AI confidence scores
- Cache hit rates
- Error rates
- DocuSeal submission success rates

## Maintenance

### Update Dependencies
```bash
cd scripts
source .venv/bin/activate
pip install --upgrade -r requirements.txt
```

### Restart Service
```bash
sudo systemctl restart medical-ai-service
```

### View Logs
```bash
sudo journalctl -u medical-ai-service -f
```

## Future Enhancements

1. **Batch Processing**: Handle multiple forms at once
2. **Custom Training**: Fine-tune models for specific manufacturers
3. **Analytics Dashboard**: Track mapping accuracy over time
4. **Webhook Support**: Real-time updates on submission status
5. **Multi-language Support**: Handle forms in different languages
