<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DocuSealTemplateSyncService;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Http\JsonResponse;

class DocuSealTemplateController extends Controller
{
    protected DocuSealTemplateSyncService $syncService;

    public function __construct(DocuSealTemplateSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Sync templates from DocuSeal API and return updated list.
     */
    public function sync(Request $request): JsonResponse
    {
        $templates = $this->syncService->pullTemplatesFromDocuSeal();
        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * List all templates from local DB.
     */
    public function index(Request $request): JsonResponse
    {
        $templates = DocusealTemplate::all();
        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }
}
