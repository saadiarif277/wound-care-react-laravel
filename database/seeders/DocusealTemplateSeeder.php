<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Docuseal\DocusealFolder;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Str;

class DocusealTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Manufacturer to DocuSeal mapping based on actual production data
        $manufacturerTemplates = [
            // MedLife Solutions
            [
                'manufacturer_name' => 'MEDLIFE SOLUTIONS',
                'folder_id' => '111417',
                'templates' => [
                    [
                        'name' => 'MedLife IVR Form',
                        'docuseal_template_id' => '1233913',
                        'document_type' => 'IVR',
                        'is_default' => true,
                    ],
                    [
                        'name' => 'MedLife Order Form',
                        'docuseal_template_id' => '1234279',
                        'document_type' => 'OrderForm',
<<<<<<< HEAD
                        'is_default' => false,
=======
                        'is_default' => true,
>>>>>>> origin/provider-side
                    ]
                ]
            ],

            // Advanced Solution
            [
                'manufacturer_name' => 'ADVANCED SOLUTION',
                'folder_id' => '108291',
                'templates' => [
                    [
                        'name' => 'Advanced Solution IVR',
                        'docuseal_template_id' => '1199885',
                        'document_type' => 'IVR',
                        'is_default' => true,
                    ]
                ]
            ],

            // Centurion Therapeutics
            [
                'manufacturer_name' => 'CENTURION THERAPEUTICS',
                'folder_id' => '111419',
                'templates' => [
                    [
                        'name' => 'Centurion Therapeutics IVR',
                        'docuseal_template_id' => '1233918',
                        'document_type' => 'IVR',
                        'is_default' => true,
                    ]
                ]
            ],

            // ACZ & Associates
            [
                'manufacturer_name' => 'ACZ & ASSOCIATES',
                'folder_id' => '75423',
                'templates' => [
                    [
                        'name' => 'ACZ & Associates IVR',
                        'docuseal_template_id' => '852440',
                        'document_type' => 'IVR',
                        'is_default' => true,
                    ],
                    [
                        'name' => 'ACZ & Associates Order Form',
                        'docuseal_template_id' => '852554',
                        'document_type' => 'OrderForm',
                        'is_default' => false,
                    ]
                ]
            ],

            // BioWound Solutions
            [
                'manufacturer_name' => 'BIOWOUND SOLUTIONS',
                'folder_id' => '113461',
                'templates' => [
                    [
                        'name' => 'BioWound IVR',
                        'docuseal_template_id' => '1254774',
                        'document_type' => 'IVR',
                        'is_default' => true,
<<<<<<< HEAD
=======
                    ],
                    [
                        'name' => 'BioWound Order Form',
                        'docuseal_template_id' => '1299495',
                        'document_type' => 'OrderForm',
                        'is_default' => false,
>>>>>>> origin/provider-side
                    ]
                ]
            ],

            // Extremity Care LLC
            [
                'manufacturer_name' => 'Extremity Care LLC',
                'folder_id' => '111449',
                'templates' => [
                    [
                        'name' => 'Extremity Care Restorigin IVR',
                        'docuseal_template_id' => '1234284',
                        'document_type' => 'IVR',
                        'is_default' => true,
                    ]
                ]
            ],
        ];

        // Create folders and templates for each manufacturer
        foreach ($manufacturerTemplates as $manufacturerData) {
            $manufacturer = Manufacturer::where('name', $manufacturerData['manufacturer_name'])->first();

            if (!$manufacturer) {
                $this->command->warn("Manufacturer '{$manufacturerData['manufacturer_name']}' not found, skipping...");
                continue;
            }

            $this->command->info("Processing manufacturer: {$manufacturer->name}");

            // Create or update DocuSeal folder (check both manufacturer_id AND docuseal_folder_id)
            $folder = DocusealFolder::where('manufacturer_id', $manufacturer->id)
                ->orWhere('docuseal_folder_id', $manufacturerData['folder_id'])
                ->first();

            if ($folder) {
                // Update existing folder to ensure correct association
                $folder->update([
                    'manufacturer_id' => $manufacturer->id,
                    'folder_name' => "{$manufacturer->name} DocuSeal Forms",
                    'docuseal_folder_id' => $manufacturerData['folder_id'],
                    'is_active' => true,
                ]);
            } else {
                // Create new folder
                DocusealFolder::create([
                    'manufacturer_id' => $manufacturer->id,
                    'folder_name' => "{$manufacturer->name} DocuSeal Forms",
                    'docuseal_folder_id' => $manufacturerData['folder_id'],
                    'is_active' => true,
                ]);
            }

            // Create or update templates
            foreach ($manufacturerData['templates'] as $templateData) {
                $existingTemplate = DocusealTemplate::where('docuseal_template_id', $templateData['docuseal_template_id'])->first();

                if ($existingTemplate) {
                    // Update existing template
                    $existingTemplate->update([
                        'template_name' => $templateData['name'],
                        'manufacturer_id' => $manufacturer->id,
                        'document_type' => $templateData['document_type'],
                        'is_default' => $templateData['is_default'],
                        'is_active' => true,
                        'field_mappings' => $this->getDefaultFieldMappings($templateData['document_type']),
                    ]);

                    $this->command->info("  Updated template: {$templateData['name']}");
                } else {
                    // Create new template
                    DocusealTemplate::create([
                        'template_name' => $templateData['name'],
                        'docuseal_template_id' => $templateData['docuseal_template_id'],
                        'manufacturer_id' => $manufacturer->id,
                        'document_type' => $templateData['document_type'],
                        'is_default' => $templateData['is_default'],
                        'field_mappings' => $this->getDefaultFieldMappings($templateData['document_type']),
                        'is_active' => true,
                    ]);

                    $this->command->info("  Created template: {$templateData['name']}");
                }
            }
        }

        // Create default generic templates if they don't exist
        $this->createGenericTemplates();

        $this->command->info('DocuSeal templates and folders seeded successfully!');
    }

    /**
     * Create generic templates for general use
     */
    private function createGenericTemplates(): void
    {
        $genericTemplates = [
            [
                'template_name' => 'Insurance Verification Form',
                'docuseal_template_id' => 'template_insurance_verification_001',
                'manufacturer_id' => null,
                'document_type' => 'InsuranceVerification',
                'is_default' => true,
                'field_mappings' => $this->getDefaultFieldMappings('InsuranceVerification'),
                'is_active' => true,
            ],
            [
                'template_name' => 'Standard Order Form',
                'docuseal_template_id' => 'template_order_form_001',
                'manufacturer_id' => null,
                'document_type' => 'OrderForm',
                'is_default' => true,
                'field_mappings' => $this->getDefaultFieldMappings('OrderForm'),
                'is_active' => true,
            ],
            [
                'template_name' => 'Provider Onboarding Form',
                'docuseal_template_id' => 'template_onboarding_001',
                'manufacturer_id' => null,
                'document_type' => 'OnboardingForm',
                'is_default' => true,
                'field_mappings' => $this->getDefaultFieldMappings('OnboardingForm'),
                'is_active' => true,
            ],
        ];

        foreach ($genericTemplates as $templateData) {
            DocusealTemplate::updateOrCreate(
                ['docuseal_template_id' => $templateData['docuseal_template_id']],
                $templateData
            );
        }
    }

    /**
     * Get default field mappings based on document type
     */
    private function getDefaultFieldMappings(string $documentType): array
    {
        switch ($documentType) {
            case 'IVR':
                return [
                    'patient_name' => 'Patient Full Name',
                    'patient_dob' => 'Date of Birth',
                    'patient_gender' => 'Gender',
                    'patient_phone' => 'Patient Phone',
                    'patient_address' => 'Patient Address',
                    'provider_name' => 'Provider Name',
                    'provider_npi' => 'Provider NPI',
                    'provider_phone' => 'Provider Phone',
                    'facility_name' => 'Facility Name',
                    'facility_address' => 'Facility Address',
                    'primary_insurance_name' => 'Primary Insurance',
                    'primary_policy_number' => 'Policy Number',
                    'primary_diagnosis_code' => 'Diagnosis Code',
                    'wound_type' => 'Wound Type',
                    'wound_location' => 'Wound Location',
                    'wound_size_length' => 'Wound Length',
                    'wound_size_width' => 'Wound Width',
                    'service_date' => 'Date of Service',
                    'provider_signature' => 'Provider Signature',
                    'provider_signature_date' => 'Signature Date',
                ];

            case 'OrderForm':
                return [
                    'order_number' => 'Order Number',
                    'patient_name' => 'Patient Name',
                    'provider_name' => 'Ordering Provider',
                    'facility_name' => 'Facility Name',
                    'product_name' => 'Product Ordered',
                    'quantity' => 'Quantity',
                    'service_date' => 'Date of Service',
                    'total_amount' => 'Total Order Amount',
                ];

            case 'InsuranceVerification':
                return [
                    'patient_name' => 'Patient Full Name',
                    'patient_dob' => 'Date of Birth',
                    'member_id' => 'Insurance Member ID',
                    'insurance_plan' => 'Insurance Plan Name',
                    'provider_name' => 'Provider Name',
                    'provider_npi' => 'Provider NPI',
                    'order_date' => 'Date of Service',
                ];

            case 'OnboardingForm':
                return [
                    'provider_name' => 'Provider Name',
                    'provider_npi' => 'NPI Number',
                    'facility_name' => 'Facility Name',
                    'facility_address' => 'Facility Address',
                ];

            default:
                return [];
        }
    }
}
