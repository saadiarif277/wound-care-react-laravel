<?php

namespace App\Http\Controllers;

use App\Services\DocusealService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DocusealDebugController extends Controller
{
    protected $docuSealService;

    public function __construct(DocusealService $docuSealService)
    {
        $this->docuSealService = $docuSealService;
    }

    /**
     * Debug Docuseal connection and list templates
     */
    public function debug()
    {
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Docuseal API key is not configured',
                'help' => 'Add DOCUSEAL_API_KEY to your .env file'
            ], 500);
        }

        try {
            // Test connection by fetching templates
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->get("{$apiUrl}/templates");

            if ($response->successful()) {
                $templates = $response->json();

                return response()->json([
                    'success' => true,
                    'api_key_configured' => true,
                    'api_url' => $apiUrl,
                    'templates_count' => count($templates),
                    'templates' => collect($templates)->map(function ($template) {
                        return [
                            'id' => $template['id'],
                            'name' => $template['name'],
                            'folder_name' => $template['folder_name'] ?? null,
                            'created_at' => $template['created_at'] ?? null,
                        ];
                    }),
                    'help' => 'Use one of these template IDs in your Quick Request form'
                ]);
            }

            return response()->json([
                'error' => 'Docuseal API returned error',
                'status' => $response->status(),
                'body' => $response->body(),
                'help' => 'Check if your API key is valid'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to connect to Docuseal',
                'message' => $e->getMessage(),
                'api_url' => $apiUrl,
                'help' => 'Check your Docuseal configuration'
            ], 500);
        }
    }

    /**
     * Test creating a submission with a specific template
     */
    public function testSubmission(Request $request)
    {
        $templateId = $request->input('template_id');

        if (!$templateId) {
            return response()->json([
                'error' => 'template_id is required',
                'help' => 'Pass ?template_id=YOUR_TEMPLATE_ID in the URL'
            ], 400);
        }

        try {
            $result = $this->docuSealService->createQuickRequestSubmission(
                $templateId,
                [
                    'email' => 'test@example.com',
                    'name' => 'Test User',
                    'send_email' => false,
                    'fields' => [
                        'patient_first_name' => 'Test',
                        'patient_last_name' => 'Patient',
                        'patient_dob' => '1990-01-01',
                    ],
                ]
            );

            return response()->json([
                'success' => true,
                'result' => $result,
                'help' => 'Template exists and submission was created successfully!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create submission',
                'message' => $e->getMessage(),
                'template_id' => $templateId,
                'help' => 'This template ID might not exist or there is an API issue'
            ], 500);
        }
    }
}
