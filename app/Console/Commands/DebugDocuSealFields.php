<?php

namespace App\Console\Commands;

use App\Services\DocusealService;
use App\Services\UnifiedFieldMappingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DebugDocuSealFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docuseal:debug-fields 
                            {episodeId : The episode ID to test}
                            {manufacturer : The manufacturer name (e.g., ACZ, MedLife)}
                            {--show-values : Show actual field values}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug field mapping between episode data and DocuSeal template';

    /**
     * Execute the console command.
     */
    public function handle(DocusealService $docuSealService, UnifiedFieldMappingService $fieldMappingService): int
    {
        $episodeId = $this->argument('episodeId');
        $manufacturerName = $this->argument('manufacturer');
        $showValues = $this->option('show-values');

        $this->info("Debugging DocuSeal field mapping for Episode #{$episodeId} with manufacturer {$manufacturerName}");
        $this->newLine();

        try {
            // Step 1: Get mapped data using unified service
            $this->info('Step 1: Getting mapped data from UnifiedFieldMappingService...');
            $mappingResult = $fieldMappingService->mapEpisodeToTemplate($episodeId, $manufacturerName);
            
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total mapped fields', count($mappingResult['data'])],
                    ['Completeness', $mappingResult['completeness']['percentage'] . '%'],
                    ['Required completeness', $mappingResult['completeness']['required_percentage'] . '%'],
                    ['Valid', $mappingResult['validation']['valid'] ? 'Yes' : 'No'],
                    ['Template ID', $mappingResult['manufacturer']['template_id']],
                ]
            );

            if (!empty($mappingResult['validation']['errors'])) {
                $this->error('Validation Errors:');
                foreach ($mappingResult['validation']['errors'] as $error) {
                    $this->line("  - {$error}");
                }
            }

            if (!empty($mappingResult['validation']['warnings'])) {
                $this->warn('Validation Warnings:');
                foreach ($mappingResult['validation']['warnings'] as $warning) {
                    $this->line("  - {$warning}");
                }
            }

            $this->newLine();

            // Step 2: Get template fields from DocuSeal
            $this->info('Step 2: Getting template fields from DocuSeal API...');
            $templateId = $mappingResult['manufacturer']['template_id'];
            $templateFields = $docuSealService->getTemplateFieldsFromAPI($templateId);

            $this->line("Template has " . count($templateFields) . " fields defined in DocuSeal");
            $this->newLine();

            // Step 3: Compare mapped fields with template fields
            $this->info('Step 3: Comparing mapped fields with template fields...');
            
            $mappedFieldNames = array_keys($mappingResult['data']);
            $templateFieldNames = array_keys($templateFields);
            
            $matchingFields = array_intersect($mappedFieldNames, $templateFieldNames);
            $missingInTemplate = array_diff($mappedFieldNames, $templateFieldNames);
            $unusedTemplateFields = array_diff($templateFieldNames, $mappedFieldNames);

            $this->table(
                ['Comparison', 'Count'],
                [
                    ['Matching fields', count($matchingFields)],
                    ['Fields not in template', count($missingInTemplate)],
                    ['Unused template fields', count($unusedTemplateFields)],
                ]
            );

            if (!empty($missingInTemplate)) {
                $this->error('Fields that will be SKIPPED (not in DocuSeal template):');
                foreach ($missingInTemplate as $field) {
                    $value = $showValues ? ' = ' . json_encode($mappingResult['data'][$field]) : '';
                    $this->line("  - {$field}{$value}");
                }
                $this->newLine();
            }

            if (!empty($unusedTemplateFields)) {
                $this->warn('Template fields not being populated:');
                foreach ($unusedTemplateFields as $field) {
                    $fieldInfo = $templateFields[$field];
                    $required = $fieldInfo['required'] ? ' (REQUIRED)' : '';
                    $type = $fieldInfo['type'] ?? 'unknown';
                    $this->line("  - {$field} [{$type}]{$required}");
                }
                $this->newLine();
            }

            if (!empty($matchingFields)) {
                $this->info('Successfully mapped fields:');
                $tableData = [];
                foreach ($matchingFields as $field) {
                    $row = [$field, $templateFields[$field]['type'] ?? 'text'];
                    if ($showValues) {
                        $row[] = json_encode($mappingResult['data'][$field]);
                    }
                    $row[] = $templateFields[$field]['required'] ? 'Yes' : 'No';
                    $tableData[] = $row;
                }
                
                $headers = ['Field Name', 'Type'];
                if ($showValues) {
                    $headers[] = 'Value';
                }
                $headers[] = 'Required';
                
                $this->table($headers, $tableData);
            }

            // Step 4: Show what would be sent to DocuSeal
            $this->newLine();
            $this->info('Step 4: Preview of data that will be sent to DocuSeal:');
            
            // Simulate the preparation
            $preparedFields = [];
            foreach ($mappingResult['data'] as $key => $value) {
                if (!str_starts_with($key, '_') && isset($templateFields[$key])) {
                    // Convert value as DocuSeal would
                    if ($value === null) {
                        $value = '';
                    } elseif (is_array($value)) {
                        $value = implode(', ', $value);
                    } elseif (is_bool($value)) {
                        $value = $value ? 'Yes' : 'No';
                    }
                    
                    $fieldId = $templateFields[$key]['id'] ?? $key;
                    $preparedFields[] = [
                        'name' => $fieldId,
                        'value' => (string) $value,
                    ];
                }
            }

            $this->line("Will send " . count($preparedFields) . " fields to DocuSeal");
            
            if ($this->confirm('Do you want to see the prepared fields?')) {
                foreach ($preparedFields as $field) {
                    $this->line("  - {$field['name']} = {$field['value']}");
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}