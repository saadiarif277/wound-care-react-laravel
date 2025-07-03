<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ConfigurationController extends Controller
{
    /**
     * Get insurance product rules
     */
    public function getInsuranceProductRules(Request $request)
    {
        $insuranceType = $request->input('insurance_type') ?? '';
        $state = $request->input('state') ?? '';
        $woundSize = $request->input('wound_size') ?? 0;
        
        $cacheKey = "insurance_rules_{$insuranceType}_{$state}_{$woundSize}";
        
        return Cache::remember($cacheKey, 3600, function () use ($insuranceType, $state, $woundSize) {
            $query = DB::table('insurance_product_rules')
                ->where('is_active', true)
                ->where('insurance_type', $insuranceType);
            
            // For Medicaid, check state-specific rules first
            if ($insuranceType === 'medicaid' && $state) {
                $stateRule = clone $query;
                $stateRule->where('state_code', $state);
                
                if ($stateRule->exists()) {
                    $query->where('state_code', $state);
                } else {
                    // Fall back to default Medicaid rules
                    $query->whereNull('state_code');
                }
            }
            
            // Apply wound size filters for applicable insurance types
            if ($woundSize && in_array($insuranceType, ['medicare'])) {
                $query->where(function ($q) use ($woundSize) {
                    $q->where(function ($sub) use ($woundSize) {
                        $sub->where('wound_size_min', '<=', $woundSize)
                            ->where('wound_size_max', '>=', $woundSize);
                    })->orWhere(function ($sub) use ($woundSize) {
                        $sub->where('wound_size_min', '<=', $woundSize)
                            ->whereNull('wound_size_max');
                    })->orWhere(function ($sub) use ($woundSize) {
                        $sub->whereNull('wound_size_min')
                            ->where('wound_size_max', '>=', $woundSize);
                    });
                });
            }
            
            $rules = $query->get();
            
            // Transform the data
            return $rules->map(function ($rule) {
                return [
                    'allowed_products' => json_decode($rule->allowed_product_codes, true),
                    'message' => $rule->coverage_message,
                    'requires_consultation' => $rule->requires_consultation,
                    'wound_size_range' => [
                        'min' => $rule->wound_size_min,
                        'max' => $rule->wound_size_max,
                    ],
                ];
            });
        });
    }
    
    /**
     * Get diagnosis codes
     */
    public function getDiagnosisCodes(Request $request)
    {
        $category = $request->input('category') ?? '';
        
        $cacheKey = "diagnosis_codes_{$category}";
        
        return Cache::remember($cacheKey, 3600, function () use ($category) {
            $query = DB::table('diagnosis_codes')
                ->where('is_active', true)
                ->orderBy('code');
            
            if ($category) {
                $query->where('category', $category);
            }
            
            $codes = $query->get();
            
            // Group by category
            return $codes->groupBy('category')->map(function ($group) {
                return $group->map(function ($code) {
                    return [
                        'code' => $code->code,
                        'description' => $code->description,
                        'specialty' => $code->specialty,
                    ];
                })->values();
            });
        });
    }
    
    /**
     * Get wound types
     */
    public function getWoundTypes()
    {
        return Cache::remember('wound_types', 3600, function () {
            return DB::table('wound_types')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->mapWithKeys(function ($type) {
                    return [$type->code => $type->display_name];
                });
        });
    }
    
    /**
     * Get product MUE limits (restricted to admins only)
     */
    public function getProductMueLimits()
    {
        // MUE is sensitive CMS data - only accessible to admins
        $user = auth()->user();
        if (!$user || !$user->hasPermission('manage-products')) {
            return response()->json([
                'message' => 'Unauthorized. MUE data is restricted to administrators.'
            ], 403);
        }

        return Cache::remember('product_mue_limits', 3600, function () {
            return DB::table('msc_products')
                ->whereNotNull('mue')
                ->pluck('mue', 'q_code');
        });
    }
    
    /**
     * Get MSC contacts
     */
    public function getMscContacts(Request $request)
    {
        $department = $request->input('department') ?? '';
        $purpose = $request->input('purpose') ?? '';
        
        $cacheKey = "msc_contacts_{$department}_{$purpose}";
        
        return Cache::remember($cacheKey, 3600, function () use ($department, $purpose) {
            $query = DB::table('msc_contacts')
                ->where('is_active', true);
            
            if ($department) {
                $query->where('department', $department);
            }
            
            if ($purpose) {
                $query->where('purpose', $purpose);
            }
            
            return $query->orderByDesc('is_primary')->get();
        });
    }
    
    /**
     * Get all configuration data for QuickRequest
     */
    public function getQuickRequestConfig()
    {
        $user = auth()->user();
        $config = [
            'wound_types' => $this->getWoundTypes(),
            'docuseal_templates' => config('docuseal.templates'),
            'docuseal_account_email' => config('docuseal.account_email'),
        ];

        // Only include MUE limits for admin users
        if ($user && $user->hasPermission('manage-products')) {
            $config['mue_limits'] = $this->getProductMueLimits();
        }

        return response()->json($config);
    }
}