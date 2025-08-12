<?php

namespace App\Console\Commands;

use App\Services\DocuSeal\DocuSealApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ExportDocusealFieldsCommand extends Command
{
    protected $signature = 'docuseal:export-fields 
        {template_id* : One or more DocuSeal template IDs}
        {--manufacturer= : Manufacturer name for folder resolution}
        {--doc=IVRs : Document type folder (IVRs|OrderForms|Onboarding)}
        {--format=json : Output format (json|csv)}
        {--out= : Override output directory (defaults to knowledge-base/data/<doc>/<manufacturer>)}
        {--dry-run : Print results without writing files}';

    protected $description = 'Export live DocuSeal template field names to knowledge-base for validation fallbacks.';

    public function handle(DocuSealApiClient $client): int
    {
    $docType = $this->option('doc') ?: 'IVRs';
        $format = strtolower((string) $this->option('format') ?: 'json');
        if (!in_array($format, ['json', 'csv'], true)) {
            $this->error('Invalid --format. Use json or csv.');
            return self::FAILURE;
        }

    $manufacturer = (string) ($this->option('manufacturer') ?: 'unknown');
    $slug = Str::slug($manufacturer) ?: 'unknown';

        $templateIds = (array) $this->argument('template_id');
        $all = [];

        foreach ($templateIds as $tid) {
            try {
                $tpl = $client->getTemplate((string) $tid);
                $fields = [];
                if (isset($tpl['fields']) && is_array($tpl['fields'])) {
                    foreach ($tpl['fields'] as $f) {
                        $name = $f['name'] ?? null;
                        if ($name) $fields[] = $name;
                    }
                }

                $this->info("Template {$tid}: ".count($fields).' fields');
                $all[$tid] = array_values(array_unique($fields));
            } catch (\Throwable $e) {
                $this->warn("Failed to fetch template {$tid}: ".$e->getMessage());
                $all[$tid] = [];
            }
        }

        // Merge unique for combined inventory
        $merged = array_values(array_unique(array_merge(...array_values($all))));

        // Determine output dir
        $baseOut = $this->option('out');
        if (!$baseOut) {
            // Prefer repository conventions: knowledge-base/data/IVRs/<Exact Manufacturer Folder>
            $dataRoot = base_path('knowledge-base/data');
            $docFolder = $docType; // allow arbitrary but default aligns with 'IVRs'

            // Try to find an existing directory that matches the provided manufacturer (case-insensitive)
            $manufacturerDir = $slug; // fallback to slug if none exists
            $candidateRoot = rtrim($dataRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $docFolder;
            if (is_dir($candidateRoot)) {
                foreach (scandir($candidateRoot) as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    $full = $candidateRoot . DIRECTORY_SEPARATOR . $entry;
                    if (is_dir($full) && strcasecmp($entry, $manufacturer) === 0) {
                        $manufacturerDir = $entry; // use exact folder name from repo
                        break;
                    }
                }
            }

            $baseOut = $candidateRoot . DIRECTORY_SEPARATOR . $manufacturerDir;
        }

        $this->line('Output directory: '.$baseOut);

        if ($this->option('dry-run')) {
            $this->line('[dry-run] Combined fields ('.count($merged).')');
            foreach ($merged as $n) $this->line(' - '.$n);
            return self::SUCCESS;
        }

        if (!is_dir($baseOut) && !mkdir($baseOut, 0755, true) && !is_dir($baseOut)) {
            $this->error('Failed to create output directory: '.$baseOut);
            return self::FAILURE;
        }

        if ($format === 'json') {
            $file = rtrim($baseOut, '/').'/fields.json';
            file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            $this->info('Wrote '.$file);
        } else {
            $file = rtrim($baseOut, '/').'/fields.csv';
            $fp = fopen($file, 'w');
            fputcsv($fp, ['field_name']);
            foreach ($merged as $n) fputcsv($fp, [$n]);
            fclose($fp);
            $this->info('Wrote '.$file);
        }

        // Keep per-template snapshots too
        foreach ($all as $tid => $fields) {
            $snap = rtrim($baseOut, '/')."/fields_{$tid}.json";
            file_put_contents($snap, json_encode($fields, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }
        $this->info('Saved per-template snapshots.');

        return self::SUCCESS;
    }
}
