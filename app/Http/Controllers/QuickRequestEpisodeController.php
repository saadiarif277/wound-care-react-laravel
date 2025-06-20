<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuickRequestEpisodeController extends Controller
{
    /**
     * Get or create episode ID for QuickRequest IVR linking
     */
    public function getEpisodeId(Request $request)
    {
        $request->validate([
            'patient_display_id' => 'required|string',
            'product_id' => 'required|integer',
            'manufacturer_id' => 'nullable|integer',
        ]);

        try {
            // Get manufacturer ID from product if not provided
            $manufacturerId = $request->manufacturer_id;
            if (!$manufacturerId && $request->product_id) {
                $product = Product::find($request->product_id);
                $manufacturerId = $product->manufacturer_id ?? null;
            }

            if (!$manufacturerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine manufacturer',
                ], 400);
            }

            // Generate a temporary patient identifier for episode creation
            // This will be updated with the actual FHIR ID when the order is submitted
            $tempPatientId = 'TEMP_' . $request->patient_display_id . '_' . Str::random(8);

            // Create a new episode that will be linked to the IVR
            $episode = PatientManufacturerIVREpisode::create([
                'id' => Str::uuid(),
                'patient_id' => $tempPatientId,
                'patient_display_id' => $request->patient_display_id,
                'manufacturer_id' => $manufacturerId,
                'status' => 'pending_ivr', // Initial status before IVR completion
                'ivr_status' => 'pending',
                'created_by' => Auth::id(),
                'metadata' => [
                    'quick_request' => true,
                    'created_for_ivr' => true,
                    'product_id' => $request->product_id,
                ],
            ]);

            Log::info('Created episode for QuickRequest IVR', [
                'episode_id' => $episode->id,
                'patient_display_id' => $request->patient_display_id,
                'manufacturer_id' => $manufacturerId,
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create episode for QuickRequest', [
                'error' => $e->getMessage(),
                'patient_display_id' => $request->patient_display_id,
                'product_id' => $request->product_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode: ' . $e->getMessage(),
            ], 500);
        }
    }
}
