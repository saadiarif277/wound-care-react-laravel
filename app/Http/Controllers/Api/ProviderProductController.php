<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProviderProductController extends Controller
{
    /**
     * Get provider's onboarded products (Q-codes only)
     */
    public function getOnboardedProducts($providerId)
    {
        try {
            $provider = User::with(['onboardedProducts' => function ($query) {
                $query->where('provider_products.onboarding_status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('provider_products.expiration_date')
                            ->orWhere('provider_products.expiration_date', '>', now());
                    });
            }])->find($providerId);

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider not found',
                    'q_codes' => []
                ], 404);
            }

            // Get Q-codes for onboarded products
            $qCodes = $provider->onboardedProducts->pluck('q_code')->filter()->values()->toArray();

            return response()->json([
                'success' => true,
                'provider_id' => $providerId,
                'provider_name' => $provider->name,
                'q_codes' => $qCodes,
                'count' => count($qCodes)
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching provider onboarded products', [
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching provider products',
                'q_codes' => []
            ], 500);
        }
    }

    /**
     * Get all providers with their onboarded products (for admin/office manager views)
     */
    public function getAllProvidersProducts(Request $request)
    {
        try {
            $providers = User::whereHas('roles', function ($query) {
                $query->where('slug', 'provider');
            })->with(['onboardedProducts' => function ($query) {
                $query->where('provider_products.onboarding_status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('provider_products.expiration_date')
                            ->orWhere('provider_products.expiration_date', '>', now());
                    });
            }])->get();

            $providerProducts = [];

            foreach ($providers as $provider) {
                $qCodes = $provider->onboardedProducts->pluck('q_code')->filter()->values()->toArray();
                $providerProducts[$provider->id] = $qCodes;
            }

            return response()->json([
                'success' => true,
                'provider_products' => $providerProducts,
                'total_providers' => count($providers)
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all provider products', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching provider products',
                'provider_products' => []
            ], 500);
        }
    }
}
