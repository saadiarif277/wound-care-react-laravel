<?php

namespace App\Http\Controllers;

use App\Services\FhirService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class FhirController extends Controller
{
    protected FhirService $fhirService;

    public function __construct(FhirService $fhirService)
    {
        $this->fhirService = $fhirService;
    }

    /**
     * Create a Patient resource
     * POST /fhir/Patient
     */
    public function createPatient(Request $request): JsonResponse
    {
        // Check if user can create product requests (since FHIR is used in QuickRequest flow)
        if (!Auth::user()->hasPermission('create-product-requests')) {
            return $this->fhirError('forbidden', 'Insufficient permissions to create patient records', 403);
        }

        try {
            // Check if the request contains a FHIR resource or form data
            $data = $request->all();
            
            if (isset($data['resourceType']) && $data['resourceType'] === 'Patient') {
                // Handle FHIR Patient resource format
                $validator = Validator::make($data, [
                    'resourceType' => 'required|string|in:Patient',
                    'identifier' => 'sometimes|array',
                    'name' => 'sometimes|array',
                    'active' => 'sometimes|boolean',
                    'meta' => 'sometimes|array',
                ]);

                if ($validator->fails()) {
                    return $this->fhirError('invalid', 'Invalid FHIR Patient resource format', 400, $validator->errors()->toArray());
                }

                // Pass the FHIR resource directly to the service
                $createdFhirPatient = $this->fhirService->createPatient($data);
            } else {
                // Handle form data format (legacy support)
                $validator = Validator::make($data, [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'dob' => 'required|date_format:Y-m-d',
                    'member_id' => 'sometimes|nullable|string|max:255',
                    'gender' => 'sometimes|nullable|string|in:male,female,other,unknown',
                    'id' => 'sometimes|nullable|string|max:255',
                ]);

                if ($validator->fails()) {
                    return $this->fhirError('invalid', 'Invalid patient data format', 400, $validator->errors()->toArray());
                }

                $validatedData = $validator->validated();

                // Transform form data to FHIR Patient resource structure
                $fhirPatientStructure = [
                    'resourceType' => 'Patient',
                    'name' => [
                        [
                            'use' => 'official',
                            'family' => $validatedData['last_name'],
                            'given' => [$validatedData['first_name']],
                        ],
                    ],
                    'birthDate' => $validatedData['dob'],
                ];

                if (!empty($validatedData['gender'])) {
                    $fhirPatientStructure['gender'] = $validatedData['gender'];
                }

                $memberIdSystem = config('app.fhir_identifier_systems.member_id', 'urn:oid:2.16.840.1.113883.3.4.5.6');
                if (!empty($validatedData['member_id'])) {
                    $fhirPatientStructure['identifier'][] = [
                        'use' => 'usual',
                        'type' => [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                    'code' => 'MB',
                                    'display' => 'Member Number',
                                ],
                            ],
                            'text' => 'Member Number',
                        ],
                        'system' => $memberIdSystem,
                        'value' => $validatedData['member_id'],
                    ];
                }

                $externalIdSystem = config('app.fhir_identifier_systems.external_id', 'urn:oid:1.2.3.4.5.external');
                if (!empty($validatedData['id'])) {
                     $fhirPatientStructure['identifier'][] = [
                        'use' => 'secondary',
                         'type' => [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                    'code' => 'PI',
                                    'display' => 'Patient internal identifier',
                                ],
                            ],
                            'text' => 'External Patient ID',
                        ],
                        'system' => $externalIdSystem,
                        'value' => $validatedData['id'],
                    ];
                }

                $createdFhirPatient = $this->fhirService->createPatient($fhirPatientStructure);
            }

            return response()->json($createdFhirPatient, 201)
                ->header('Content-Type', 'application/fhir+json')
                // Ensure $createdFhirPatient has an 'id' before using it here
                ->header('Location', url("/api/v1/fhir/patient/" . ($createdFhirPatient['id'] ?? 'unknown')));

        } catch (\Exception $e) {
            Log::error('FHIR Patient creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->fhirError('processing', 'Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Read a Patient resource
     * GET /fhir/Patient/{id}
     */
    public function readPatient(string $id): JsonResponse
    {
        // Check PHI permissions
        if (!Auth::user()->hasPermission('view-phi')) {
            return $this->fhirError('forbidden', 'Insufficient permissions to view PHI', 403);
        }

        try {
            $fhirPatient = $this->fhirService->getPatientById($id);

            if (!$fhirPatient) {
                return $this->fhirError('not-found', "Patient with id '{$id}' not found", 404);
            }

            return response()->json($fhirPatient)
                ->header('Content-Type', 'application/fhir+json');

        } catch (\Exception $e) {
            Log::error('FHIR Patient read failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
        }
    }

    /**
     * Update a Patient resource
     * PUT /fhir/Patient/{id}
     */
    public function updatePatient(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'resourceType' => 'required|string|in:Patient',
                'id' => 'required|string',
                'name' => 'array',
                'gender' => 'string|in:male,female,other,unknown',
                'birthDate' => 'date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return $this->fhirError('invalid', 'Invalid resource format', 400);
            }

            if ($request->input('id') !== $id) {
                return $this->fhirError('invalid', 'Resource ID mismatch', 400);
            }

            $fhirPatient = $this->fhirService->updatePatient($id, $request->all());

            if (!$fhirPatient) {
                return $this->fhirError('not-found', "Patient with id '{$id}' not found", 404);
            }

            return response()->json($fhirPatient)
                ->header('Content-Type', 'application/fhir+json');

        } catch (\Exception $e) {
            Log::error('FHIR Patient update failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
        }
    }

    /**
     * Patch a Patient resource
     * PATCH /fhir/Patient/{id}
     */
    public function patchPatient(Request $request, string $id): JsonResponse
    {
        try {
            $fhirPatient = $this->fhirService->patchPatient($id, $request->all());

            if (!$fhirPatient) {
                return $this->fhirError('not-found', "Patient with id '{$id}' not found", 404);
            }

            return response()->json($fhirPatient)
                ->header('Content-Type', 'application/fhir+json');

        } catch (\Exception $e) {
            Log::error('FHIR Patient patch failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
        }
    }

    /**
     * Delete a Patient resource
     * DELETE /fhir/Patient/{id}
     */
    public function deletePatient(string $id): JsonResponse
    {
        try {
            $deleted = $this->fhirService->deletePatient($id);

            if (!$deleted) {
                return $this->fhirError('not-found', "Patient with id '{$id}' not found", 404);
            }

            return response()->json(null, 204);

        } catch (\Exception $e) {
            Log::error('FHIR Patient deletion failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
        }
    }

    /**
     * Search Patient resources
     * GET /fhir/Patient
     */
    public function searchPatients(Request $request): JsonResponse
    {
        try {
            $searchParams = [
                'name' => $request->query('name'),
                'birthdate' => $request->query('birthdate'),
                'gender' => $request->query('gender'),
                'identifier' => $request->query('identifier'),
                '_count' => (int) ($request->query('_count', 20)),
                '_page' => (int) ($request->query('_page', 1)),
            ];

            $bundle = $this->fhirService->searchPatients($searchParams);

            return response()->json($bundle)
                ->header('Content-Type', 'application/fhir+json');

        } catch (\Exception $e) {
            Log::error('FHIR Patient search failed', ['params' => $request->query(), 'error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
        }
    }

    /**
     * Search Observation resources
     * GET /fhir/Observation
     */
    public function searchObservations(Request $request): JsonResponse
    {
        try {
            // Define allowed search parameters for Observation
            // Common parameters: patient, category, code, date, status, _count, _page
            $allowedParams = [
                'patient', 'category', 'code', 'date', 'status',
                '_count', '_page', 'subject', 'encounter' // Add other relevant params
            ];

            $searchParams = [];
            foreach ($allowedParams as $param) {
                if ($request->has($param)) {
                    $searchParams[$param] = $request->query($param);
                }
            }

            // Ensure _count and _page are integers if provided
            if (isset($searchParams['_count'])) {
                $searchParams['_count'] = (int) $searchParams['_count'];
            }
            if (isset($searchParams['_page'])) {
                $searchParams['_page'] = (int) $searchParams['_page'];
            }

            $bundle = $this->fhirService->searchObservations($searchParams);

            return response()->json($bundle)
                ->header('Content-Type', 'application/fhir+json');

        } catch (\Exception $e) {
            Log::error('FHIR Observation search failed', ['params' => $request->query(), 'error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error during Observation search', 500);
        }
    }

    /**
     * Create a Coverage resource
     * POST /fhir/Coverage
     */
    public function createCoverage(Request $request): JsonResponse
    {
        // Check if user can create product requests (since FHIR is used in QuickRequest flow)
        if (!Auth::user()->hasPermission('create-product-requests')) {
            return $this->fhirError('forbidden', 'Insufficient permissions to create coverage records', 403);
        }

        try {
            $data = $request->all();
            
            // Validate the incoming Coverage resource
            $validator = Validator::make($data, [
                'resourceType' => 'required|string|in:Coverage',
                'status' => 'required|string',
                'beneficiary' => 'required|array',
                'beneficiary.reference' => 'required|string',
                'payor' => 'required|array',
            ]);

            if ($validator->fails()) {
                return $this->fhirError('invalid', 'Invalid Coverage resource format', 400, $validator->errors()->toArray());
            }

            // For now, just return the resource with a generated ID
            // In a real implementation, this would be saved to Azure FHIR
            $data['id'] = \Illuminate\Support\Str::uuid()->toString();
            $data['meta'] = [
                'versionId' => '1',
                'lastUpdated' => now()->toIso8601String()
            ];

            Log::info('FHIR Coverage created', ['coverage_id' => $data['id']]);

            return response()->json($data, 201)
                ->header('Content-Type', 'application/fhir+json')
                ->header('Location', url("/fhir/Coverage/" . $data['id']));

        } catch (\Exception $e) {
            Log::error('FHIR Coverage creation failed', ['error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a QuestionnaireResponse resource
     * POST /fhir/QuestionnaireResponse
     */
    public function createQuestionnaireResponse(Request $request): JsonResponse
    {
        // Check if user can create product requests (since FHIR is used in QuickRequest flow)
        if (!Auth::user()->hasPermission('create-product-requests')) {
            return $this->fhirError('forbidden', 'Insufficient permissions to create questionnaire responses', 403);
        }

        try {
            $data = $request->all();
            
            // Validate the incoming QuestionnaireResponse resource
            $validator = Validator::make($data, [
                'resourceType' => 'required|string|in:QuestionnaireResponse',
                'status' => 'required|string',
                'subject' => 'required|array',
                'subject.reference' => 'required|string',
                'authored' => 'required|string',
                'item' => 'required|array',
            ]);

            if ($validator->fails()) {
                return $this->fhirError('invalid', 'Invalid QuestionnaireResponse resource format', 400, $validator->errors()->toArray());
            }

            // For now, just return the resource with a generated ID
            // In a real implementation, this would be saved to Azure FHIR
            $data['id'] = \Illuminate\Support\Str::uuid()->toString();
            $data['meta'] = [
                'versionId' => '1',
                'lastUpdated' => now()->toIso8601String()
            ];

            Log::info('FHIR QuestionnaireResponse created', ['questionnaire_response_id' => $data['id']]);

            return response()->json($data, 201)
                ->header('Content-Type', 'application/fhir+json')
                ->header('Location', url("/fhir/QuestionnaireResponse/" . $data['id']));

        } catch (\Exception $e) {
            Log::error('FHIR QuestionnaireResponse creation failed', ['error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a DeviceRequest resource
     * POST /fhir/DeviceRequest
     */
    public function createDeviceRequest(Request $request): JsonResponse
    {
        // Check if user can create product requests (since FHIR is used in QuickRequest flow)
        if (!Auth::user()->hasPermission('create-product-requests')) {
            return $this->fhirError('forbidden', 'Insufficient permissions to create device requests', 403);
        }

        try {
            $data = $request->all();
            
            // Validate the incoming DeviceRequest resource
            $validator = Validator::make($data, [
                'resourceType' => 'required|string|in:DeviceRequest',
                'status' => 'required|string',
                'intent' => 'required|string',
                'subject' => 'required|array',
                'subject.reference' => 'required|string',
                'code' => 'required|array',
            ]);

            if ($validator->fails()) {
                return $this->fhirError('invalid', 'Invalid DeviceRequest resource format', 400, $validator->errors()->toArray());
            }

            // For now, just return the resource with a generated ID
            // In a real implementation, this would be saved to Azure FHIR
            $data['id'] = \Illuminate\Support\Str::uuid()->toString();
            $data['meta'] = [
                'versionId' => '1',
                'lastUpdated' => now()->toIso8601String()
            ];

            Log::info('FHIR DeviceRequest created', ['device_request_id' => $data['id']]);

            return response()->json($data, 201)
                ->header('Content-Type', 'application/fhir+json')
                ->header('Location', url("/fhir/DeviceRequest/" . $data['id']));

        } catch (\Exception $e) {
            Log::error('FHIR DeviceRequest creation failed', ['error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * View version history for a Patient resource
     * GET /fhir/Patient/{id}/_history
     */
    public function patientHistory(string $id): JsonResponse
    {
        try {
            $bundle = $this->fhirService->getPatientHistory($id);

            if (!$bundle) {
                return $this->fhirError('not-found', "Patient with id '{$id}' not found", 404);
            }

            return response()->json($bundle)
                ->header('Content-Type', 'application/fhir+json');

        } catch (\Exception $e) {
            Log::error('FHIR Patient history failed', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
        }
    }

    /**
     * View system-wide Patient resource history
     * GET /fhir/Patient/_history
     */
    public function patientsHistory(): JsonResponse
    {
        try {
            $bundle = $this->fhirService->getPatientsHistory();

            return response()->json($bundle)
                ->header('Content-Type', 'application/fhir+json');

        } catch (\Exception $e) {
            Log::error('FHIR Patients history failed', ['error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
        }
    }

    /**
     * Perform batch or transaction
     * POST /fhir
     */
    public function transaction(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'resourceType' => 'required|string|in:Bundle',
                'type' => 'required|string|in:batch,transaction',
                'entry' => 'required|array',
            ]);

            if ($validator->fails()) {
                return $this->fhirError('invalid', 'Invalid bundle format', 400);
            }

            $bundle = $this->fhirService->processTransaction($request->all());

            return response()->json($bundle)
                ->header('Content-Type', 'application/fhir+json');

        } catch (\Exception $e) {
            Log::error('FHIR Transaction failed', ['error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
        }
    }

    /**
     * Retrieve server capability statement
     * GET /fhir/metadata
     */
    public function metadata(): JsonResponse
    {
        try {
            $capabilityStatement = $this->fhirService->getCapabilityStatement();

            return response()->json($capabilityStatement)
                ->header('Content-Type', 'application/fhir+json');

        } catch (\Exception $e) {
            Log::error('FHIR Metadata failed', ['error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
        }
    }

    /**
     * Generate FHIR-compliant error response
     */
    private function fhirError(string $code, string $diagnostics, int $httpStatus, ?array $operationOutcomeDetails = null): JsonResponse
    {
        $outcome = [
            'resourceType' => 'OperationOutcome',
            'issue' => [
                [
                    'severity' => 'error',
                    'code' => $code,
                    'diagnostics' => $diagnostics,
                ],
            ],
        ];
        if ($operationOutcomeDetails) {
            $outcome['issue'][0]['details'] = ['text' => json_encode($operationOutcomeDetails)];
        }

        return response()->json($outcome, $httpStatus)
            ->header('Content-Type', 'application/fhir+json');
    }
}
