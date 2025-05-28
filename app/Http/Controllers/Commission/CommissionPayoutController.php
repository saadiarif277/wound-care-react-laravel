<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Models\Commissions\CommissionPayout;
use App\Services\PayoutCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CommissionPayoutController extends Controller
{
    protected $payoutService;

    public function __construct(PayoutCalculatorService $payoutService)
    {
        $this->payoutService = $payoutService;
    }

    public function index(Request $request)
    {
        // Dummy data for testing
        $dummyPayouts = [
            [
                'id' => 1,
                'rep_id' => 1,
                'rep' => ['id' => 1, 'name' => 'John Doe'],
                'period_start' => now()->subMonth(),
                'period_end' => now(),
                'total_amount' => 2250.00,
                'status' => 'processed',
                'approved_by' => 1,
                'approver' => ['id' => 1, 'name' => 'Admin User'],
                'approved_at' => now()->subDays(5),
                'processed_at' => now()->subDays(3),
                'payment_reference' => 'PAY-2024-001',
                'notes' => 'Monthly payout processed',
            ],
            [
                'id' => 2,
                'rep_id' => 2,
                'rep' => ['id' => 2, 'name' => 'Jane Smith'],
                'period_start' => now()->subMonth(),
                'period_end' => now(),
                'total_amount' => 1750.00,
                'status' => 'approved',
                'approved_by' => 1,
                'approver' => ['id' => 1, 'name' => 'Admin User'],
                'approved_at' => now()->subDays(2),
                'processed_at' => null,
                'payment_reference' => null,
                'notes' => 'Pending processing',
            ],
            [
                'id' => 3,
                'rep_id' => 1,
                'rep' => ['id' => 1, 'name' => 'John Doe'],
                'period_start' => now()->subDays(7),
                'period_end' => now(),
                'total_amount' => 850.00,
                'status' => 'calculated',
                'approved_by' => null,
                'approver' => null,
                'approved_at' => null,
                'processed_at' => null,
                'payment_reference' => null,
                'notes' => 'Weekly payout calculated',
            ],
        ];

        return inertia('Commission/Payouts/Index', [
            'payouts' => [
                'data' => $dummyPayouts,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => count($dummyPayouts),
            ],
        ]);
    }

    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->payoutService->generatePayouts(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date)
            );

            return response()->json(['message' => 'Payouts generated successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function approve(Request $request, CommissionPayout $payout)
    {
        try {
            $this->payoutService->approvePayout($payout, Auth::id());
            return response()->json(['message' => 'Payout approved successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function process(Request $request, CommissionPayout $payout)
    {
        $validator = Validator::make($request->all(), [
            'payment_reference' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->payoutService->markPayoutAsProcessed(
                $payout,
                $request->payment_reference
            );

            return response()->json(['message' => 'Payout marked as processed']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(CommissionPayout $payout)
    {
        return response()->json($payout->load(['rep', 'approver', 'commissionRecords']));
    }
}
