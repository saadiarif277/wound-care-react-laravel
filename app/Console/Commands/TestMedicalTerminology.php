<?php

namespace App\Console\Commands;

use App\Services\MedicalTerminologyService;
use Illuminate\Console\Command;

class TestMedicalTerminology extends Command
{
    protected $signature = 'medical:test-terminology {action=demo} {--term=} {--code=} {--cui=}';
    
    protected $description = 'Test the enhanced Medical Terminology Service with UMLS integration';
    
    private MedicalTerminologyService $medicalService;
    
    public function __construct(MedicalTerminologyService $medicalService)
    {
        parent::__construct();
        $this->medicalService = $medicalService;
    }
    
    public function handle()
    {
        $action = $this->argument('action');
        
        switch ($action) {
            case 'demo':
                $this->runFullDemo();
                break;
                
            case 'search':
                $this->searchConcepts();
                break;
                
            case 'validate':
                $this->validateTerms();
                break;
                
            case 'crosswalk':
                $this->crosswalkCodes();
                break;
                
            case 'suggest':
                $this->suggestCodes();
                break;
                
            case 'icd10':
                $this->validateICD10();
                break;
                
            case 'cpt':
                $this->validateCPT();
                break;
                
            case 'stats':
                $this->showStats();
                break;
                
            default:
                $this->error("Unknown action: $action");
                $this->info("Available actions: demo, search, validate, crosswalk, suggest, icd10, cpt, stats");
        }
    }
    
    private function runFullDemo(): void
    {
        $this->info('🏥 Medical Terminology Service Demo with UMLS Integration');
        $this->line('');
        
        // 1. Test UMLS Connection
        $this->info('1️⃣ Testing UMLS Connection...');
        $stats = $this->medicalService->getTerminologyStats();
        
        if ($stats['umls_enabled']) {
            $this->info('✅ UMLS API is enabled');
            $umlsStatus = $stats['umls_status'];
            if ($umlsStatus['connected']) {
                $this->info("✅ UMLS API connected (Response time: {$umlsStatus['response_time']})");
            } else {
                $this->error("❌ UMLS API connection failed: {$umlsStatus['error']}");
            }
        } else {
            $this->warn('⚠️ UMLS API is not configured (using local dictionaries only)');
        }
        
        $this->line('');
        
        // 2. Search for Medical Concepts
        $this->info('2️⃣ Searching for Medical Concepts...');
        $searchTerm = 'diabetic foot ulcer';
        $this->info("Searching for: '$searchTerm'");
        
        $searchResults = $this->medicalService->searchConcepts($searchTerm, ['pageSize' => 3]);
        if ($searchResults['success']) {
            $this->table(
                ['CUI', 'Name', 'Semantic Types'],
                collect($searchResults['results'])->map(fn($r) => [
                    $r['cui'],
                    $r['name'],
                    implode(', ', array_slice($r['semantic_types'] ?? [], 0, 2))
                ])->toArray()
            );
            
            // Get details for the first result
            if (!empty($searchResults['results'])) {
                $cui = $searchResults['results'][0]['cui'];
                $this->info("Getting details for CUI: $cui");
                
                $details = $this->medicalService->getConceptDetails($cui);
                if ($details['success']) {
                    $this->info("Name: {$details['name']}");
                    $this->info("Atoms Count: {$details['atoms_count']}");
                    $this->info("Relations Count: {$details['relations_count']}");
                    
                    // Get definitions
                    $definitions = $this->medicalService->getConceptDefinitions($cui);
                    if ($definitions['success'] && !empty($definitions['definitions'])) {
                        $this->info("Definition: " . $definitions['definitions'][0]['value']);
                    }
                }
            }
        }
        
        $this->line('');
        
        // 3. Validate Medical Terms
        $this->info('3️⃣ Validating Medical Terms...');
        $termsToValidate = [
            'pressure_ulcer',
            'diabetic_foot_ulcer', 
            'venous_stasis_ulcer',
            'invalid_wound_type'
        ];
        
        $validationResults = $this->medicalService->validateMedicalTerms($termsToValidate, 'wound_care');
        
        $this->info("Overall Confidence: " . round($validationResults['overall_confidence'] * 100, 2) . "%");
        $this->info("Valid Terms: {$validationResults['valid_terms']}/{$validationResults['total_terms']}");
        
        $this->table(
            ['Term', 'Valid', 'Confidence', 'Category', 'Source'],
            collect($validationResults['validation_results'])->map(fn($r) => [
                $r['original_term'],
                $r['is_valid'] ? '✅' : '❌',
                round($r['confidence'] * 100, 2) . '%',
                $r['matched_category'] ?? 'N/A',
                $r['umls_validation'] ? 'UMLS' : 'Local'
            ])->toArray()
        );
        
        $this->line('');
        
        // 4. ICD-10 to CPT Mapping
        $this->info('4️⃣ ICD-10 to CPT Code Mapping...');
        $icd10Code = 'L89.0'; // Pressure ulcer of unspecified elbow
        $this->info("Mapping ICD-10 code: $icd10Code");
        
        $mapping = $this->medicalService->mapICD10ToCPT($icd10Code);
        if ($mapping['success'] && !empty($mapping['cpt_codes'])) {
            $this->table(
                ['CPT Code', 'Description', 'Confidence'],
                collect($mapping['cpt_codes'])->map(fn($c) => [
                    $c['code'],
                    $c['name'],
                    round($c['confidence'] * 100, 2) . '%'
                ])->toArray()
            );
        } else {
            $this->warn('No CPT mappings found');
        }
        
        $this->line('');
        
        // 5. Code Suggestions
        $this->info('5️⃣ Medical Code Suggestions...');
        $description = 'wound debridement';
        $this->info("Getting code suggestions for: '$description'");
        
        $suggestions = $this->medicalService->suggestMedicalCodes($description, 'cpt');
        if (!empty($suggestions)) {
            $this->table(
                ['Code System', 'Code', 'Description', 'Confidence'],
                collect($suggestions)->slice(0, 5)->map(fn($s) => [
                    $s['code_system'],
                    $s['code'],
                    substr($s['description'], 0, 50) . '...',
                    round($s['confidence'] * 100, 2) . '%'
                ])->toArray()
            );
        }
        
        $this->line('');
        
        // 6. Crosswalk Example
        $this->info('6️⃣ Code Crosswalk Example...');
        $icd10 = 'E11.9'; // Type 2 diabetes mellitus without complications
        $this->info("Cross-referencing ICD-10 code: $icd10");
        
        $crosswalk = $this->medicalService->crosswalkCode('ICD10CM', $icd10);
        if ($crosswalk['success']) {
            $this->info("Total mappings found: {$crosswalk['total_mappings']}");
            foreach ($crosswalk['crosswalks'] as $vocab => $mappings) {
                $this->info("$vocab: " . count($mappings) . " mappings");
            }
        }
        
        $this->line('');
        $this->info('✨ Demo completed! Use other actions to explore specific features.');
    }
    
    private function searchConcepts(): void
    {
        $term = $this->option('term') ?? $this->ask('Enter search term');
        
        $this->info("Searching for: $term");
        
        $results = $this->medicalService->searchConcepts($term, [
            'pageSize' => 10,
            'sabs' => 'SNOMEDCT_US,ICD10CM,CPT'
        ]);
        
        if ($results['success']) {
            $this->info("Found {$results['total']} results");
            
            $this->table(
                ['CUI', 'Name', 'Source', 'Semantic Types'],
                collect($results['results'])->map(fn($r) => [
                    $r['cui'],
                    substr($r['name'], 0, 60),
                    $r['source'] ?? 'N/A',
                    implode(', ', array_slice($r['semantic_types'] ?? [], 0, 2))
                ])->toArray()
            );
        } else {
            $this->error("Search failed: {$results['error']}");
        }
    }
    
    private function validateTerms(): void
    {
        $term = $this->option('term') ?? $this->ask('Enter term to validate');
        
        $results = $this->medicalService->validateMedicalTerms([$term], 'general');
        $result = $results['validation_results'][0];
        
        $this->info("Term: {$result['original_term']}");
        $this->info("Valid: " . ($result['is_valid'] ? 'Yes ✅' : 'No ❌'));
        $this->info("Confidence: " . round($result['confidence'] * 100, 2) . "%");
        
        if ($result['matched_category']) {
            $this->info("Category: {$result['matched_category']}");
        }
        
        if ($result['umls_validation'] && $result['umls_validation']['is_valid']) {
            $this->info("UMLS CUI: {$result['umls_validation']['umls_cui']}");
            $this->info("Preferred Name: {$result['umls_validation']['preferred_name']}");
        }
        
        if (!empty($result['suggestions'])) {
            $this->info("\nSuggestions:");
            foreach ($result['suggestions'] as $suggestion) {
                $this->line("  - {$suggestion['term']} (similarity: " . round($suggestion['similarity'] * 100) . "%)");
            }
        }
    }
    
    private function crosswalkCodes(): void
    {
        $code = $this->option('code') ?? $this->ask('Enter code (e.g., E11.9)');
        $source = $this->ask('Enter source vocabulary (e.g., ICD10CM)', 'ICD10CM');
        
        $this->info("Cross-referencing $source code: $code");
        
        $crosswalk = $this->medicalService->crosswalkCode($source, $code);
        
        if ($crosswalk['success']) {
            $this->info("Found {$crosswalk['total_mappings']} mappings");
            
            foreach ($crosswalk['crosswalks'] as $targetVocab => $mappings) {
                $this->info("\n$targetVocab mappings:");
                foreach (array_slice($mappings, 0, 5) as $mapping) {
                    $this->line("  - {$mapping['target_code']}: {$mapping['name']}");
                }
            }
        } else {
            $this->error("Crosswalk failed: {$crosswalk['error']}");
        }
    }
    
    private function suggestCodes(): void
    {
        $description = $this->option('term') ?? $this->ask('Enter description');
        $codeType = $this->ask('Code type (all/icd10/cpt/hcpcs)', 'all');
        
        $this->info("Getting code suggestions for: '$description'");
        
        $suggestions = $this->medicalService->suggestMedicalCodes($description, $codeType);
        
        if (!empty($suggestions)) {
            $this->table(
                ['Code System', 'Code', 'Description', 'Confidence'],
                collect($suggestions)->map(fn($s) => [
                    $s['code_system'],
                    $s['code'],
                    substr($s['description'], 0, 60),
                    round($s['confidence'] * 100, 2) . '%'
                ])->toArray()
            );
        } else {
            $this->warn('No suggestions found');
        }
    }
    
    private function validateICD10(): void
    {
        $code = $this->option('code') ?? $this->ask('Enter ICD-10 code');
        
        $this->info("Validating ICD-10 code: $code");
        
        $result = $this->medicalService->validateICD10Code($code);
        
        if ($result['valid']) {
            $this->info("✅ Valid ICD-10 code");
            $this->info("Description: {$result['description']}");
            $this->info("CUI: {$result['cui']}");
            $this->info("Billable: " . ($result['is_billable'] ? 'Yes' : 'No'));
            
            if (!empty($result['parent_codes'])) {
                $this->info("\nParent codes:");
                foreach ($result['parent_codes'] as $parent) {
                    $this->line("  - {$parent['cui']}: {$parent['name']}");
                }
            }
        } else {
            $this->error("❌ Invalid ICD-10 code: {$result['error']}");
        }
    }
    
    private function validateCPT(): void
    {
        $code = $this->option('code') ?? $this->ask('Enter CPT code');
        
        $this->info("Validating CPT code: $code");
        
        $result = $this->medicalService->validateCPTCode($code);
        
        if ($result['valid']) {
            $this->info("✅ Valid CPT code");
            $this->info("Description: {$result['description']}");
            $this->info("CUI: {$result['cui']}");
            
            if (!empty($result['related_procedures'])) {
                $this->info("\nRelated procedures:");
                foreach ($result['related_procedures'] as $related) {
                    $this->line("  - {$related['code']}: {$related['name']}");
                }
            }
        } else {
            $this->error("❌ Invalid CPT code: {$result['error']}");
        }
    }
    
    private function showStats(): void
    {
        $stats = $this->medicalService->getTerminologyStats();
        
        $this->info('Medical Terminology Service Statistics');
        $this->line('');
        
        $this->info("Total Dictionaries: {$stats['total_dictionaries']}");
        $this->info("Total Categories: {$stats['total_categories']}");
        $this->info("Total Terms: {$stats['total_terms']}");
        $this->info("UMLS Enabled: " . ($stats['umls_enabled'] ? 'Yes ✅' : 'No ❌'));
        
        $this->line('');
        $this->info('Dictionary Breakdown:');
        
        foreach ($stats['dictionaries'] as $name => $info) {
            $this->line("  {$name}: {$info['terms']} terms in {$info['categories']} categories");
        }
        
        if ($stats['umls_enabled'] && isset($stats['umls_status'])) {
            $this->line('');
            $this->info('UMLS Connection Status:');
            $umlsStatus = $stats['umls_status'];
            
            if ($umlsStatus['connected']) {
                $this->info("  Status: Connected ✅");
                $this->info("  Response Time: {$umlsStatus['response_time']}");
                $this->info("  API Version: {$umlsStatus['api_version']}");
            } else {
                $this->error("  Status: Disconnected ❌");
                $this->error("  Error: {$umlsStatus['error']}");
            }
        }
    }
} 