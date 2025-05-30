<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EngineController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function clinicalRules(Request $request)
    {
        $this->authorize('manage-clinical-rules');

        // Mock data for Clinical Opportunity Engine rules
        $rules = [
            [
                'id' => 1,
                'name' => 'Diabetic Foot Ulcer Protocol',
                'category' => 'wound_type',
                'condition' => 'wound_type = "diabetic_foot_ulcer"',
                'recommendation' => 'Recommend advanced wound care products',
                'active' => true,
                'priority' => 1,
                'created_at' => '2024-01-15',
                'last_modified' => '2024-03-10',
            ],
            [
                'id' => 2,
                'name' => 'Venous Leg Ulcer Compression',
                'category' => 'treatment_protocol',
                'condition' => 'wound_type = "venous_leg_ulcer" AND wound_duration > 30',
                'recommendation' => 'Require compression therapy documentation',
                'active' => true,
                'priority' => 2,
                'created_at' => '2024-02-01',
                'last_modified' => '2024-03-15',
            ],
            [
                'id' => 3,
                'name' => 'High-Risk Patient Alert',
                'category' => 'patient_risk',
                'condition' => 'patient_age > 65 AND diabetes = true',
                'recommendation' => 'Flag for clinical review',
                'active' => true,
                'priority' => 3,
                'created_at' => '2024-02-15',
                'last_modified' => '2024-03-20',
            ],
        ];

        // Apply filters
        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $rules = array_filter($rules, function ($rule) use ($search) {
                return strpos(strtolower($rule['name']), $search) !== false ||
                       strpos(strtolower($rule['category']), $search) !== false;
            });
        }

        if ($request->filled('category')) {
            $category = $request->input('category');
            $rules = array_filter($rules, fn ($rule) => $rule['category'] === $category);
        }

        if ($request->filled('active')) {
            $active = $request->input('active') === 'true';
            $rules = array_filter($rules, fn ($rule) => $rule['active'] === $active);
        }

        return Inertia::render('Engines/ClinicalRules', [
            'rules' => array_values($rules),
            'filters' => $request->only(['search', 'category', 'active']),
            'categories' => ['wound_type', 'treatment_protocol', 'patient_risk', 'documentation'],
            'stats' => [
                'total_rules' => count($rules),
                'active_rules' => count(array_filter($rules, fn ($r) => $r['active'])),
                'inactive_rules' => count(array_filter($rules, fn ($r) => !$r['active'])),
            ],
        ]);
    }

    public function recommendationRules(Request $request)
    {
        $this->authorize('manage-recommendation-rules');

        // Mock data for Product Recommendation Engine rules
        $rules = [
            [
                'id' => 1,
                'name' => 'Advanced Wound Matrix for Deep Ulcers',
                'wound_criteria' => ['depth > 2mm', 'wound_type = "diabetic_foot_ulcer"'],
                'recommended_products' => [
                    ['product_id' => 1, 'product_name' => 'CollaGenesis Matrix', 'priority' => 1],
                    ['product_id' => 2, 'product_name' => 'WoundEx Advanced', 'priority' => 2],
                ],
                'confidence_score' => 0.95,
                'active' => true,
                'usage_count' => 247,
                'success_rate' => 0.89,
                'created_at' => '2024-01-10',
            ],
            [
                'id' => 2,
                'name' => 'Compression System for Venous Ulcers',
                'wound_criteria' => ['wound_type = "venous_leg_ulcer"', 'ankle_brachial_index > 0.8'],
                'recommended_products' => [
                    ['product_id' => 3, 'product_name' => 'CompriFlex System', 'priority' => 1],
                    ['product_id' => 4, 'product_name' => 'VenousWrap Plus', 'priority' => 2],
                ],
                'confidence_score' => 0.92,
                'active' => true,
                'usage_count' => 189,
                'success_rate' => 0.84,
                'created_at' => '2024-01-20',
            ],
        ];

        return Inertia::render('Engines/RecommendationRules', [
            'rules' => $rules,
            'filters' => $request->only(['search', 'wound_type', 'active']),
            'stats' => [
                'total_rules' => count($rules),
                'average_confidence' => round(array_sum(array_column($rules, 'confidence_score')) / count($rules), 2),
                'total_usage' => array_sum(array_column($rules, 'usage_count')),
                'average_success_rate' => round(array_sum(array_column($rules, 'success_rate')) / count($rules), 2),
            ],
        ]);
    }

    public function commission(Request $request)
    {
        $this->authorize('manage-commission-engine');

        // Mock data for Commission Engine configuration
        $commissionRules = [
            [
                'id' => 1,
                'rule_name' => 'MSC Rep Base Commission',
                'role' => 'msc_rep',
                'commission_type' => 'percentage',
                'rate' => 0.05, // 5%
                'minimum_order' => 100.00,
                'maximum_commission' => 1000.00,
                'active' => true,
                'applies_to' => 'all_products',
                'created_at' => '2024-01-01',
            ],
            [
                'id' => 2,
                'rule_name' => 'MSC Subrep Commission',
                'role' => 'msc_subrep',
                'commission_type' => 'percentage',
                'rate' => 0.03, // 3%
                'minimum_order' => 50.00,
                'maximum_commission' => 500.00,
                'active' => true,
                'applies_to' => 'assigned_facilities',
                'created_at' => '2024-01-01',
            ],
            [
                'id' => 3,
                'rule_name' => 'High-Value Order Bonus',
                'role' => 'msc_rep',
                'commission_type' => 'flat_rate',
                'rate' => 100.00, // $100 bonus
                'minimum_order' => 2000.00,
                'maximum_commission' => 100.00,
                'active' => true,
                'applies_to' => 'orders_over_threshold',
                'created_at' => '2024-02-01',
            ],
        ];

        $engineStats = [
            'total_rules' => count($commissionRules),
            'active_rules' => count(array_filter($commissionRules, fn ($r) => $r['active'])),
            'total_commissions_ytd' => 125000.00,
            'average_commission_rate' => 0.045,
            'last_calculation_run' => '2024-03-25 08:00:00',
        ];

        return Inertia::render('Engines/Commission', [
            'commissionRules' => $commissionRules,
            'engineStats' => $engineStats,
            'filters' => $request->only(['search', 'role', 'active']),
        ]);
    }
} 