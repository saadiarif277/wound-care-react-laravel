<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Models\Commissions\CommissionRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommissionRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-commission-rules')->only(['index', 'show']);
        $this->middleware('permission:create-commission-rules')->only(['create', 'store']);
        $this->middleware('permission:edit-commission-rules')->only(['edit', 'update']);
        $this->middleware('permission:delete-commission-rules')->only('destroy');
    }

    public function index()
    {
        // Dummy data for testing
        $dummyRules = [
            [
                'id' => 1,
                'target_type' => 'product',
                'target_id' => 101,
                'percentage_rate' => 5.00,
                'valid_from' => now()->subMonths(2),
                'valid_to' => null,
                'is_active' => true,
                'description' => 'Standard product commission rate',
                'created_at' => now()->subMonths(2),
                'updated_at' => now()->subMonths(2),
            ],
            [
                'id' => 2,
                'target_type' => 'manufacturer',
                'target_id' => 201,
                'percentage_rate' => 7.50,
                'valid_from' => now()->subMonth(),
                'valid_to' => now()->addMonths(2),
                'is_active' => true,
                'description' => 'Premium manufacturer commission rate',
                'created_at' => now()->subMonth(),
                'updated_at' => now()->subMonth(),
            ],
            [
                'id' => 3,
                'target_type' => 'category',
                'target_id' => 301,
                'percentage_rate' => 4.00,
                'valid_from' => now()->subMonths(3),
                'valid_to' => now()->addMonth(),
                'is_active' => false,
                'description' => 'Basic category commission rate',
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(1),
            ],
        ];

        return inertia('Commission/Rules/Index', [
            'rules' => [
                'data' => $dummyRules,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => count($dummyRules),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target_type' => 'required|in:product,manufacturer,category',
            'target_id' => 'required|integer',
            'percentage_rate' => 'required|numeric|min:0|max:100',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after:valid_from',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rule = CommissionRule::create($request->all());

        return response()->json($rule, 201);
    }

    public function show(CommissionRule $rule)
    {
        return response()->json($rule->load('target'));
    }

    public function update(Request $request, CommissionRule $rule)
    {
        $validator = Validator::make($request->all(), [
            'percentage_rate' => 'numeric|min:0|max:100',
            'valid_from' => 'date',
            'valid_to' => 'nullable|date|after:valid_from',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rule->update($request->all());

        return response()->json($rule);
    }

    public function destroy(CommissionRule $rule)
    {
        $rule->delete();
        return response()->json(null, 204);
    }
}
