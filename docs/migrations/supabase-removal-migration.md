# Supabase Service Removal Migration

## Overview

This document tracks the migration from Supabase services to alternative implementations, following service cleanup best practices.

## Changes Made

### 1. **Replaced Supabase Edge Functions with AIEnhancementService**

**Created new service**: `app/Services/AIEnhancementService.php`
- Supports multiple AI providers (Azure OpenAI, OpenAI, Mock)
- Maintains same functionality as Supabase Edge Functions
- Includes caching and error handling
- Configurable via `config/ai.php`

**Updated services**:
- `ClinicalOpportunityService`: Now uses `AIEnhancementService` instead of `SupabaseService`
- `MSCProductRecommendationService`: Now uses `AIEnhancementService` instead of `SupabaseService`

### 2. **Configuration Changes**

**Added**: `config/ai.php`
- Centralized AI configuration
- Support for multiple providers
- Feature flags for AI enhancements
- Rate limiting configuration

**Environment variables to add**:
```env
# AI Provider Configuration
AI_PROVIDER=mock  # Options: azure, openai, mock

# Azure OpenAI (if using)
AZURE_OPENAI_ENDPOINT=
AZURE_OPENAI_API_KEY=
AZURE_OPENAI_DEPLOYMENT=gpt-4
AZURE_OPENAI_API_VERSION=2023-12-01-preview

# OpenAI (if using)
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4

# Feature Flags
AI_CLINICAL_OPPORTUNITIES=true
AI_PRODUCT_RECOMMENDATIONS=true
```

### 3. **Removed Dependencies**

- Removed `SupabaseService` dependency from:
  - `ClinicalOpportunityService`
  - `MSCProductRecommendationService`
- Removed `callSupabaseEdgeFunction` methods

## Migration Steps

### For Development

1. **Update .env file**:
   ```bash
   AI_PROVIDER=mock  # Use mock for testing without API keys
   ```

2. **Clear application cache**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

3. **Update service providers** (if registered):
   - Remove any `SupabaseService` bindings
   - Add `AIEnhancementService` to container if needed

### For Production

1. **Set up AI provider**:
   - Choose between Azure OpenAI or OpenAI
   - Add appropriate API keys to environment

2. **Test AI functionality**:
   ```bash
   php artisan tinker
   # Test AI enhancement service
   $ai = app(App\Services\AIEnhancementService::class);
   $result = $ai->enhanceProductRecommendations(['test' => true], []);
   ```

3. **Monitor for errors**:
   - Check logs for any AI enhancement failures
   - Verify fallback to rule-based logic works

## Rollback Plan

If issues arise:

1. **Restore original services**:
   - The original services are in git history
   - Can be restored with: `git checkout HEAD~1 -- app/Services/ClinicalOpportunityEngine/ClinicalOpportunityService.php`

2. **Re-add Supabase configuration**:
   ```env
   SUPABASE_URL=
   SUPABASE_ANON_KEY=
   SUPABASE_SERVICE_ROLE_KEY=
   ```

## Testing Checklist

- [ ] Clinical opportunities still generate (with or without AI)
- [ ] Product recommendations still work
- [ ] Mock provider returns expected responses
- [ ] Error handling works when AI fails
- [ ] Caching reduces API calls
- [ ] No references to SupabaseService remain

## Next Steps

1. **Remove Supabase configuration**:
   - Remove from `config/services.php`
   - Remove from `.env.example`
   - Remove Supabase-related files

2. **Update other Supabase dependencies**:
   - Database connections (if any)
   - Storage configurations
   - Any remaining Edge Function calls

3. **Clean up**:
   - Delete `SupabaseService.php`
   - Delete `SupabaseRLSService.php`
   - Remove Supabase tests 
