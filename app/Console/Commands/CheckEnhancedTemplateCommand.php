<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckEnhancedTemplateCommand extends Command
{
    protected $signature = 'docuseal:check-enhanced {template-id}';
    protected $description = 'Check enhanced template field mappings and OCR metadata';

    public function handle(): int
    {
        $templateId = $this->argument('template-id');
        
        $template = DocusealTemplate::where('docuseal_template_id', $templateId)->first();
        if (!$template) {
            $this->error("Template not found: {$templateId}");
            return 1;
        }

        $this->info("🔍 Template: {$template->template_name}");
        $this->info("📋 ID: {$template->docuseal_template_id}");
        $this->newLine();

        // Show field mappings
        $this->info("📊 Field Mappings:");
        $mappings = $template->field_mappings ?? [];
        foreach ($mappings as $fieldName => $mapping) {
            if (is_array($mapping)) {
                $label = $mapping['field_label'] ?? $fieldName;
                $systemField = $mapping['system_field'] ?? 'N/A';
                $ocrDetected = $mapping['ocr_detected'] ?? false;
                $confidence = $mapping['ocr_confidence'] ?? null;
                
                $status = $ocrDetected ? ' ✅ (OCR)' : '';
                $confStr = $confidence ? " [{$confidence}]" : '';
                
                $this->line("  • {$fieldName}: {$label} → {$systemField}{$status}{$confStr}");
            } else {
                $this->line("  • {$fieldName}: {$mapping} (legacy format)");
            }
        }

        $this->newLine();

        // Show OCR metadata
        $this->info("🤖 OCR Enhancement Metadata:");
        $metadata = $template->extraction_metadata ?? [];
        if (isset($metadata['ocr_enhanced']) && $metadata['ocr_enhanced']) {
            $this->line("  ✅ OCR Enhanced: Yes");
            $this->line("  📅 Enhancement Date: " . ($metadata['ocr_enhancement_date'] ?? 'N/A'));
            $this->line("  🔍 Fields Detected: " . ($metadata['ocr_fields_detected'] ?? 'N/A'));
            $this->line("  ⬆️  Field Improvements: " . ($metadata['ocr_field_improvements'] ?? 'N/A'));
            $this->line("  🆕 New Fields: " . ($metadata['ocr_new_fields'] ?? 'N/A'));
            
            if (isset($metadata['ocr_comparison_summary'])) {
                $summary = $metadata['ocr_comparison_summary'];
                $this->line("  📊 Comparison Summary:");
                $this->line("    - Matched Fields: " . ($summary['matched_fields'] ?? 'N/A'));
                $this->line("    - OCR Only: " . ($summary['ocr_only_fields'] ?? 'N/A'));
                $this->line("    - Docuseal Only: " . ($summary['docuseal_only_fields'] ?? 'N/A'));
                $this->line("    - Mapping Suggestions: " . ($summary['mapping_suggestions'] ?? 'N/A'));
            }
        } else {
            $this->line("  ❌ Not OCR Enhanced");
        }

        return 0;
    }
}
