<?php

namespace App\Console\Commands;

use App\Services\DocuSeal\TemplateFieldValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ExportDocusealTemplateFields extends Command
{
    protected $signature = 'docuseal:export-fields
        {templateIds* : One or more DocuSeal template IDs}
        {--manufacturer= : Manufacturer name (for folder slug)}
        {--csv : Also write a CSV alongside JSON}
        {--out= : Custom output directory (defaults to knowledge-base/data/IVRs/<slug>)}
        {--dry-run : Do not write files, just print summary}';

    protected $description = 'Export live DocuSeal template field names for specific template IDs to JSON/CSV inventory files';

    public function handle(TemplateFieldValidationService $validator): int
    {
        $ids = (array) $this->argument('templateIds');
        $mfr = (string) ($this->option('manufacturer') ?? 'unknown-manufacturer');
        $dry = (bool) $this->option('dry-run');
        $doCsv = (bool) $this->option('csv');

        $slug = Str::slug($mfr);
        $baseOut = (string) ($this->option('out') ?: base_path("knowledge-base/data/IVRs/{$slug}"));

        $this->info("Manufacturer: {$mfr} (slug: {$slug})");
        $this->info('Templates: ' . implode(', ', $ids));

        if (!$dry && !is_dir($baseOut)) {
            mkdir($baseOut, 0755, true);
        }

        $summary = [];
        foreach ($ids as $id) {
            $tplId = (string) $id;
            $fields = $validator->getTemplateFields($tplId);
            $fields = array_values(array_unique(array_filter($fields)));

            $count = count($fields);
            $summary[] = ['template_id' => $tplId, 'count' => $count];

            $this->line(" - {$tplId}: {$count} fields");

            if ($dry) {
                continue;
            }

            $jsonPath = rtrim($baseOut, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$tplId}-fields.json";
            file_put_contents($jsonPath, json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($doCsv) {
                $csvPath = rtrim($baseOut, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$tplId}-fields.csv";
                $fp = fopen($csvPath, 'w');
                fputcsv($fp, ['name']);
                foreach ($fields as $f) {
                    fputcsv($fp, [$f]);
                }
                fclose($fp);
            }
        }

        $this->table(['Template ID', 'Field Count'], $summary);
        if ($dry) {
            $this->comment('Dry run complete (no files written).');
        } else {
            $this->info("Wrote inventories under: {$baseOut}");
        }

        return self::SUCCESS;
    }
}
