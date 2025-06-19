<?php

namespace App\Http\Controllers;

use App\Services\DocuSealService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocuSealSubmissionController extends Controller
{
    protected $docuSealService;

    public function __construct(DocuSealService $docuSealService)
    {
        $this->docuSealService = $docuSealService;
    }

    /**
     * Create a new DocuSeal submission for IVR signature
     */
    public function createSubmission(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|string',
            'email' => 'required|email',
            'name' => 'required|string',
            'send_email' => 'boolean',
            'fields' => 'array',
            'external_id' => 'nullable|string',
        ]);

        try {
            // Create the submission using the DocuSealService
            $result = $this->docuSealService->createQuickRequestSubmission(
                $validated['template_id'],
                [
                    'email' => $validated['email'],
                    'name' => $validated['name'],
                    'send_email' => $validated['send_email'] ?? false,
                    'fields' => $validated['fields'] ?? [],
                    'external_id' => $validated['external_id'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'submission_id' => $result['submission_id'],
                'signing_url' => $result['signing_url'],
            ]);

        } catch (\Exception $e) {
            Log::error('DocuSeal submission creation failed', [
                'error' => $e->getMessage(),
                'template_id' => $validated['template_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create DocuSeal submission: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check the status of a DocuSeal submission
     */
    public function checkStatus($submissionId)
    {
        try {
            $submission = $this->docuSealService->getSubmission($submissionId);

            return response()->json([
                'success' => true,
                'status' => $submission['status'] ?? 'unknown',
                'completed_at' => $submission['completed_at'] ?? null,
                'data' => $submission,
            ]);

        } catch (\Exception $e) {
            Log::error('DocuSeal status check failed', [
                'error' => $e->getMessage(),
                'submission_id' => $submissionId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check submission status',
            ], 500);
        }
    }
}
