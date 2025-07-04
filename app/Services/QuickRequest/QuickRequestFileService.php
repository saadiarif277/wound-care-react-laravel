<?php

namespace App\Services\QuickRequest;

use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\PhiAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuickRequestFileService
{
    public function handleFileUploads(
        Request $request, 
        ProductRequest $productRequest, 
        PatientManufacturerIVREpisode $episode
    ): array {
        $documentMetadata = [];
        $documentTypes = [
            'insurance_card_front' => 'phi/insurance-cards/',
            'insurance_card_back' => 'phi/insurance-cards/',
            'face_sheet' => 'phi/face-sheets/',
            'clinical_notes' => 'phi/clinical-notes/',
            'wound_photo' => 'phi/wound-photos/',
        ];

        foreach ($documentTypes as $fieldName => $storagePath) {
            if ($request->hasFile($fieldName)) {
                try {
                    $path = $request->file($fieldName)->store(
                        $storagePath . date('Y/m'), 
                        's3-encrypted'
                    );
                    
                    $documentMetadata[$fieldName] = [
                        'path' => $path,
                        'uploaded_at' => now(),
                        'size' => $request->file($fieldName)->getSize(),
                        'mime_type' => $request->file($fieldName)->getMimeType(),
                        'original_name' => $request->file($fieldName)->getClientOriginalName(),
                    ];

                    PhiAuditService::logCreation('Document', $path, [
                        'document_type' => $fieldName,
                        'patient_fhir_id' => $episode->patient_fhir_id,
                        'product_request_id' => $productRequest->id
                    ]);

                    Log::info('File uploaded successfully', [
                        'field_name' => $fieldName,
                        'file_path' => $path,
                        'product_request_id' => $productRequest->id
                    ]);

                } catch (\Exception $e) {
                    Log::error('File upload failed', [
                        'field_name' => $fieldName,
                        'error' => $e->getMessage(),
                        'product_request_id' => $productRequest->id
                    ]);
                    
                    $documentMetadata[$fieldName] = [
                        'error' => $e->getMessage(),
                        'upload_failed' => true
                    ];
                }
            }
        }

        return $documentMetadata;
    }

    public function updateProductRequestWithDocuments(
        ProductRequest $productRequest, 
        array $documentMetadata
    ): void {
        if (!empty($documentMetadata)) {
            $clinicalSummary = $productRequest->clinical_summary ?? [];
            $clinicalSummary['documents'] = $documentMetadata;
            $productRequest->update(['clinical_summary' => $clinicalSummary]);
        }
    }
} 