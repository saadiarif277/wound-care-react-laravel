# Test DocuSeal Integration Fix
# This script tests the enhanced DocuSeal submission generation endpoint

Write-Host "🧪 Testing DocuSeal Integration Fix" -ForegroundColor Cyan
Write-Host "=================================" -ForegroundColor Cyan

# Configuration
$baseUrl = "http://localhost" # Adjust if different
$debugEndpoint = "/quick-requests/docuseal/debug-integration"
$submissionEndpoint = "/quick-requests/docuseal/generate-submission-slug"

# Test 1: Debug endpoint
Write-Host "`n🔍 Step 1: Running diagnostic tests..." -ForegroundColor Yellow

try {
    $debugResponse = Invoke-RestMethod -Uri "$baseUrl$debugEndpoint" -Method GET -ContentType "application/json"

    Write-Host "✅ Debug endpoint accessible" -ForegroundColor Green

    # Display key diagnostic results
    $config = $debugResponse.debug_info.configuration
    $manufacturer = $debugResponse.debug_info.manufacturer_info
    $template = $debugResponse.debug_info.template_info
    $api = $debugResponse.debug_info.api_connectivity

    Write-Host "`n📊 Diagnostic Results:" -ForegroundColor White
    Write-Host "  API Key Configured: $($config.api_key_configured)" -ForegroundColor $(if($config.api_key_configured) {"Green"} else {"Red"})
    Write-Host "  Manufacturer Found: $($manufacturer.found)" -ForegroundColor $(if($manufacturer.found) {"Green"} else {"Red"})
    Write-Host "  Template Found: $($template.found)" -ForegroundColor $(if($template.found) {"Green"} else {"Red"})
    Write-Host "  API Connectivity: $($api.status)" -ForegroundColor $(if($api.status -eq "success") {"Green"} else {"Red"})

    # Display recommendations
    Write-Host "`n💡 Recommendations:" -ForegroundColor White
    foreach ($rec in $debugResponse.recommendations) {
        Write-Host "  $rec" -ForegroundColor $(if($rec.StartsWith("✅")) {"Green"} elseif($rec.StartsWith("⚠️")) {"Yellow"} else {"Red"})
    }

    # Check if we can proceed with submission test
    $canTestSubmission = $config.api_key_configured -and $manufacturer.found -and $template.found -and ($api.status -eq "success")

    if ($canTestSubmission) {
        Write-Host "`n🎯 All prerequisites met! Testing submission generation..." -ForegroundColor Green

        # Test 2: Submission generation
        $testData = @{
            user_email = "test@mscwoundcare.com"
            integration_email = "limitless@mscwoundcare.com"
            manufacturerId = $manufacturer.id
            prefill_data = @{
                patient_first_name = "John"
                patient_last_name = "Doe"
                patient_dob = "1990-01-01"
                provider_name = "Dr. Smith"
                provider_npi = "1234567890"
                wound_type = "Diabetic Foot Ulcer"
                primary_insurance_name = "Medicare"
            }
        } | ConvertTo-Json -Depth 3

        try {
            $submissionResponse = Invoke-RestMethod -Uri "$baseUrl$submissionEndpoint" -Method POST -Body $testData -ContentType "application/json"

            if ($submissionResponse.success) {
                Write-Host "🎉 SUCCESS! Submission created successfully" -ForegroundColor Green
                Write-Host "  Slug: $($submissionResponse.slug)" -ForegroundColor White
                Write-Host "  Template: $($submissionResponse.template_name)" -ForegroundColor White
                Write-Host "  Manufacturer: $($submissionResponse.manufacturer)" -ForegroundColor White
                Write-Host "  Fields Mapped: $($submissionResponse.mapped_fields_count)" -ForegroundColor White
                Write-Host "  Embed URL: $($submissionResponse.embed_url)" -ForegroundColor White

                Write-Host "`n✅ The 500 error has been FIXED!" -ForegroundColor Green -BackgroundColor DarkGreen
            } else {
                Write-Host "❌ Submission failed: $($submissionResponse.error)" -ForegroundColor Red
            }
        }
        catch {
            Write-Host "❌ Submission test failed with error:" -ForegroundColor Red
            Write-Host "  $($_.Exception.Message)" -ForegroundColor Red

            if ($_.Exception.Response) {
                $statusCode = $_.Exception.Response.StatusCode.value__
                Write-Host "  HTTP Status: $statusCode" -ForegroundColor Red

                if ($statusCode -eq 500) {
                    Write-Host "  ⚠️ Still getting 500 error - check Laravel logs for details" -ForegroundColor Yellow
                }
            }
        }
    } else {
        Write-Host "`n⚠️ Cannot test submission - prerequisites not met" -ForegroundColor Yellow
        Write-Host "Please fix the issues shown in recommendations above" -ForegroundColor Yellow
    }
}
catch {
    Write-Host "❌ Debug endpoint failed:" -ForegroundColor Red
    Write-Host "  $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "  Make sure your Laravel application is running" -ForegroundColor Yellow
}

Write-Host "`n🏁 Test completed!" -ForegroundColor Cyan

# Additional helpful information
Write-Host "`n📚 Additional Information:" -ForegroundColor White
Write-Host "  Debug URL: $baseUrl$debugEndpoint" -ForegroundColor Gray
Write-Host "  Submission URL: $baseUrl$submissionEndpoint" -ForegroundColor Gray
Write-Host "  Check Laravel logs: storage/logs/laravel.log" -ForegroundColor Gray
Write-Host "  For detailed logs, search for: DocuSeal submission generation" -ForegroundColor Gray
