<?php

namespace Tests\Manual;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Manual test for DocuSeal JWT generation
 * 
 * Run with: php tests/Manual/DocuSealJwtGenerationTest.php
 */

// Autoload
require_once __DIR__ . '/../../vendor/autoload.php';

echo "Testing DocuSeal JWT Generation\n";
echo "===============================\n\n";

// Test JWT encoding
$apiKey = 'test-docuseal-api-key';

$payload = [
    'user_email' => 'test@example.com',
    'template_id' => 'template_123abc',
    'name' => 'Test Form Submission',
    'iat' => time(),
    'exp' => time() + (60 * 30), // 30 minutes
    'metadata' => [
        'order_id' => '123e4567-e89b-12d3-a456-426614174000',
        'order_number' => 'ORD-2025-001',
        'organization_id' => '987e6543-e21b-12d3-a456-426614174000',
    ]
];

echo "Payload:\n";
print_r($payload);
echo "\n";

// Generate JWT
$token = JWT::encode($payload, $apiKey, 'HS256');

echo "Generated JWT Token:\n";
echo $token . "\n\n";

// Decode to verify
try {
    $decoded = JWT::decode($token, new Key($apiKey, 'HS256'));
    echo "Decoded JWT:\n";
    print_r($decoded);
    echo "\n";
    
    echo "✅ JWT generation and decoding successful!\n";
} catch (\Exception $e) {
    echo "❌ Error decoding JWT: " . $e->getMessage() . "\n";
}

// Test invalid key
echo "\nTesting with invalid key:\n";
try {
    $decoded = JWT::decode($token, new Key('wrong-key', 'HS256'));
    echo "❌ Should have failed with invalid key!\n";
} catch (\Exception $e) {
    echo "✅ Correctly failed with invalid key: " . $e->getMessage() . "\n";
}

// Example API endpoint call
echo "\n\nExample API Endpoint Call:\n";
echo "POST /api/v1/admin/docuseal/generate-token\n";
echo "Headers:\n";
echo "  Authorization: Bearer <your-auth-token>\n";
echo "  Content-Type: application/json\n";
echo "Body:\n";
echo json_encode([
    'template_id' => 'template_123abc',
    'name' => 'Insurance Verification Form',
    'order_id' => '123e4567-e89b-12d3-a456-426614174000' // optional
], JSON_PRETTY_PRINT) . "\n";

echo "\nExpected Response:\n";
echo json_encode([
    'token' => '<jwt-token>',
    'expires_at' => date('c', time() + (60 * 30))
], JSON_PRETTY_PRINT) . "\n";