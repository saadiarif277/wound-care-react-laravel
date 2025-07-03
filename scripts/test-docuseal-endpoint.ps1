# Test Docuseal Endpoint Fix
Write-Host "Testing Docuseal Endpoint Fix" -ForegroundColor Green
Write-Host "================================" -ForegroundColor Green

# Test data that matches what the frontend sends
$testData = @{
    user_email = "test@mscwoundcare.com"
    integration_email = "provider@example.com"
    prefill_data = @{
        patient_name = "John Doe"
        provider_name = "Dr. Smith"
        patient_email = "patient@example.com"
        provider_email = "provider@example.com"
    }
    manufacturerId = "32"  # String value like frontend sends
    productCode = "Q4250"
} | ConvertTo-Json -Depth 3

Write-Host "Test data prepared:" -ForegroundColor Yellow
Write-Host $testData -ForegroundColor Gray

# Test with Herd domain (adjust if different)
$baseUrl = "http://wound-care-react-laravel.test"
$endpoint = "$baseUrl/quick-requests/docuseal/generate-submission-slug"

Write-Host "`nTesting endpoint: $endpoint" -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri $endpoint -Method POST -Body $testData -ContentType "application/json" -ErrorAction Stop

    Write-Host "`n✅ SUCCESS!" -ForegroundColor Green
    Write-Host "Response:" -ForegroundColor Yellow
    $response | ConvertTo-Json -Depth 3 | Write-Host -ForegroundColor Gray

} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    $responseBody = ""

    if ($_.Exception.Response) {
        $stream = $_.Exception.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        $responseBody = $reader.ReadToEnd()
    }

    Write-Host "`n❌ FAILED!" -ForegroundColor Red
    Write-Host "Status Code: $statusCode" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red

    if ($responseBody) {
        Write-Host "Response Body:" -ForegroundColor Yellow
        try {
            $jsonResponse = $responseBody | ConvertFrom-Json
            $jsonResponse | ConvertTo-Json -Depth 3 | Write-Host -ForegroundColor Gray
        } catch {
            Write-Host $responseBody -ForegroundColor Gray
        }
    }
}

Write-Host "`n🔍 Next Steps:" -ForegroundColor Cyan
Write-Host "1. Check Laravel logs: storage/logs/laravel.log" -ForegroundColor White
Write-Host "2. Verify you are logged in to the application" -ForegroundColor White
Write-Host "3. Ensure you have create-product-requests permission" -ForegroundColor White
Write-Host "4. Check Docuseal API key configuration" -ForegroundColor White
