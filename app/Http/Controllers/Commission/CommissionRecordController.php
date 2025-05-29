<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Models\Commissions\CommissionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommissionRecordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-commission-records')->only(['index', 'show', 'summary']);
        $this->middleware('permission:approve-commission-records')->only(['approve']);
    }

    public function index(Request $request)
    {
        $query = CommissionRecord::with(['order', 'orderItem', 'rep', 'parentRep', 'approver', 'payout']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rep_id')) {
            $query->where('rep_id', $request->rep_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('calculation_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('calculation_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('rep', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhereHas('order', function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%");
            });
        }

        // Apply role-based filtering
        $user = Auth::user();
        if (!$user->hasPermission('view-all-commission-records')) {
            // MSC Reps can only see their own records and their sub-reps
            if ($user->hasRole('msc-rep')) {
                $query->where(function ($q) use ($user) {
                    $q->where('rep_id', $user->id)
                      ->orWhere('parent_rep_id', $user->id);
                });
            }
            // MSC Sub-reps can only see their own records
            elseif ($user->hasRole('msc-subrep')) {
                $query->where('rep_id', $user->id);
            }
        }

        $records = $query->orderBy('calculation_date', 'desc')
                        ->paginate($request->get('per_page', 20));

        return inertia('Commission/Records/Index', [
            'records' => $records,
            'summary' => $this->summary($request),
            'filters' => $request->only(['status', 'rep_id', 'date_from', 'date_to', 'search']),
        ]);
    }

    public function approve(Request $request, CommissionRecord $record)
    {
        $this->authorize('approve', $record);

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'notes' => $request->notes,
        ]);

        return response()->json($record->load(['order', 'orderItem', 'rep', 'parentRep', 'approver', 'payout']));
    }

    public function show(CommissionRecord $record)
    {
        $this->authorize('view', $record);

        return response()->json($record->load(['order', 'orderItem', 'rep', 'parentRep', 'approver', 'payout']));
    }

    public function summary(Request $request)
    {
        $query = CommissionRecord::query();

        // Apply same filters as index
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rep_id')) {
            $query->where('rep_id', $request->rep_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('calculation_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('calculation_date', '<=', $request->date_to);
        }

        // Apply role-based filtering
        $user = Auth::user();
        if (!$user->hasPermission('view-all-commission-records')) {
            if ($user->hasRole('msc-rep')) {
                $query->where(function ($q) use ($user) {
                    $q->where('rep_id', $user->id)
                      ->orWhere('parent_rep_id', $user->id);
                });
            } elseif ($user->hasRole('msc-subrep')) {
                $query->where('rep_id', $user->id);
            }
        }

        return [
            'total_commission' => $query->sum('amount'),
            'pending_commission' => $query->where('status', 'pending')->sum('amount'),
            'approved_commission' => $query->where('status', 'approved')->sum('amount'),
            'paid_commission' => $query->where('status', 'paid')->sum('amount'),
            'record_count' => $query->count(),
        ];
    }
}
