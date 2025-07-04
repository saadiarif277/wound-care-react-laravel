<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * DocusealDebugController
 * 
 * Debug utilities for Docuseal integration
 */
class DocusealDebugController extends Controller
{
    /**
     * Debug endpoint for testing Docuseal integration
     */
    public function debug(Request $request): JsonResponse
    {
        try {
            $config = [
                'api_key_configured' => !empty(config('docuseal.api_key')),
                'api_url' => config('docuseal.api_url'),
                'account_email' => config('docuseal.account_email'),
                'timeout' => config('docuseal.timeout'),
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Docuseal debug information',
                'config' => $config,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Docuseal debug failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Debug failed: ' . $e->getMessage(),
            ], 500);
        }
    }
} 