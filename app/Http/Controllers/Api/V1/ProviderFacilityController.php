<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Fhir\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProviderFacilityController extends Controller
{
    /**
     * Get facilities that a specific provider has access to
     * GET /api/v1/providers/{providerId}/facilities
     */
    public function getProviderFacilities(int $providerId): JsonResponse
    {
        try {
            // Find the provider
            $provider = User::find($providerId);

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider not found'
                ], 404);
            }

            // Get facilities that this provider has access to
            $facilities = $provider->facilities()
                ->where('facility_user.role', 'provider')
                ->where('facilities.active', true)
                ->get()
                ->map(function($facility) {
                    return [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'address' => $facility->full_address ?? 'No address',
                        'city' => $facility->city,
                        'state' => $facility->state,
                        'zip_code' => $facility->zip_code,
                        'phone' => $facility->phone,
                        'npi' => $facility->npi,
                    ];
                });

            Log::info('ProviderFacilityController: Retrieved facilities for provider', [
                'provider_id' => $providerId,
                'provider_name' => $provider->name,
                'facilities_count' => $facilities->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $facilities,
                'provider' => [
                    'id' => $provider->id,
                    'name' => $provider->name,
                    'npi' => $provider->npi_number
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('ProviderFacilityController: Error retrieving provider facilities', [
                'provider_id' => $providerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving provider facilities',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
