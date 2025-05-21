<?php

namespace App\Http\Controllers;

use App\Models\CommissionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommissionRecordController extends Controller
{
    public function index(Request $request)
    {
        // Dummy data for testing
        $dummyRecords = [
            [
                'id' => 1,
                'order_id' => 1001,
                'order_item_id' => 2001,
                'rep_id' => 1,
                'rep' => ['id' => 1, 'name' => 'John Doe'],
                'parent_rep_id' => null,
                'parent_rep' => null,
                'amount' => 150.00,
                'percentage_rate' => 5.00,
                'type' => 'direct-rep',
                'status' => 'pending',
                'calculation_date' => now()->subDays(2),
                'approved_by' => null,
                'approved_at' => null,
                'payout_id' => null,
                'notes' => null,
            ],
            [
                'id' => 2,
                'order_id' => 1002,
                'order_item_id' => 2002,
                'rep_id' => 2,
                'rep' => ['id' => 2, 'name' => 'Jane Smith'],
                'parent_rep_id' => 1,
                'parent_rep' => ['id' => 1, 'name' => 'John Doe'],
                'amount' => 75.00,
                'percentage_rate' => 3.50,
                'type' => 'sub-rep-share',
                'status' => 'approved',
                'calculation_date' => now()->subDays(5),
                'approved_by' => 1,
                'approved_at' => now()->subDays(3),
                'payout_id' => null,
                'notes' => 'Approved by manager',
            ],
            [
                'id' => 3,
                'order_id' => 1003,
                'order_item_id' => 2003,
                'rep_id' => 1,
                'rep' => ['id' => 1, 'name' => 'John Doe'],
                'parent_rep_id' => null,
                'parent_rep' => null,
                'amount' => 225.00,
                'percentage_rate' => 7.50,
                'type' => 'direct-rep',
                'status' => 'paid',
                'calculation_date' => now()->subDays(10),
                'approved_by' => 1,
                'approved_at' => now()->subDays(8),
                'payout_id' => 1,
                'notes' => 'Processed in monthly payout',
            ],
        ];

        return inertia('Commission/Records/Index', [
            'records' => [
                'data' => $dummyRecords,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => count($dummyRecords),
            ],
            'summary' => $this->summary($request),
        ]);
    }

    public function approve(Request $request, CommissionRecord $record)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'notes' => $request->notes,
        ]);

        return response()->json($record->load(['order', 'orderItem', 'rep', 'parentRep', 'approver', 'payout']));
    }

    public function show(CommissionRecord $record)
    {
        return response()->json($record->load(['order', 'orderItem', 'rep', 'parentRep', 'approver', 'payout']));
    }

    public function summary(Request $request)
    {
        // Dummy summary data
        return [
            'total_commission' => 450.00,
            'pending_commission' => 150.00,
            'approved_commission' => 75.00,
            'paid_commission' => 225.00,
            'record_count' => 3,
        ];
    }
}
