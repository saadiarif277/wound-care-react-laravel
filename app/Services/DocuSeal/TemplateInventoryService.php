<?php

namespace App\Services\DocuSeal;

use App\Models\Docuseal\DocusealTemplate;
use App\Models\CanonicalField;
use App\Services\UnifiedFieldMappingService;
use Illuminate\Support\Facades\Log;

class TemplateInventoryService
{
    public function __construct(
        protected UnifiedFieldMappingService $mappingService,
        protected ?TemplateFieldValidationService $validator = null
    ) {
        if (!$this->validator) {
            try {
                $this->validator = app(TemplateFieldValidationService::class);
            } catch (\Throwable $e) {
                Log::warning('TemplateInventoryService: validator unavailable', ['error' => $e->getMessage()]);
                $this->validator = null;
            }
        }
    }

    public function list(): array
    {
        $templates = DocusealTemplate::with('manufacturer')->get();

        $items = [];
        foreach ($templates as $tpl) {
            $map = (array) ($tpl->field_mappings ?? []);
            $live = $this->safeGetLiveFields($tpl->docuseal_template_id);
            $coverage = $this->estimateCoverage($map, $live);

            $items[] = [
                'id' => (string) $tpl->id,
                'template_name' => $tpl->template_name,
                'docuseal_template_id' => (string) $tpl->docuseal_template_id,
                'document_type' => $tpl->document_type,
                'manufacturer_id' => (string) $tpl->manufacturer_id,
                'manufacturer' => $tpl->manufacturer ? [
                    'id' => (string) $tpl->manufacturer->id,
                    'name' => (string) $tpl->manufacturer->name,
                    'is_active' => (bool) $tpl->manufacturer->is_active
                ] : null,
                'is_active' => (bool) $tpl->is_active,
                'is_default' => (bool) $tpl->is_default,
                'field_mappings' => $map,
                'extraction_metadata' => $tpl->extraction_metadata,
                'last_extracted_at' => optional($tpl->last_extracted_at)?->toIso8601String(),
                'created_at' => optional($tpl->created_at)?->toIso8601String(),
                'updated_at' => optional($tpl->updated_at)?->toIso8601String(),
                'field_coverage_percentage' => $coverage['percentage'],
                'submission_count' => method_exists($tpl, 'submissions') ? $tpl->submissions()->count() : 0,
                'success_rate' => null,
            ];
        }

        return [
            'templates' => $items,
            'stats' => $this->aggregateStats($items),
            'sync_status' => [
                'is_syncing' => false,
                'last_sync' => null,
                'templates_found' => count($items),
                'templates_updated' => 0,
                'errors' => 0,
            ],
        ];
    }

    public function mappingStats(string $templateId): array
    {
        $tpl = DocusealTemplate::find($templateId);
        if (!$tpl) {
            return [
                'totalFields' => 0,
                'mappedFields' => 0,
                'unmappedFields' => 0,
                'activeFields' => 0,
                'requiredFieldsMapped' => 0,
                'totalRequiredFields' => 0,
                'optionalFieldsMapped' => 0,
                'coveragePercentage' => 0,
                'requiredCoveragePercentage' => 0,
                'highConfidenceCount' => 0,
                'validationStatus' => ['valid' => 0, 'warning' => 0, 'error' => 1],
                'lastUpdated' => null,
                'lastUpdatedBy' => null,
            ];
        }

        $map = (array) ($tpl->field_mappings ?? []);
        $live = $this->safeGetLiveFields($tpl->docuseal_template_id);
        $coverage = $this->estimateCoverage($map, $live);

        $totalRequired = CanonicalField::where('is_required', true)->count();
        $requiredMapped = 0;
        foreach ($map as $canonical => $doc) {
            if (CanonicalField::where('field_name', $canonical)->where('is_required', true)->exists()) {
                $requiredMapped++;
            }
        }

        $validation = $this->validateMappingAgainstLive($map, $live);

        return [
            'totalFields' => count($map),
            'mappedFields' => count(array_filter($map)),
            'unmappedFields' => max(count($map) - count(array_filter($map)), 0),
            'activeFields' => count($live),
            'requiredFieldsMapped' => $requiredMapped,
            'totalRequiredFields' => $totalRequired,
            'optionalFieldsMapped' => count($map) - $requiredMapped,
            'coveragePercentage' => $coverage['percentage'],
            'requiredCoveragePercentage' => $totalRequired > 0 ? round(($requiredMapped / $totalRequired) * 100) : 0,
            'highConfidenceCount' => $coverage['filled'],
            'validationStatus' => $validation['status'],
            'lastUpdated' => optional($tpl->updated_at)?->toIso8601String(),
            'lastUpdatedBy' => $tpl->lastMappedBy?->email ?? ($tpl->last_mapped_by ?? null),
        ];
    }

    public function sync(bool $force = false): array
    {
        $templates = DocusealTemplate::all();
        $found = $templates->count();
        $updated = 0;
        $errors = 0;

        foreach ($templates as $tpl) {
            try {
                $live = $this->safeGetLiveFields($tpl->docuseal_template_id, $force);
                $map = (array) ($tpl->field_mappings ?? []);
                $coverage = $this->estimateCoverage($map, $live);

                $meta = $tpl->extraction_metadata ?? [];
                $meta['total_fields'] = count($live);
                $meta['mapped_fields'] = count($map);
                $meta['extraction_confidence'] = $coverage['percentage'];
                $meta['last_sync_at'] = now()->toIso8601String();

                $tpl->extraction_metadata = $meta;
                $tpl->last_extracted_at = now();
                $tpl->save();
                $updated++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Template sync failed', [
                    'template_id' => $tpl->docuseal_template_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'templates_found' => $found,
            'templates_updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function canonicalFields(): array
    {
        return CanonicalField::orderBy('field_name')
            ->get(['id', 'field_name', 'description', 'is_required'])
            ->map(function ($f) {
                return [
                    'id' => (string) $f->id,
                    'name' => (string) $f->field_name,
                    'label' => $f->description ?: ucwords(str_replace(['_', '-'], ' ', (string) $f->field_name)),
                    'is_required' => (bool) $f->is_required,
                ];
            })->toArray();
    }

    protected function aggregateStats(array $items): array
    {
        $total = count($items);
        $active = count(array_filter($items, fn($i) => $i['is_active']));
        $mfgs = count(array_unique(array_map(fn($i) => $i['manufacturer']['name'] ?? 'Unknown', $items)));
        $avg = $total > 0 ? round(array_sum(array_map(fn($i) => (int) ($i['field_coverage_percentage'] ?? 0), $items)) / $total) : 0;
        $subs = array_sum(array_map(fn($i) => (int) ($i['submission_count'] ?? 0), $items));
        $need = count(array_filter($items, fn($i) => ((int) ($i['field_coverage_percentage'] ?? 0)) < 80));

        return [
            'total_templates' => $total,
            'active_templates' => $active,
            'manufacturers_covered' => $mfgs,
            'avg_field_coverage' => $avg,
            'total_submissions' => $subs,
            'templates_needing_attention' => $need,
        ];
    }

    protected function safeGetLiveFields(?string $templateId, bool $force = false): array
    {
        if (!$this->validator || empty($templateId)) return [];
        try {
            $fields = $this->validator->getTemplateFields((string) $templateId);
            return array_values(array_unique(array_filter($fields)));
        } catch (\Throwable $e) {
            Log::warning('Live fields fetch failed', ['template_id' => $templateId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    protected function estimateCoverage(array $map, array $live): array
    {
        $mappedDocFields = array_values(array_unique(array_values($map)));
        $total = count($mappedDocFields);
        $present = count(array_intersect($mappedDocFields, $live));
        $pct = $total > 0 ? (int) round(($present / $total) * 100) : 0;
        return ['percentage' => $pct, 'filled' => $present, 'total' => $total];
    }

    protected function validateMappingAgainstLive(array $map, array $live): array
    {
        $mappedDocFields = array_values(array_unique(array_values($map)));
        $missing = array_values(array_diff($mappedDocFields, $live));
        $extra = array_values(array_diff($live, $mappedDocFields));

        $status = [
            'valid' => max(count($mappedDocFields) - count($missing), 0),
            'warning' => count($extra),
            'error' => count($missing),
        ];

        return [
            'status' => $status,
            'missing' => $missing,
            'extra' => $extra,
        ];
    }
}
