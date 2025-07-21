#!/bin/bash

# Script to rename V2 services back to their original names
# This will give us a clean codebase without the temporary V2 suffix

echo "🔄 Starting service renaming process..."

# Step 1: Rename service files back to original names
echo "📁 Renaming service files..."

# Core services
mv app/Services/UnifiedFieldMappingServiceV2.php app/Services/UnifiedFieldMappingService.php 2>/dev/null
mv app/Services/DocusealServiceV2.php app/Services/DocusealService.php 2>/dev/null
mv app/Services/EntityDataServiceV2.php app/Services/EntityDataService.php 2>/dev/null
mv app/Services/DocumentIntelligenceServiceV2.php app/Services/DocumentIntelligenceService.php 2>/dev/null
mv app/Services/FhirDocusealIntegrationServiceV2.php app/Services/FhirDocusealIntegrationService.php 2>/dev/null

# Field mapping services
mv app/Services/FieldMapping/DataExtractorV2.php app/Services/FieldMapping/DataExtractor.php 2>/dev/null
mv app/Services/FieldMapping/FieldTransformerV2.php app/Services/FieldMapping/FieldTransformer.php 2>/dev/null
mv app/Services/FieldMapping/FieldMatcherV2.php app/Services/FieldMapping/FieldMatcher.php 2>/dev/null

# DocuSeal services
mv app/Services/DocuSeal/DocuSealApiClientV2.php app/Services/DocuSeal/DocuSealApiClient.php 2>/dev/null
mv app/Services/DocuSeal/TemplateFieldValidationServiceV2.php app/Services/DocuSeal/TemplateFieldValidationService.php 2>/dev/null

echo "✅ Service files renamed"

# Step 2: Update class names inside the files
echo "📝 Updating class names..."

# Update class declarations
find app/Services -name "*.php" -type f -exec sed -i 's/class \([A-Za-z]*\)V2/class \1/g' {} \;

# Update imports that use V2 suffix
find app -name "*.php" -type f -exec sed -i 's/use App\\Services\\\([A-Za-z\\]*\)V2;/use App\\Services\\\1;/g' {} \;
find app -name "*.php" -type f -exec sed -i 's/use App\\Services\\FieldMapping\\\([A-Za-z]*\)V2;/use App\\Services\\FieldMapping\\\1;/g' {} \;
find app -name "*.php" -type f -exec sed -i 's/use App\\Services\\DocuSeal\\\([A-Za-z]*\)V2;/use App\\Services\\DocuSeal\\\1;/g' {} \;

# Update aliased imports (e.g., "as DocusealService")
find app -name "*.php" -type f -exec sed -i 's/use App\\Services\\\([A-Za-z\\]*\)V2 as \([A-Za-z]*\);/use App\\Services\\\1;/g' {} \;
find app -name "*.php" -type f -exec sed -i 's/use App\\Services\\FieldMapping\\\([A-Za-z]*\)V2 as \([A-Za-z]*\);/use App\\Services\\FieldMapping\\\1;/g' {} \;
find app -name "*.php" -type f -exec sed -i 's/use App\\Services\\DocuSeal\\\([A-Za-z]*\)V2 as \([A-Za-z]*\);/use App\\Services\\DocuSeal\\\1;/g' {} \;

# Update class references in code
find app -name "*.php" -type f -exec sed -i 's/\\App\\Services\\\([A-Za-z\\]*\)V2::/\\App\\Services\\\1::/g' {} \;
find app -name "*.php" -type f -exec sed -i 's/\\App\\Services\\FieldMapping\\\([A-Za-z]*\)V2::/\\App\\Services\\FieldMapping\\\1::/g' {} \;
find app -name "*.php" -type f -exec sed -i 's/\\App\\Services\\DocuSeal\\\([A-Za-z]*\)V2::/\\App\\Services\\DocuSeal\\\1::/g' {} \;

echo "✅ Class names updated"

# Step 3: Update AppServiceProvider to remove V2 references and aliases
echo "🔧 Updating service providers..."

# Create a temporary PHP script to update AppServiceProvider
cat > /tmp/update_app_provider.php << 'EOF'
<?php
$file = 'app/Providers/AppServiceProvider.php';
$content = file_get_contents($file);

// Remove V2 from service registrations
$content = preg_replace('/(\$this->app->singleton\(\\\\App\\\\Services\\\\[A-Za-z\\\\]*)V2(::class)/', '$1$2', $content);
$content = preg_replace('/(\$this->app->make\(\\\\App\\\\Services\\\\[A-Za-z\\\\]*)V2(::class)/', '$1$2', $content);
$content = preg_replace('/(new \\\\App\\\\Services\\\\[A-Za-z\\\\]*)V2(\()/', '$1$2', $content);

// Remove all alias lines
$content = preg_replace('/\s*\/\/ Temporary alias.*\n\s*\$this->app->alias\([^;]+;\n/m', '', $content);

file_put_contents($file, $content);
echo "AppServiceProvider updated\n";
EOF

php /tmp/update_app_provider.php
rm /tmp/update_app_provider.php

echo "✅ Service providers updated"

# Step 4: Clear Laravel caches
echo "🧹 Clearing Laravel caches..."
php artisan optimize:clear

echo "✅ Caches cleared"

# Step 5: List any remaining files that might need attention
echo ""
echo "📋 Checking for any remaining V2 references..."
remaining=$(grep -r "V2" app/Services --include="*.php" | grep -v "vendor" | grep -E "(class|use|::)" | wc -l)

if [ $remaining -gt 0 ]; then
    echo "⚠️  Found $remaining remaining V2 references:"
    grep -r "V2" app/Services --include="*.php" | grep -v "vendor" | grep -E "(class|use|::)"
else
    echo "✅ No remaining V2 references found!"
fi

echo ""
echo "🎉 Service renaming complete!"
echo ""

# Step 6: Mark questionable services with RV suffix for review
echo "🔍 Marking questionable services for review..."

# Services we identified as potentially obsolete or needing review
if [ -f "app/Services/IvrDocusealService.php" ]; then
    mv app/Services/IvrDocusealService.php app/Services/IvrDocusealServiceRV.php 2>/dev/null
    sed -i 's/class IvrDocusealService/class IvrDocusealServiceRV/g' app/Services/IvrDocusealServiceRV.php
    echo "  - Marked IvrDocusealService for review"
fi

if [ -f "app/Services/FhirToIvrFieldExtractor.php" ]; then
    mv app/Services/FhirToIvrFieldExtractor.php app/Services/FhirToIvrFieldExtractorRV.php 2>/dev/null
    sed -i 's/class FhirToIvrFieldExtractor/class FhirToIvrFieldExtractorRV/g' app/Services/FhirToIvrFieldExtractorRV.php
    echo "  - Marked FhirToIvrFieldExtractor for review"
fi

if [ -f "app/Services/IVRMappingOrchestrator.php" ]; then
    mv app/Services/IVRMappingOrchestrator.php app/Services/IVRMappingOrchestratorRV.php 2>/dev/null
    sed -i 's/class IVRMappingOrchestrator/class IVRMappingOrchestratorRV/g' app/Services/IVRMappingOrchestratorRV.php
    echo "  - Marked IVRMappingOrchestrator for review"
fi

if [ -f "app/Services/ImprovedOcrFieldDetectionService.php" ]; then
    mv app/Services/ImprovedOcrFieldDetectionService.php app/Services/ImprovedOcrFieldDetectionServiceRV.php 2>/dev/null
    sed -i 's/class ImprovedOcrFieldDetectionService/class ImprovedOcrFieldDetectionServiceRV/g' app/Services/ImprovedOcrFieldDetectionServiceRV.php
    echo "  - Marked ImprovedOcrFieldDetectionService for review"
fi

# Mark FhirDocusealIntegrationService for review since it only uses obsolete services
if [ -f "app/Services/FhirDocusealIntegrationService.php" ]; then
    mv app/Services/FhirDocusealIntegrationService.php app/Services/FhirDocusealIntegrationServiceRV.php 2>/dev/null
    sed -i 's/class FhirDocusealIntegrationService/class FhirDocusealIntegrationServiceRV/g' app/Services/FhirDocusealIntegrationServiceRV.php
    echo "  - Marked FhirDocusealIntegrationService for review (uses obsolete services)"
fi

echo ""
echo "✅ Review marking complete!"
echo ""
echo "📝 Summary:"
echo "- All active V2 services renamed back to original names"
echo "- Service providers and imports updated"
echo "- Questionable services marked with 'RV' suffix for review"
echo ""
echo "🗑️  Services marked for review (RV suffix):"
ls -la app/Services/*RV.php 2>/dev/null || echo "  None found"
echo ""
echo "Next steps:"
echo "1. Test the application to ensure everything works"
echo "2. Review and delete any *RV.php files after confirming they're not needed"
echo "3. Delete any remaining obsolete services" 