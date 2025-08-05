# MSC Wound Care Portal - Project Overview

## Project Purpose
The MSC Wound Care Portal is a HIPAA-compliant healthcare platform for wound care management. It provides comprehensive wound assessment, tracking, and order management with strict data separation between operational data (Azure SQL) and PHI data (Azure Health Data Services/FHIR).

## Key Features
- **Wound Care Management**: Comprehensive wound assessment and tracking
- **Product Request/Order Management**: Provider-initiated product requests that become orders
- **Commission Management**: Automated commission calculation and tracking for sales reps
- **Document Management**: Docuseal integration for IVR forms and document signing
- **Insurance Verification**: Integration with payer APIs for eligibility checking
- **Clinical Decision Support**: AI-powered clinical opportunities and product recommendations
- **HIPAA Compliance**: Strict PHI/non-PHI data separation with audit logging
- **Multi-Role Access**: Providers, sales reps, admins, office managers with RBAC

## Architecture Highlights
- **Data Separation**: Non-PHI in Azure SQL, PHI in Azure FHIR
- **Service Layer Pattern**: Comprehensive services for business logic
- **Inertia.js SPA**: Server-side routing with client-side navigation
- **Event-Driven**: Webhooks for external service updates
- **Field Mapping System**: Flexible mapping between document templates and canonical fields

## Key Workflows
1. **Quick Request Flow**: Provider creates request → Patient data to FHIR → Insurance verification → Product selection → Document generation → Manufacturer notification
2. **Episode-Based Orders**: Orders grouped by patient care episodes with IVR per episode
3. **Commission System**: Hierarchical rule matching with automated payouts

## Environment
- Platform: Linux (WSL2)
- Working Directory: /home/rvalen/Projects/msc-woundcare-portal
- Git Repo: Yes (current branch: azure-dev, main branch: master)