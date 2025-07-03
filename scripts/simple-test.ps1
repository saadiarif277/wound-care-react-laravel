# Simple Docuseal Test
Write-Host "Testing Docuseal Integration..." -ForegroundColor Green

try {
    # Test debug endpoint
    $response = Invoke-RestMethod -Uri "http://localhost/quick-requests/docuseal/debug-integration" -Method GET
    Write-Host "Debug endpoint works!" -ForegroundColor Green

    # Test submission endpoint with minimal data
    $testData = @{
        user_email = "test@mscwoundcare.com"
        manufacturerId = 32
        prefill_data = @{
            patient_first_name = "Test"
            patient_last_name = "Patient"
        }
    } | ConvertTo-Json

    $result = Invoke-RestMethod -Uri "http://localhost/quick-requests/docuseal/generate-submission-slug" -Method POST -Body $testData -ContentType "application/json"

    if ($result.success) {
        Write-Host "SUCCESS! Docuseal integration is working!" -ForegroundColor Green
        Write-Host "Slug: $($result.slug)" -ForegroundColor White
    } else {
        Write-Host "Failed: $($result.error)" -ForegroundColor Red
    }
}
catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}
