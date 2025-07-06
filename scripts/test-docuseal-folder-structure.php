<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing DocuSeal Folder Structure\n";
echo "===================================\n\n";

$apiKey = config('services.docuseal.api_key');
$apiUrl = config('services.docuseal.api_url');

echo "🔑 API Key: " . substr($apiKey, 0, 10) . "... (" . strlen($apiKey) . " chars)\n";
echo "🌐 API URL: {$apiUrl}\n\n";

// Function to make DocuSeal API calls
function makeDocusealRequest($endpoint, $apiKey, $apiUrl) {
    echo "📞 Testing endpoint: {$endpoint}\n";
    
    $response = Http::withHeaders([
        'X-Auth-Token' => $apiKey,
        'Content-Type' => 'application/json',
    ])->get("{$apiUrl}{$endpoint}");
    
    echo "   Status: {$response->status()}\n";
    
    if ($response->successful()) {
        $data = $response->json();
        echo "   ✅ Success!\n";
        return $data;
    } else {
        echo "   ❌ Failed: {$response->body()}\n";
        return null;
    }
}

try {
    // 1. List all folders/directories
    echo "1️⃣ Exploring DocuSeal Folders/Directories...\n";
    $folders = makeDocusealRequest('/folders', $apiKey, $apiUrl);
    
    if ($folders) {
        echo "📁 Found " . count($folders) . " folders:\n";
        foreach ($folders as $index => $folder) {
            echo "   [{$index}] " . json_encode($folder, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        }
        echo "\n";
    }
    
    // 2. List templates with folder information
    echo "2️⃣ Exploring Templates with Folder Info...\n";
    $templates = makeDocusealRequest('/templates', $apiKey, $apiUrl);
    
    if ($templates) {
        echo "📋 Found " . count($templates) . " templates:\n";
        foreach ($templates as $index => $template) {
            echo "   [{$index}] Template Details:\n";
            echo "      ID: " . ($template['id'] ?? 'N/A') . "\n";
            echo "      Name: " . ($template['name'] ?? 'N/A') . "\n";
            echo "      Folder: " . ($template['folder'] ?? $template['folder_id'] ?? $template['directory'] ?? 'Root') . "\n";
            echo "      Created: " . ($template['created_at'] ?? 'N/A') . "\n";
            echo "      Status: " . ($template['status'] ?? 'N/A') . "\n";
            
            // Show full structure for debugging
            echo "      Full Data: " . json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            echo "      ---\n";
        }
        echo "\n";
    }
    
    // 3. Try to find manufacturer-specific folders
    echo "3️⃣ Looking for Manufacturer Folders...\n";
    $manufacturers = ['MEDLIFE SOLUTIONS', 'Centurion', 'MedLife', 'medlife'];
    
    if ($folders) {
        foreach ($manufacturers as $manufacturer) {
            echo "🔍 Looking for '{$manufacturer}' folder...\n";
            
            foreach ($folders as $folder) {
                $folderName = $folder['name'] ?? $folder['title'] ?? '';
                if (stripos($folderName, $manufacturer) !== false) {
                    echo "   ✅ Found potential match: {$folderName}\n";
                    echo "      Folder ID: " . ($folder['id'] ?? 'N/A') . "\n";
                    echo "      Full Data: " . json_encode($folder, JSON_PRETTY_PRINT) . "\n";
                    
                    // Try to get templates in this folder
                    $folderId = $folder['id'] ?? null;
                    if ($folderId) {
                        echo "   📋 Getting templates in folder {$folderId}...\n";
                        $folderTemplates = makeDocusealRequest("/folders/{$folderId}/templates", $apiKey, $apiUrl);
                        
                        if ($folderTemplates) {
                            echo "      Found " . count($folderTemplates) . " templates in this folder:\n";
                            foreach ($folderTemplates as $template) {
                                echo "         - ID: " . ($template['id'] ?? 'N/A') . 
                                     ", Name: " . ($template['name'] ?? 'N/A') . "\n";
                            }
                        }
                    }
                }
            }
        }
    }
    
    // 4. Try alternative endpoints
    echo "4️⃣ Trying Alternative Endpoints...\n";
    
    $alternativeEndpoints = [
        '/account',
        '/account/folders',
        '/account/templates',
        '/templates?include_folders=true',
        '/templates?expand=folder',
    ];
    
    foreach ($alternativeEndpoints as $endpoint) {
        $data = makeDocusealRequest($endpoint, $apiKey, $apiUrl);
        if ($data) {
            echo "   Data structure: " . json_encode(array_keys($data), JSON_PRETTY_PRINT) . "\n";
        }
        echo "\n";
    }
    
    // 5. Search for our specific template ID
    echo "5️⃣ Looking for Template ID '1233913' (MedLife Solutions)...\n";
    
    if ($templates) {
        foreach ($templates as $template) {
            if (($template['id'] ?? '') === '1233913' || ($template['id'] ?? '') === 1233913) {
                echo "   ✅ Found target template!\n";
                echo "      Full Details: " . json_encode($template, JSON_PRETTY_PRINT) . "\n";
                break;
            }
        }
    }
    
    // Try direct template access
    echo "   🎯 Trying direct access to template 1233913...\n";
    $directTemplate = makeDocusealRequest('/templates/1233913', $apiKey, $apiUrl);
    if ($directTemplate) {
        echo "      ✅ Direct access successful!\n";
        echo "      Template Details: " . json_encode($directTemplate, JSON_PRETTY_PRINT) . "\n";
    }

} catch (Exception $e) {
    echo "❌ Test failed with exception:\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "🎉 DocuSeal Folder Structure Exploration Complete!\n";
