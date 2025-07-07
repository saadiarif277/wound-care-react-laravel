<?php

namespace App\Services\PDF;

use App\Models\PDF\ManufacturerPdfTemplate;
use App\Models\PDF\PdfDocument;
use App\Models\PDF\PdfFieldMapping;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\PDF\Transformers\PdfDataTransformer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;
use setasign\Fpdi\Fpdi;

/**
 * Service for filling PDF forms with mapped data
 */
class PDFMappingService
{
    private string $pdftkPath;
    private string $tempPath;
    
    public function __construct()
    {
        $this->pdftkPath = config('pdf.pdftk_path', '/usr/bin/pdftk');
        $this->tempPath = storage_path('app/temp/pdf');
        
        // Ensure temp directory exists
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    /**
     * Fill a manufacturer PDF with order data
     */
    public function fillManufacturerPDF(
        int $manufacturerId,
        array $orderData,
        string $documentType = 'ivr',
        ?int $templateId = null
    ): PdfDocument {
        try {
            // Get template and mappings
            $template = $this->getTemplate($manufacturerId, $documentType, $templateId);
            if (!$template) {
                throw new Exception("No PDF template found for manufacturer {$manufacturerId} and type {$documentType}");
            }

            $mappings = $template->fieldMappings;
            
            // Transform data according to mappings
            $pdfData = $this->transformDataForPDF($orderData, $mappings);
            
            // Validate required fields
            $validationErrors = $this->validatePdfData($pdfData, $mappings);
            if (!empty($validationErrors)) {
                Log::warning('PDF data validation warnings', [
                    'manufacturer_id' => $manufacturerId,
                    'document_type' => $documentType,
                    'warnings' => $validationErrors
                ]);
            }
            
            // Download template from Azure
            $templatePath = $this->downloadTemplate($template);
            
            // Fill PDF using pdftk
            $filledPdfPath = $this->fillPdfWithPdftk($templatePath, $pdfData);
            
            // Create PDF document record
            $pdfDocument = $this->createPdfDocument(
                $template,
                $filledPdfPath,
                $orderData,
                $pdfData,
                $documentType
            );
            
            // Clean up temp files
            $this->cleanupTempFiles([$templatePath, $filledPdfPath]);
            
            Log::info('PDF filled successfully', [
                'document_id' => $pdfDocument->document_id,
                'manufacturer_id' => $manufacturerId,
                'document_type' => $documentType
            ]);
            
            return $pdfDocument;
            
        } catch (Exception $e) {
            Log::error('Failed to fill manufacturer PDF', [
                'manufacturer_id' => $manufacturerId,
                'document_type' => $documentType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new Exception('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate IVR for episode review
     */
    public function generateIVRForReview(PatientManufacturerIVREpisode $episode): PdfDocument
    {
        try {
            // Use QuickRequestOrchestrator data
            $orchestrator = app(\App\Services\QuickRequest\QuickRequestOrchestrator::class);
            $orderData = $orchestrator->prepareDocusealData($episode);
            
            // Add episode-specific data
            $orderData['episode_id'] = $episode->id;
            $orderData['is_draft'] = true;
            $orderData['generated_for_review'] = true;
            
            // Generate PDF
            $pdfDocument = $this->fillManufacturerPDF(
                $episode->manufacturer_id,
                $orderData,
                'ivr'
            );
            
            // Update document with episode reference
            $pdfDocument->update([
                'episode_id' => $episode->id,
                'status' => 'generated',
                'metadata' => array_merge($pdfDocument->metadata ?? [], [
                    'generated_for_review' => true,
                    'review_generated_at' => now()->toISOString()
                ])
            ]);
            
            return $pdfDocument;
            
        } catch (Exception $e) {
            Log::error('Failed to generate IVR for review', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get template for manufacturer
     */
    private function getTemplate(int $manufacturerId, string $documentType, ?int $templateId = null): ?ManufacturerPdfTemplate
    {
        if ($templateId) {
            return ManufacturerPdfTemplate::find($templateId);
        }
        
        return ManufacturerPdfTemplate::getLatestForManufacturer($manufacturerId, $documentType);
    }

    /**
     * Transform order data to PDF field values
     */
    private function transformDataForPDF(array $orderData, $mappings): array
    {
        $pdfData = [];
        
        foreach ($mappings as $mapping) {
            try {
                // Get value using field mapping
                $value = $mapping->getFinalValue($orderData);
                
                // Handle different field types
                switch ($mapping->field_type) {
                    case 'checkbox':
                        // Convert to Yes/Off for PDF checkboxes
                        $value = $value ? 'Yes' : 'Off';
                        break;
                    
                    case 'date':
                        // Format dates consistently
                        if ($value && !empty($value)) {
                            $value = PdfDataTransformer::formatDate($value, 'm/d/Y');
                        }
                        break;
                    
                    case 'select':
                    case 'radio':
                        // Ensure value is in options
                        if ($mapping->options && !in_array($value, $mapping->options)) {
                            $value = $mapping->default_value ?? '';
                        }
                        break;
                }
                
                $pdfData[$mapping->pdf_field_name] = $value;
                
            } catch (Exception $e) {
                Log::warning('Failed to map PDF field', [
                    'field' => $mapping->pdf_field_name,
                    'error' => $e->getMessage()
                ]);
                
                $pdfData[$mapping->pdf_field_name] = $mapping->default_value ?? '';
            }
        }
        
        return $pdfData;
    }

    /**
     * Validate PDF data against mappings
     */
    private function validatePdfData(array $pdfData, $mappings): array
    {
        $errors = [];
        
        foreach ($mappings as $mapping) {
            $value = $pdfData[$mapping->pdf_field_name] ?? null;
            $fieldErrors = $mapping->validateValue($value);
            
            if (!empty($fieldErrors)) {
                $errors = array_merge($errors, $fieldErrors);
            }
        }
        
        return $errors;
    }

    /**
     * Download template from Azure storage
     */
    private function downloadTemplate(ManufacturerPdfTemplate $template): string
    {
        $tempFile = $this->tempPath . '/' . Str::random(16) . '_template.pdf';
        
        // Download from Azure Blob Storage
        $content = Storage::disk('azure')->get($template->azure_container . '/' . $template->file_path);
        file_put_contents($tempFile, $content);
        
        return $tempFile;
    }

    /**
     * Fill PDF using pdftk
     */
    private function fillPdfWithPdftk(string $templatePath, array $fieldData): string
    {
        // Generate FDF file with field data
        $fdfData = $this->generateFDF($fieldData);
        $fdfFile = $this->tempPath . '/' . Str::random(16) . '.fdf';
        file_put_contents($fdfFile, $fdfData);
        
        // Output file
        $outputFile = $this->tempPath . '/' . Str::random(16) . '_filled.pdf';
        
        // Use pdftk to fill the form
        $command = sprintf(
            '%s %s fill_form %s output %s flatten',
            $this->pdftkPath,
            escapeshellarg($templatePath),
            escapeshellarg($fdfFile),
            escapeshellarg($outputFile)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            unlink($fdfFile);
            throw new Exception('PDF filling failed: ' . implode("\n", $output));
        }
        
        // Clean up FDF file
        unlink($fdfFile);
        
        return $outputFile;
    }

    /**
     * Generate FDF format for form data
     */
    private function generateFDF(array $data): string
    {
        $fdf = "%FDF-1.2\n%âãÏÓ\n1 0 obj\n<< /FDF << /Fields [\n";
        
        foreach ($data as $field => $value) {
            // Escape special characters
            $field = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $field);
            $value = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $value);
            
            $fdf .= "<< /T ($field) /V ($value) >>\n";
        }
        
        $fdf .= "] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        
        return $fdf;
    }

    /**
     * Create PDF document record and upload to Azure
     */
    private function createPdfDocument(
        ManufacturerPdfTemplate $template,
        string $filledPdfPath,
        array $orderData,
        array $pdfData,
        string $documentType
    ): PdfDocument {
        // Generate unique filename
        $filename = sprintf(
            '%s/%s/%s_%s_%s.pdf',
            date('Y/m'),
            $documentType,
            Str::slug($template->manufacturer->name),
            $documentType,
            Str::random(8)
        );
        
        // Upload to Azure
        $azureService = app(AzurePDFStorageService::class);
        $blobUrl = $azureService->uploadPDF($filledPdfPath, $filename);
        
        // Calculate file hash
        $hash = hash_file('sha256', $filledPdfPath);
        
        // Create document record
        $pdfDocument = PdfDocument::create([
            'order_id' => $orderData['order_id'] ?? null,
            'episode_id' => $orderData['episode_id'] ?? null,
            'template_id' => $template->id,
            'document_type' => $documentType,
            'status' => 'generated',
            'file_path' => $filename,
            'azure_container' => 'order-pdfs',
            'azure_blob_url' => $blobUrl,
            'filled_data' => $pdfData,
            'generated_at' => now(),
            'generated_by' => auth()->id(),
            'hash' => $hash,
            'metadata' => [
                'manufacturer_id' => $template->manufacturer_id,
                'manufacturer_name' => $template->manufacturer->name,
                'template_version' => $template->version,
                'field_count' => count($pdfData),
                'has_required_signatures' => $template->signatureConfigs()->required()->exists()
            ]
        ]);
        
        // Set expiration if IVR
        if ($documentType === 'ivr') {
            $pdfDocument->update([
                'expires_at' => now()->addDays(30)
            ]);
        }
        
        return $pdfDocument;
    }

    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Add signature to PDF document
     */
    public function addSignatureToPDF(PdfDocument $document, array $signatureData): string
    {
        try {
            // Download current PDF
            $currentPdfPath = $this->downloadPdfDocument($document);
            
            // Get signature config
            $signatureConfig = $document->template->signatureConfigs()
                ->where('signature_type', $signatureData['signature_type'])
                ->first();
            
            if (!$signatureConfig) {
                throw new Exception("No signature configuration found for type: {$signatureData['signature_type']}");
            }
            
            // Create signature image from base64 data
            $signatureImagePath = $this->createSignatureImage($signatureData['signature_data']);
            
            // Add signature to PDF using FPDI
            $signedPdfPath = $this->addSignatureUsingFpdi(
                $currentPdfPath,
                $signatureImagePath,
                $signatureConfig
            );
            
            // Clean up temp files
            $this->cleanupTempFiles([$currentPdfPath, $signatureImagePath]);
            
            return $signedPdfPath;
            
        } catch (Exception $e) {
            Log::error('Failed to add signature to PDF', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Download PDF document from Azure
     */
    private function downloadPdfDocument(PdfDocument $document): string
    {
        $tempFile = $this->tempPath . '/' . Str::random(16) . '_current.pdf';
        
        $content = Storage::disk('azure')->get($document->azure_container . '/' . $document->file_path);
        file_put_contents($tempFile, $content);
        
        return $tempFile;
    }

    /**
     * Create signature image from base64 data
     */
    private function createSignatureImage(string $signatureData): string
    {
        $tempFile = $this->tempPath . '/' . Str::random(16) . '_signature.png';
        
        // Remove data URL prefix if present
        if (str_starts_with($signatureData, 'data:image')) {
            $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
        }
        
        $imageData = base64_decode($signatureData);
        file_put_contents($tempFile, $imageData);
        
        return $tempFile;
    }

    /**
     * Add signature to PDF using FPDI
     */
    private function addSignatureUsingFpdi(
        string $pdfPath,
        string $signatureImagePath,
        $signatureConfig
    ): string {
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($pdfPath);
        
        // Import all pages
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
            
            // Add signature if this is the right page
            if ($pageNo === $signatureConfig->page_number) {
                $coords = $signatureConfig->getAbsoluteCoordinates($size['width'], $size['height']);
                
                $pdf->Image(
                    $signatureImagePath,
                    $coords['x'],
                    $coords['y'],
                    $coords['width'],
                    $coords['height']
                );
            }
        }
        
        $outputPath = $this->tempPath . '/' . Str::random(16) . '_signed.pdf';
        $pdf->Output($outputPath, 'F');
        
        return $outputPath;
    }

    /**
     * Extract form fields from a PDF using pdftk
     */
    public function extractFormFields(string $pdfPath): array
    {
        try {
            // Use pdftk to dump data fields
            $command = sprintf(
                '%s %s dump_data_fields 2>&1',
                $this->pdftkPath,
                escapeshellarg($pdfPath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                Log::warning('Failed to extract PDF fields using pdftk', [
                    'command' => $command,
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode
                ]);
                
                // Return empty array if extraction fails
                return [];
            }
            
            // Parse the output to extract field names
            $fields = [];
            $currentField = [];
            
            foreach ($output as $line) {
                if (strpos($line, '---') === 0) {
                    // Field separator, process current field
                    if (!empty($currentField) && isset($currentField['FieldName'])) {
                        $fieldName = $currentField['FieldName'];
                        $fields[] = $fieldName;
                    }
                    $currentField = [];
                } else {
                    // Parse field data
                    if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
                        $key = trim($matches[1]);
                        $value = trim($matches[2]);
                        $currentField[$key] = $value;
                    }
                }
            }
            
            // Process last field
            if (!empty($currentField) && isset($currentField['FieldName'])) {
                $fieldName = $currentField['FieldName'];
                $fields[] = $fieldName;
            }
            
            // Remove duplicates and sort
            $fields = array_unique($fields);
            sort($fields);
            
            Log::info('PDF fields extracted', [
                'pdf_path' => $pdfPath,
                'field_count' => count($fields),
                'fields' => $fields
            ]);
            
            return $fields;
            
        } catch (Exception $e) {
            Log::error('Failed to extract PDF form fields', [
                'pdf_path' => $pdfPath,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Fill PDF template with test data
     */
    public function fillPdfTemplate(ManufacturerPdfTemplate $template, array $testData): array
    {
        try {
            // Download template from storage
            $azureService = app(AzurePDFStorageService::class);
            $templatePath = $this->tempPath . '/' . Str::random(16) . '_template.pdf';
            
            // Download the template
            if ($azureService->isConfigured()) {
                $downloadResult = $azureService->downloadTemplate($template->file_path);
                if (!$downloadResult['success']) {
                    throw new Exception($downloadResult['error'] ?? 'Failed to download template');
                }
                file_put_contents($templatePath, $downloadResult['content']);
            } else {
                // Use local storage
                $content = Storage::disk('public')->get('pdfs/' . $template->file_path);
                file_put_contents($templatePath, $content);
            }
            
            // Fill the PDF with test data
            $filledPdfPath = $this->fillPdfWithPdftk($templatePath, $testData);
            
            // Read the filled PDF content
            $pdfContent = file_get_contents($filledPdfPath);
            
            // Clean up temp files
            $this->cleanupTempFiles([$templatePath, $filledPdfPath]);
            
            return [
                'success' => true,
                'pdf_content' => $pdfContent
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to fill PDF template', [
                'template_id' => $template->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}