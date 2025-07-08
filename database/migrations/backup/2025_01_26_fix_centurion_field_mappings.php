<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, ensure Centurion Therapeutics manufacturer exists
        $centurion = Manufacturer::firstOrCreate(
            ['name' => 'Centurion Therapeutics'],
            [
                'slug' => 'centurion-therapeutics',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Update the Centurion template to use the correct manufacturer
        $template = DocusealTemplate::where('template_name', 'Centurion Therapeutics AmnioBand/Allopatch IVR')
            ->first();

        if ($template) {
            // Create simplified field mappings that match the exact CSV field names
            $fieldMappings = [
                // Patient Information
                'Patient Name' => 'patientInfo.patientName',
                'DOB' => 'patientInfo.dateOfBirth',
                'Gender' => 'patientInfo.gender',
                'Address' => 'patientInfo.address',
                'City' => 'patientInfo.city',
                'State' => 'patientInfo.state',
                'Zip' => 'patientInfo.zip',
                'Home Phone' => 'patientInfo.homePhone',
                'Mobile' => 'patientInfo.mobile',
                
                // Insurance Information
                'Primary Insurance' => 'insuranceInfo.primaryInsurance.primaryInsuranceName',
                'Secondary Insurance' => 'insuranceInfo.secondaryInsurance.secondaryInsuranceName',
                'Payer Phone' => 'insuranceInfo.primaryInsurance.primaryPayerPhone',
                'Secondary Payor Phone' => 'insuranceInfo.secondaryInsurance.secondaryPayerPhone',
                'Policy Number' => 'insuranceInfo.primaryInsurance.primaryPolicyNumber',
                'Secondary Policy Number' => 'insuranceInfo.secondaryInsurance.secondaryPolicyNumber',
                'Suscriber Name' => 'insuranceInfo.primaryInsurance.primarySubscriberName',
                'Secondary Subscriber Name' => 'insuranceInfo.secondaryInsurance.secondarySubscriberName',
                
                // Provider Information
                'Provider Name' => 'providerInfo.providerName',
                'Specialty' => 'providerInfo.specialty',
                'PTAN' => 'providerInfo.ptan',
                'NPI' => 'providerInfo.npi',
                'Tax ID' => 'providerInfo.taxId',
                'Medicare Provider' => 'providerInfo.medicareProvider',
                
                // Facility Information
                'Facility Name' => 'facilityInfo.facilityName',
                'Facility Address' => 'facilityInfo.facilityAddress',
                'Facility City' => 'facilityInfo.facilityCity',
                'Facility State' => 'facilityInfo.facilityState',
                'Facility Zip' => 'facilityInfo.facilityZip',
                'Facility NPI' => 'facilityInfo.facilityNpi',
                'Facility Contact' => 'facilityInfo.facilityContact',
                'Facility Contact Phone' => 'facilityInfo.facilityContactPhone',
                'Facility Contact Fax' => 'facilityInfo.facilityContactFax',
                'Facility Contact Email' => 'facilityInfo.facilityContactEmail',
                
                // Place of Service checkboxes
                'Check: POS-22' => 'placeOfService.pos22',
                'Check: POS-11' => 'placeOfService.pos11',
                'Check: POS-12' => 'placeOfService.pos12',
                'Check: POS-13' => 'placeOfService.pos13',
                'Check: POS-31' => 'placeOfService.pos31',
                'Check: POS-32' => 'placeOfService.pos32',
                
                // Product Information
                'AmnioBand Q4151' => 'productInfo.amnioBandQ4151',
                'Allopatch Q4128' => 'productInfo.allopatchQ4128',
                
                // Wound Information
                'Primary' => 'woundInfo.primaryDiagnosis',
                'Secondary' => 'woundInfo.secondaryDiagnosis',
                'Tertiary' => 'woundInfo.tertiaryDiagnosis',
                'Known Conditions' => 'woundInfo.knownConditions',
                'Wound Size' => 'woundInfo.woundSize',
                
                // Treatment Information
                'Anticipated Treatment Start Date' => 'treatmentInfo.anticipatedStartDate',
                'Frequency' => 'treatmentInfo.frequency',
                'Number of Applications' => 'treatmentInfo.numberOfApplications',
                
                // Other Information
                'Check: New Wound' => 'requestType.newWound',
                'chkAdditionalApplication' => 'requestType.additionalApplication',
                'Check: Reverification' => 'requestType.reverification',
                'Check: New Insurance' => 'requestType.newInsurance',
                'If YES how many days has the patient been admitted to the skilled nursing facility or nursing home' => 'admissionInfo.nursingHomeDays',
                'No: Pre-Auth Assistance' => 'authorization.preAuthAssistance',
                'Signature Date' => 'signature.date',
                'Sales Representative' => 'salesInfo.representativeName',
                
                // Keep consistent naming for common fields
                'Name' => 'submitterInfo.name',
                'Email' => 'submitterInfo.email',
                'Phone' => 'submitterInfo.phone',
            ];

            $template->update([
                'manufacturer_id' => $centurion->id,
                'field_mappings' => $fieldMappings,
                'updated_at' => now(),
            ]);

            echo "Updated Centurion Therapeutics template with simplified field mappings\n";
        }

        // Also create/update field mappings for Advanced Solution template if it exists
        $advancedSolution = Manufacturer::where('name', 'like', '%Advanced%')->first();
        
        if ($advancedSolution) {
            $advancedTemplate = DocusealTemplate::firstOrCreate(
                [
                    'template_name' => 'Advanced Solution IVR',
                    'manufacturer_id' => $advancedSolution->id,
                ],
                [
                    'docuseal_template_id' => 'pending_sync', // Will be updated by sync command
                    'document_type' => 'IVR',
                    'is_default' => true,
                    'is_active' => true,
                    'field_mappings' => [
                        // Use similar mapping structure as above
                        'Patient Name' => 'patientInfo.patientName',
                        'Date of Birth' => 'patientInfo.dateOfBirth',
                        'Insurance Name' => 'insuranceInfo.primaryInsurance.primaryInsuranceName',
                        'Member ID' => 'insuranceInfo.primaryInsurance.primaryMemberId',
                        'Provider Name' => 'providerInfo.providerName',
                        'Facility Name' => 'facilityInfo.facilityName',
                        'Wound Type' => 'woundInfo.woundType',
                        'Wound Location' => 'woundInfo.woundLocation',
                    ],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            
            echo "Created/Updated Advanced Solution template with field mappings\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the manufacturer association if needed
        $template = DocusealTemplate::where('template_name', 'Centurion Therapeutics AmnioBand/Allopatch IVR')
            ->first();
            
        if ($template) {
            // Find MiMedx manufacturer
            $mimedx = Manufacturer::where('name', 'MiMedx')->first();
            if ($mimedx) {
                $template->update([
                    'manufacturer_id' => $mimedx->id,
                    'field_mappings' => [], // Clear mappings
                ]);
            }
        }
    }
};