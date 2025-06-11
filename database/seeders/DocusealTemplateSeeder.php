<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Str;

class DocusealTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'template_name' => 'Insurance Verification Form',
                'docuseal_template_id' => 'template_insurance_verification_001',
                'manufacturer_id' => null,
                'document_type' => 'InsuranceVerification',
                'is_default' => true,
                'field_mappings' => [
                    'patient_name' => 'Patient Full Name',
                    'patient_dob' => 'Date of Birth',
                    'member_id' => 'Insurance Member ID',
                    'insurance_plan' => 'Insurance Plan Name',
                    'provider_name' => 'Provider Name',
                    'provider_npi' => 'Provider NPI',
                    'order_date' => 'Date of Service',
                ],
                'is_active' => true,
            ],
            [
                'template_name' => 'Standard Order Form',
                'docuseal_template_id' => 'template_order_form_001',
                'manufacturer_id' => null,
                'document_type' => 'OrderForm',
                'is_default' => true,
                'field_mappings' => [
                    'order_number' => 'Order Number',
                    'patient_name' => 'Patient Name',
                    'provider_name' => 'Ordering Provider',
                    'facility_name' => 'Facility Name',
                    'total_amount' => 'Total Order Amount',
                    'date_of_service' => 'Date of Service',
                ],
                'is_active' => true,
            ],
            [
                'template_name' => 'Provider Onboarding Form',
                'docuseal_template_id' => 'template_onboarding_001',
                'manufacturer_id' => null,
                'document_type' => 'OnboardingForm',
                'is_default' => true,
                'field_mappings' => [
                    'provider_name' => 'Provider Name',
                    'provider_npi' => 'NPI Number',
                    'facility_name' => 'Facility Name',
                    'facility_address' => 'Facility Address',
                ],
                'is_active' => true,
            ],
            [
                'template_name' => 'ACZ Distribution IVR Form',
                'docuseal_template_id' => '852440',
                'manufacturer_id' => null, // Will be linked to ACZ Distribution manufacturer
                'document_type' => 'IVR',
                'is_default' => false,
                'field_mappings' => [
                    // Treating Physician/Facility Information
                    'treatingPhysicianFacility.npi' => 'provider_npi',
                    'treatingPhysicianFacility.taxId' => 'facility_tax_id',
                    'treatingPhysicianFacility.ptan' => 'facility_ptan',
                    'treatingPhysicianFacility.medicaidNumber' => 'provider_medicaid_number',
                    'treatingPhysicianFacility.phone' => 'facility_phone',
                    'treatingPhysicianFacility.fax' => 'facility_fax',
                    'treatingPhysicianFacility.managementCompany' => 'management_company',
                    'treatingPhysicianFacility.physicianName' => 'provider_name',
                    'treatingPhysicianFacility.facilityName' => 'facility_name',
                    
                    // Patient Demographics & Insurance
                    'patientDemographicInsurance.placeOfService' => 'place_of_service',
                    'patientDemographicInsurance.insurancePrimary.name' => 'primary_insurance_name',
                    'patientDemographicInsurance.insurancePrimary.policyNumber' => 'primary_policy_number',
                    'patientDemographicInsurance.insurancePrimary.payerPhone' => 'primary_payer_phone',
                    'patientDemographicInsurance.insurancePrimary.providerStatus' => 'primary_provider_status',
                    'patientDemographicInsurance.insuranceSecondary.name' => 'secondary_insurance_name',
                    'patientDemographicInsurance.insuranceSecondary.policyNumber' => 'secondary_policy_number',
                    'patientDemographicInsurance.insuranceSecondary.payerPhone' => 'secondary_payer_phone',
                    'patientDemographicInsurance.insuranceSecondary.providerStatus' => 'secondary_provider_status',
                    'patientDemographicInsurance.permissionForPriorAuth' => 'permission_for_prior_auth',
                    'patientDemographicInsurance.inHospice' => 'in_hospice',
                    'patientDemographicInsurance.underPartAStay' => 'under_part_a_stay',
                    'patientDemographicInsurance.underGlobalSurgicalPeriod' => 'under_global_surgical_period',
                    'patientDemographicInsurance.previousSurgeryCptCodes' => 'previous_surgery_cpt_codes',
                    'patientDemographicInsurance.previousSurgeryDate' => 'previous_surgery_date',
                    
                    // Wound Information
                    'woundInformation.locationOfWound' => 'wound_location',
                    'woundInformation.icd10Codes' => 'icd10_codes',
                    'woundInformation.totalWoundSizeOrMedicalHistory' => 'wound_size_or_history',
                    
                    // Product Information
                    'productInformation.productName' => 'product_name',
                    'productInformation.productCode' => 'product_code',
                    
                    // Representative Information
                    'representative.name' => 'sales_rep_name',
                    'representative.isoIfApplicable' => 'iso_if_applicable',
                    'representative.additionalNotificationEmails' => 'additional_notification_emails',
                    
                    // Physician Information
                    'physician.name' => 'physician_name',
                    'physician.specialty' => 'physician_specialty',
                    
                    // Facility Information
                    'facility.name' => 'facility_name',
                    'facility.address' => 'facility_address',
                    'facility.cityStateZip' => 'facility_city_state_zip',
                    'facility.contactName' => 'facility_contact_name',
                    'facility.contactPhoneEmail' => 'facility_contact_phone_email',
                    
                    // Patient Information
                    'patient.name' => 'patient_name',
                    'patient.dob' => 'patient_dob',
                    'patient.address' => 'patient_address',
                    'patient.cityStateZip' => 'patient_city_state_zip',
                    'patient.phone' => 'patient_phone',
                    'patient.faxEmail' => 'patient_fax_email',
                    'patient.caregiverInfo' => 'patient_caregiver_info',
                    
                    // Service Information
                    'servicedBy' => 'serviced_by',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $templateData) {
            DocusealTemplate::create([
                'id' => Str::uuid(),
                ...$templateData,
            ]);
        }

        $this->command->info('DocuSeal templates seeded successfully!');
    }
}
