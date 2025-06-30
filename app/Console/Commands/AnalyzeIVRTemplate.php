<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
<<<<<<< HEAD
use App\Services\FuzzyMapping\IVRMappingOrchestrator;
=======
use App\Services\UnifiedFieldMappingService;
>>>>>>> origin/provider-side
use App\Models\Order\Manufacturer;
use App\Models\IVRTemplateField;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnalyzeIVRTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ivr:analyze-template 
                            {manufacturer : Manufacturer name or ID}
                            {--template=insurance-verification : Template name}
                            {--csv= : Path to CSV file with field names}
                            {--import : Import fields from CSV}
                            {--test : Test mapping with sample data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze IVR template compatibility and field mappings';

<<<<<<< HEAD
    protected IVRMappingOrchestrator $orchestrator;

    public function __construct(IVRMappingOrchestrator $orchestrator)
    {
        parent::__construct();
        $this->orchestrator = $orchestrator;
=======
    protected UnifiedFieldMappingService $fieldMappingService;

    public function __construct(UnifiedFieldMappingService $fieldMappingService)
    {
        parent::__construct();
        $this->fieldMappingService = $fieldMappingService;
>>>>>>> origin/provider-side
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $manufacturerInput = $this->argument('manufacturer');
        $templateName = $this->option('template');
        
        // Find manufacturer
        $manufacturer = is_numeric($manufacturerInput) 
            ? Manufacturer::find($manufacturerInput)
            : Manufacturer::where('name', 'like', '%' . $manufacturerInput . '%')->first();
            
        if (!$manufacturer) {
            $this->error("Manufacturer '{$manufacturerInput}' not found.");
            return 1;
        }
        
        $this->info("Analyzing template for: {$manufacturer->name}");
        $this->info("Template: {$templateName}");
        $this->newLine();
        
        // Import CSV fields if requested
        if ($this->option('import') && $this->option('csv')) {
            $this->importFieldsFromCsv($manufacturer->id, $templateName);
            $this->newLine();
        }
        
        // Analyze template
        $analysis = $this->orchestrator->analyzeTemplateCompatibility($manufacturer->id, $templateName);
        
        // Display results
        $this->displayAnalysisResults($analysis);
        
        // Test mapping if requested
        if ($this->option('test')) {
            $this->newLine();
            $this->testMapping($manufacturer->id, $templateName);
        }
        
        return 0;
    }
    
    protected function importFieldsFromCsv(int $manufacturerId, string $templateName): void
    {
        $csvPath = $this->option('csv');
        
        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return;
        }
        
        $this->info("Importing fields from CSV...");
        
        $handle = fopen($csvPath, 'r');
        $headers = fgetcsv($handle);
        
        if (!$headers) {
            $this->error("Could not read CSV headers");
            fclose($handle);
            return;
        }
        
        $fieldsImported = 0;
        $fieldOrder = 0;
        
        // Import each column as a field
        foreach ($headers as $header) {
            $fieldName = trim($header);
            if (empty($fieldName)) continue;
            
            $existingField = IVRTemplateField::where('manufacturer_id', $manufacturerId)
                ->where('template_name', $templateName)
                ->where('field_name', $fieldName)
                ->first();
                
            if (!$existingField) {
                IVRTemplateField::create([
                    'manufacturer_id' => $manufacturerId,
                    'template_name' => $templateName,
                    'field_name' => $fieldName,
                    'field_type' => $this->detectFieldType($fieldName),
                    'is_required' => $this->isLikelyRequired($fieldName),
                    'field_order' => $fieldOrder++,
                    'section' => $this->detectSection($fieldName),
                ]);
                $fieldsImported++;
                $this->line("  ✓ Imported: {$fieldName}");
            } else {
                $this->line("  - Skipped (exists): {$fieldName}");
            }
        }
        
        fclose($handle);
        
        $this->info("Imported {$fieldsImported} new fields");
    }
    
    protected function detectFieldType(string $fieldName): string
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
        if (strpos($fieldName, 'checkbox') !== false || strpos($fieldName, 'yes/no') !== false) {
            return 'checkbox';
        }
        
        return 'text';
    }
    
    protected function isLikelyRequired(string $fieldName): bool
    {
        $requiredPatterns = [
            'patient_name', 'patient_dob', 'provider_name', 'provider_npi',
            'insurance_id', 'diagnosis', 'signature', 'date'
        ];
        
        $fieldName = strtolower($fieldName);
        foreach ($requiredPatterns as $pattern) {
            if (strpos($fieldName, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function detectSection(string $fieldName): string
    {
        $fieldName = strtolower($fieldName);
        
        if (strpos($fieldName, 'patient') !== false) return 'patient_information';
        if (strpos($fieldName, 'provider') !== false || strpos($fieldName, 'physician') !== false) return 'provider_information';
        if (strpos($fieldName, 'insurance') !== false || strpos($fieldName, 'policy') !== false) return 'insurance_information';
        if (strpos($fieldName, 'wound') !== false || strpos($fieldName, 'diagnosis') !== false) return 'clinical_information';
        if (strpos($fieldName, 'facility') !== false || strpos($fieldName, 'organization') !== false) return 'facility_information';
        if (strpos($fieldName, 'signature') !== false || strpos($fieldName, 'consent') !== false) return 'authorization';
        
        return 'other';
    }
    
    protected function displayAnalysisResults(array $analysis): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Fields', $analysis['total_fields']],
                ['Mapped Fields', $analysis['mapped_fields']],
                ['High Confidence', $analysis['high_confidence_fields']],
                ['Low Confidence', $analysis['low_confidence_fields']],
                ['Unmapped Fields', count($analysis['unmapped_fields'])],
                ['Compatibility Score', $analysis['compatibility_score'] . '%'],
            ]
        );
        
        if (!empty($analysis['unmapped_fields'])) {
            $this->newLine();
            $this->warn('Unmapped Fields:');
            foreach ($analysis['unmapped_fields'] as $field) {
                $this->line("  - {$field}");
            }
        }
        
        if ($this->option('verbose')) {
            $this->newLine();
            $this->info('Field Analysis Details:');
            $this->table(
                ['Field', 'Mapped', 'Confidence', 'Type', 'Required'],
                array_map(function ($field) {
                    return [
                        $field['field_name'],
                        $field['mapped'] ? '✓' : '✗',
                        $field['mapped'] ? ($field['confidence'] ?? 'N/A') : '-',
                        $field['mapping_type'] ?? '-',
                        $field['is_required'] ? 'Yes' : 'No',
                    ];
                }, $analysis['field_analysis'])
            );
        }
    }
    
    protected function testMapping(int $manufacturerId, string $templateName): void
    {
        $this->info('Testing mapping with sample data...');
        
        // Sample FHIR data - properly formatted for the fuzzy mapper
        $sampleFhirData = [
            'patient' => [
                'name' => 'John Doe',
                'birthDate' => '1970-01-01',
                'gender' => 'male',
                'address' => '123 Main St, Anytown, CA 12345',
                'telecom' => [
                    'phone' => '(555) 123-4567',
                    'fax' => '(555) 123-4568',
                ],
            ],
            'practitioner' => [
                'name' => 'Dr. Jane Smith',
                'identifier' => [
                    'npi' => '1234567890',
                    'tin' => '12-3456789',
                    'ptan' => 'ABC123',
                ],
                'telecom' => [
                    'phone' => '(555) 987-6543',
                    'fax' => '(555) 987-6544',
                ],
                'address' => '456 Medical Plaza, Suite 100, Anytown, CA 12345',
            ],
            'organization' => [
                'name' => 'Sample Medical Center',
                'identifier' => [
                    'npi' => '9876543210',
                    'tin' => '98-7654321',
                    'ptan' => 'XYZ789',
                ],
                'address' => '789 Hospital Blvd, Anytown, CA 12345',
                'contact' => [
                    'name' => 'Office Manager',
                    'phone' => '(555) 555-5555',
                    'fax' => '(555) 555-5556',
                    'email' => 'office@samplemedical.com',
                ],
                'medicareAdminContractor' => 'Noridian Healthcare Solutions',
            ],
            'coverage' => [
                'payor' => [
                    'display' => 'Medicare',
                    'phone' => '1-800-MEDICARE',
                ],
                'identifier' => [
                    'value' => 'ABC123456789',
                ],
                'subscriber' => [
                    'display' => 'John Doe',
                    'birthDate' => '1970-01-01',
                ],
                'class' => [
                    'type' => 'PPO',
                ],
                'network' => 'In-Network',
                'preAuthRef' => 'PA123456',
                'secondary' => [
                    'payor' => [
                        'display' => 'Blue Cross Blue Shield',
                        'phone' => '1-800-123-4567',
                    ],
                    'identifier' => [
                        'value' => 'XYZ987654321',
                    ],
                    'subscriber' => [
                        'display' => 'John Doe',
                        'birthDate' => '1970-01-01',
                    ],
                    'class' => [
                        'type' => 'HMO',
                    ],
                    'network' => 'In-Network',
                ],
            ],
            'condition' => [
                'code' => [
                    'coding' => [
                        'code' => 'E11.621',
                    ],
                    'text' => 'Diabetic foot ulcer',
                ],
                'bodySite' => 'Left foot, plantar aspect',
                'onsetPeriod' => '3 months',
                'woundType' => [
                    'diabeticFootUlcer' => true,
                ],
            ],
            'procedure' => [
                'code' => [
                    'cpt' => '15275',
                ],
                'performedDateTime' => '2024-01-15',
                'followUp' => 'No',
            ],
            'observation' => [
                'woundSize' => '3.5cm x 2.0cm x 0.5cm',
            ],
            'deviceRequest' => [
                'product' => [
                    'info' => 'Advanced Wound Care Product',
                    'size' => '5cm x 5cm',
                ],
                'quantity' => '4',
            ],
            'encounter' => [
                'hospitalization' => [
                    'admitSource' => 'Home',
                ],
                'length' => '0',
            ],
            'consent' => [
                'provision' => [
                    'actor' => 'Dr. Jane Smith',
                ],
                'dateTime' => '2024-01-15',
            ],
            'documentReference' => [
                'clinicalNotes' => 'Yes',
                'insuranceCard' => 'Yes',
            ],
            'history' => [
                'previousSurgery' => [
                    'cpt' => '15272',
                    'date' => '2023-10-01',
                ],
            ],
        ];
        
        $additionalData = [
            'metadata' => [
                'salesRep' => 'John Sales Rep',
                'iso' => 'ISO123',
                'distributor' => 'Sample Distributor',
                'notificationEmails' => 'notifications@example.com',
            ],
            'patient' => [
                'communication' => [
                    'okToContact' => 'Yes',
                ],
            ],
            'episodeOfCare' => [
                'type' => 'New Application',
            ],
            'researchStudy' => [
                'enrollment' => 'No',
            ],
            'timing' => [
                'repeat' => [
                    'frequency' => 'Weekly',
                ],
            ],
        ];
        
        $result = $this->orchestrator->mapDataForIVR(
            $sampleFhirData,
            $additionalData,
            $manufacturerId,
            $templateName
        );
        
        // Always show statistics
        $this->newLine();
        $this->info('Mapping Statistics:');
        $this->table(
            ['Metric', 'Value'],
            collect($result['statistics'] ?? [])->map(function ($value, $key) {
                return [Str::title(str_replace('_', ' ', $key)), $value];
            })->toArray()
        );
        
        if ($result['success']) {
            $this->info('✓ Mapping successful!');
            
            // Use detailed fields if available
            $mappedFields = isset($result['mapped_fields_detailed']) 
                ? $result['mapped_fields_detailed'] 
                : $result['mapped_fields'];
            
            $tableData = [];
            foreach ($mappedFields as $field => $data) {
                if (is_array($data) && isset($data['value'])) {
                    // Structured format
                    $tableData[] = [
                        $field,
                        substr($data['value'] ?? '', 0, 30),
                        $data['strategy'] ?? '-',
                        isset($data['confidence']) ? round($data['confidence'] * 100) . '%' : '-',
                    ];
                } else {
                    // Simple string format
                    $tableData[] = [
                        $field,
                        substr((string)$data, 0, 30),
                        'mapped',
                        '100%',
                    ];
                }
            }
            
            if (!empty($tableData)) {
                $this->table(['Field', 'Value', 'Strategy', 'Confidence'], $tableData);
            } else {
                $this->warn('No fields were mapped.');
            }
            
            if (!empty($result['validation']['errors'])) {
                $this->newLine();
                $this->warn('Validation Errors:');
                foreach ($result['validation']['errors'] as $field => $errors) {
                    $this->line("  {$field}:");
                    foreach ($errors as $error) {
                        $this->line("    - {$error}");
                    }
                }
            }
        } else {
            $this->error('✗ Mapping failed!');
            $this->error($result['error'] ?? 'Unknown error');
            
            if (isset($result['validation']['errors'])) {
                $this->newLine();
                $this->warn('Errors:');
                foreach ($result['validation']['errors'] as $field => $errors) {
                    $this->line("  {$field}:");
                    foreach ($errors as $error) {
                        $this->line("    - {$error}");
                    }
                }
            }
        }
    }
}