<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocuSealService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DocuSealController extends Controller
{
    public function __construct(
        private DocuSealService $docuSealService
    ) {}

    /**
     * Create or update a DocuSeal submission
     */
    public function createOrUpdateSubmission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|integer',
            'manufacturer' => 'required|string',
            'additional_data' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->docuSealService->createOrUpdateSubmission(
                $request->input('episode_id'),
                $request->input('manufacturer'),
                $request->input('additional_data', [])
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create submission',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get submission details
     */
    public function getSubmission(string $submissionId): JsonResponse
    {
        try {
            $submission = $this->docuSealService->getSubmission($submissionId);

            return response()->json([
                'submission' => $submission,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get submission',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send submission for signing
     */
    public function sendForSigning(Request $request, string $submissionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'signers' => 'required|array',
            'signers.*.email' => 'required|email',
            'signers.*.name' => 'string',
            'signers.*.role' => 'string',
            'signers.*.message' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->docuSealService->sendForSigning(
                $submissionId,
                $request->input('signers')
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to send for signing',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download signed document
     */
    public function downloadDocument(Request $request, string $submissionId): \Illuminate\Http\Response
    {
        $format = $request->query('format', 'pdf');

        try {
            $document = $this->docuSealService->downloadDocument($submissionId, $format);

            return response($document)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"document-{$submissionId}.{$format}\"");

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to download document',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get template fields for a manufacturer
     */
    public function getTemplateFields(string $manufacturer): JsonResponse
    {
        try {
            $fields = $this->docuSealService->getTemplateFields($manufacturer);

            return response()->json($fields);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get template fields',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch process multiple episodes
     */
    public function batchProcessEpisodes(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_ids' => 'required|array',
            'episode_ids.*' => 'integer',
            'manufacturer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->docuSealService->batchProcessEpisodes(
                $request->input('episode_ids'),
                $request->input('manufacturer')
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Batch processing failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get episodes by status
     */
    public function getEpisodesByStatus(Request $request, string $status): JsonResponse
    {
        $manufacturer = $request->query('manufacturer');

        try {
            $episodes = $this->docuSealService->getEpisodesByStatus($status, $manufacturer);

            return response()->json([
                'episodes' => $episodes,
                'status' => $status,
                'manufacturer' => $manufacturer,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get episodes',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get DocuSeal analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'manufacturer' => 'string|nullable',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $dateRange = null;
            if ($request->has('start_date') && $request->has('end_date')) {
                $dateRange = [
                    $request->input('start_date'),
                    $request->input('end_date'),
                ];
            }

            $analytics = $this->docuSealService->generateAnalytics(
                $request->input('manufacturer'),
                $dateRange
            );

            return response()->json($analytics);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get analytics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}