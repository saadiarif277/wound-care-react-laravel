<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ManufacturerController extends Controller
{
    /**
     * Get all manufacturers with their Docuseal template configurations
     */
    public function index(): JsonResponse
    {
        // Cache for 1 hour since manufacturer data doesn't change often
        $manufacturers = Cache::remember('api.manufacturers.with-templates', 3600, function () {
            return Manufacturer::query()
                ->with(['docusealTemplates' => function ($query) {
                    $query->where('is_active', true)
                          ->where('document_type', 'ivr')
                          ->orderBy('created_at', 'desc');
                }])
                ->get()
                ->map(function ($manufacturer) {
                    // Get the active IVR template
                    $activeTemplate = $manufacturer->docusealTemplates->first();
                    
                    // Get manufacturer config from field-mapping.php
                    $fieldMappingConfig = config("field-mapping.manufacturers.{$manufacturer->name}", []);
                    
                    return [
                        'id' => $manufacturer->id,
                        'name' => $manufacturer->name,
                        'signature_required' => $manufacturer->signature_required ?? true,
                        'email_recipients' => $manufacturer->email_recipients ?? [],
                        'docuseal_template_id' => $activeTemplate?->docuseal_template_id,
                        'docuseal_folder_id' => $activeTemplate?->folder_id,
                        'template_name' => $activeTemplate?->name,
                        'field_mapping' => $fieldMappingConfig['docuseal_field_names'] ?? [],
                        'custom_fields' => $this->getManufacturerFields($manufacturer, $activeTemplate),
                        'products' => $manufacturer->products ?? [],
                        'active' => !empty($activeTemplate),
                        // Order form properties
                        'has_order_form' => $fieldMappingConfig['has_order_form'] ?? false,
                        'order_form_template_id' => $fieldMappingConfig['order_form_template_id'] ?? null,
                    ];
                });
        });

        return response()->json([
            'data' => $manufacturers,
            'meta' => [
                'total' => $manufacturers->count(),
                'cached_at' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * Get a specific manufacturer by ID or name
     */
    public function show($manufacturerIdOrName): JsonResponse
    {
        $cacheKey = "api.manufacturer.{$manufacturerIdOrName}";
        
        $manufacturer = Cache::remember($cacheKey, 3600, function () use ($manufacturerIdOrName) {
            $query = Manufacturer::with(['docusealTemplates' => function ($query) {
                $query->where('is_active', true)
                      ->where('document_type', 'ivr')
                      ->orderBy('created_at', 'desc');
            }]);

            // Try to find by ID first, then by name
            if (is_numeric($manufacturerIdOrName)) {
                return $query->find($manufacturerIdOrName);
            } else {
                return $query->whereRaw('LOWER(name) = LOWER(?)', [$manufacturerIdOrName])->first();
            }
        });

        if (!$manufacturer) {
            return response()->json([
                'error' => 'Manufacturer not found'
            ], 404);
        }

        $activeTemplate = $manufacturer->docusealTemplates->first();
        $fieldMappingConfig = config("field-mapping.manufacturers.{$manufacturer->name}", []);

        return response()->json([
            'data' => [
                'id' => $manufacturer->id,
                'name' => $manufacturer->name,
                'signature_required' => $manufacturer->signature_required ?? true,
                'email_recipients' => $manufacturer->email_recipients ?? [],
                'docuseal_template_id' => $activeTemplate?->docuseal_template_id,
                'docuseal_folder_id' => $activeTemplate?->folder_id,
                'template_name' => $activeTemplate?->name,
                'field_mapping' => $fieldMappingConfig['docuseal_field_names'] ?? [],
                'custom_fields' => $this->getManufacturerFields($manufacturer, $activeTemplate),
                'products' => $manufacturer->products ?? [],
                'active' => !empty($activeTemplate),
                'created_at' => $manufacturer->created_at,
                'updated_at' => $manufacturer->updated_at,
                // Order form properties
                'has_order_form' => $fieldMappingConfig['has_order_form'] ?? false,
                'order_form_template_id' => $fieldMappingConfig['order_form_template_id'] ?? null,
            ]
        ]);
    }

    /**
     * Get manufacturer fields configuration
     */
    private function getManufacturerFields(Manufacturer $manufacturer): array
    {
        // Return a standard set of fields that all IVR forms should have
        return [
            [
                'name' => 'patient_name',
                'label' => 'Patient Name',
                'type' => 'text',
                'required' => true
            ],
            [
                'name' => 'patient_dob',
                'label' => 'Date of Birth',
                'type' => 'date',
                'required' => true
            ],
            [
                'name' => 'patient_phone',
                'label' => 'Patient Phone',
                'type' => 'tel',
                'required' => true
            ],
            [
                'name' => 'insurance_name',
                'label' => 'Insurance Company',
                'type' => 'text',
                'required' => true
            ],
            [
                'name' => 'member_id',
                'label' => 'Member ID',
                'type' => 'text',
                'required' => true
            ],
            [
                'name' => 'physician_name',
                'label' => 'Physician Name',
                'type' => 'text',
                'required' => true
            ],
            [
                'name' => 'physician_npi',
                'label' => 'Physician NPI',
                'type' => 'text',
                'required' => true
            ],
            [
                'name' => 'facility_name',
                'label' => 'Facility Name',
                'type' => 'text',
                'required' => true
            ]
        ];
    }

    /**
     * Get the active PDF template for a manufacturer
     */
    public function getTemplate($manufacturerIdOrName): JsonResponse
    {
        try {
            // Find manufacturer by ID or name
            if (is_numeric($manufacturerIdOrName)) {
                $manufacturer = Manufacturer::find($manufacturerIdOrName);
            } else {
                $manufacturer = Manufacturer::whereRaw('LOWER(name) = LOWER(?)', [$manufacturerIdOrName])->first();
            }

            if (!$manufacturer) {
                return response()->json([
                    'success' => false,
                    'error' => 'Manufacturer not found'
                ], 404);
            }

            // Get the active PDF template for IVR documents
            $pdfTemplate = \App\Models\PDF\ManufacturerPdfTemplate::getLatestForManufacturer(
                $manufacturer->id, 
                'ivr'
            );

            // Get legacy field mapping config for fallback
            $fieldMappingConfig = config("field-mapping.manufacturers.{$manufacturer->name}", []);

            return response()->json([
                'success' => true,
                'data' => [
                    'manufacturer_id' => $manufacturer->id,
                    'manufacturer_name' => $manufacturer->name,
                    // PDF Template (new system)
                    'pdf_template_id' => $pdfTemplate?->id,
                    'pdf_template_name' => $pdfTemplate?->template_name,
                    'pdf_template_version' => $pdfTemplate?->version,
                    'pdf_template_fields' => $pdfTemplate?->template_fields ?? [],
                    'pdf_template_active' => $pdfTemplate?->is_active ?? false,
                    // Legacy fallback for DocuSeal template ID
                    'docuseal_template_id' => $fieldMappingConfig['docuseal_template_id'] ?? null,
                    'template_name' => $pdfTemplate?->template_name ?? 'Default IVR Template',
                    // Field mappings
                    'field_mapping' => $fieldMappingConfig['docuseal_field_names'] ?? [],
                    'custom_fields' => $this->getManufacturerFields($manufacturer),
                    // Template metadata
                    'has_active_template' => !empty($pdfTemplate),
                    'template_type' => $pdfTemplate ? 'pdf' : 'none',
                    'last_updated' => $pdfTemplate?->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get manufacturer template', [
                'manufacturer' => $manufacturerIdOrName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve template information',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear manufacturer cache
     */
    public function clearCache(): JsonResponse
    {
        Cache::forget('api.manufacturers.with-templates');
        Cache::flush(); // Clear all manufacturer-specific caches

        return response()->json([
            'message' => 'Manufacturer cache cleared successfully'
        ]);
    }
}