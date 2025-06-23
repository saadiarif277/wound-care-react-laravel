<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\QuickRequestService;
use App\Services\Templates\DocuSealBuilder;
use App\Services\DocuSealService;
use App\Models\Episode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class QuickRequestController extends Controller
{
    private QuickRequestService $service;

    public function __construct(QuickRequestService $service)
    {
        $this->service = $service;
    }

    /**
     * Start a new quick request episode and create initial order.
     */
    public function startEpisode(Request $request)
    {
        $data = $request->validate([
            'patient_id'           => 'required|string',
            'patient_fhir_id'      => 'required|string',
            'patient_display_id'   => 'required|string',
            'manufacturer_id'      => 'required|uuid',
            'order_details'        => 'required|array',
        ]);

        $episode = $this->service->startEpisode($data);

        return response()->json([
            'success'    => true,
            'episode_id' => $episode->id,
            'order_id'   => $episode->orders()->first()->id,
        ], 201);
    }

    /**
     * Add a follow-up order to an existing episode.
     */
    public function addFollowUp(Request $request, Episode $episode)
    {
        $data = $request->validate([
            'parent_order_id' => 'required|uuid',
            'order_details'   => 'required|array',
        ]);

        $order = $this->service->addFollowUp($episode, $data);

        return response()->json([
            'success'  => true,
            'order_id' => $order->id,
        ], 201);
    }

    /**
     * Approve an episode and send notification.
     */
    public function approve(Request $request, Episode $episode)
    {
        $this->service->approve($episode);

        return response()->json(['success' => true]);
    }

    /**
     * Generate DocuSeal builder token for IVR forms.
     */
    public function generateBuilderToken(Request $request)
    {
        Log::info('generateBuilderToken called', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'input' => $request->all()
        ]);
        
        // Accept both camelCase and snake_case
        $data = $request->validate([
            'manufacturer_id' => 'sometimes|integer|exists:manufacturers,id',
            'manufacturerId' => 'sometimes|integer|exists:manufacturers,id',
            'product_code'    => 'nullable|string',
            'productCode'     => 'nullable|string',
            'template_id'     => 'nullable|string',
            'patient_display_id' => 'nullable|string',
            'episode_id'      => 'nullable|string'
        ]);
        
        // Normalize to snake_case
        $manufacturerId = $data['manufacturer_id'] ?? $data['manufacturerId'] ?? null;
        $productCode = $data['product_code'] ?? $data['productCode'] ?? null;
        
        if (!$manufacturerId) {
            return response()->json(['error' => 'Manufacturer ID is required'], 422);
        }

        try {
            // Get the appropriate template
            $docuSealService = new DocuSealService();
            $builder = new DocuSealBuilder($docuSealService);
            $template = $builder->getTemplate($manufacturerId, $productCode);
            
            Log::info('DocuSeal template found', [
                'template_id' => $template->id,
                'docuseal_template_id' => $template->docuseal_template_id,
                'manufacturer_id' => $manufacturerId,
                'product_code' => $productCode
            ]);
            
            // Generate a builder token using the DocuSeal builder approach
                        $user = Auth::user();
                        $submitterData = [
                'email' => $user->email,
                'name' => $user->name,
                'external_id' => 'quickrequest_' . uniqid(),
                'fields' => [] // Pre-fill fields will be added later when we have form data
            ];

            // Generate the builder token
            $builderToken = $this->service->getDocuSealService()->generateBuilderToken(
                $template->docuseal_template_id,
                $submitterData
            );

            // Return the builder token (frontend expects builderToken and builderUrl)
            return response()->json([
                'builderToken' => $builderToken,
                'builderUrl' => config('docuseal.api_url', 'https://api.docuseal.com')
            ], 200, ['Content-Type' => 'application/json']);
            
        } catch (\Exception $e) {
            Log::error('DocuSeal builder token generation failed', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $manufacturerId,
                'product_code' => $productCode,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to generate DocuSeal form: ' . $e->getMessage()
            ], 500, ['Content-Type' => 'application/json']);
        }
    }
}