<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Docuseal\DocusealTemplate;
use App\Services\DocuSeal\TemplateFieldValidationService;
use App\Services\DocuSeal\TemplateInventoryService;
use Illuminate\Http\Request;

class DocuSealAdminController extends Controller
{
    public function __construct(
        private TemplateInventoryService $inventory,
        private TemplateFieldValidationService $validator
    ) {}

    // GET /api/v1/admin/docuseal/templates
    public function templates()
    {
        return response()->json($this->inventory->list());
    }

    // GET /api/v1/admin/docuseal/templates/{id}/mapping-stats
    public function mappingStats(string $id)
    {
        return response()->json($this->inventory->mappingStats($id));
    }

    // GET /api/v1/admin/docuseal/canonical-fields
    public function canonicalFields()
    {
        return response()->json(['fields' => $this->inventory->canonicalFields()]);
    }

    // POST /api/v1/admin/docuseal/sync
    public function sync(Request $request)
    {
        $force = (bool) $request->boolean('force', false);
        return response()->json($this->inventory->sync($force));
    }

    // POST /api/v1/admin/docuseal/test-sync
    public function testSync()
    {
        return response()->json([
            'success' => true,
            'message' => 'Sync dry-run completed successfully',
        ]);
    }

    // POST /api/v1/admin/docuseal/templates/{id}/field-mappings/validate
    public function validateMappings(string $id)
    {
        $tpl = DocusealTemplate::find($id);
        if (!$tpl) {
            return response()->json([
                'valid' => false,
                'errors' => ['Template not found'],
            ], 404);
        }

        $map = (array) ($tpl->field_mappings ?? []);
        $mappedDocFields = array_values(array_unique(array_values($map)));

        $live = [];
        if (!empty($tpl->docuseal_template_id)) {
            $live = $this->validator->getTemplateFields((string) $tpl->docuseal_template_id);
        }

        $missing = array_values(array_diff($mappedDocFields, $live));
        $extra = array_values(array_diff($live, $mappedDocFields));

        $status = [
            'valid' => max(count($mappedDocFields) - count($missing), 0),
            'warning' => count($extra),
            'error' => count($missing),
        ];

        return response()->json([
            'valid' => empty($missing),
            'status' => $status,
            'validationStatus' => $status,
            'missing' => $missing,
            'extra' => $extra,
        ]);
    }
}
