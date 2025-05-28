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
