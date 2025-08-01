<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocusealService;

class DebugMedLifeAmnioAmpIVRMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:medlife-amnio-amp-ivr-mapping {--data=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug MedLife AMNIO AMP IVR field mapping to identify missing fields';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $docusealService = app(DocusealService::class);

        // Sample form data for testing
        $formData = [
            "request_type" => "new_request",
            "provider_id" => 3,
            "facility_id" => 1,
            "organization_id" => 22,
            "organization_name" => "Test Healthcare Network",
            "patient_name" => "John Doe",
            "patient_first_name" => "John",
            "patient_last_name" => "Doe",
            "patient_dob" => "1965-03-15",
            "patient_gender" => "male",
            "patient_is_subscriber" => true,
            "primary_insurance_name" => "Cigna",
            "primary_member_id" => "MED123456789",
            "primary_plan_type" => "ffs",
            "has_secondary_insurance" => false,
            "prior_auth_permission" => true,
            "wound_type" => "diabetic_foot_ulcer",
            "wound_types" => ["diabetic_foot_ulcer"],
            "wound_location" => "right_foot",
            "wound_size_length" => "4",
            "wound_size_width" => "4",
            "wound_size_depth" => "0",
            "application_cpt_codes" => [""],
            "place_of_service" => "11",
            "shipping_speed" => "standard_next_day",
            "expected_service_date" => "2025-08-02",
            "order_items" => [],
            "failed_conservative_treatment" => true,
            "information_accurate" => true,
            "medical_necessity_established" => true,
            "maintain_documentation" => true,
            "authorize_prior_auth" => true,
            "provider_npi" => "12345",
            "selected_products" => [
                [
                    "product_id" => 4,
                    "quantity" => 1,
                    "size" => "1.54",
                    "product" => [
                        "id" => 4,
                        "code" => "Q4316",
                        "name" => "Amchoplast",
                        "manufacturer" => "MedLife Solutions",
                        "manufacturer_id" => 1,
                        "available_sizes" => ["1.54", "2.55", "4.00", "6.00", "8.00", "9.00", "12.00", "15.00", "16.00", "24.00", "25.00", "28.00", "32.00", "48.00", "49.00", "72.00", "100.00", "200.00", "400.00"],
                        "size_options" => ["14 mm disc", "18 mm disc", "2×2 cm", "2×3 cm", "2×4 cm", "3×3 cm", "2×6 cm", "3×5 cm", "4×4 cm", "4×6 cm", "5×5 cm", "4×7 cm", "4×8 cm", "6×8 cm", "7×7 cm", "6×12 cm", "10×10 cm", "10×20 cm", "20×20 cm"],
                        "size_pricing" => [
                            "14 mm disc" => "1.54",
                            "18 mm disc" => "2.55",
                            "2×2 cm" => "4.00",
                            "2×3 cm" => "6.00",
                            "2×4 cm" => "8.00",
                            "3×3 cm" => "9.00",
                            "2×6 cm" => "12.00",
                            "3×5 cm" => "15.00",
                            "4×4 cm" => "16.00",
                            "4×6 cm" => "24.00",
                            "5×5 cm" => "25.00",
                            "4×7 cm" => "28.00",
                            "4×8 cm" => "32.00",
                            "6×8 cm" => "48.00",
                            "7×7 cm" => "49.00",
                            "6×12 cm" => "72.00",
                            "10×10 cm" => "100.00",
                            "10×20 cm" => "200.00",
                            "20×20 cm" => "400.00"
                        ],
                        "size_unit" => "cm",
                        "price_per_sq_cm" => "441.60",
                        "msc_price" => 264.96,
                        "commission_rate" => null,
                        "docuseal_template_id" => null,
                        "signature_required" => false
                    ]
                ]
            ],
            "manufacturer_fields" => [],
            "docuseal_submission_id" => "",
            "delivery_date" => "2025-08-02",
            "patient_member_id" => "MED123456789",
            "patient_address_line1" => "123 Main Street",
            "patient_address_line2" => "Apt 4B",
            "patient_city" => "New York",
            "patient_state" => "NY",
            "patient_zip" => "10001",
            "patient_phone" => "(555) 123-4567",
            "patient_email" => "john.doe@email.com",
            "primary_payer_phone" => "",
            "wound_location_details" => "Plantar surface, first metatarsal head",
            "primary_diagnosis_code" => "E11.621",
            "secondary_diagnosis_code" => "L97.519",
            "wound_duration_weeks" => "6",
            "wound_duration_days" => "2",
            "prior_applications" => "0",
            "prior_application_product" => "Standard dressing",
            "prior_application_within_12_months" => false,
            "anticipated_applications" => "4",
            "medicare_part_b_authorized" => false,
            "hospice_status" => false,
            "part_a_status" => false,
            "global_period_status" => false,
            "primary_physician_network_status" => "in_network",
            "primary_payer_id" => "3",
            // Additional data that would be available in a real scenario
            "provider_name" => "Dr. Jane Smith",
            "facility_name" => "Test Healthcare Network",
            "facility_address_line1" => "456 Medical Center Blvd",
            "facility_city" => "New York",
            "facility_state" => "NY",
            "facility_zip" => "10002",
            // MedLife specific fields
            "nursing_home_status" => false,
            "nursing_home_over_100_days" => false,
            "surgery_cpt_codes" => [],
            "surgery_date" => null,
            "secondary_insurance_name" => "Medicare",
            "secondary_member_id" => "1AB2C3D4E5F6",
            "facility_contact_name" => "Jane Smith",
            "facility_contact_email" => "jane.smith@facility.com",
            "provider_ptan" => "123456789",
            "facility_ptan" => "987654321",
            "facility_npi" => "1234567890",
            "facility_tax_id" => "98-7654321"
        ];

        // Merge with any additional data from command option
        if ($this->option('data')) {
            $additionalData = json_decode($this->option('data'), true);
            if ($additionalData) {
                $formData = array_merge($formData, $additionalData);
            }
        }

        $this->info('=== MedLife AMNIO AMP IVR Field Mapping Debug ===');
        $this->newLine();

        $result = $docusealService->debugMedLifeAmnioAmpIVRMapping($formData);

        // Display template info
        $this->info('📋 Template Information:');
        $this->line('Template ID: ' . $result['template_info']['template_id']);
        $this->line('Template Name: ' . $result['template_info']['template_name']);
        $this->line('Manufacturer: ' . $result['template_info']['manufacturer']);
        $this->newLine();

        // Display input data summary
        $this->info('📊 Input Data Summary:');
        $this->line('Total Fields: ' . $result['input_data']['total_fields']);
        $this->line('Available Fields: ' . implode(', ', array_slice($result['input_data']['available_fields'], 0, 10)) . '...');
        $this->newLine();

        // Display mapping results
        $this->info('🎯 Mapping Results:');
        $this->line('Total Template Fields: ' . $result['mapping_results']['total_template_fields']);
        $this->line('Mapped Fields: ' . $result['mapping_results']['mapped_fields']);
        $this->line('Success Rate: ' . number_format($result['mapping_results']['success_rate'], 1) . '%');
        $this->newLine();

        // Display field categories
        $this->info('📋 Field Categories:');
        foreach ($result['field_categories'] as $category => $data) {
            $this->line("• {$category}: {$data['mapped_fields']}/{$data['total_fields']} fields mapped ({$data['success_rate']}%)");
        }
        $this->newLine();

        // Display missing fields analysis
        $this->info('❌ Missing Fields Analysis:');
        foreach ($result['missing_fields_analysis'] as $missing) {
            $this->line("• {$missing['docuseal_field']}");
            $this->line("  Source Fields: " . implode(', ', $missing['source_fields']));
            $this->line("  Missing Sources: " . implode(', ', $missing['missing_sources']));
            $this->line("  Required: " . ($missing['required'] ? 'YES' : 'NO'));
            $this->newLine();
        }

        // Display recommendations
        $this->info('💡 Recommendations:');
        foreach ($result['recommendations'] as $rec) {
            $icon = match($rec['type']) {
                'critical' => '🚨',
                'warning' => '⚠️',
                'info' => 'ℹ️',
                default => '💡'
            };
            $this->line("{$icon} {$rec['message']}");
        }
        $this->newLine();

        // Display summary
        if ($result['mapping_results']['success_rate'] >= 80) {
            $this->info('✅ Excellent! Field mapping is working well.');
        } elseif ($result['mapping_results']['success_rate'] >= 60) {
            $this->warn('⚠️ Field mapping needs improvement. Check missing fields above.');
        } else {
            $this->error('❌ Field mapping needs significant improvement. Review missing fields and add data sources.');
        }

        return Command::SUCCESS;
    }
}
