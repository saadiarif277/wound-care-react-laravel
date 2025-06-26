<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order\Manufacturer;
use App\Models\IVRTemplateField;
use App\Models\IVRFieldMapping;

// Your comprehensive field mappings
$ivrForms = [
    [
        "file" => "Q4 PM Coll-e-Derm IVR.pdf",
        "manufacturer" => "PM Coll-e-Derm", // Will need to map to actual manufacturer
        "fields" => [
            "Product Requested (2x2cm)",
            "Product Requested (2x3cm)",
            "Product Requested (2x4cm)",
            "Product Requested (4x4cm)",
            "Product Requested (4x6cm)",
            "Product Requested (4x8cm)",
            "Application Type (New Application)",
            "Application Type (Additional Application)",
            "Application Type (Re-verification)",
            "Application Type (New Insurance)",
            "Patient Name",
            "DOB",
            "Sex (Male/Female)",
            "Address",
            "City",
            "State",
            "Zip",
            "SNF Admission Status (Yes/No)",
            "SNF Days Admitted",
            "Primary Insurance",
            "Secondary Insurance",
            "Primary Payer Phone",
            "Secondary Payer Phone",
            "Primary Policy Number",
            "Secondary Policy Number",
            "Provider Name",
            "Provider NPI",
            "Provider Tax ID#",
            "Provider Medicare Provider #",
            "Facility Name",
            "Facility Address",
            "Facility City",
            "Facility State",
            "Facility Zip",
            "Facility NPI",
            "Facility Tax ID#",
            "Facility Contact Name",
            "Facility Contact Phone",
            "Facility Contact Fax",
            "Facility Contact Email",
            "HCPCS Code (Q4193)",
            "CPT (Legs/Arms/Trunk ≤100 sq cm)",
            "CPT (Legs/Arms/Trunk ≥100 sq cm)",
            "CPT (Feet/Hands/Head ≤100 sq cm)",
            "CPT (Feet/Hands/Head ≥100 sq cm)",
            "Anticipated Application Date",
            "Number of Anticipated Applications",
            "Diabetic Ulcer Code 1",
            "Diabetic Ulcer Code 2",
            "Venous Ulcer Code 1",
            "Venous Ulcer Code 2",
            "Surgical Dehiscence Code",
            "Other Condition Code 1",
            "Other Condition Code 2",
            "Pressure Ulcer Code 1",
            "Pressure Ulcer Code 2",
            "Trauma Wounds Code 1",
            "Trauma Wounds Code 2",
            "Place of Service (Physician Office/Clinic)",
            "Place of Service (Patient Home)",
            "Place of Service (Assisted Living Facility)",
            "Place of Service (Nursing Facility)",
            "Place of Service (Skilled Nursing Facility)",
            "Place of Service (Other)"
        ]
    ],
    [
        "file" => "Q2 CompleteFT IVR.pdf",
        "manufacturer" => "CompleteFT",
        "fields" => [
            "Product Requested (12 mm disc)",
            "Product Requested (16 mm disc)",
            "Product Requested (1.5×1.5 cm)",
            "Product Requested (2×2 cm)",
            "Product Requested (2×3 cm)",
            "Product Requested (2×4 cm)",
            "Product Requested (4×4 cm)",
            "Product Requested (4×6 cm)",
            "Product Requested (4×8 cm)",
            "Product Requested (5×5 cm)",
            "Application Type (New Application)",
            "Application Type (Additional Application)",
            "Application Type (Re-verification)",
            "Application Type (New Insurance)",
            "Patient Name",
            "DOB",
            "Sex (Male/Female)",
            "Address",
            "City",
            "State",
            "Zip",
            "SNF Admission Status (Yes/No)",
            "SNF Days Admitted",
            "Primary Insurance",
            "Secondary Insurance",
            "Primary Payer Phone",
            "Secondary Payer Phone",
            "Primary Policy Number",
            "Secondary Policy Number",
            "Provider Name",
            "Provider NPI",
            "Provider Tax ID#",
            "Provider Medicare Provider #",
            "Facility Name",
            "Facility Address",
            "Facility City",
            "Facility State",
            "Facility Zip",
            "Facility NPI",
            "Facility Tax ID#",
            "Facility Contact Name",
            "Facility Contact Phone",
            "Facility Contact Fax",
            "Facility Contact Email",
            "HCPCS Code (Q4271)",
            "CPT (Legs/Arms/Trunk ≤100 sq cm)",
            "CPT (Legs/Arms/Trunk ≥100 sq cm)",
            "CPT (Feet/Hands/Head ≤100 sq cm)",
            "CPT (Feet/Hands/Head ≥100 sq cm)",
            "Anticipated Application Date",
            "Number of Anticipated Applications",
            "Diabetic Ulcer Code 1",
            "Diabetic Ulcer Code 2",
            "Venous Ulcer Code 1",
            "Venous Ulcer Code 2",
            "Surgical Dehiscence Code",
            "Other Condition Code 1",
            "Other Condition Code 2",
            "Pressure Ulcer Code 1",
            "Pressure Ulcer Code 2",
            "Trauma Wounds Code 1",
            "Trauma Wounds Code 2",
            "Place of Service (Physician Office/Clinic)",
            "Place of Service (Patient Home)",
            "Place of Service (Assisted Living Facility)",
            "Place of Service (Nursing Facility)",
            "Place of Service (Skilled Nursing Facility)",
            "Place of Service (Other)"
        ]
    ],
    [
        "file" => "Updated Q2 IVR ACZ.pdf",
        "manufacturer" => "ACZ & ASSOCIATES", // Maps to ID 1
        "fields" => [
            "Treating Physician NPI",
            "Treating Physician Tax ID",
            "Treating Physician PTAN",
            "Treating Physician Medicaid #",
            "Treating Physician Phone",
            "Treating Physician Fax",
            "Management Co",
            "Physician Name (line entries)",
            "Facility Name (line entries)",
            "Place of Service (Physician Office)",
            "Place of Service (Hospital Outpatient)",
            "Place of Service (Surgery Center)",
            "Place of Service (Home)",
            "Place of Service (Nursing Care Facility)",
            "Place of Service (Other)",
            "Insurance Name 1",
            "Policy Number 1",
            "Payer Phone 1",
            "Provider Status 1 (In-Network/Out-of-Network)",
            "Insurance Name 2",
            "Policy Number 2",
            "Payer Phone 2",
            "Provider Status 2 (In-Network/Out-of-Network)",
            "Authorization Permission (Yes/No)",
            "Hospice Status (Yes/No)",
            "Part A Stay Status (Yes/No)",
            "Global Surgical Period Status (Yes/No)",
            "Previous Surgery CPT Codes",
            "Previous Surgery Date",
            "Wound Location (Legs/Arms/Trunk ≤100 sq cm)",
            "Wound Location (Legs/Arms/Trunk ≥100 sq cm)",
            "Wound Location (Feet/Hands/Head ≤100 sq cm)",
            "Wound Location (Feet/Hands/Head ≥100 sq cm)",
            "ICD-10 Codes",
            "Total Wound Size / Medical History",
            "Product Q4205 (Membrane Wrap)",
            "Product Q4289 (Revoshield)",
            "Product Q4313 (Dermabind)",
            "Product Q4275 (Esano aca)",
            "Representative Name",
            "ISO If Applicable",
            "Additional Notification Emails",
            "Patient Name",
            "Patient DOB",
            "Patient Address",
            "Patient Phone",
            "Patient Fax/Email",
            "Patient Caregiver Info"
        ]
    ],
    [
        "file" => "WoundPlus.Patient.Insurance.Verification.Form.September2023R1 (2) (1).pdf",
        "manufacturer" => "WoundPlus",
        "fields" => [
            "Facility Name",
            "Facility Address",
            "Facility Contact Name",
            "Facility Contact Phone",
            "Medicare Admin Contractor",
            "Medicare Admin Contractor Phone",
            "Physician Name",
            "Physician Address",
            "Physician Phone",
            "Physician Fax",
            "Physician NPI",
            "Physician TIN",
            "Physician PTAN",
            "Patient Name",
            "Patient Address",
            "Patient DOB",
            "Patient Phone",
            "OK to Contact Patient (Yes/No)",
            "Primary Insurance",
            "Secondary Insurance",
            "Subscriber Name 1",
            "Subscriber DOB 1",
            "Policy Number 1",
            "Subscriber Name 2",
            "Subscriber DOB 2",
            "Policy Number 2",
            "Wound Type (Diabetic Foot Ulcer)",
            "Wound Type (Venous Leg Ulcer)",
            "Wound Type (Pressure Ulcer)",
            "Wound Type (Traumatic Burns)",
            "Wound Type (Radiation Burns)",
            "Wound Type (Necrotizing Faciitis)",
            "Wound Type (Dehisced Surgical Wound)",
            "Other Wound Type",
            "Wound Size(s)",
            "Application CPT(s)",
            "Date of Procedure",
            "Anticipated Number of Applications",
            "HCPCS Code (Q4277)",
            "Authorized Signature",
            "Signature Date"
        ]
    ],
    [
        "file" => "Template IVR Advanced Solution Universal REV2.0 copy (2).pdf",
        "manufacturer" => "Advanced Solution", // Maps to ID 2
        "fields" => [
            // Already imported, but we'll check anyway
            "Sales Rep",
            "Place of Service (Office)",
            "Place of Service (Outpatient Hospital)",
            "Place of Service (Ambulatory Surgical Center)",
            "Place of Service (Other)",
            "Facility Name",
            "Facility Address",
            "Facility Contact Name",
            "Facility Contact Phone",
            "Facility Contact Fax",
            "Medicare Admin Contractor",
            "Facility NPI",
            "Facility TIN",
            "Facility PTAN",
            "Physician Name",
            "Physician Address",
            "Physician Phone",
            "Physician Fax",
            "Physician NPI",
            "Physician TIN",
            "Patient Name",
            "Patient Address",
            "Patient DOB",
            "Patient Phone",
            "OK to Contact Patient (Yes/No)",
            "Primary Insurance Subscriber Name",
            "Primary Insurance Policy Number",
            "Primary Insurance Subscriber DOB",
            "Primary Insurance Plan Type (HMO/PPO/Other)",
            "Primary Insurance Phone",
            "Primary Insurance Network Participation (Yes/No/Not Sure)",
            "Secondary Insurance Subscriber Name",
            "Secondary Insurance Policy Number",
            "Secondary Insurance Subscriber DOB",
            "Secondary Insurance Plan Type (HMO/PPO/Other)",
            "Secondary Insurance Phone",
            "Secondary Insurance Network Participation (Yes/No/Not Sure)",
            "Wound Type (Diabetic Foot Ulcer)",
            "Wound Type (Venous Leg Ulcer)",
            "Wound Type (Pressure Ulcer)",
            "Wound Type (Traumatic Burns)",
            "Wound Type (Radiation Burns)",
            "Wound Type (Necrotizing Faciitis)",
            "Wound Type (Dehisced Surgical Wound)",
            "Other Wound Type",
            "Wound Size(s)",
            "Application CPT(s)",
            "Date of Procedure",
            "ICD-10 Diagnosis Code(s)",
            "Product Information",
            "Prior Authorization Required (Yes/No)",
            "Clinical Notes Attached",
            "Physician Agreement Signature",
            "Agreement Date"
        ]
    ],
    [
        "file" => "AMNIO AMP MedLife IVR-fillable .pdf",
        "manufacturer" => "MedLife Solutions", // Maps to ID 7
        "fields" => [
            "Distributor / Company",
            "Practice Name",
            "Physician Name",
            "Practice PTAN",
            "Physician PTAN",
            "Physician NPI",
            "Practice NPI",
            "Tax ID",
            "Office Contact Name",
            "Office Contact Email",
            "Patient Name",
            "Patient DOB",
            "Primary Insurance",
            "Member ID",
            "Secondary Insurance",
            "Member ID 2",
            "Insurance Card Attached (Yes/No)",
            "Place of Service (Office)",
            "Place of Service (Home)",
            "Place of Service (Assisted Living)",
            "Place of Service (Other)",
            "SNF Status (Yes/No)",
            "Days in SNF",
            "Post-op Period Status (Yes/No)",
            "Previous Surgery CPT Codes",
            "Previous Surgery Date",
            "Procedure Date",
            "Wound Location",
            "Size of Graft Requested",
            "ICD-10 Code 1",
            "ICD-10 Code 2",
            "ICD-10 Code 3",
            "ICD-10 Code 4"
        ]
    ],
    [
        "file" => "Centurion AmnioBand IVR (Only used for STAT IVRS after hours).pdf",
        "manufacturer" => "CENTURION THERAPEUTICS", // Maps to ID 3
        "fields" => [
            "Provider Name",
            "Facility Name",
            "Physician NPI",
            "Practice NPI",
            "Tax ID",
            "Office Contact Name",
            "Office Contact Email",
            "Patient Name",
            "Patient DOB",
            "Primary Insurance",
            "Member ID",
            "Secondary Insurance",
            "Member ID 2",
            "Insurance Card Attached (Yes/No)",
            "Place of Service (Office)",
            "Place of Service (Home)",
            "Place of Service (Assisted Living)",
            "Place of Service (Other)",
            "SNF Status (Yes/No)",
            "SNF Over 100 Days (Yes/No)",
            "Post-op Period Status (Yes/No)",
            "Previous Surgery CPT Codes",
            "Surgery Date",
            "Procedure Date",
            "Wound Size (L)",
            "Wound Size (W)",
            "Wound Size (Total)",
            "Wound Location",
            "Graft Size Requested",
            "ICD-10 Code 1",
            "CPT 1",
            "HCPCS 1",
            "ICD-10 Code 2",
            "CPT 2",
            "HCPCS 2",
            "ICD-10 Code 3",
            "CPT 3",
            "HCPCS 3",
            "ICD-10 Code 4",
            "CPT 4",
            "HCPCS 4"
        ]
    ],
    [
        "file" => "BioWerX Fillable IVR Apr 2024.pdf",
        "manufacturer" => "BioWerX",
        "fields" => [
            "Fax Number",
            "Email",
            "New Request (Yes/No)",
            "Re-verification (Yes/No)",
            "Additional Applications (Yes/No)",
            "New Insurance (Yes/No)",
            "Primary Insurance Payer Name",
            "Secondary Insurance Payer Name",
            "Primary Policy Number",
            "Secondary Policy Number",
            "Primary Payer Phone",
            "Secondary Payer Phone",
            "Product (Q4204 XWRAP)",
            "Wound Type (Diabetic Foot Ulcer)",
            "Wound Type (Venous Leg Ulcer)",
            "Wound Type (Chronic Ulcer)",
            "Wound Type (Dehisced Surgical Wound)",
            "Wound Location",
            "Wound Duration",
            "Facility Name",
            "Physician Name",
            "Facility NPI",
            "Facility Tax ID",
            "Facility PTAN",
            "Patient Name",
            "Patient DOB",
            "Patient Address",
            "SNF Status (Yes/No)",
            "Days in SNF",
            "Global Period Status (Yes/No)",
            "Repeat/Other Fields"
        ]
    ],
    [
        "file" => "BioWound IVR v3 (2).pdf",
        "manufacturer" => "BIOWOUND SOLUTIONS", // Maps to ID 3
        "fields" => [
            "Primary Insurance Payer Name",
            "Secondary Insurance Payer Name",
            "Primary Policy Number",
            "Secondary Policy Number",
            "Primary Payer Phone",
            "Secondary Payer Phone",
            "Wound Type (Q4205 Membrane Wrap)",
            "Wound Type (Q4238 Derm-Maxx)",
            "Wound Type (Q4161 Bio-Connekt)",
            "Wound Type (Q4267 NeoStim DL)",
            "Wound Type (Q4266 NeoStim SL)",
            "Wound Type (Q4265 NeoStim TL)",
            "Wound Type (Q4239 Amnio-maxx)",
            "SNF Status (Yes/No)",
            "Global Period Status (Yes/No)",
            "ICD-10 Codes",
            "Procedure CPT Codes",
            "Wound Size (Total)",
            "Facility Name",
            "Facility Address",
            "Facility NPI",
            "Facility Tax ID",
            "Facility PTAN",
            "Physician Name",
            "Physician Specialty",
            "Physician NPI",
            "Patient Name",
            "Patient DOB",
            "Patient Address",
            "Patient Phone",
            "Authorized Signature",
            "Signature Date"
        ]
    ],
    [
        "file" => "California-Non-HOPD-IVR-Form.pdf",
        "manufacturer" => "California Non-HOPD",
        "fields" => [
            "Patient Name",
            "DOB",
            "Sex (Male/Female)",
            "Address",
            "City",
            "State",
            "Zip",
            "Home Phone",
            "Mobile",
            "SNF Status (Yes/No)",
            "SNF Days Admitted",
            "Primary Insurance",
            "Secondary Insurance",
            "Primary Payer Phone",
            "Secondary Payer Phone",
            "Primary Policy Number",
            "Secondary Policy Number",
            "Primary Subscriber Name",
            "Secondary Subscriber Name",
            "Specialty",
            "Provider NPI",
            "Provider Tax ID",
            "Medicaid Provider #",
            "Facility Name",
            "Facility Address",
            "Facility City",
            "Facility State",
            "Facility Zip",
            "Facility NPI",
            "Facility Tax ID",
            "Facility Contact Name",
            "Facility Contact Phone",
            "Facility Contact Fax",
            "Treatment Setting (HOPD)",
            "Treatment Setting (Provider's Office)",
            "Anticipated Treatment Start Date",
            "Frequency",
            "Number of Applications",
            "Prior Authorization Assistance (Yes/No)",
            "Clinical Notes Attached",
            "Known Conditions",
            "Provider Signature",
            "Signature Date"
        ]
    ],
    [
        "file" => "Universal_Benefits_Verification_April_23_V2 (1).pdf",
        "manufacturer" => "Universal Benefits",
        "fields" => [
            "Fax Number",
            "Practice Name",
            "Member ID 1",
            "Member ID 2",
            "Patient DOB",
            "Patient ID",
            "Physician Name",
            "Physician PTAN",
            "Physician NPI",
            "Office Contact Name",
            "Office Contact Email",
            "SNF Status (Yes/No)",
            "SNF Over 100 Days (Yes/No)",
            "Wound 1 (L)",
            "Wound 1 (W)",
            "Wound 1 (Total)",
            "Wound 2 (L)",
            "Wound 2 (W)",
            "Wound 2 (Total)",
            "Wound 3 (L)",
            "Wound 3 (W)",
            "Wound 3 (Total)",
            "Procedure Date",
            "Treatment Start Date",
            "ICD-10 Code 1",
            "ICD-10 Code 2",
            "ICD-10 Code 3",
            "ICD-10 Code 4",
            "HCPCS Codes",
            "CPT Application Codes",
            "Product Type (Skin Substitute)",
            "Product Type (Wound Supply Kits)"
        ]
    ],
    [
        "file" => "Imbed Pior Auth Intake_12232024.pdf",
        "manufacturer" => "Imbed Microlyte Matrix", // Maps to ID 6
        "fields" => [
            "Date of Birth",
            "Tax ID",
            "Distributor Name / Account Manager",
            "Practice Name",
            "Patient Address",
            "Patient Phone",
            "Patient NPI",
            "Patient Tax ID",
            "Primary Insurance Payer Name",
            "Primary Payer Phone",
            "Primary Policy Number",
            "Secondary Insurance Payer Name",
            "Secondary Payer Phone",
            "Secondary Policy Number",
            "Application CPT 15271",
            "Application CPT 15272",
            "Application CPT 15273",
            "Application CPT 15274",
            "Application CPT 15275",
            "Application CPT 15276",
            "Application CPT 15277",
            "Application CPT 15278",
            "Procedure Date",
            "Number of Units 4×4",
            "Number of Units 5×5",
            "Number of Units 10×10",
            "Number of Units 3×3",
            "Number of Units 1.6 mm Disc",
            "Number of Units 2×2",
            "Primary Diagnosis Code",
            "Secondary Diagnosis Code",
            "Clinical Study Participation (Yes/No)",
            "Clinical Notes Attached"
        ]
    ]
];

// Map manufacturer names to database IDs
$manufacturerMap = [
    'ACZ & ASSOCIATES' => 1,
    'Advanced Solution' => 2,
    'CENTURION THERAPEUTICS' => 4, // Corrected ID
    'Centurion Therapeutics' => 4, // Alternative name
    'BIOWOUND SOLUTIONS' => 4, // Using Centurion ID
    'Imbed Microlyte Matrix' => 3, // IMBED
    'IMBED' => 3,
    // These manufacturers don't exist in the database yet
    // 'PM Coll-e-Derm' => null,
    // 'CompleteFT' => null,
    // 'WoundPlus' => null,
    // 'MedLife Solutions' => null,
    // 'BioWerX' => null,
    // 'California Non-HOPD' => null,
    // 'Universal Benefits' => null,
];

echo "Starting comprehensive IVR field import...\n\n";

$totalImported = 0;
$totalSkipped = 0;

foreach ($ivrForms as $form) {
    $manufacturerName = $form['manufacturer'];
    $fileName = $form['file'];
    
    echo "Processing: {$fileName} for {$manufacturerName}\n";
    
    // Find or create manufacturer
    $manufacturerId = null;
    if (isset($manufacturerMap[$manufacturerName])) {
        $manufacturerId = $manufacturerMap[$manufacturerName];
    } else {
        // Try to find by name
        $manufacturer = Manufacturer::where('name', 'like', '%' . $manufacturerName . '%')->first();
        if ($manufacturer) {
            $manufacturerId = $manufacturer->id;
        } else {
            echo "  ⚠️  Manufacturer '{$manufacturerName}' not found, skipping...\n\n";
            continue;
        }
    }
    
    $imported = 0;
    $skipped = 0;
    $order = 0;
    
    foreach ($form['fields'] as $fieldName) {
        try {
            // Use raw query to check existence
            $exists = \DB::table('ivr_template_fields')
                ->where('manufacturer_id', $manufacturerId)
                ->where('template_name', 'insurance-verification')
                ->where('field_name', $fieldName)
                ->exists();
                
            if ($exists) {
                $skipped++;
                continue;
            }
            
            // Determine field type
            $fieldType = detectFieldType($fieldName);
            $section = detectSection($fieldName);
            $isRequired = isLikelyRequired($fieldName);
            
            // Create using raw insert
            \DB::table('ivr_template_fields')->insert([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'manufacturer_id' => $manufacturerId,
                'template_name' => 'insurance-verification',
                'field_name' => $fieldName,
                'field_type' => $fieldType,
                'is_required' => $isRequired,
                'field_order' => $order++,
                'section' => $section,
                'field_metadata' => json_encode([
                    'source_file' => $fileName,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $imported++;
            
        } catch (\Exception $e) {
            echo "  ✗ Error importing {$fieldName}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "  ✓ Imported: {$imported} fields\n";
    echo "  - Skipped: {$skipped} fields\n\n";
    
    $totalImported += $imported;
    $totalSkipped += $skipped;
}

echo "Import complete!\n";
echo "Total imported: {$totalImported}\n";
echo "Total skipped: {$totalSkipped}\n\n";

// Now create common field mappings
echo "Creating common FHIR field mappings...\n";

$commonMappings = [
    // Patient mappings
    ['fhir_path' => 'patient.name', 'variations' => ['Patient Name', 'Patient', 'Name']],
    ['fhir_path' => 'patient.birthDate', 'variations' => ['DOB', 'Patient DOB', 'Date of Birth']],
    ['fhir_path' => 'patient.gender', 'variations' => ['Sex (Male/Female)', 'Gender']],
    ['fhir_path' => 'patient.address', 'variations' => ['Address', 'Patient Address']],
    ['fhir_path' => 'patient.address.city', 'variations' => ['City']],
    ['fhir_path' => 'patient.address.state', 'variations' => ['State']],
    ['fhir_path' => 'patient.address.postalCode', 'variations' => ['Zip']],
    ['fhir_path' => 'patient.telecom.phone', 'variations' => ['Patient Phone', 'Phone', 'Home Phone', 'Mobile']],
    
    // Provider mappings
    ['fhir_path' => 'practitioner.name', 'variations' => ['Provider Name', 'Physician Name', 'Treating Physician']],
    ['fhir_path' => 'practitioner.identifier.npi', 'variations' => ['Provider NPI', 'Physician NPI', 'Treating Physician NPI', 'NPI']],
    ['fhir_path' => 'practitioner.identifier.tin', 'variations' => ['Provider Tax ID#', 'Tax ID', 'Physician TIN', 'Treating Physician Tax ID']],
    ['fhir_path' => 'practitioner.identifier.ptan', 'variations' => ['Provider Medicare Provider #', 'Physician PTAN', 'Treating Physician PTAN', 'Practice PTAN']],
    ['fhir_path' => 'practitioner.telecom.phone', 'variations' => ['Physician Phone', 'Provider Phone', 'Treating Physician Phone']],
    ['fhir_path' => 'practitioner.telecom.fax', 'variations' => ['Physician Fax', 'Provider Fax', 'Treating Physician Fax']],
    
    // Facility mappings
    ['fhir_path' => 'organization.name', 'variations' => ['Facility Name', 'Practice Name', 'Clinic Name']],
    ['fhir_path' => 'organization.address', 'variations' => ['Facility Address', 'Practice Address']],
    ['fhir_path' => 'organization.identifier.npi', 'variations' => ['Facility NPI', 'Practice NPI']],
    ['fhir_path' => 'organization.identifier.tin', 'variations' => ['Facility Tax ID#', 'Facility TIN']],
    ['fhir_path' => 'organization.identifier.ptan', 'variations' => ['Facility PTAN']],
    
    // Insurance mappings
    ['fhir_path' => 'coverage.payor.display', 'variations' => ['Primary Insurance', 'Primary Insurance Payer Name', 'Insurance Name 1']],
    ['fhir_path' => 'coverage.identifier.value', 'variations' => ['Primary Policy Number', 'Policy Number 1', 'Policy Number', 'Member ID']],
    ['fhir_path' => 'coverage.subscriber.display', 'variations' => ['Primary Subscriber Name', 'Subscriber Name 1']],
    
    // Clinical mappings
    ['fhir_path' => 'condition.code.coding.code', 'variations' => ['ICD-10 Codes', 'ICD-10 Code 1', 'Primary Diagnosis Code', 'Diabetic Ulcer Code 1']],
    ['fhir_path' => 'procedure.performedDateTime', 'variations' => ['Date of Procedure', 'Procedure Date', 'Anticipated Application Date']],
];

$mappingsCreated = 0;

foreach ($commonMappings as $mapping) {
    foreach ($mapping['variations'] as $fieldVariation) {
        // Find all template fields with this name
        // Find all template fields with this name using raw query
        $templateFields = \DB::table('ivr_template_fields')
            ->where('field_name', $fieldVariation)
            ->get();
        
        foreach ($templateFields as $field) {
            try {
                // Check if mapping already exists
                $exists = \DB::table('ivr_field_mappings')
                    ->where('manufacturer_id', $field->manufacturer_id)
                    ->where('template_name', $field->template_name)
                    ->where('ivr_field_name', $field->field_name)
                    ->where('fhir_path', $mapping['fhir_path'])
                    ->exists();
                    
                if (!$exists) {
                    \DB::table('ivr_field_mappings')->insert([
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'manufacturer_id' => $field->manufacturer_id,
                        'template_name' => $field->template_name,
                        'fhir_path' => $mapping['fhir_path'],
                        'ivr_field_name' => $field->field_name,
                        'mapping_type' => 'exact',
                        'confidence_score' => 0.95,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $mappingsCreated++;
                }
            } catch (\Exception $e) {
                echo "  ⚠️ Error creating mapping for {$fieldVariation}: " . $e->getMessage() . "\n";
            }
        }
    }
}

echo "Created {$mappingsCreated} field mappings\n\n";
echo "Done!\n";

// Helper functions
function detectFieldType(string $fieldName): string
{
    $fieldName = strtolower($fieldName);
    
    if (strpos($fieldName, 'date') !== false || strpos($fieldName, 'dob') !== false) {
        return 'date';
    }
    if (strpos($fieldName, 'phone') !== false || strpos($fieldName, 'fax') !== false) {
        return 'phone';
    }
    if (strpos($fieldName, 'email') !== false) {
        return 'email';
    }
    if (strpos($fieldName, 'signature') !== false) {
        return 'signature';
    }
    if (preg_match('/\(yes\/no\)/i', $fieldName) || strpos($fieldName, 'checkbox') !== false) {
        return 'checkbox';
    }
    if (strpos($fieldName, 'address') !== false && strpos($fieldName, 'email') === false) {
        return 'address';
    }
    if (preg_match('/\(\d+x\d+/', $fieldName) || strpos($fieldName, 'product requested') !== false) {
        return 'checkbox';
    }
    
    return 'text';
}

function detectSection(string $fieldName): string
{
    $fieldName = strtolower($fieldName);
    
    if (strpos($fieldName, 'patient') !== false) return 'patient_information';
    if (strpos($fieldName, 'provider') !== false || strpos($fieldName, 'physician') !== false) return 'provider_information';
    if (strpos($fieldName, 'insurance') !== false || strpos($fieldName, 'policy') !== false || strpos($fieldName, 'payer') !== false) return 'insurance_information';
    if (strpos($fieldName, 'wound') !== false || strpos($fieldName, 'diagnosis') !== false || strpos($fieldName, 'icd') !== false) return 'clinical_information';
    if (strpos($fieldName, 'facility') !== false || strpos($fieldName, 'practice') !== false) return 'facility_information';
    if (strpos($fieldName, 'signature') !== false || strpos($fieldName, 'consent') !== false) return 'authorization';
    if (strpos($fieldName, 'product') !== false || strpos($fieldName, 'hcpcs') !== false) return 'product_selection';
    if (strpos($fieldName, 'place of service') !== false || strpos($fieldName, 'pos') !== false) return 'service_location';
    
    return 'other';
}

function isLikelyRequired(string $fieldName): bool
{
    $requiredPatterns = [
        'patient_name', 'patient name', 'patient_dob', 'dob',
        'provider_name', 'physician name', 'provider_npi', 'npi',
        'insurance', 'policy', 'diagnosis', 'signature', 'date'
    ];
    
    $fieldName = strtolower($fieldName);
    foreach ($requiredPatterns as $pattern) {
        if (strpos($fieldName, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}