<?php

namespace App\Services\Templates;

use App\Models\DocuSeal\DocuSealTemplate;
use App\Models\Order\Product;
use App\Services\DocuSealService;
use Illuminate\Support\Facades\Auth;
use Exception;

class DocuSealBuilder
{
    private ?DocuSealService $docuSealService = null;

    public function __construct(?DocuSealService $docuSealService = null)
    {
        $this->docuSealService = $docuSealService;
    }
    /**
     * Get the DocuSeal template for a given manufacturer.
     * Templates are organized by manufacturer folders in DocuSeal.
     *
     * @param string $manufacturerId
     * @param string|null $productCode (not used, kept for compatibility)
     * @return \App\Models\DocuSeal\DocuSealTemplate
     * @throws Exception
     */
    public function getTemplate(string $manufacturerId, ?string $productCode = null): \App\Models\DocuSeal\DocuSealTemplate
    {
        // First try to find a manufacturer-specific IVR template
        $template = \App\Models\DocuSeal\DocuSealTemplate::where('manufacturer_id', $manufacturerId)
            ->where('is_active', true)
            ->where('document_type', 'IVR')
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($template) {
            return $template;
        }
        
        // If no manufacturer-specific template, try to find a generic IVR template
        $genericTemplate = \App\Models\DocuSeal\DocuSealTemplate::whereNull('manufacturer_id')
            ->where('is_active', true)
            ->where('document_type', 'IVR')
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($genericTemplate) {
            return $genericTemplate;
        }
        
        throw new Exception("No active IVR template found for manufacturer ID: {$manufacturerId}");
    }

    /**
     * Generate DocuSeal builder token for a given manufacturer and product code.
     *
     * @param string $manufacturerId
     * @param string|null $productCode
     * @return array [templateId, builderToken, builderUrl]
     * @throws Exception
     */
    public function generateBuilderToken(string $manufacturerId, ?string $productCode = null): array
    {
        // Get the template first
        $template = $this->getTemplate($manufacturerId, $productCode);
        
        // Prepare submitter data
        $user = Auth::user();
        $submitterData = [
            'email' => $user->email,
            'name' => $user->name,
            'external_id' => "episode_" . uniqid(),
            'fields' => [] // No pre-filled fields for builder mode
        ];

        // Generate the builder token via DocuSealService
        $builderToken = $this->docuSealService->generateBuilderToken(
            $template->docuseal_template_id,
            $submitterData
        );

        // DocuSeal builder URL is typically the embed URL
        $builderUrl = config('docuseal.api_url', 'https://api.docuseal.com') . '/builder';

        return [$template->docuseal_template_id, $builderToken, $builderUrl];
    }
}