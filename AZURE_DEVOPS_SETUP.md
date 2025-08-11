# Azure DevOps Pipeline Setup Guide

## Variable Groups Setup

To complete the deployment setup, you need to create two variable groups in Azure DevOps Library:

### 1. Create Variable Group: `msc-woundcare-portal-secrets-uat`

This group contains all sensitive secrets and API keys. **Mark all variables as SECRET**.

Navigate to Azure DevOps → Pipelines → Library → Add variable group

**Variable Group Name:** `msc-woundcare-portal-secrets-uat`

**Variables to add (all marked as SECRET):**

```
APP_KEY = base64:OcSWsd3YV9vVk7eGaazS8sENcbacooWt+GA80Lv3eYM=
AZURE_FHIR_CLIENT_SECRET = b~X8Q~zV-uzjsPX6tftRfSQYBYiOIv0Dt9Y95bSW
DB_PASSWORD = B@xter1123$$!
MAILGUN_SECRET = a882a7b335eb036159287408c481d954-812b35f5-a9d6e6b9
MAILGUN_WEBHOOK_SIGNING_SECRET = 9f118c34b996b4048b7070d8671a4873
REDIS_PASSWORD = n1swelsBiZkvTx3IXq8cIOq210vbRgfc0AzCaLScFmI=
UMLS_API_KEY = 8b634738-d64a-4dea-a48a-15934504a13e
AZURE_SPEECH_KEY = 1niLnd9xLv3idBqCXsGXUNs9mStjkhMksN1TKNsxFueoSF6WAg6rJQQJ99BCACHYHv6XJ3w3AAAAACOGSouN
AZURE_DOCUMENT_INTELLIGENCE_KEY = CPBG2LnTpdGKMKrONcWPWkD97e5ceXskv2eH4a2gzfeh39t0lqPcJQQJ99BFACYeBjFXJ3w3AAAAACOGeD0P
AZURE_DI_KEY = CPBG2LnTpdGKMKrONcWPWkD97e5ceXskv2eH4a2gzfeh39t0lqPcJQQJ99BFACYeBjFXJ3w3AAAAACOGeD0P
AVAILITY_CLIENT_ID = 33b643080448344321873840c85f0dd9
AVAILITY_CLIENT_SECRET = 23688a3fd16b4f0f6c5c4db43c9b81df
DOCUSEAL_API_KEY = rj6M42wpPkU1vhzaLi9qykeWPqsgS8VBdxJh1UrLSmt
DOCUSEAL_WEBHOOK_SECRET = 123abc123a$$
AZURE_OPENAI_API_KEY = 1niLnd9xLv3idBqCXsGXUNs9mStjkhMksN1TKNsxFueoSF6WAg6rJQQJ99BCACHYHv6XJ3w3AAAAACOGSouN
ECW_CLIENT_ID = your-client-id
ECW_CLIENT_SECRET = your-client-secret
EPIC_CLIENT_ID = your_epic_client_id_here
EPIC_CLIENT_SECRET = your_epic_client_secret_here
SUPERINTEREFACE_API_KEY = 1ca5c3bf-a011-4211-90ae-7d593341592b
INTERCOM_ACCESS_TOKEN = 12345
GOOGLE_MAPS_API_KEY = AIzaSyBlVWjkRiQcRbyejwdPgjnTn4v8VZqYZlY
VITE_GOOGLE_MAPS_API_KEY = AIzaSyBlVWjkRiQcRbyejwdPgjnTn4v8VZqYZlY
FIRECRAWL_API_KEY = fc-469546b6442e4093afc8c430b2acd35a
AZURE_INSURANCE_ASSISTANT_ID = asst_j0WF52r4zKaoltrygOT7yVgD
```

### 2. Create Variable Group: `msc-woundcare-portal-azure-config-uat`

This group contains Azure service endpoints and configuration URLs.

**Variable Group Name:** `msc-woundcare-portal-azure-config-uat`

**Variables to add:**

```
APP_URL = https://uat-msc-portal.azurewebsites.net
AZURE_FHIR_CLIENT_ID = 5e769fcd-9301-475d-9673-28d0bfae037a
AZURE_FHIR_TENANT_ID = 0cb4abeb-8281-4b8c-8de8-6a7f8c777f0a
AZURE_FHIR_BASE_URL = https://azurehealthdatamsc-ahds-msc-fhir.fhir.azurehealthcareapis.com
AZURE_FHIR_SCOPE = https://azurehealthdatamsc-ahds-msc-fhir.fhir.azurehealthcareapis.com/.default
AZURE_FHIR_AUTHORITY = https://login.microsoftonline.com/0cb4abeb-8281-4b8c-8de8-6a7f8c777f0a
AZURE_FHIR_ENDPOINT = https://azurehealthdatamsc-ahds-msc-fhir.fhir.azurehealthcareapis.com
DB_HOST = msc-stage-db.mysql.database.azure.com
DB_DATABASE = msc-dev-rv
DB_USERNAME = mscstagedb
MAILGUN_DOMAIN = sandboxb428f0c3466949abaefa2707959d6f34.mailgun.org
REDIS_HOST = mscwound.redis.cache.windows.net
REDIS_CONNECTION = mscwound.redis.cache.windows.net:6380,password=n1swelsBiZkvTx3IXq8cIOq210vbRgfc0AzCaLScFmI=,ssl=True
AZURE_STORAGE_BLOB_URL = https://mscappstorage.blob.core.windows.net/mscblob-dev/?sv=2022-11-02&ss=b&srt=co&se=2025-06-08T07%3A25%3A13Z&sp=rwl&sig=xw3QxGY5aH4%2FbIjRgrDNLwXGucCGqLc5W1%2F8RKEQ%2F08%3D
AZURE_AISERVICES_COGNITIVESERVICES_ENDPOINT = https://msc-ai-services.services.ai.azure.com/models
AZURE_AISERVICES_OPENAI_BASE = https://msc-ai-services.openai.azure.com/
AZURE_SPEECH_ENDPOINT = https://eastus2.tts.speech.microsoft.com
AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT = https://msc-portal-resource.cognitiveservices.azure.com/
AZURE_DI_ENDPOINT = https://msc-portal-resource.cognitiveservices.azure.com/
AZURE_OPENAI_ENDPOINT = https://msc-ai-services.cognitiveservices.azure.com
SESSION_DOMAIN = uat-msc-portal.azurewebsites.net
SANCTUM_STATEFUL_DOMAINS = uat-msc-portal.azurewebsites.net
VITE_PUSHER_APP_KEY = (your_pusher_key_if_needed)
VITE_PUSHER_APP_CLUSTER = (your_pusher_cluster_if_needed)
```

## Service Connection Setup

You also need to update the service connection name in the pipeline:

1. Go to Azure DevOps → Project Settings → Service connections
2. Create or find your Azure service connection
3. Update the pipeline YAML file to use the correct service connection name:

```yaml
azureSubscription: 'Your-Actual-Service-Connection-Name'  # Update this
appName: 'uat-msc-portal'  # Update with your actual app service name
```

## Environment Setup

1. Go to Azure DevOps → Pipelines → Environments
2. Create a new environment named `UAT`
3. Configure any approval processes if needed

## Resource Group and App Service

Make sure you have:
- Azure Resource Group for UAT environment
- App Service for PHP 8.2 in the UAT resource group
- Update the `appName` in the pipeline with your actual App Service name

## Branch Protection

To avoid affecting your dev branch:
1. The pipeline is configured to trigger only on `UAT` branch
2. Make sure your dev branch deploys to a different resource group
3. Consider using different service connections for dev vs UAT if needed

## Deployment Steps

1. Create the variable groups as described above
2. Update service connection name in pipeline
3. Update app service name in pipeline
4. Push to the `UAT` branch to trigger deployment

## Security Notes

- Never commit secrets to the repository
- Always mark sensitive variables as "secret" in variable groups
- Use different credentials for UAT vs production environments
- Regularly rotate API keys and secrets
