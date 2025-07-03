<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order\Manufacturer;
use App\Models\IVRFieldMapping;
use Illuminate\Support\Facades\DB;

/**
 * Import real IVR field mappings from actual Docuseal templates
 * This fixes the "filling out so little" issue by providing comprehensive field mappings
 */

// Real IVR field data from production templates
$ivrFieldData = [
    [
        'template_name' => 'Q4 PM Coll-e-Derm IVR',
        'manufacturer_name' => 'Q4 PM',
        'fields' => [
            'Product Requested (2x2cm)', 'Product Requested (2x3cm)', 'Product Requested (2x4cm)',
            'Product Requested (4x4cm)', 'Product Requested (4x6cm)', 'Product Requested (4x8cm)',
            'Application Type (New Application)', 'Application Type (Additional Application)',
            'Application Type (Re-verification)', 'Application Type (New Insurance)',
            'Patient Name', 'DOB', 'Sex (Male/Female)', 'Address', 'City', 'State', 'Zip',
            'SNF Admission Status (Yes/No)', 'SNF Days Admitted',
            'Primary Insurance', 'Secondary Insurance', 'Primary Payer Phone', 'Secondary Payer Phone',
            'Primary Policy Number', 'Secondary Policy Number',
            'Provider Name', 'Provider NPI', 'Provider Tax ID#', 'Provider Medicare Provider #',
            'Facility Name', 'Facility Address', 'Facility City', 'Facility State', 'Facility Zip',
            'Facility NPI', 'Facility Tax ID#', 'Facility Contact Name', 'Facility Contact Phone',
            'Facility Contact Fax', 'Facility Contact Email',
            'HCPCS Code (Q4193)', 'CPT (Legs/Arms/Trunk ≤100 sq cm)', 'CPT (Legs/Arms/Trunk ≥100 sq cm)',
            'CPT (Feet/Hands/Head ≤100 sq cm)', 'CPT (Feet/Hands/Head ≥100 sq cm)',
            'Anticipated Application Date', 'Number of Anticipated Applications',
            'Diabetic Ulcer Code 1', 'Diabetic Ulcer Code 2', 'Venous Ulcer Code 1', 'Venous Ulcer Code 2',
            'Surgical Dehiscence Code', 'Other Condition Code 1', 'Other Condition Code 2',
            'Pressure Ulcer Code 1', 'Pressure Ulcer Code 2', 'Trauma Wounds Code 1', 'Trauma Wounds Code 2',
            'Place of Service (Physician Office/Clinic)', 'Place of Service (Patient Home)',
            'Place of Service (Assisted Living Facility)', 'Place of Service (Nursing Facility)',
            'Place of Service (Skilled Nursing Facility)', 'Place of Service (Other)'
        ]
    ],
    [
        'template_name' => 'ACZ Associates IVR',
        'manufacturer_name' => 'ACZ & Associates',
        'fields' => [
            'Treating Physician NPI', 'Treating Physician Tax ID', 'Treating Physician PTAN',
            'Treating Physician Medicaid #', 'Treating Physician Phone', 'Treating Physician Fax',
            'Management Co', 'Physician Name (line entries)', 'Facility Name (line entries)',
            'Place of Service (Physician Office)', 'Place of Service (Hospital Outpatient)',
            'Place of Service (Surgery Center)', 'Place of Service (Home)',
            'Place of Service (Nursing Care Facility)', 'Place of Service (Other)',
            'Insurance Name 1', 'Policy Number 1', 'Payer Phone 1',
            'Provider Status 1 (In-Network/Out-of-Network)',
            'Insurance Name 2', 'Policy Number 2', 'Payer Phone 2',
            'Provider Status 2 (In-Network/Out-of-Network)',
            'Authorization Permission (Yes/No)', 'Hospice Status (Yes/No)',
            'Part A Stay Status (Yes/No)', 'Global Surgical Period Status (Yes/No)',
            'Previous Surgery CPT Codes', 'Previous Surgery Date',
            'Wound Location (Legs/Arms/Trunk ≤100 sq cm)', 'Wound Location (Legs/Arms/Trunk ≥100 sq cm)',
            'Wound Location (Feet/Hands/Head ≤100 sq cm)', 'Wound Location (Feet/Hands/Head ≥100 sq cm)',
            'ICD-10 Codes', 'Total Wound Size / Medical History',
            'Product Q4205 (Membrane Wrap)', 'Product Q4289 (Revoshield)',
            'Product Q4313 (Dermabind)', 'Product Q4275 (Esano aca)',
            'Representative Name', 'ISO If Applicable', 'Additional Notification Emails',
            'Patient Name', 'Patient DOB', 'Patient Address', 'Patient Phone',
            'Patient Fax/Email', 'Patient Caregiver Info'
        ]
    ],
    [
        'template_name' => 'Advanced Solution Universal IVR',
        'manufacturer_name' => 'Advanced Solution',
        'fields' => [
            'Sales Rep', 'Place of Service (Office)', 'Place of Service (Outpatient Hospital)',
            'Place of Service (Ambulatory Surgical Center)', 'Place of Service (Other)',
            'Facility Name', 'Facility Address', 'Facility Contact Name', 'Facility Contact Phone',
            'Facility Contact Fax', 'Medicare Admin Contractor', 'Facility NPI', 'Facility TIN',
            'Facility PTAN', 'Physician Name', 'Physician Address', 'Physician Phone',
            'Physician Fax', 'Physician NPI', 'Physician TIN', 'Patient Name', 'Patient Address',
            'Patient DOB', 'Patient Phone', 'OK to Contact Patient (Yes/No)',
            'Primary Insurance Subscriber Name', 'Primary Insurance Policy Number',
            'Primary Insurance Subscriber DOB', 'Primary Insurance Plan Type (HMO/PPO/Other)',
            'Primary Insurance Phone', 'Primary Insurance Network Participation (Yes/No/Not Sure)',
            'Secondary Insurance Subscriber Name', 'Secondary Insurance Policy Number',
            'Secondary Insurance Subscriber DOB', 'Secondary Insurance Plan Type (HMO/PPO/Other)',
            'Secondary Insurance Phone', 'Secondary Insurance Network Participation (Yes/No/Not Sure)',
            'Wound Type (Diabetic Foot Ulcer)', 'Wound Type (Venous Leg Ulcer)',
            'Wound Type (Pressure Ulcer)', 'Wound Type (Traumatic Burns)',
            'Wound Type (Radiation Burns)', 'Wound Type (Necrotizing Faciitis)',
            'Wound Type (Dehisced Surgical Wound)', 'Other Wound Type', 'Wound Size(s)',
            'Application CPT(s)', 'Date of Procedure', 'ICD-10 Diagnosis Code(s)',
            'Product Information', 'Prior Authorization Required (Yes/No)',
            'Clinical Notes Attached', 'Physician Agreement Signature', 'Agreement Date'
        ]
    ],
    [
        'template_name' => 'AMNIO AMP MedLife IVR',
        'manufacturer_name' => 'AMNIO AMP',
        'fields' => [
            'Distributor / Company', 'Practice Name', 'Physician Name', 'Practice PTAN',
            'Physician PTAN', 'Physician NPI', 'Practice NPI', 'Tax ID', 'Office Contact Name',
            'Office Contact Email', 'Patient Name', 'Patient DOB', 'Primary Insurance',
            'Member ID', 'Secondary Insurance', 'Member ID 2', 'Insurance Card Attached (Yes/No)',
            'Place of Service (Office)', 'Place of Service (Home)', 'Place of Service (Assisted Living)',
            'Place of Service (Other)', 'SNF Status (Yes/No)', 'Days in SNF',
            'Post-op Period Status (Yes/No)', 'Previous Surgery CPT Codes', 'Previous Surgery Date',
            'Procedure Date', 'Wound Location', 'Size of Graft Requested',
            'ICD-10 Code 1', 'ICD-10 Code 2', 'ICD-10 Code 3', 'ICD-10 Code 4'
        ]
    ],
    [
        'template_name' => 'BioWound IVR',
        'manufacturer_name' => 'BioWound',
        'fields' => [
            'Primary Insurance Payer Name', 'Secondary Insurance Payer Name',
            'Primary Policy Number', 'Secondary Policy Number', 'Primary Payer Phone',
            'Secondary Payer Phone', 'Wound Type (Q4205 Membrane Wrap)', 'Wound Type (Q4238 Derm-Maxx)',
            'Wound Type (Q4161 Bio-Connekt)', 'Wound Type (Q4267 NeoStim DL)',
            'Wound Type (Q4266 NeoStim SL)', 'Wound Type (Q4265 NeoStim TL)',
            'Wound Type (Q4239 Amnio-maxx)', 'SNF Status (Yes/No)', 'Global Period Status (Yes/No)',
            'ICD-10 Codes', 'Procedure CPT Codes', 'Wound Size (Total)', 'Facility Name',
            'Facility Address', 'Facility NPI', 'Facility Tax ID', 'Facility PTAN',
            'Physician Name', 'Physician Specialty', 'Physician NPI', 'Patient Name',
            'Patient DOB', 'Patient Address', 'Patient Phone', 'Authorized Signature', 'Signature Date'
        ]
    ]
];

echo "🔧 Importing Real IVR Field Mappings...\n\n";

DB::beginTransaction();

try {
    // Clear existing mappings
    echo "Clearing existing IVR field mappings...\n";
    IVRFieldMapping::truncate();

    $totalFields = 0;
    $totalMappings = 0;

    foreach ($ivrFieldData as $templateData) {
        $manufacturerName = $templateData['manufacturer_name'];
        $templateName = $templateData['template_name'];

        echo "Processing {$manufacturerName} - {$templateName}...\n";

        // Find or create manufacturer
        $manufacturer = Manufacturer::where('name', 'LIKE', "%{$manufacturerName}%")->first();
        if (!$manufacturer) {
            echo "  ⚠️  Manufacturer '{$manufacturerName}' not found, creating...\n";
            $manufacturer = Manufacturer::create([
                'name' => $manufacturerName,
                'slug' => \Illuminate\Support\Str::slug($manufacturerName),
                'is_active' => true
            ]);
        }

        $fieldCount = count($templateData['fields']);
        $totalFields += $fieldCount;

                // Create field mappings
        foreach ($templateData['fields'] as $fieldName) {
            // Determine FHIR path based on field name
            $fhirPath = determineFhirPath($fieldName);

            // Determine mapping type
            $mappingType = determineMappingType($fieldName);

            // Create mapping
            IVRFieldMapping::create([
                'manufacturer_id' => $manufacturer->id,
                'template_id' => $templateName,
                'source_field' => $fhirPath,
                'target_field' => $fieldName,
                'confidence' => 0.95, // High confidence for real field names
                'match_type' => $mappingType,
                'usage_count' => 0,
                'success_rate' => null,
                'metadata' => json_encode([
                    'field_type' => $mappingType,
                    'template_version' => '1.0',
                    'import_source' => 'production_templates'
                ])
            ]);

            $totalMappings++;
        }

        echo "  ✅ Imported {$fieldCount} fields for {$manufacturerName}\n";
    }

    DB::commit();

    echo "\n🎉 Import Complete!\n";
    echo "📊 Summary:\n";
    echo "  - Total Templates: " . count($ivrFieldData) . "\n";
    echo "  - Total Fields: {$totalFields}\n";
    echo "  - Total Mappings: {$totalMappings}\n";
    echo "  - Average Fields per Template: " . round($totalFields / count($ivrFieldData)) . "\n";

} catch (\Exception $e) {
    DB::rollback();
    echo "❌ Import failed: " . $e->getMessage() . "\n";
    throw $e;
}

/**
 * Determine FHIR path based on field name
 */
function determineFhirPath($fieldName) {
    $fieldLower = strtolower($fieldName);

    // Patient fields
    if (str_contains($fieldLower, 'patient name')) return 'Patient.name';
    if (str_contains($fieldLower, 'dob') || str_contains($fieldLower, 'date of birth')) return 'Patient.birthDate';
    if (str_contains($fieldLower, 'sex') || str_contains($fieldLower, 'gender')) return 'Patient.gender';
    if (str_contains($fieldLower, 'patient address') || str_contains($fieldLower, 'address')) return 'Patient.address';
    if (str_contains($fieldLower, 'city')) return 'Patient.address.city';
    if (str_contains($fieldLower, 'state')) return 'Patient.address.state';
    if (str_contains($fieldLower, 'zip')) return 'Patient.address.postalCode';
    if (str_contains($fieldLower, 'patient phone') || str_contains($fieldLower, 'phone')) return 'Patient.telecom';

    // Provider fields
    if (str_contains($fieldLower, 'provider name') || str_contains($fieldLower, 'physician name')) return 'Practitioner.name';
    if (str_contains($fieldLower, 'provider npi') || str_contains($fieldLower, 'physician npi')) return 'Practitioner.identifier:npi';
    if (str_contains($fieldLower, 'provider tax') || str_contains($fieldLower, 'physician tax')) return 'Practitioner.identifier:tax';
    if (str_contains($fieldLower, 'provider phone') || str_contains($fieldLower, 'physician phone')) return 'Practitioner.telecom';

    // Facility fields
    if (str_contains($fieldLower, 'facility name')) return 'Organization.name';
    if (str_contains($fieldLower, 'facility address')) return 'Organization.address';
    if (str_contains($fieldLower, 'facility npi')) return 'Organization.identifier:npi';
    if (str_contains($fieldLower, 'facility phone')) return 'Organization.telecom';

    // Insurance fields
    if (str_contains($fieldLower, 'primary insurance') || str_contains($fieldLower, 'insurance name 1')) return 'Coverage.payor';
    if (str_contains($fieldLower, 'secondary insurance') || str_contains($fieldLower, 'insurance name 2')) return 'Coverage.payor:secondary';
    if (str_contains($fieldLower, 'policy number') || str_contains($fieldLower, 'member id')) return 'Coverage.subscriberId';

    // Clinical fields
    if (str_contains($fieldLower, 'icd') || str_contains($fieldLower, 'diagnosis')) return 'Condition.code';
    if (str_contains($fieldLower, 'wound type')) return 'Condition.code:wound';
    if (str_contains($fieldLower, 'wound location')) return 'Condition.bodySite';
    if (str_contains($fieldLower, 'wound size')) return 'Observation.valueQuantity';

    // Procedure fields
    if (str_contains($fieldLower, 'cpt') || str_contains($fieldLower, 'procedure')) return 'Procedure.code';
    if (str_contains($fieldLower, 'hcpcs')) return 'DeviceRequest.codeCodeableConcept';

    // Product fields
    if (str_contains($fieldLower, 'product')) return 'DeviceRequest.codeCodeableConcept';

    // Default
    return 'Extension.valueString';
}

/**
 * Determine mapping type based on field name
 */
function determineMappingType($fieldName) {
    $fieldLower = strtolower($fieldName);

    // Exact matches for specific field names
    if (str_contains($fieldLower, 'patient name') ||
        str_contains($fieldLower, 'provider name') ||
        str_contains($fieldLower, 'physician name') ||
        str_contains($fieldLower, 'facility name')) return 'exact';

    // Pattern matches for structured fields
    if (str_contains($fieldLower, 'product requested') ||
        str_contains($fieldLower, 'product (q4') ||
        str_contains($fieldLower, 'application type') ||
        str_contains($fieldLower, 'place of service')) return 'pattern';

    // Semantic matches for complex fields
    if (str_contains($fieldLower, 'wound') ||
        str_contains($fieldLower, 'diagnosis') ||
        str_contains($fieldLower, 'insurance')) return 'semantic';

    // Default to fuzzy text mapping
    return 'fuzzy';
}

echo "\n🔄 Run this command to import the mappings:\n";
echo "php artisan tinker scripts/import-ivr-field-mappings.php\n";
