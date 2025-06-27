<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OcrFieldDetectionService;

class TestOcrDirectCommand extends Command
{
    protected $signature = 'docuseal:test-ocr-direct {pdf-path}';
    protected $description = 'Test OCR service directly with a specific PDF file';

    private OcrFieldDetectionService $ocrFieldDetection;

    public function __construct(OcrFieldDetectionService $ocrFieldDetection)
    {
        parent::__construct();
        $this->ocrFieldDetection = $ocrFieldDetection;
    }

    public function handle(): int
    {
        $pdfPath = $this->argument('pdf-path');
        
        if (!file_exists($pdfPath)) {
            $this->error("PDF file not found: {$pdfPath}");
            return 1;
        }

        $this->info("🔍 Testing OCR on: {$pdfPath}");
        $this->info("📏 File size: " . number_format(filesize($pdfPath)) . " bytes");

        try {
            $this->line("🤖 Calling OCR service...");
            $fields = $this->ocrFieldDetection->extractFieldLabelsFromPdf($pdfPath);
            
            $this->info("✅ OCR completed successfully!");
            $this->info("📊 Fields detected: " . count($fields));
            
            if (!empty($fields)) {
                $this->table(['Label', 'Type', 'Confidence', 'Suggested System Field'], 
                    array_map(fn($field) => [
                        $field['label'],
                        $field['type'],
                        $field['confidence'],
                        $field['suggested_system_field']
                    ], $fields)
                );
            } else {
                $this->warn("No fields were detected.");
            }

        } catch (\Exception $e) {
            $this->error("❌ OCR failed: " . $e->getMessage());
            $this->line("Stack trace:");
            $this->line($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
