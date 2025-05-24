<?php

/**
 * Test script for MSC-MVP FHIR Server REST API
 *
 * This script demonstrates how to interact with the FHIR API endpoints.
 * The API acts as a proxy to Azure Health Data Services.
 */

require_once 'vendor/autoload.php';

use Illuminate\Http\Client\Factory as HttpClient;

$httpClient = new HttpClient();
$baseUrl = 'http://localhost:8000/api/fhir'; // Adjust to your app URL

// Test 1: Get Capability Statement
echo "=== Test 1: Get Capability Statement ===\n";
try {
    $response = $httpClient->get("{$baseUrl}/metadata");
    echo "Status: " . $response->status() . "\n";
    echo "Content-Type: " . $response->header('Content-Type') . "\n";

    if ($response->successful()) {
        $capability = $response->json();
        echo "FHIR Server: " . $capability['title'] . " v" . $capability['version'] . "\n";
        echo "FHIR Version: " . $capability['fhirVersion'] . "\n";
        echo "Supported Resources: " . implode(', ', array_column($capability['rest'][0]['resource'], 'type')) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Create a Patient (Example FHIR Resource)
echo "=== Test 2: Create Patient Resource ===\n";
$patientData = [
    'resourceType' => 'Patient',
    'identifier' => [
        [
            'use' => 'usual',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                        'code' => 'MR',
                        'display' => 'Medical record number'
                    ]
                ]
            ],
            'value' => 'MRN-' . uniqid()
        ]
    ],
    'active' => true,
    'name' => [
        [
            'use' => 'official',
            'family' => 'Test',
            'given' => ['Patient', 'Example']
        ]
    ],
    'gender' => 'male',
    'birthDate' => '1980-01-01',
    'telecom' => [
        [
            'system' => 'phone',
            'value' => '555-0123',
            'use' => 'home'
        ],
        [
            'system' => 'email',
            'value' => 'patient.test@example.com',
            'use' => 'home'
        ]
    ],
    'address' => [
        [
            'use' => 'home',
            'line' => ['123 Main St', 'Apt 1'],
            'city' => 'Anytown',
            'state' => 'CA',
            'postalCode' => '12345',
            'country' => 'US'
        ]
    ],
    'extension' => [
        [
            'url' => 'http://msc-mvp.com/fhir/StructureDefinition/wound-care-consent',
            'valueCode' => 'active'
        ],
        [
            'url' => 'http://msc-mvp.com/fhir/StructureDefinition/platform-status',
            'valueCode' => 'active'
        ]
    ]
];

try {
    $response = $httpClient->withHeaders([
        'Content-Type' => 'application/fhir+json'
    ])->post("{$baseUrl}/Patient", $patientData);

    echo "Status: " . $response->status() . "\n";
    echo "Location Header: " . $response->header('Location') . "\n";

    if ($response->successful()) {
        $patient = $response->json();
        $patientId = $patient['id'];
        echo "Created Patient ID: {$patientId}\n";
        echo "Resource Type: " . $patient['resourceType'] . "\n";
        echo "Active: " . ($patient['active'] ? 'true' : 'false') . "\n";

        // Store patient ID for further tests
        file_put_contents('test-patient-id.txt', $patientId);
    } else {
        echo "Error Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Read Patient Resource
if (file_exists('test-patient-id.txt')) {
    echo "=== Test 3: Read Patient Resource ===\n";
    $patientId = trim(file_get_contents('test-patient-id.txt'));

    try {
        $response = $httpClient->withHeaders([
            'Accept' => 'application/fhir+json'
        ])->get("{$baseUrl}/Patient/{$patientId}");

        echo "Status: " . $response->status() . "\n";

        if ($response->successful()) {
            $patient = $response->json();
            echo "Patient ID: " . $patient['id'] . "\n";
            echo "Resource Type: " . $patient['resourceType'] . "\n";
            echo "Last Updated: " . $patient['meta']['lastUpdated'] . "\n";

            // Display MSC extensions
            if (isset($patient['extension'])) {
                echo "MSC Extensions:\n";
                foreach ($patient['extension'] as $extension) {
                    $urlParts = explode('/', $extension['url']);
                    $extensionName = end($urlParts);
                    echo "  - {$extensionName}: " . $extension['valueCode'] . "\n";
                }
            }
        } else {
            echo "Error Response: " . $response->body() . "\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

// Test 4: Search Patients
echo "=== Test 4: Search Patients ===\n";
try {
    $response = $httpClient->withHeaders([
        'Accept' => 'application/fhir+json'
    ])->get("{$baseUrl}/Patient", [
        'gender' => 'male',
        '_count' => 5
    ]);

    echo "Status: " . $response->status() . "\n";

    if ($response->successful()) {
        $bundle = $response->json();
        echo "Bundle Type: " . $bundle['type'] . "\n";
        echo "Total Results: " . $bundle['total'] . "\n";
        echo "Entries: " . count($bundle['entry']) . "\n";

        if (isset($bundle['entry'])) {
            foreach ($bundle['entry'] as $index => $entry) {
                $resource = $entry['resource'];
                echo "  Entry " . ($index + 1) . ": {$resource['resourceType']} {$resource['id']}\n";
            }
        }
    } else {
        echo "Error Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Transaction Bundle
echo "=== Test 5: Transaction Bundle ===\n";
$transactionBundle = [
    'resourceType' => 'Bundle',
    'type' => 'transaction',
    'entry' => [
        [
            'request' => [
                'method' => 'POST',
                'url' => 'Patient'
            ],
            'resource' => [
                'resourceType' => 'Patient',
                'identifier' => [
                    [
                        'use' => 'usual',
                        'type' => [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                    'code' => 'MR',
                                    'display' => 'Medical record number'
                                ]
                            ]
                        ],
                        'value' => 'MRN-BATCH-' . uniqid()
                    ]
                ],
                'active' => true,
                'name' => [
                    [
                        'use' => 'official',
                        'family' => 'Batch',
                        'given' => ['Transaction', 'Patient']
                    ]
                ],
                'gender' => 'female',
                'birthDate' => '1990-05-15'
            ]
        ]
    ]
];

try {
    $response = $httpClient->withHeaders([
        'Content-Type' => 'application/fhir+json'
    ])->post($baseUrl, $transactionBundle);

    echo "Status: " . $response->status() . "\n";

    if ($response->successful()) {
        $responseBundle = $response->json();
        echo "Response Bundle Type: " . $responseBundle['type'] . "\n";
        echo "Entries: " . count($responseBundle['entry']) . "\n";

        foreach ($responseBundle['entry'] as $index => $entry) {
            echo "  Entry " . ($index + 1) . " Status: " . $entry['status'] . "\n";
            if (isset($entry['location'])) {
                echo "  Entry " . ($index + 1) . " Location: " . $entry['location'] . "\n";
            }
        }
    } else {
        echo "Error Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== FHIR API Test Complete ===\n";

// Configuration Notice
echo "\nNOTE: Before running this test, ensure you have configured your Azure Health Data Services in .env:\n";
echo "AZURE_TENANT_ID=your-tenant-id\n";
echo "AZURE_CLIENT_ID=your-client-id\n";
echo "AZURE_CLIENT_SECRET=your-client-secret\n";
echo "AZURE_FHIR_ENDPOINT=https://your-workspace.fhir.azurehealthcareapis.com\n";
