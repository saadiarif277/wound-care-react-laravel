<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Str;

class ACZOrderFormTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if ACZ OrderForm template already exists
        $existingTemplate = DocusealTemplate::where('manufacturer_id', '1')
            ->where('document_type', 'OrderForm')
            ->first();

        if ($existingTemplate) {
            $this->command->info('ACZ OrderForm template already exists, skipping...');
            return;
        }

        // Create ACZ OrderForm template
        DocusealTemplate::create([
            'id' => Str::uuid(),
            'template_name' => 'ACZ & Associates Order Form',
            'docuseal_template_id' => 'acz-order-form-template-' . time(), // Placeholder ID
            'manufacturer_id' => '1', // ACZ & Associates manufacturer ID
            'document_type' => 'OrderForm',
            'is_default' => false,
            'field_mappings' => [
                'Order Number' => 'order_number',
                'Order Date' => 'order_date',
                'Manufacturer Name' => 'manufacturer_name',
                'Patient Name' => 'patient_name',
                'Patient Email' => 'patient_email',
                'Patient Phone' => 'patient_phone',
                'Patient Address' => 'patient_address',
                'Provider Name' => 'provider_name',
                'Provider NPI' => 'provider_npi',
                'Provider Email' => 'provider_email',
                'Facility Name' => 'facility_name',
                'Facility NPI' => 'facility_npi',
                'Product Code' => 'product_code',
                'Product Description' => 'product_description',
                'Quantity' => 'quantity',
                'Shipping Address' => 'shipping_address',
                'Shipping Method' => 'shipping_method',
                'Integration Email' => 'integration_email',
                'Episode ID' => 'episode_id'
            ],
            'is_active' => true,
        ]);

        $this->command->info('ACZ OrderForm template created successfully!');
    }
}
