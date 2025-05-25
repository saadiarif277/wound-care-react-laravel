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
            $validator = Validator::make($request->all(), [
                'resourceType' => 'required|string|in:Patient',
                'name' => 'array',
                'gender' => 'string|in:male,female,other,unknown',
                'birthDate' => 'date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return $this->fhirError('invalid', 'Invalid resource format', 400);
            }

            $fhirPatient = $this->fhirService->createPatient($request->all());

            return response()->json($fhirPatient, 201)
                ->header('Content-Type', 'application/fhir+json')
                ->header('Location', url("/fhir/Patient/{$fhirPatient['id']}"));

        } catch (\Exception $e) {
            Log::error('FHIR Patient creation failed', ['error' => $e->getMessage()]);
            return $this->fhirError('processing', 'Internal server error', 500);
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
    private function fhirError(string $code, string $diagnostics, int $httpStatus): JsonResponse
    {
        $operationOutcome = [
            'resourceType' => 'OperationOutcome',
            'issue' => [
                [
                    'severity' => $httpStatus >= 500 ? 'error' : 'warning',
                    'code' => $code,
                    'diagnostics' => $diagnostics,
                ]
            ]
        ];

        return response()->json($operationOutcome, $httpStatus)
            ->header('Content-Type', 'application/fhir+json');
    }
}
