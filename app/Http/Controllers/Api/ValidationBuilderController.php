<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ValidationBuilderEngine;
use App\Services\CmsCoverageApiService;
use App\Models\Order;
use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ValidationBuilderController extends Controller
{
    private ValidationBuilderEngine $validationEngine;
    private CmsCoverageApiService $cmsService;

    public function __construct(
        ValidationBuilderEngine $validationEngine,
        CmsCoverageApiService $cmsService
    ) {
        $this->validationEngine = $validationEngine;
        $this->cmsService = $cmsService;
    }

    /**
     * Get validation rules for a specific specialty
     */
    public function getValidationRules(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');
            $state = $request->input('state');

            $validationRules = $this->validationEngine->buildValidationRulesForSpecialty($specialty, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty' => $specialty,
                    'state' => $state,
                    'validation_rules' => $validationRules
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting validation rules', [
                'specialty' => $request->input('specialty'),
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving validation rules'
            ], 500);
        }
    }

    /**
     * Get validation rules for current user's specialty
     */
    public function getUserValidationRules(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $state = $request->input('state');

            $validationRules = $this->validationEngine->buildValidationRulesForUser($user, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'state' => $state,
                    'validation_rules' => $validationRules
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user validation rules', [
                'user_id' => $request->user()->id,
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user validation rules'
            ], 500);
        }
    }

    /**
     * Validate an order
     */
    public function validateOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            'specialty' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $orderId = $request->input('order_id');
            $specialty = $request->input('specialty');

            $order = Order::where('id', $orderId)->firstOrFail();
            $validationResults = $this->validationEngine->validateOrder($order, $specialty);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'specialty' => $specialty,
                    'validation_results' => $validationResults
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating order', [
                'order_id' => $request->input('order_id'),
                'specialty' => $request->input('specialty'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error validating order'
            ], 500);
        }
    }

    /**
     * Validate a product request
     */
    public function validateProductRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_request_id' => 'required|integer|exists:product_requests,id',
            'specialty' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $productRequestId = $request->input('product_request_id');
            $specialty = $request->input('specialty');

            $productRequest = ProductRequest::where('id', $productRequestId)->firstOrFail();
            $validationResults = $this->validationEngine->validateProductRequest($productRequest, $specialty);

            return response()->json([
                'success' => true,
                'data' => [
                    'product_request_id' => $productRequestId,
                    'specialty' => $specialty,
                    'validation_results' => $validationResults
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating product request', [
                'product_request_id' => $request->input('product_request_id'),
                'specialty' => $request->input('specialty'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error validating product request'
            ], 500);
        }
    }

    /**
     * Get CMS LCDs for a specialty
     */
    public function getCmsLcds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');
            $state = $request->input('state');

            $lcds = $this->cmsService->getLCDsBySpecialty($specialty, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty' => $specialty,
                    'state' => $state,
                    'lcds' => $lcds,
                    'count' => count($lcds)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting CMS LCDs', [
                'specialty' => $request->input('specialty'),
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving CMS LCDs'
            ], 500);
        }
    }

    /**
     * Get CMS NCDs for a specialty
     */
    public function getCmsNcds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');

            $ncds = $this->cmsService->getNCDsBySpecialty($specialty);

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty' => $specialty,
                    'ncds' => $ncds,
                    'count' => count($ncds)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting CMS NCDs', [
                'specialty' => $request->input('specialty'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving CMS NCDs'
            ], 500);
        }
    }

    /**
     * Get CMS Articles for a specialty
     */
    public function getCmsArticles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');
            $state = $request->input('state');

            $articles = $this->cmsService->getArticlesBySpecialty($specialty, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty' => $specialty,
                    'state' => $state,
                    'articles' => $articles,
                    'count' => count($articles)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting CMS Articles', [
                'specialty' => $request->input('specialty'),
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving CMS Articles'
            ], 500);
        }
    }

    /**
     * Search CMS coverage documents
     */
    public function searchCmsDocuments(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required|string|min:3',
            'state' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $keyword = $request->input('keyword');
            $state = $request->input('state');

            $searchResults = $this->cmsService->searchCoverageDocuments($keyword, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => $keyword,
                    'state' => $state,
                    'results' => $searchResults
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching CMS documents', [
                'keyword' => $request->input('keyword'),
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error searching CMS documents'
            ], 500);
        }
    }

    /**
     * Get MAC jurisdiction for a state
     */
    public function getMacJurisdiction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'state' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $state = $request->input('state');

            $macInfo = $this->cmsService->getMACJurisdiction($state);

            return response()->json([
                'success' => true,
                'data' => [
                    'state' => $state,
                    'mac_info' => $macInfo
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting MAC jurisdiction', [
                'state' => $request->input('state'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving MAC jurisdiction'
            ], 500);
        }
    }

    /**
     * Get available specialties
     */
    public function getAvailableSpecialties(): JsonResponse
    {
        try {
            $specialties = $this->cmsService->getAvailableSpecialties();

            return response()->json([
                'success' => true,
                'data' => [
                    'specialties' => $specialties
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting available specialties', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available specialties'
            ], 500);
        }
    }

    /**
     * Clear cache for a specialty
     */
    public function clearSpecialtyCache(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialty' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $specialty = $request->input('specialty');

            $this->cmsService->clearSpecialtyCache($specialty);

            return response()->json([
                'success' => true,
                'message' => "Cache cleared for specialty: {$specialty}"
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing specialty cache', [
                'specialty' => $request->input('specialty'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error clearing specialty cache'
            ], 500);
        }
    }
}
