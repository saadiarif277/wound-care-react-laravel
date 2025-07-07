<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ListAvailablePdfsCommand extends Command
{
    protected $signature = 'docuseal:list-pdfs {--show-mapping : Show suggested template mappings}';
    protected $description = 'List all available PDF files in docs/ivr-forms and their potential template mappings';

    public function handle(): int
    {
        $this->info('📁 Available PDF Files in docs/ivr-forms');
        $this->newLine();

        $docsPath = base_path('docs/ivr-forms');
        if (!file_exists($docsPath)) {
            $this->error('docs/ivr-forms directory not found');
            return 1;
        }

        $pdfs = $this->scanForPdfs($docsPath);
        $templates = DocusealTemplate::with('manufacturer')->get();

        // Display PDF files grouped by directory
        foreach ($pdfs as $directory => $files) {
            $this->info("📂 $directory");
            foreach ($files as $file) {
                $this->line("   📄 $file");
            }
            $this->newLine();
        }

        // Show mapping suggestions if requested
        if ($this->option('show-mapping')) {
            $this->info('🔗 Suggested Template Mappings:');
            $this->newLine();

            $mappings = [];
            foreach ($templates as $template) {
                $bestMatch = $this->findBestPdfMatch($template, $pdfs);
                if ($bestMatch) {
                    $mappings[] = [
                        'Template' => $template->template_name,
                        'Manufacturer' => $template->manufacturer->name ?? 'N/A',
                        'Best PDF Match' => $bestMatch['file'],
                        'Directory' => $bestMatch['directory'],
                        'Score' => $bestMatch['score']
                    ];
                } else {
                    $mappings[] = [
                        'Template' => $template->template_name,
                        'Manufacturer' => $template->manufacturer->name ?? 'N/A',
                        'Best PDF Match' => '❌ No match found',
                        'Directory' => '-',
                        'Score' => 0
                    ];
                }
            }

            $this->table(
                ['Template', 'Manufacturer', 'Best PDF Match', 'Directory', 'Score'],
                $mappings
            );

            // Generate manual mapping code
            $this->newLine();
            $this->info('💡 Suggested manual mappings for EnhanceDocusealFieldsWithOcrCommand:');
            $this->line('');
            $this->line('private array $manualPdfMappings = [');
            foreach ($mappings as $mapping) {
                if ($mapping['Score'] > 0) {
                    $path = $mapping['Directory'] . '/' . $mapping['Best PDF Match'];
                    $this->line("    '{$mapping['Template']}' => '{$path}',");
                }
            }
            $this->line('];');
        }

        return 0;
    }

    private function scanForPdfs(string $basePath): array
    {
        $pdfs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'pdf') {
                $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', dirname($file->getRealPath()));
                $relativePath = str_replace('\\', '/', $relativePath);
                
                if (!isset($pdfs[$relativePath])) {
                    $pdfs[$relativePath] = [];
                }
                $pdfs[$relativePath][] = $file->getBasename();
            }
        }

        ksort($pdfs);
        return $pdfs;
    }

    private function findBestPdfMatch(DocusealTemplate $template, array $pdfs): ?array
    {
        $bestMatch = null;
        $bestScore = 0;

        $templateName = $template->template_name;
        $manufacturerName = $template->manufacturer->name ?? '';

        foreach ($pdfs as $directory => $files) {
            foreach ($files as $file) {
                $score = $this->calculateMatchScore($templateName, $manufacturerName, $file, $directory);
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [
                        'file' => $file,
                        'directory' => $directory,
                        'score' => $score
                    ];
                }
            }
        }

        return $bestMatch;
    }

    private function calculateMatchScore(string $templateName, string $manufacturerName, string $fileName, string $directory): int
    {
        $score = 0;
        
        // Normalize strings for comparison
        $templateLower = strtolower($templateName);
        $manufacturerLower = strtolower($manufacturerName);
        $fileLower = strtolower($fileName);
        $directoryLower = strtolower($directory);
        
        // Remove common noise words
        $normalizedManufacturer = str_replace(['&', 'solutions', 'llc', ' '], '', $manufacturerLower);
        $normalizedDirectory = str_replace(['&', 'solutions', 'llc', ' '], '', $directoryLower);
        $normalizedFile = str_replace(['&', 'solutions', 'llc', ' '], '', $fileLower);
        
        // Check manufacturer matches
        if (!empty($manufacturerName)) {
            if (stripos($normalizedDirectory, $normalizedManufacturer) !== false) {
                $score += 50;
            }
            if (stripos($normalizedFile, $normalizedManufacturer) !== false) {
                $score += 30;
            }
        }
        
        // Check for IVR matches
        if (stripos($templateLower, 'ivr') !== false && stripos($fileLower, 'ivr') !== false) {
            $score += 40;
        }
        
        // Special case matches
        if ($manufacturerName === 'MEDLIFE SOLUTIONS' && stripos($fileLower, 'amnio') !== false) {
            $score += 50;
        }
        if ($manufacturerName === 'ADVANCED SOLUTION' && stripos($fileLower, 'advanced solution') !== false) {
            $score += 50;
        }
        if ($manufacturerName === 'Extremity Care LLC') {
            if (stripos($templateLower, 'restorigin') !== false && stripos($fileLower, 'restorigin') !== false) {
                $score += 70;
            }
            if (stripos($templateLower, 'coll-e-derm') !== false && stripos($fileLower, 'coll-e-derm') !== false) {
                $score += 70;
            }
        }
        if ($manufacturerName === 'BIOWOUND SOLUTIONS' && stripos($directoryLower, 'biowound') !== false) {
            $score += 50;
        }
        if ($manufacturerName === 'CENTURION THERAPEUTICS' && stripos($directoryLower, 'centurion') !== false) {
            $score += 50;
        }
        
        // Check for order form matches
        if (stripos($templateLower, 'order') !== false && stripos($fileLower, 'order') !== false) {
            $score += 30;
        }
        
        // Check for form matches
        if (stripos($templateLower, 'form') !== false && stripos($fileLower, 'form') !== false) {
            $score += 20;
        }
        
        return $score;
    }
}
