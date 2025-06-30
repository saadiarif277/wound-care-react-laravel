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
<<<<<<< HEAD
=======

    /**
     * Debug endpoint to check provider products with detailed information
     * 
     * @param int $providerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function debugProviderProducts($providerId)
    {
        try {
            $provider = User::with(['roles', 'onboardedProducts' => function ($query) {
                $query->withPivot(['onboarding_status', 'expiration_date', 'notes', 'onboarded_at']);
            }])->find($providerId);

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider not found'
                ], 404);
            }

            // Check if user is actually a provider
            $isProvider = $provider->roles->contains('slug', 'provider');

            // Get all products with their status
            $allProducts = $provider->onboardedProducts->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'q_code' => $product->q_code,
                    'manufacturer' => $product->manufacturer,
                    'onboarding_status' => $product->pivot->onboarding_status,
                    'onboarded_at' => $product->pivot->onboarded_at,
                    'expiration_date' => $product->pivot->expiration_date,
                    'is_active' => $product->pivot->onboarding_status === 'active' && 
                                 (!$product->pivot->expiration_date || $product->pivot->expiration_date > now()),
                    'notes' => $product->pivot->notes,
                ];
            });

            // Get only active products
            $activeProducts = $allProducts->filter(function ($product) {
                return $product['is_active'];
            });

            // Get permissions
            $permissions = $provider->getAllPermissions()->pluck('slug')->toArray();

            return response()->json([
                'success' => true,
                'provider' => [
                    'id' => $provider->id,
                    'name' => $provider->name,
                    'email' => $provider->email,
                    'is_provider' => $isProvider,
                    'roles' => $provider->roles->pluck('slug'),
                    'permissions' => $permissions,
                    'has_view_msc_pricing' => in_array('view-msc-pricing', $permissions),
                    'has_view_national_asp' => in_array('view-national-asp', $permissions),
                ],
                'products' => [
                    'total_count' => $allProducts->count(),
                    'active_count' => $activeProducts->count(),
                    'active_q_codes' => $activeProducts->pluck('q_code')->values(),
                    'all_products' => $allProducts->values(),
                ],
                'debug_info' => [
                    'current_time' => now()->toDateTimeString(),
                    'provider_products_table_count' => \DB::table('provider_products')
                        ->where('user_id', $providerId)
                        ->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in debugProviderProducts', [
                'provider_id' => $providerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error debugging provider products',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error'
            ], 500);
        }
    }
>>>>>>> origin/provider-side
}
