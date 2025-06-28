<?php

// Direct update script for MedLife field mappings

$host = 'msc-stage-db.mysql.database.azure.com';
$port = 3306;
$database = 'msc-dev-rv';
$username = 'mscstagedb';
$password = 'B@xter1123$$!';

try {
    // Create connection
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Connected to database successfully\n\n";
    
    // First, find the MedLife manufacturer
    $stmt = $pdo->prepare("SELECT * FROM manufacturers WHERE name LIKE '%MEDLIFE%'");
    $stmt->execute();
    $manufacturer = $stmt->fetch();
    
    if (!$manufacturer) {
        echo "MedLife manufacturer not found!\n";
        exit(1);
    }
    
    echo "Found manufacturer: {$manufacturer['name']} (ID: {$manufacturer['id']})\n\n";
    
    // Find DocuSeal templates for MedLife
    $stmt = $pdo->prepare("SELECT * FROM docuseal_templates WHERE manufacturer_id = ?");
    $stmt->execute([$manufacturer['id']]);
    $templates = $stmt->fetchAll();
    
    echo "Found " . count($templates) . " templates for MedLife:\n";
    foreach ($templates as $template) {
        echo "  - {$template['template_name']} (ID: {$template['id']}, DocuSeal ID: {$template['docuseal_template_id']})\n";
    }
    echo "\n";
    
    // Update field mappings for IVR template
    $ivrMappings = [
        // Product specific fields
        'amnio_amp_size' => ['source' => 'amnio_amp_size', 'type' => 'radio'],
        
        // Patient fields
        'patient_name' => ['source' => 'patient_name', 'type' => 'text'],
        'patient_first_name' => ['source' => 'patient_first_name', 'type' => 'text'],
        'patient_last_name' => ['source' => 'patient_last_name', 'type' => 'text'],
        'patient_dob' => ['source' => 'patient_dob', 'type' => 'date'],
        'patient_gender' => ['source' => 'patient_gender', 'type' => 'radio'],
        
        // Provider fields
        'physician_name' => ['source' => 'physician_name', 'type' => 'text'],
        'physician_npi' => ['source' => 'physician_npi', 'type' => 'text'],
        'provider_name' => ['source' => 'provider_name', 'type' => 'text'],
        'provider_npi' => ['source' => 'provider_npi', 'type' => 'text'],
        
        // Facility fields
        'facility_name' => ['source' => 'facility_name', 'type' => 'text'],
        'facility_address' => ['source' => 'facility_address', 'type' => 'text'],
        'facility_city' => ['source' => 'facility_city', 'type' => 'text'],
        'facility_state' => ['source' => 'facility_state', 'type' => 'text'],
        'facility_zip' => ['source' => 'facility_zip', 'type' => 'text'],
        
        // Clinical fields
        'wound_location' => ['source' => 'wound_location', 'type' => 'text'],
        'wound_size' => ['source' => 'wound_size', 'type' => 'text'],
        'wound_length' => ['source' => 'wound_length', 'type' => 'text'],
        'wound_width' => ['source' => 'wound_width', 'type' => 'text'],
        'wound_depth' => ['source' => 'wound_depth', 'type' => 'text'],
        'diagnosis_code' => ['source' => 'diagnosis_code', 'type' => 'text'],
        'icd10_code' => ['source' => 'icd10_code', 'type' => 'text'],
        
        // Insurance fields
        'insurance_name' => ['source' => 'insurance_name', 'type' => 'text'],
        'insurance_id' => ['source' => 'insurance_id', 'type' => 'text'],
        'policy_number' => ['source' => 'policy_number', 'type' => 'text'],
        'group_number' => ['source' => 'group_number', 'type' => 'text'],
    ];
    
    // Update IVR template
    $stmt = $pdo->prepare("
        UPDATE docuseal_templates 
        SET field_mappings = ? 
        WHERE manufacturer_id = ? 
        AND document_type = 'IVR'
    ");
    $stmt->execute([json_encode($ivrMappings), $manufacturer['id']]);
    
    echo "Updated IVR template field mappings\n";
    
    // Update Order Form template mappings
    $orderFormMappings = [
        // Product fields
        'amnio_amp_size' => ['source' => 'amnio_amp_size', 'type' => 'radio'],
        'product_size' => ['source' => 'amnio_amp_size', 'type' => 'radio'],
        
        // Order fields
        'order_date' => ['source' => 'order_date', 'type' => 'date'],
        'requested_delivery_date' => ['source' => 'requested_delivery_date', 'type' => 'date'],
        'po_number' => ['source' => 'po_number', 'type' => 'text'],
        
        // Provider signature
        'provider_signature' => ['source' => 'provider_signature', 'type' => 'signature'],
        'signature_date' => ['source' => 'signature_date', 'type' => 'date'],
    ];
    
    $stmt = $pdo->prepare("
        UPDATE docuseal_templates 
        SET field_mappings = ? 
        WHERE manufacturer_id = ? 
        AND document_type = 'OrderForm'
    ");
    $stmt->execute([json_encode($orderFormMappings), $manufacturer['id']]);
    
    echo "Updated Order Form template field mappings\n\n";
    
    // Verify the update
    $stmt = $pdo->prepare("SELECT * FROM docuseal_templates WHERE manufacturer_id = ?");
    $stmt->execute([$manufacturer['id']]);
    $updatedTemplates = $stmt->fetchAll();
    
    echo "Verification - Updated templates:\n";
    foreach ($updatedTemplates as $template) {
        $mappings = json_decode($template['field_mappings'], true);
        echo "\n{$template['template_name']}:\n";
        echo "  - Total mappings: " . count($mappings) . "\n";
        echo "  - Has amnio_amp_size: " . (isset($mappings['amnio_amp_size']) ? 'YES' : 'NO') . "\n";
        if (isset($mappings['amnio_amp_size'])) {
            echo "  - amnio_amp_size mapping: " . json_encode($mappings['amnio_amp_size']) . "\n";
        }
    }
    
    echo "\n✅ Field mappings updated successfully!\n";
    echo "\nThe amnio_amp_size field should now be properly mapped for MedLife forms.\n";
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}