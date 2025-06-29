<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ManufacturerController extends Controller
{
    /**
     * Get all manufacturers with their DocuSeal template configurations
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
            ]
        ]);
    }

    /**
     * Get manufacturer fields configuration
     */
    private function getManufacturerFields(Manufacturer $manufacturer, ?DocusealTemplate $template): array
    {
        // If template has stored fields, use those
        if ($template && !empty($template->fields)) {
            return $template->fields;
        }

        // Otherwise, return a standard set of fields that all IVR forms should have
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