<?php

namespace App\Http\Controllers;

use App\Services\FhirService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
        try {
            // Validate the incoming PatientFormData structure
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'dob' => 'required|date_format:Y-m-d',
                'member_id' => 'sometimes|nullable|string|max:255',
                'gender' => 'sometimes|nullable|string|in:male,female,other,unknown',
                'id' => 'sometimes|nullable|string|max:255', // e.g., eCW internal ID, if provided
            ]);

            if ($validator->fails()) {
                return $this->fhirError('invalid', 'Invalid patient data format', 400, $validator->errors()->toArray());
            }

            $validatedData = $validator->validated();

            // Transform validated data to FHIR Patient resource structure
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

            // Add member_id as an identifier
            // The system for member_id needs to be defined (e.g., from config or a constant)
            // For now, using a placeholder system.
            $memberIdSystem = config('app.fhir_identifier_systems.member_id', 'urn:oid:2.16.840.1.113883.3.4.5.6'); // Example system
            if (!empty($validatedData['member_id'])) {
                $fhirPatientStructure['identifier'][] = [
                    'use' => 'usual',
                    'type' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                'code' => 'MB', // Member Number
                                'display' => 'Member Number',
                            ],
                        ],
                        'text' => 'Member Number',
                    ],
                    'system' => $memberIdSystem,
                    'value' => $validatedData['member_id'],
                ];
            }

            // If an external ID (like eCW ID) was passed, store it as another identifier
            $externalIdSystem = config('app.fhir_identifier_systems.ecw_id', 'urn:oid:1.2.3.4.5.ecw'); // Example system for eCW ID
            if (!empty($validatedData['id'])) {
                 $fhirPatientStructure['identifier'][] = [
                    'use' => 'secondary',
                     'type' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                'code' => 'PI', // Patient internal identifier
                                'display' => 'Patient internal identifier',
                            ],
                        ],
                        'text' => 'External Patient ID',
                    ],
                    'system' => $externalIdSystem, // Define a system for this ID
                    'value' => $validatedData['id'],
                ];
            }

            // TODO: Consider adding other fields: active, telecom, address if available from a more comprehensive form

            // The fhirService->createPatient method should expect a FHIR resource array
            $createdFhirPatient = $this->fhirService->createPatient($fhirPatientStructure);

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
