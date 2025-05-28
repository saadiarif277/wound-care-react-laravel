<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Models\Commissions\Commission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CommissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-commissions')->only(['index', 'show']);
        $this->middleware('permission:create-commissions')->only(['create', 'store']);
        $this->middleware('permission:edit-commissions')->only(['edit', 'update']);
        $this->middleware('permission:delete-commissions')->only('destroy');
        $this->middleware('permission:approve-commissions')->only('approve');
        $this->middleware('permission:process-commissions')->only('process');
    }

    public function index()
    {
        $commissions = Commission::with(['rep', 'approvedBy'])->get();
        return response()->json(['commissions' => $commissions]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rep_id' => 'required|exists:users,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $commission = Commission::create($validated);
        return response()->json(['commission' => $commission], 201);
    }

    public function show(Commission $commission)
    {
        return response()->json(['commission' => $commission->load(['rep', 'approvedBy'])]);
    }

    public function update(Request $request, Commission $commission)
    {
        if ($commission->status !== 'calculated') {
            return response()->json(['message' => 'Cannot update a commission that has been approved or processed'], 403);
        }

        $validated = $request->validate([
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date|after:period_start',
            'total_amount' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $commission->update($validated);
        return response()->json(['commission' => $commission]);
    }

    public function destroy(Commission $commission)
    {
        if ($commission->status !== 'calculated') {
            return response()->json(['message' => 'Cannot delete a commission that has been approved or processed'], 403);
        }

        $commission->delete();
        return response()->json(null, 204);
    }

    public function approve(Request $request, Commission $commission)
    {
        if ($commission->status !== 'calculated') {
            return response()->json(['message' => 'Commission must be in calculated status to approve'], 403);
        }

        $commission->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json(['commission' => $commission->load(['rep', 'approvedBy'])]);
    }

    public function process(Request $request, Commission $commission)
    {
        if ($commission->status !== 'approved') {
            return response()->json(['message' => 'Commission must be approved before processing'], 403);
        }

        $validated = $request->validate([
            'payment_reference' => 'required|string|max:255',
        ]);

        $commission->update([
            'status' => 'processed',
            'processed_at' => now(),
            'payment_reference' => $validated['payment_reference'],
        ]);

        return response()->json(['commission' => $commission->load(['rep', 'approvedBy'])]);
    }
}
