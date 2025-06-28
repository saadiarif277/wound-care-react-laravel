#!/bin/bash

# Test Field Mapping API Endpoints
# This script tests all the field mapping endpoints

API_BASE="http://localhost:8000/api/v1/admin/docuseal"
TOKEN="your-api-token-here"  # Replace with actual token

echo "Testing Field Mapping API Endpoints..."
echo "====================================="

# Function to make API calls
api_call() {
    local method=$1
    local endpoint=$2
    local data=$3
    
    echo -e "\n\n>>> Testing: $method $endpoint"
    
    if [ -z "$data" ]; then
        curl -X $method \
             -H "Authorization: Bearer $TOKEN" \
             -H "Accept: application/json" \
             "$API_BASE$endpoint" \
             -w "\nHTTP Status: %{http_code}\n"
    else
        curl -X $method \
             -H "Authorization: Bearer $TOKEN" \
             -H "Accept: application/json" \
             -H "Content-Type: application/json" \
             -d "$data" \
             "$API_BASE$endpoint" \
             -w "\nHTTP Status: %{http_code}\n"
    fi
}

# 1. Get canonical fields
echo -e "\n1. Getting canonical fields..."
api_call GET "/canonical-fields"

# 2. Get templates list
echo -e "\n2. Getting templates list..."
TEMPLATES_RESPONSE=$(api_call GET "/templates")

# Extract first template ID (you'll need to parse this manually or use jq)
TEMPLATE_ID="1"  # Replace with actual template ID

# 3. Get field mappings for a template
echo -e "\n3. Getting field mappings for template $TEMPLATE_ID..."
api_call GET "/templates/$TEMPLATE_ID/field-mappings"

# 4. Get mapping statistics
echo -e "\n4. Getting mapping statistics for template $TEMPLATE_ID..."
api_call GET "/templates/$TEMPLATE_ID/mapping-stats"

# 5. Update field mappings
echo -e "\n5. Updating field mappings..."
MAPPING_DATA='{
    "mappings": [
        {
            "template_field_name": "patient_first_name",
            "canonical_field_id": 1,
            "transformation_rules": [
                {
                    "type": "format",
                    "operation": "uppercase",
                    "parameters": {}
                }
            ],
            "confidence_score": 0.95
        }
    ]
}'
api_call POST "/templates/$TEMPLATE_ID/field-mappings" "$MAPPING_DATA"

# 6. Get field mapping suggestions
echo -e "\n6. Getting field mapping suggestions..."
SUGGEST_DATA='{
    "template_fields": ["patient_first_name", "patient_last_name", "physician_npi"]
}'
api_call POST "/templates/$TEMPLATE_ID/field-mappings/suggest" "$SUGGEST_DATA"

# 7. Validate mappings
echo -e "\n7. Validating mappings..."
api_call POST "/templates/$TEMPLATE_ID/field-mappings/validate"

# 8. Bulk mapping operation
echo -e "\n8. Testing bulk mapping operation..."
BULK_DATA='{
    "operation": "map_by_pattern",
    "parameters": {
        "pattern": "patient.*name",
        "canonical_field_id": 1,
        "transformation_rules": []
    }
}'
api_call POST "/templates/$TEMPLATE_ID/field-mappings/bulk" "$BULK_DATA"

# 9. Export mappings
echo -e "\n9. Exporting mappings..."
api_call GET "/field-mappings/export/$TEMPLATE_ID"

# 10. Test sync
echo -e "\n10. Testing template sync..."
api_call POST "/test-sync"

echo -e "\n\n====================================="
echo "Field Mapping API Tests Complete!"
echo "====================================="
echo ""
echo "Note: Replace the TOKEN and TEMPLATE_ID variables with actual values before running."
echo "You may also need to install 'jq' for JSON parsing if you want to extract values automatically."