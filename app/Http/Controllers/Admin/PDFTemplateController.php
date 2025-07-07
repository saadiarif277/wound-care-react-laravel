<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PDF\ManufacturerPdfTemplate;
use App\Models\PDF\PdfFieldMapping;
use App\Models\Order\Manufacturer;
use App\Services\PDF\AzurePDFStorageService;
use App\Services\PDF\PDFMappingService;
use App\Services\PDF\PDFDocumentIntelligenceService;
use App\Services\PDF\AIFieldMappingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PDFTemplateController extends Controller
{
    protected AzurePDFStorageService $storageService;
    protected PDFMappingService $pdfMappingService;
    protected PDFDocumentIntelligenceService $documentIntelligence;
    protected AIFieldMappingService $aiFieldMapping;

    public function __construct(
        AzurePDFStorageService $storageService,
        PDFMappingService $pdfMappingService,
        PDFDocumentIntelligenceService $documentIntelligence,
        AIFieldMappingService $aiFieldMapping
    ) {
        $this->storageService = $storageService;
        $this->pdfMappingService = $pdfMappingService;
        $this->documentIntelligence = $documentIntelligence;
        $this->aiFieldMapping = $aiFieldMapping;
    }

    /**
     * Display a listing of PDF templates.
     */
    public function index(Request $request)
    {
        $templates = ManufacturerPdfTemplate::with(['manufacturer', 'fieldMappings'])
            ->when($request->manufacturer_id, function ($query, $manufacturerId) {
                return $query->where('manufacturer_id', $manufacturerId);
            })
            ->when($request->document_type, function ($query, $documentType) {
                return $query->where('document_type', $documentType);
            })
            ->when($request->is_active !== null, function ($query) use ($request) {
                return $query->where('is_active', $request->is_active);
            })
            ->orderBy('manufacturer_id')
            ->orderBy('document_type')
            ->orderBy('version', 'desc')
            ->paginate(20);

        $manufacturers = Manufacturer::orderBy('name')->get();

        return Inertia::render('Admin/PDFTemplateManager', [
            'templates' => $templates,
            'manufacturers' => $manufacturers,
            'filters' => $request->only(['manufacturer_id', 'document_type', 'is_active'])
        ]);
    }

    /**
     * Store a newly uploaded PDF template.
     */
    public function store(Request $request)
    {
        // Enhanced debugging for upload issues
        $debugMode = config('app.debug') || $request->has('debug');
        $debugInfo = [];
        
        try {
            // Log initial request details
            if ($debugMode) {
                $debugInfo['request_files'] = $request->hasFile('pdf_file') ? 'File present' : 'No file uploaded';
                $debugInfo['request_method'] = $request->method();
                $debugInfo['content_type'] = $request->header('Content-Type');
                $debugInfo['all_files'] = array_keys($request->allFiles());
                $debugInfo['request_keys'] = array_keys($request->all());
                $debugInfo['request_all'] = $request->all(); // Show all request data
                
                // Check various ways the file might be present
                $debugInfo['file_checks'] = [
                    'hasFile_pdf_file' => $request->hasFile('pdf_file'),
                    'file_pdf_file_exists' => $request->file('pdf_file') !== null,
                    'files_array' => count($_FILES),
                    'files_keys' => array_keys($_FILES),
                    'server_content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
                ];
                
                // Check raw PHP files array
                if (!empty($_FILES)) {
                    $debugInfo['raw_files'] = [];
                    foreach ($_FILES as $key => $file) {
                        $debugInfo['raw_files'][$key] = [
                            'name' => $file['name'] ?? 'no name',
                            'type' => $file['type'] ?? 'no type',
                            'size' => $file['size'] ?? 0,
                            'error' => $file['error'] ?? 'no error code',
                            'tmp_name' => isset($file['tmp_name']) ? 'exists' : 'missing',
                        ];
                    }
                }
                
                if ($request->hasFile('pdf_file')) {
                    $file = $request->file('pdf_file');
                    $debugInfo['file_details'] = [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'size_mb' => round($file->getSize() / 1024 / 1024, 2),
                        'is_valid' => $file->isValid(),
                        'error' => $file->getError(),
                        'error_message' => $file->getErrorMessage(),
                    ];
                } else {
                    // Additional debugging when file is not found
                    $debugInfo['php_upload_errors'] = [
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'post_max_size' => ini_get('post_max_size'),
                        'max_file_uploads' => ini_get('max_file_uploads'),
                        'file_uploads' => ini_get('file_uploads'),
                    ];
                    
                    // Check if it's a POST size issue
                    if (!empty($_SERVER['CONTENT_LENGTH'])) {
                        $postMaxSize = $this->convertPhpSizeToBytes(ini_get('post_max_size'));
                        $contentLength = (int) $_SERVER['CONTENT_LENGTH'];
                        if ($contentLength > $postMaxSize) {
                            $debugInfo['post_size_exceeded'] = [
                                'content_length' => $contentLength,
                                'post_max_size' => $postMaxSize,
                                'exceeded_by' => $contentLength - $postMaxSize,
                            ];
                        }
                    }
                }
                
                Log::info('PDF Upload Debug - Initial Request', $debugInfo);
            }
            
            // Parse metadata if it's JSON
            if ($request->has('metadata') && is_string($request->metadata)) {
                $request->merge(['metadata' => json_decode($request->metadata, true) ?? []]);
            }
            
            // Validate with better error messages
            $validated = $request->validate([
                'manufacturer_id' => 'required|exists:manufacturers,id',
                'template_name' => 'required|string|max:255',
                'document_type' => 'required|in:ivr,order_form,shipping_label,invoice,other',
                'version' => 'required|string|max:50',
                'pdf_file' => 'required|file|mimes:pdf|max:10240', // 10MB max
                'is_active' => 'boolean',
                'metadata' => 'nullable|array',
            ], [
                'pdf_file.required' => 'Please select a PDF file to upload.',
                'pdf_file.file' => 'The uploaded file is not valid.',
                'pdf_file.mimes' => 'Only PDF files are allowed.',
                'pdf_file.max' => 'The PDF file size must not exceed 10MB.',
                'manufacturer_id.required' => 'Please select a manufacturer.',
                'manufacturer_id.exists' => 'The selected manufacturer is invalid.',
                'template_name.required' => 'Please provide a template name.',
                'document_type.required' => 'Please select a document type.',
                'version.required' => 'Please provide a version number.',
            ]);

            DB::beginTransaction();
            
            // Upload PDF to Azure/local storage
            $file = $request->file('pdf_file');
            
            // Additional file validation
            if (!$file->isValid()) {
                throw new \Exception('Uploaded file is not valid. Error code: ' . $file->getError());
            }
            
            $fileName = Str::slug($validated['template_name']) . '-' . $validated['version'] . '-' . time() . '.pdf';
            $filePath = 'pdf-templates/' . $validated['manufacturer_id'] . '/' . $fileName;
            
            // Log upload attempt
            if ($debugMode) {
                Log::info('PDF Upload Debug - Attempting storage upload', [
                    'file_path' => $filePath,
                    'file_size' => $file->getSize(),
                    'real_path' => $file->getRealPath(),
                    'real_path_exists' => file_exists($file->getRealPath()),
                ]);
            }
            
            $uploadResult = $this->storageService->uploadTemplate(
                $file->getRealPath(),
                $filePath,
                [
                    'manufacturer_id' => $validated['manufacturer_id'],
                    'document_type' => $validated['document_type'],
                    'version' => $validated['version'],
                ]
            );

            if (!$uploadResult['success']) {
                $errorDetails = [
                    'error' => $uploadResult['error'] ?? 'Unknown error',
                    'details' => $uploadResult['details'] ?? null,
                    'file_path' => $filePath,
                ];
                
                if ($debugMode) {
                    Log::error('PDF Upload Debug - Storage upload failed', $errorDetails);
                }
                
                throw new \Exception($uploadResult['error'] ?? 'Failed to upload PDF template to storage');
            }

            // Extract fields from PDF
            if ($debugMode) {
                Log::info('PDF Upload Debug - Extracting form fields', [
                    'file_path' => $file->getRealPath(),
                ]);
            }
            
            $extractedFields = [];
            try {
                $extractedFields = $this->pdfMappingService->extractFormFields($file->getRealPath());
            } catch (\Exception $extractException) {
                Log::warning('PDF field extraction failed, continuing with empty fields', [
                    'error' => $extractException->getMessage(),
                    'file' => $fileName,
                ]);
                
                // Don't fail the upload if field extraction fails
                $extractedFields = [];
            }

            // Create template record
            $template = ManufacturerPdfTemplate::create([
                'manufacturer_id' => $validated['manufacturer_id'],
                'template_name' => $validated['template_name'],
                'document_type' => $validated['document_type'],
                'file_path' => $filePath,
                'azure_container' => 'pdf-templates',
                'version' => $validated['version'],
                'is_active' => $validated['is_active'] ?? false,
                'template_fields' => $extractedFields,
                'metadata' => array_merge(
                    $validated['metadata'] ?? [],
                    [
                        'uploaded_by' => auth()->id(),
                        'uploaded_at' => now()->toIso8601String(),
                        'file_size' => $file->getSize(),
                        'field_count' => count($extractedFields),
                        'upload_debug' => $debugMode ? $debugInfo : null,
                    ]
                ),
            ]);

            // If this is the only template for this manufacturer/type, activate it
            $existingActiveCount = ManufacturerPdfTemplate::where('manufacturer_id', $validated['manufacturer_id'])
                ->where('document_type', $validated['document_type'])
                ->where('is_active', true)
                ->where('id', '!=', $template->id)
                ->count();

            if ($existingActiveCount === 0 && !$template->is_active) {
                $template->update(['is_active' => true]);
            }

            DB::commit();

            $successMessage = 'PDF template uploaded successfully. Found ' . count($extractedFields) . ' form fields.';
            
            if ($debugMode) {
                Log::info('PDF Upload Debug - Success', [
                    'template_id' => $template->id,
                    'field_count' => count($extractedFields),
                ]);
            }

            // Return JSON response for fetch requests
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'redirect' => route('admin.pdf-templates.show', $template),
                    'template_id' => $template->id,
                ]);
            }
            
            return redirect()->route('admin.pdf-templates.show', $template)
                ->with('success', $successMessage);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Add debug info to validation errors if in debug mode
            if ($debugMode && !empty($debugInfo)) {
                // For JSON requests, return JSON response
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $e->errors(),
                        'upload_debug' => $debugInfo,
                    ], 422);
                }
                
                return back()->withErrors($e->errors())
                    ->withInput()
                    ->with('upload_debug', $debugInfo);
            }
            // Otherwise let Laravel handle it normally
            throw $e;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            $errorDetails = [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'debug_info' => $debugInfo,
            ];
            
            Log::error('Failed to upload PDF template', $errorDetails);

            // Provide more helpful error messages
            $userMessage = 'Failed to upload template: ';
            
            if (str_contains($e->getMessage(), 'storage')) {
                $userMessage .= 'Storage service error. Please check your Azure/storage configuration.';
            } elseif (str_contains($e->getMessage(), 'not valid')) {
                $userMessage .= 'The uploaded file appears to be corrupted or invalid.';
            } elseif (str_contains($e->getMessage(), 'permission')) {
                $userMessage .= 'Permission denied. Please check storage permissions.';
            } else {
                $userMessage .= $e->getMessage();
            }
            
            if ($debugMode) {
                $userMessage .= ' [Debug mode: Check logs for detailed information]';
            }
            
            // For JSON requests, return JSON response
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $userMessage,
                    'errors' => ['pdf_file' => $userMessage],
                    'upload_debug' => $debugMode ? $debugInfo : null,
                ], 500);
            }

            return back()->withErrors(['pdf_file' => $userMessage])
                ->withInput()
                ->with('upload_debug', $debugMode ? $debugInfo : null);
        }
    }

    /**
     * Display the specified PDF template.
     */
    public function show(ManufacturerPdfTemplate $template)
    {
        $template->load(['manufacturer', 'fieldMappings']);

        // Get available data sources for field mapping
        $dataSources = $this->getAvailableDataSources();

        return Inertia::render('Admin/PDFTemplateDetail', [
            'template' => $template,
            'dataSources' => $dataSources,
            'sampleData' => $this->getSampleData(),
        ]);
    }

    /**
     * Update the specified PDF template metadata and mappings.
     */
    public function update(Request $request, ManufacturerPdfTemplate $template)
    {
        $validated = $request->validate([
            'template_name' => 'sometimes|string|max:255',
            'metadata' => 'nullable|array',
            'field_mappings' => 'nullable|array',
            'field_mappings.*.pdf_field_name' => 'required_with:field_mappings|string',
            'field_mappings.*.data_source' => 'required_with:field_mappings|string',
            'field_mappings.*.field_type' => 'required_with:field_mappings|in:text,checkbox,radio,select,signature,date,image',
            'field_mappings.*.transform_function' => 'nullable|string',
            'field_mappings.*.default_value' => 'nullable|string',
            'field_mappings.*.is_required' => 'boolean',
            'field_mappings.*.display_order' => 'integer',
        ]);

        DB::beginTransaction();

        try {
            // Update template
            $template->update([
                'template_name' => $validated['template_name'] ?? $template->template_name,
                'metadata' => array_merge(
                    $template->metadata ?? [],
                    $validated['metadata'] ?? [],
                    [
                        'last_updated_by' => auth()->id(),
                        'last_updated_at' => now()->toIso8601String(),
                    ]
                ),
            ]);

            // Update field mappings
            if (isset($validated['field_mappings'])) {
                // Delete existing mappings
                $template->fieldMappings()->delete();

                // Create new mappings
                foreach ($validated['field_mappings'] as $mapping) {
                    $template->fieldMappings()->create($mapping);
                }
            }

            DB::commit();

            return back()->with('success', 'Template updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update PDF template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to update template: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified PDF template.
     */
    public function destroy(ManufacturerPdfTemplate $template)
    {
        // Check if this is the only active template
        if ($template->is_active) {
            $otherActiveCount = ManufacturerPdfTemplate::where('manufacturer_id', $template->manufacturer_id)
                ->where('document_type', $template->document_type)
                ->where('is_active', true)
                ->where('id', '!=', $template->id)
                ->count();

            if ($otherActiveCount === 0) {
                return back()->withErrors(['error' => 'Cannot delete the only active template. Please activate another template first.']);
            }
        }

        try {
            // Soft delete the template
            $template->delete();

            return redirect()->route('admin.pdf-templates.index')
                ->with('success', 'Template deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete PDF template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete template: ' . $e->getMessage()]);
        }
    }

    /**
     * Extract fields from the PDF template.
     */
    public function extractFields(ManufacturerPdfTemplate $template)
    {
        try {
            // Download template from storage
            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
            $pdfContent = $this->storageService->downloadTemplate($template->file_path);
            
            if (!$pdfContent['success']) {
                throw new \Exception($pdfContent['error'] ?? 'Failed to download template');
            }

            file_put_contents($tempPath, $pdfContent['content']);

            // Extract fields
            $fields = $this->pdfMappingService->extractFormFields($tempPath);

            // Update template with extracted fields
            $template->update([
                'template_fields' => $fields,
                'metadata' => array_merge(
                    $template->metadata ?? [],
                    [
                        'fields_extracted_at' => now()->toIso8601String(),
                        'field_count' => count($fields),
                    ]
                ),
            ]);

            unlink($tempPath);

            return response()->json([
                'success' => true,
                'fields' => $fields,
                'count' => count($fields),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to extract PDF fields', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to extract fields: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test fill the PDF template with sample data.
     */
    public function testFill(Request $request, ManufacturerPdfTemplate $template)
    {
        $validated = $request->validate([
            'test_data' => 'required|array',
        ]);

        try {
            // Use the PDF mapping service to fill the template
            $result = $this->pdfMappingService->fillPdfTemplate(
                $template,
                $validated['test_data']
            );

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to fill PDF');
            }

            // Return the filled PDF as a download
            return response($result['pdf_content'])
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="test-' . $template->template_name . '.pdf"');

        } catch (\Exception $e) {
            Log::error('Failed to test fill PDF template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fill template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activate a PDF template.
     */
    public function activate(ManufacturerPdfTemplate $template)
    {
        DB::beginTransaction();

        try {
            // Deactivate other templates of the same type for this manufacturer
            ManufacturerPdfTemplate::where('manufacturer_id', $template->manufacturer_id)
                ->where('document_type', $template->document_type)
                ->where('id', '!=', $template->id)
                ->update(['is_active' => false]);

            // Activate this template
            $template->update(['is_active' => true]);

            DB::commit();

            return back()->with('success', 'Template activated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to activate PDF template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to activate template: ' . $e->getMessage()]);
        }
    }

    /**
     * Deactivate a PDF template.
     */
    public function deactivate(ManufacturerPdfTemplate $template)
    {
        // Check if this is the only active template
        $otherActiveCount = ManufacturerPdfTemplate::where('manufacturer_id', $template->manufacturer_id)
            ->where('document_type', $template->document_type)
            ->where('is_active', true)
            ->where('id', '!=', $template->id)
            ->count();

        if ($otherActiveCount === 0) {
            return back()->withErrors(['error' => 'Cannot deactivate the only active template.']);
        }

        $template->update(['is_active' => false]);

        return back()->with('success', 'Template deactivated successfully.');
    }

    /**
     * Get available data sources for field mapping.
     */
    protected function getAvailableDataSources()
    {
        return [
            'patient' => [
                'patient_first_name' => 'Patient First Name',
                'patient_last_name' => 'Patient Last Name',
                'patient_dob' => 'Patient Date of Birth',
                'patient_gender' => 'Patient Gender',
                'patient_member_id' => 'Patient Member ID',
                'patient_address_line1' => 'Patient Address Line 1',
                'patient_address_line2' => 'Patient Address Line 2',
                'patient_city' => 'Patient City',
                'patient_state' => 'Patient State',
                'patient_zip' => 'Patient ZIP Code',
                'patient_phone' => 'Patient Phone',
                'patient_email' => 'Patient Email',
            ],
            'provider' => [
                'provider_name' => 'Provider Name',
                'provider_npi' => 'Provider NPI',
                'provider_email' => 'Provider Email',
                'provider_phone' => 'Provider Phone',
            ],
            'facility' => [
                'facility_name' => 'Facility Name',
                'facility_npi' => 'Facility NPI',
                'facility_address' => 'Facility Address',
                'facility_city' => 'Facility City',
                'facility_state' => 'Facility State',
                'facility_zip' => 'Facility ZIP',
                'facility_phone' => 'Facility Phone',
            ],
            'clinical' => [
                'wound_type' => 'Wound Type',
                'wound_location' => 'Wound Location',
                'wound_size_length' => 'Wound Length (cm)',
                'wound_size_width' => 'Wound Width (cm)',
                'wound_size_depth' => 'Wound Depth (cm)',
                'wound_duration' => 'Wound Duration',
                'primary_diagnosis_code' => 'Primary Diagnosis Code',
                'secondary_diagnosis_code' => 'Secondary Diagnosis Code',
            ],
            'insurance' => [
                'primary_insurance_name' => 'Primary Insurance Name',
                'primary_member_id' => 'Primary Member ID',
                'primary_group_number' => 'Primary Group Number',
                'primary_payer_phone' => 'Primary Payer Phone',
            ],
            'product' => [
                'product_name' => 'Product Name',
                'product_code' => 'Product Code',
                'product_size' => 'Product Size',
                'quantity' => 'Quantity',
            ],
            'order' => [
                'order_number' => 'Order Number',
                'expected_service_date' => 'Expected Service Date',
                'signature_date' => 'Today\'s Date',
            ],
        ];
    }

    /**
     * Get sample data for testing PDF fill.
     */
    protected function getSampleData()
    {
        return [
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'patient_dob' => '1960-01-15',
            'patient_gender' => 'male',
            'patient_member_id' => '123456789',
            'patient_address_line1' => '123 Main Street',
            'patient_city' => 'Springfield',
            'patient_state' => 'IL',
            'patient_zip' => '62701',
            'patient_phone' => '(555) 123-4567',
            'patient_email' => 'john.doe@example.com',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'facility_name' => 'Springfield Medical Center',
            'facility_npi' => '0987654321',
            'wound_type' => 'Diabetic Foot Ulcer',
            'wound_location' => 'Right Foot',
            'wound_size_length' => '3.5',
            'wound_size_width' => '2.0',
            'primary_diagnosis_code' => 'E11.621',
            'primary_insurance_name' => 'Medicare',
            'primary_member_id' => 'MED123456',
            'product_name' => 'Advanced Wound Dressing',
            'product_code' => 'Q4100',
            'quantity' => '1',
            'order_number' => 'ORD-2024-0001',
            'expected_service_date' => date('Y-m-d', strtotime('+7 days')),
            'signature_date' => date('Y-m-d'),
        ];
    }

    /**
     * Analyze PDF template with Azure Document Intelligence.
     */
    public function analyzeWithAI(Request $request, ManufacturerPdfTemplate $template)
    {
        try {
            // Download template from storage
            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
            $pdfContent = $this->storageService->downloadTemplate($template->file_path);
            
            if (!$pdfContent['success']) {
                throw new \Exception($pdfContent['error'] ?? 'Failed to download template');
            }

            file_put_contents($tempPath, $pdfContent['content']);

            // Analyze with Document Intelligence
            $analysis = $this->documentIntelligence->analyzePDFTemplate($tempPath);

            // Clean up temp file
            unlink($tempPath);

            if (!$analysis['success']) {
                // Fallback to traditional extraction if AI fails
                Log::warning('Document Intelligence analysis failed, falling back to pdftk', [
                    'template_id' => $template->id,
                    'error' => $analysis['error'] ?? 'Unknown error'
                ]);

                return $this->extractFields($template);
            }

            // Update template with AI-analyzed fields
            $extractedFields = array_column($analysis['analysis']['fields'] ?? [], 'name');
            
            $template->update([
                'template_fields' => $extractedFields,
                'metadata' => array_merge(
                    $template->metadata ?? [],
                    [
                        'ai_analyzed_at' => now()->toIso8601String(),
                        'ai_field_count' => count($extractedFields),
                        'ai_confidence' => $analysis['confidence'] ?? 0,
                        'ai_document_type' => $analysis['analysis']['document_type'] ?? null,
                        'ai_field_categories' => $analysis['analysis']['field_categories'] ?? null,
                    ]
                ),
            ]);

            return response()->json([
                'success' => true,
                'analysis' => $analysis['analysis'],
                'field_count' => $analysis['field_count'],
                'confidence' => $analysis['confidence'],
                'method' => $analysis['processing_method'] ?? 'azure_document_intelligence',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to analyze PDF with AI', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to analyze template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get AI-powered field mapping suggestions.
     */
    public function suggestMappings(Request $request, ManufacturerPdfTemplate $template)
    {
        $validated = $request->validate([
            'min_confidence' => 'nullable|numeric|min:0|max:1',
            'max_suggestions' => 'nullable|integer|min:1|max:10',
            'include_historical' => 'nullable|boolean',
        ]);

        try {
            $options = [
                'min_confidence' => $validated['min_confidence'] ?? 0.5,
                'max_suggestions' => $validated['max_suggestions'] ?? 5,
                'cache_duration' => 3600, // 1 hour cache
            ];

            $suggestions = $this->aiFieldMapping->getSuggestionsForTemplate($template, $options);

            return response()->json($suggestions);

        } catch (\Exception $e) {
            Log::error('Failed to get AI mapping suggestions', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate suggestions: ' . $e->getMessage(),
                'suggestions' => [],
            ], 500);
        }
    }

    /**
     * Convert PHP ini size notation to bytes
     */
    private function convertPhpSizeToBytes(string $size): int
    {
        $unit = strtolower($size[strlen($size) - 1]);
        $value = (int) $size;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Apply AI-suggested field mappings.
     */
    public function applyAIMappings(Request $request, ManufacturerPdfTemplate $template)
    {
        $validated = $request->validate([
            'accepted_suggestions' => 'required|array',
            'accepted_suggestions.*' => 'required|array',
            'accepted_suggestions.*.data_source' => 'required|string',
            'accepted_suggestions.*.confidence' => 'required|numeric|min:0|max:1',
            'accepted_suggestions.*.field_type' => 'nullable|string',
            'accepted_suggestions.*.is_required' => 'nullable|boolean',
        ]);

        try {
            $result = $this->aiFieldMapping->applySuggestions($template, $validated['accepted_suggestions']);

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to apply suggestions');
            }

            // Reload template with updated mappings
            $template->load('fieldMappings');

            return response()->json([
                'success' => true,
                'applied' => $result['applied'],
                'failed' => $result['failed'],
                'total_applied' => $result['total_applied'],
                'total_failed' => $result['total_failed'],
                'message' => "Applied {$result['total_applied']} field mappings successfully.",
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to apply AI mapping suggestions', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to apply mappings: ' . $e->getMessage(),
            ], 500);
        }
    }
}