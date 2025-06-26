<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Adding additional field mappings for unmapped fields...\n";

// Additional mappings for common unmapped fields
$additionalMappings = [
    // Sales/Administrative
    ['source' => 'Sales Rep', 'target' => 'metadata.salesRep', 'match_type' => 'semantic'],
    ['source' => 'Representative Name', 'target' => 'metadata.salesRep', 'match_type' => 'semantic'],
    ['source' => 'ISO If Applicable', 'target' => 'metadata.iso', 'match_type' => 'semantic'],
    ['source' => 'Distributor / Company', 'target' => 'metadata.distributor', 'match_type' => 'semantic'],
    ['source' => 'Additional Notification Emails', 'target' => 'metadata.notificationEmails', 'match_type' => 'semantic'],
    
    // Medicare/Administrative
    ['source' => 'Medicare Admin Contractor', 'target' => 'organization.medicareAdminContractor', 'match_type' => 'semantic'],
    ['source' => 'Medicare Admin Contractor Phone', 'target' => 'organization.medicareAdminContractorPhone', 'match_type' => 'semantic'],
    
    // Practitioner Address
    ['source' => 'Physician Address', 'target' => 'practitioner.address', 'match_type' => 'semantic'],
    ['source' => 'Provider Address', 'target' => 'practitioner.address', 'match_type' => 'semantic'],
    
    // Contact Preferences
    ['source' => 'OK to Contact Patient (Yes/No)', 'target' => 'patient.communication.okToContact', 'match_type' => 'semantic'],
    
    // Insurance Plan Details
    ['source' => 'Primary Insurance Plan Type (HMO/PPO/Other)', 'target' => 'coverage.class.type', 'match_type' => 'semantic'],
    ['source' => 'Primary Insurance Network Participation (Yes/No/Not Sure)', 'target' => 'coverage.network', 'match_type' => 'semantic'],
    ['source' => 'Secondary Insurance Plan Type (HMO/PPO/Other)', 'target' => 'coverage.secondary.class.type', 'match_type' => 'semantic'],
    ['source' => 'Secondary Insurance Network Participation (Yes/No/Not Sure)', 'target' => 'coverage.secondary.network', 'match_type' => 'semantic'],
    ['source' => 'Provider Status 1 (In-Network/Out-of-Network)', 'target' => 'coverage.network', 'match_type' => 'semantic'],
    ['source' => 'Provider Status 2 (In-Network/Out-of-Network)', 'target' => 'coverage.secondary.network', 'match_type' => 'semantic'],
    
    // More Wound Types
    ['source' => 'Wound Type (Traumatic Burns)', 'target' => 'condition.woundType.traumaticBurns', 'match_type' => 'semantic'],
    ['source' => 'Wound Type (Radiation Burns)', 'target' => 'condition.woundType.radiationBurns', 'match_type' => 'semantic'],
    ['source' => 'Wound Type (Necrotizing Faciitis)', 'target' => 'condition.woundType.necrotizingFaciitis', 'match_type' => 'semantic'],
    ['source' => 'Wound Type (Dehisced Surgical Wound)', 'target' => 'condition.woundType.dehiscedSurgicalWound', 'match_type' => 'semantic'],
    ['source' => 'Other Wound Type', 'target' => 'condition.woundType.other', 'match_type' => 'semantic'],
    
    // CPT/Procedure Codes
    ['source' => 'Application CPT(s)', 'target' => 'procedure.code.cpt', 'match_type' => 'semantic'],
    ['source' => 'CPT (Legs/Arms/Trunk ≤100 sq cm)', 'target' => 'procedure.code.cpt', 'match_type' => 'pattern'],
    ['source' => 'CPT (Legs/Arms/Trunk ≥100 sq cm)', 'target' => 'procedure.code.cpt', 'match_type' => 'pattern'],
    ['source' => 'Previous Surgery CPT Codes', 'target' => 'history.previousSurgery.cpt', 'match_type' => 'semantic'],
    
    // Product Information
    ['source' => 'Product Information', 'target' => 'deviceRequest.product.info', 'match_type' => 'semantic'],
    ['source' => 'Size of Graft Requested', 'target' => 'deviceRequest.product.size', 'match_type' => 'semantic'],
    ['source' => 'Number of Anticipated Applications', 'target' => 'deviceRequest.quantity', 'match_type' => 'semantic'],
    
    // Authorization
    ['source' => 'Prior Authorization Required (Yes/No)', 'target' => 'coverage.preAuthRef', 'match_type' => 'semantic'],
    ['source' => 'Authorization Permission (Yes/No)', 'target' => 'coverage.preAuthRef', 'match_type' => 'semantic'],
    
    // Clinical Documents
    ['source' => 'Clinical Notes Attached', 'target' => 'documentReference.clinicalNotes', 'match_type' => 'semantic'],
    ['source' => 'Insurance Card Attached (Yes/No)', 'target' => 'documentReference.insuranceCard', 'match_type' => 'semantic'],
    
    // Signatures
    ['source' => 'Physician Agreement Signature', 'target' => 'consent.provision.actor', 'match_type' => 'semantic'],
    ['source' => 'Authorized Signature', 'target' => 'consent.provision.actor', 'match_type' => 'semantic'],
    ['source' => 'Agreement Date', 'target' => 'consent.dateTime', 'match_type' => 'semantic'],
    ['source' => 'Signature Date', 'target' => 'consent.dateTime', 'match_type' => 'semantic'],
    
    // SNF/Care Settings
    ['source' => 'SNF Status (Yes/No)', 'target' => 'encounter.hospitalization.admitSource', 'match_type' => 'semantic'],
    ['source' => 'SNF Days Admitted', 'target' => 'encounter.length', 'match_type' => 'semantic'],
    ['source' => 'Days in SNF', 'target' => 'encounter.length', 'match_type' => 'semantic'],
    ['source' => 'SNF Over 100 Days (Yes/No)', 'target' => 'encounter.hospitalization.specialArrangement', 'match_type' => 'semantic'],
    ['source' => 'SNF Admission Status (Yes/No)', 'target' => 'encounter.hospitalization.admitSource', 'match_type' => 'semantic'],
    
    // Hospice/Special Status
    ['source' => 'Hospice Status (Yes/No)', 'target' => 'episodeOfCare.type', 'match_type' => 'semantic'],
    ['source' => 'Part A Stay Status (Yes/No)', 'target' => 'coverage.costToBeneficiary', 'match_type' => 'semantic'],
    ['source' => 'Global Surgical Period Status (Yes/No)', 'target' => 'procedure.followUp', 'match_type' => 'semantic'],
    ['source' => 'Global Period Status (Yes/No)', 'target' => 'procedure.followUp', 'match_type' => 'semantic'],
    ['source' => 'Post-op Period Status (Yes/No)', 'target' => 'procedure.followUp', 'match_type' => 'semantic'],
    
    // History
    ['source' => 'Previous Surgery Date', 'target' => 'history.previousSurgery.date', 'match_type' => 'semantic'],
    ['source' => 'Surgery Date', 'target' => 'history.previousSurgery.date', 'match_type' => 'semantic'],
    ['source' => 'Total Wound Size / Medical History', 'target' => 'observation.woundSize', 'match_type' => 'semantic'],
    
    // Wound Measurements
    ['source' => 'Wound Size (L)', 'target' => 'observation.woundSize.length', 'match_type' => 'pattern'],
    ['source' => 'Wound Size (W)', 'target' => 'observation.woundSize.width', 'match_type' => 'pattern'],
    ['source' => 'Wound Size (Total)', 'target' => 'observation.woundSize.area', 'match_type' => 'pattern'],
    ['source' => 'Wound 1 (L)', 'target' => 'observation.woundSize.wound1.length', 'match_type' => 'pattern'],
    ['source' => 'Wound 1 (W)', 'target' => 'observation.woundSize.wound1.width', 'match_type' => 'pattern'],
    
    // Management
    ['source' => 'Management Co', 'target' => 'organization.partOf', 'match_type' => 'semantic'],
    
    // Application Type
    ['source' => 'Application Type (New Application)', 'target' => 'episodeOfCare.type', 'match_type' => 'pattern'],
    ['source' => 'Application Type (Additional Application)', 'target' => 'episodeOfCare.type', 'match_type' => 'pattern'],
    ['source' => 'New Request (Yes/No)', 'target' => 'episodeOfCare.type', 'match_type' => 'semantic'],
    
    // Provider Specialty
    ['source' => 'Physician Specialty', 'target' => 'practitioner.qualification.code', 'match_type' => 'semantic'],
    ['source' => 'Specialty', 'target' => 'practitioner.qualification.code', 'match_type' => 'semantic'],
    
    // Medicaid
    ['source' => 'Treating Physician Medicaid #', 'target' => 'practitioner.identifier.medicaid', 'match_type' => 'semantic'],
    ['source' => 'Medicaid Provider #', 'target' => 'practitioner.identifier.medicaid', 'match_type' => 'semantic'],
    
    // Patient Contact
    ['source' => 'Patient Fax/Email', 'target' => 'patient.telecom.faxEmail', 'match_type' => 'semantic'],
    ['source' => 'Patient Caregiver Info', 'target' => 'patient.contact', 'match_type' => 'semantic'],
    
    // Frequency/Duration
    ['source' => 'Frequency', 'target' => 'timing.repeat.frequency', 'match_type' => 'semantic'],
    ['source' => 'Wound Duration', 'target' => 'condition.onsetPeriod', 'match_type' => 'semantic'],
    
    // Study Participation
    ['source' => 'Clinical Study Participation (Yes/No)', 'target' => 'researchStudy.enrollment', 'match_type' => 'semantic'],
    
    // Known Conditions
    ['source' => 'Known Conditions', 'target' => 'condition.code.text', 'match_type' => 'semantic'],
];

$created = 0;
$skipped = 0;

foreach ($additionalMappings as $mapping) {
    // Find all fields with this source name
    $fields = DB::table('ivr_template_fields')
        ->where('field_name', $mapping['source'])
        ->get();
    
    foreach ($fields as $field) {
        // Check if mapping already exists
        $exists = DB::table('ivr_field_mappings')
            ->where('manufacturer_id', $field->manufacturer_id)
            ->where('template_id', $field->template_name)
            ->where('source_field', $mapping['source'])
            ->where('target_field', $mapping['target'])
            ->exists();
            
        if (!$exists) {
            try {
                DB::table('ivr_field_mappings')->insert([
                    'manufacturer_id' => $field->manufacturer_id,
                    'template_id' => $field->template_name,
                    'source_field' => $mapping['source'],
                    'target_field' => $mapping['target'],
                    'confidence' => $mapping['match_type'] === 'semantic' ? 0.85 : 0.90,
                    'match_type' => $mapping['match_type'],
                    'usage_count' => 0,
                    'created_by' => 'system',
                    'metadata' => json_encode([
                        'auto_generated' => true,
                        'generation_batch' => 'additional_mappings',
                        'created_at' => now()->toISOString()
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            } catch (Exception $e) {
                // Skip duplicates
                $skipped++;
            }
        } else {
            $skipped++;
        }
    }
}

echo "\nCreated {$created} additional mappings\n";
echo "Skipped {$skipped} existing mappings\n";

// Show summary by manufacturer
echo "\nMapping Summary by Manufacturer:\n";
$summary = DB::table('ivr_field_mappings as m')
    ->join('manufacturers as mfr', 'm.manufacturer_id', '=', 'mfr.id')
    ->select('mfr.name', DB::raw('COUNT(*) as total_mappings'))
    ->groupBy('mfr.id', 'mfr.name')
    ->orderBy('mfr.name')
    ->get();

foreach ($summary as $s) {
    echo "  {$s->name}: {$s->total_mappings} mappings\n";
}

echo "\nDone!\n";