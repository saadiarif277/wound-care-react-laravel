#!/bin/bash

# Script to upload MSC Wound Portal environment variables to Azure Key Vault
# Key Vault: kv-msc-mvp-prod-eus2

VAULT_NAME="kv-msc-mvp-prod-eus2"

echo "Uploading secrets to Azure Key Vault: $VAULT_NAME"

# Application Configuration
az keyvault secret set --vault-name $VAULT_NAME --name "APP-DEBUG" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "APP-ENV" --value "local"
az keyvault secret set --vault-name $VAULT_NAME --name "APP-KEY" --value "base64:OcSWsd3YV9vVk7eGaazS8sENcbacooWt+GA80Lv3eYM="
az keyvault secret set --vault-name $VAULT_NAME --name "APP-NAME" --value "MSC Platform"
az keyvault secret set --vault-name $VAULT_NAME --name "APP-URL" --value "http://localhost:8000"

# Azure FHIR/AHDS Configuration
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-FHIR-CLIENT-ID" --value "5e769fcd-9301-475d-9673-28d0bfae037a"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-FHIR-CLIENT-SECRET" --value "b~X8Q~zV-uzjsPX6tftRfSQYBYiOIv0Dt9Y95bSW"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-FHIR-TENANT-ID" --value "0cb4abeb-8281-4b8c-8de8-6a7f8c777f0a"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-FHIR-BASE-URL" --value "https://azurehealthdatamsc-ahds-msc-fhir.fhir.azurehealthcareapis.com"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-FHIR-SCOPE" --value "https://azurehealthdatamsc-ahds-msc-fhir.fhir.azurehealthcareapis.com/.default"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-FHIR-AUTHORITY" --value "https://login.microsoftonline.com/0cb4abeb-8281-4b8c-8de8-6a7f8c777f0a"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-FHIR-ENDPOINT" --value "https://azurehealthdatamsc-ahds-msc-fhir.fhir.azurehealthcareapis.com"

# FHIR Feature Flags
az keyvault secret set --vault-name $VAULT_NAME --name "FHIR-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "FHIR-SERVICE-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "FHIR-PATIENT-HANDLER-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "FHIR-PROVIDER-HANDLER-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "FHIR-INSURANCE-HANDLER-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "FHIR-CLINICAL-HANDLER-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "FHIR-ORDER-HANDLER-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "FHIR-DEBUG-MODE" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "FHIR-EPISODE-CACHE-WARMING-ENABLED" --value "true"

# Database Configuration
az keyvault secret set --vault-name $VAULT_NAME --name "DB-CONNECTION" --value "mysql"
az keyvault secret set --vault-name $VAULT_NAME --name "DB-HOST" --value "msc-stage-db.mysql.database.azure.com"
az keyvault secret set --vault-name $VAULT_NAME --name "DB-PORT" --value "3306"
az keyvault secret set --vault-name $VAULT_NAME --name "DB-DATABASE" --value "msc-dev-rv"
az keyvault secret set --vault-name $VAULT_NAME --name "DB-USERNAME" --value "mscstagedb"
az keyvault secret set --vault-name $VAULT_NAME --name "DB-PASSWORD" --value "B@xter1123\$\$!"
az keyvault secret set --vault-name $VAULT_NAME --name "DB-SSL-REQUIRED" --value "OFF"

# Mail Configuration
az keyvault secret set --vault-name $VAULT_NAME --name "MAIL-MAILER" --value "mailgun"
az keyvault secret set --vault-name $VAULT_NAME --name "MAILGUN-DOMAIN" --value "sandboxb428f0c3466949abaefa2707959d6f34.mailgun.org"
az keyvault secret set --vault-name $VAULT_NAME --name "MAILGUN-SECRET" --value "a882a7b335eb036159287408c481d954-812b35f5-a9d6e6b9"
az keyvault secret set --vault-name $VAULT_NAME --name "MAILGUN-ENDPOINT" --value "api.mailgun.net"
az keyvault secret set --vault-name $VAULT_NAME --name "MAILGUN-WEBHOOK-SIGNING-SECRET" --value "9f118c34b996b4048b7070d8671a4873"
az keyvault secret set --vault-name $VAULT_NAME --name "VITE-MAIL-FROM-ADDRESS" --value "richard@mscwoundcare.com"
az keyvault secret set --vault-name $VAULT_NAME --name "VITE-MAIL-FROM-NAME" --value "MSC Wound Care Portal"

# Cache & Queue
az keyvault secret set --vault-name $VAULT_NAME --name "CACHE-DRIVER" --value "file"
az keyvault secret set --vault-name $VAULT_NAME --name "QUEUE-CONNECTION" --value "sync"
az keyvault secret set --vault-name $VAULT_NAME --name "REDIS-HOST" --value "mscwound.redis.cache.windows.net"
az keyvault secret set --vault-name $VAULT_NAME --name "REDIS-PASSWORD" --value "n1swelsBiZkvTx3IXq8cIOq210vbRgfc0AzCaLScFmI="
az keyvault secret set --vault-name $VAULT_NAME --name "REDIS-PORT" --value "6380"
az keyvault secret set --vault-name $VAULT_NAME --name "REDIS-DATABASE" --value "2"
az keyvault secret set --vault-name $VAULT_NAME --name "REDIS-CONNECTION" --value "mscwound.redis.cache.windows.net:6380,password=n1swelsBiZkvTx3IXq8cIOq210vbRgfc0AzCaLScFmI=,ssl=True"

# Azure Storage Configuration
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-STORAGE-BLOB-URL" --value "https://mscappstorage.blob.core.windows.net/mscblob-dev/?sv=2022-11-02&ss=b&srt=co&se=2025-06-08T07%3A25%3A13Z&sp=rwl&sig=xw3QxGY5aH4%2FbIjRgrDNLwXGucCGqLc5W1%2F8RKEQ%2F08%3D"

# File Storage
az keyvault secret set --vault-name $VAULT_NAME --name "FILESYSTEM-DISK" --value "local"

# Health Vocabulary REST API
az keyvault secret set --vault-name $VAULT_NAME --name "HEALTH-VOCAB-API-URL" --value "http://localhost:8001"

# UMLS
az keyvault secret set --vault-name $VAULT_NAME --name "UMLS-API-KEY" --value "8b634738-d64a-4dea-a48a-15934504a13e"

# Azure Services
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-AISERVICES-COGNITIVESERVICES-ENDPOINT" --value "https://msc-ai-services.services.ai.azure.com/models"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-AISERVICES-OPENAI-BASE" --value "https://msc-ai-services.openai.azure.com/"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-SPEECH-ENDPOINT" --value "https://eastus2.tts.speech.microsoft.com"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-SPEECH-KEY" --value "1niLnd9xLv3idBqCXsGXUNs9mStjkhMksN1TKNsxFueoSF6WAg6rJQQJ99BCACHYHv6XJ3w3AAAAACOGSouN"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-SPEECH-REGION" --value "eastus2"

# Azure Document Intelligence Configuration
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-DOCUMENT-INTELLIGENCE-ENDPOINT" --value "https://msc-portal-resource.cognitiveservices.azure.com/"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-DOCUMENT-INTELLIGENCE-KEY" --value "CPBG2LnTpdGKMKrONcWPWkD97e5ceXskv2eH4a2gzfeh39t0lqPcJQQJ99BFACYeBjFXJ3w3AAAAACOGeD0P"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-DOCUMENT-INTELLIGENCE-API-VERSION" --value "2024-11-30"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-DI-ENDPOINT" --value "https://msc-portal-resource.cognitiveservices.azure.com/"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-DI-KEY" --value "CPBG2LnTpdGKMKrONcWPWkD97e5ceXskv2eH4a2gzfeh39t0lqPcJQQJ99BFACYeBjFXJ3w3AAAAACOGeD0P"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-DI-API-VERSION" --value "2024-02-29-preview"

# Availity API
az keyvault secret set --vault-name $VAULT_NAME --name "AVAILITY-API-BASE-URL" --value "https://api.availity.com/availity/development-partner/v1"
az keyvault secret set --vault-name $VAULT_NAME --name "AVAILITY-CLIENT-ID" --value "33b643080448344321873840c85f0dd9"
az keyvault secret set --vault-name $VAULT_NAME --name "AVAILITY-CLIENT-SECRET" --value "23688a3fd16b4f0f6c5c4db43c9b81df"
az keyvault secret set --vault-name $VAULT_NAME --name "AVAILITY-CACHE-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "AVAILITY-CACHE-TTL" --value "3600"
az keyvault secret set --vault-name $VAULT_NAME --name "AVAILITY-LOGGING-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "AVAILITY-LOG-LEVEL" --value "info"
az keyvault secret set --vault-name $VAULT_NAME --name "AVAILITY-LOG-REQUEST-BODY" --value "false"
az keyvault secret set --vault-name $VAULT_NAME --name "AVAILITY-LOG-RESPONSE-BODY" --value "false"

# CMS API
az keyvault secret set --vault-name $VAULT_NAME --name "CMS-API-BASE-URL" --value "https://api.coverage.cms.gov/v1"
az keyvault secret set --vault-name $VAULT_NAME --name "CMS-API-CACHE-MINUTES" --value "60"
az keyvault secret set --vault-name $VAULT_NAME --name "CMS-API-MAX-RETRIES" --value "3"
az keyvault secret set --vault-name $VAULT_NAME --name "CMS-API-THROTTLE-LIMIT" --value "9000"
az keyvault secret set --vault-name $VAULT_NAME --name "CMS-API-TIMEOUT" --value "30"

# DocuSeal API
az keyvault secret set --vault-name $VAULT_NAME --name "DOCUSEAL-API-URL" --value "https://api.docuseal.com"
az keyvault secret set --vault-name $VAULT_NAME --name "DOCUSEAL-API-KEY" --value "rj6M42wpPkU1vhzaLi9qykeWPqsgS8VBdxJh1UrLSmt"
az keyvault secret set --vault-name $VAULT_NAME --name "DOCUSEAL-WEBHOOK-SECRET" --value "123abc123a\$\$"
az keyvault secret set --vault-name $VAULT_NAME --name "DOCUSEAL-TIMEOUT" --value "3000"
az keyvault secret set --vault-name $VAULT_NAME --name "DOCUSEAL-MAX-RETRIES" --value "3"
az keyvault secret set --vault-name $VAULT_NAME --name "DOCUSEAL-RETRY-DELAY" --value "1000"

# Azure AI Foundry Configuration
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-AI-FOUNDRY-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-OPENAI-ENDPOINT" --value "https://msc-ai-services.cognitiveservices.azure.com"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-OPENAI-API-KEY" --value "1niLnd9xLv3idBqCXsGXUNs9mStjkhMksN1TKNsxFueoSF6WAg6rJQQJ99BCACHYHv6XJ3w3AAAAACOGSouN"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-OPENAI-DEPLOYMENT-NAME" --value "gpt-4o"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-OPENAI-API-VERSION" --value "2025-01-01-preview"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-OPENAI-REALTIME-DEPLOYMENT-NAME" --value "gpt-4o-mini-realtime-preview"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-OPENAI-REALTIME-API-VERSION" --value "2024-10-01-preview"
az keyvault secret set --vault-name $VAULT_NAME --name "AI-DEFAULT-VOICE" --value "en-US-JennyNeural"
az keyvault secret set --vault-name $VAULT_NAME --name "AI-REALTIME-VOICE" --value "alloy"

# ECW Integration
az keyvault secret set --vault-name $VAULT_NAME --name "ECW-CLIENT-ID" --value "your-client-id"
az keyvault secret set --vault-name $VAULT_NAME --name "ECW-CLIENT-SECRET" --value "your-client-secret"
az keyvault secret set --vault-name $VAULT_NAME --name "ECW-ENVIRONMENT" --value "sandbox"
az keyvault secret set --vault-name $VAULT_NAME --name "ECW-REDIRECT-URI" --value "https://your-domain.com/api/ecw/callback"
az keyvault secret set --vault-name $VAULT_NAME --name "ECW-SCOPE" --value "patient/Patient.read patient/Observation.read patient/DocumentReference.read"

# Epic Integration
az keyvault secret set --vault-name $VAULT_NAME --name "EPIC-CLIENT-ID" --value "your_epic_client_id_here"
az keyvault secret set --vault-name $VAULT_NAME --name "EPIC-CLIENT-SECRET" --value "your_epic_client_secret_here"
az keyvault secret set --vault-name $VAULT_NAME --name "EPIC-ENVIRONMENT" --value "sandbox"
az keyvault secret set --vault-name $VAULT_NAME --name "EPIC-FHIR-BASE-URL" --value "https://fhir.epic.com/interconnect-fhir-oauth/api/FHIR/R4"
az keyvault secret set --vault-name $VAULT_NAME --name "EPIC-AUTHORIZATION-ENDPOINT" --value "https://fhir.epic.com/interconnect-fhir-oauth/oauth2/authorize"
az keyvault secret set --vault-name $VAULT_NAME --name "EPIC-TOKEN-ENDPOINT" --value "https://fhir.epic.com/interconnect-fhir-oauth/oauth2/token"
az keyvault secret set --vault-name $VAULT_NAME --name "EPIC-SCOPE" --value "patient/Patient.read patient/Condition.read patient/Observation.read patient/DiagnosticReport.read"
az keyvault secret set --vault-name $VAULT_NAME --name "EPIC-TIMEOUT" --value "30"

# Laravel Sanctum
az keyvault secret set --vault-name $VAULT_NAME --name "SESSION-DOMAIN" --value "localhost"
az keyvault secret set --vault-name $VAULT_NAME --name "SESSION-DRIVER" --value "cookie"
az keyvault secret set --vault-name $VAULT_NAME --name "SESSION-LIFETIME" --value "120"
az keyvault secret set --vault-name $VAULT_NAME --name "SESSION-SECURE-COOKIE" --value "false"
az keyvault secret set --vault-name $VAULT_NAME --name "SANCTUM-STATEFUL-DOMAINS" --value "localhost:3000,localhost:5173,127.0.0.1:5173,localhost"

# Vite Configuration
az keyvault secret set --vault-name $VAULT_NAME --name "VITE-APP-NAME" --value "MSC Platform"
az keyvault secret set --vault-name $VAULT_NAME --name "VITE-PUSHER-APP-KEY" --value ""
az keyvault secret set --vault-name $VAULT_NAME --name "VITE-PUSHER-APP-CLUSTER" --value ""

# API Keys
az keyvault secret set --vault-name $VAULT_NAME --name "SUPERINTEREFACE-API-KEY" --value "1ca5c3bf-a011-4211-90ae-7d593341592b"
az keyvault secret set --vault-name $VAULT_NAME --name "INTERCOM-ACCESS-TOKEN" --value "12345"
az keyvault secret set --vault-name $VAULT_NAME --name "GOOGLE-MAPS-API-KEY" --value "AIzaSyBlVWjkRiQcRbyejwdPgjnTn4v8VZqYZlY"
az keyvault secret set --vault-name $VAULT_NAME --name "VITE-GOOGLE-MAPS-API-KEY" --value "AIzaSyBlVWjkRiQcRbyejwdPgjnTn4v8VZqYZlY"
az keyvault secret set --vault-name $VAULT_NAME --name "FIRECRAWL-API-KEY" --value "fc-469546b6442e4093afc8c430b2acd35a"

# Insurance AI Assistant Configuration
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-ID" --value "asst_j0WF52r4zKaoltrygOT7yVgD"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-VOICE-ENABLED" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-MODEL" --value "gpt-4o"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-TEMPERATURE" --value "0.7"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-MAX-TOKENS" --value "2000"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-CONTEXT-WINDOW" --value "8000"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-SYSTEM-PROMPT" --value "You are an insurance AI assistant specialized in wound care insurance verification and form assistance with access to extensive manufacturer field mappings and ML-enhanced recommendations."
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-TRAINING-VERSION" --value "2024-11-20"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-CACHE-TTL" --value "3600"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-ML-ENHANCEMENT" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-MANUFACTURER-CONTEXT" --value "true"
az keyvault secret set --vault-name $VAULT_NAME --name "AZURE-INSURANCE-ASSISTANT-BEHAVIORAL-TRACKING" --value "true"

echo "All secrets uploaded to Key Vault: $VAULT_NAME"
