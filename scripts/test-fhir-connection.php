<?php

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "Testing FHIR Connection...\n\n";

// Get configuration
$tenantId = $_ENV['AZURE_FHIR_TENANT_ID'];
$clientId = $_ENV['AZURE_FHIR_CLIENT_ID'];
$clientSecret = $_ENV['AZURE_FHIR_CLIENT_SECRET'];
$fhirEndpoint = $_ENV['AZURE_FHIR_ENDPOINT'];

echo "Configuration loaded:\n";
echo "Tenant ID: " . substr($tenantId, 0, 8) . "...\n";
echo "Client ID: " . substr($clientId, 0, 8) . "...\n";
echo "FHIR Endpoint: $fhirEndpoint\n\n";

try {
    $client = new Client();

    // Get access token
    echo "Getting access token...\n";
    $tokenResponse = $client->post(
        "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
        [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://ahdsmscmvpprod-fhir-msc-mvp-prod.fhir.azurehealthcareapis.com/.default'
            ]
        ]
    );

    $tokenData = json_decode($tokenResponse->getBody()->getContents(), true);
    if (!isset($tokenData['access_token'])) {
        throw new Exception("Failed to get access token");
    }

    $token = $tokenData['access_token'];
    echo "Access token obtained successfully!\n\n";

    // Test FHIR metadata endpoint
    echo "Testing FHIR metadata endpoint...\n";
    $metadataResponse = $client->get($fhirEndpoint . '/metadata', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]
    ]);

    $metadata = json_decode($metadataResponse->getBody()->getContents(), true);
    echo "Successfully connected to FHIR server!\n";
    echo "FHIR version: " . ($metadata['fhirVersion'] ?? 'Unknown') . "\n";
    echo "Software: " . ($metadata['software']['name'] ?? 'Unknown') . " " . ($metadata['software']['version'] ?? '') . "\n\n";

    // Test Patient endpoint
    echo "Testing Patient endpoint...\n";
    $patientResponse = $client->get($fhirEndpoint . '/Patient?_summary=count', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ],
        'debug' => true,
        'http_errors' => false
    ]);

    $statusCode = $patientResponse->getStatusCode();
    $responseBody = $patientResponse->getBody()->getContents();
    
    if ($statusCode !== 200) {
        echo "Response Status Code: " . $statusCode . "\n";
        echo "Response Headers:\n";
        foreach ($patientResponse->getHeaders() as $name => $values) {
            echo $name . ": " . implode(", ", $values) . "\n";
        }
        echo "Response Body:\n" . $responseBody . "\n";
        throw new Exception("Failed to access Patient endpoint: " . $responseBody);
    }

    $patientData = json_decode($responseBody, true);
    echo "Successfully accessed Patient endpoint!\n";
    echo "Total patients: " . ($patientData['total'] ?? 'Unknown') . "\n";

    echo "\nAll tests completed successfully! ✅\n";

} catch (Exception | GuzzleException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
