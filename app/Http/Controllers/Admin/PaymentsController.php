<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaymentsController extends Controller
{
    /**
     * Display the payments page
     */
    public function index()
    {
        // Get all providers (temporarily showing all until we have orders with payment data)
        $providers = User::with(['currentOrganization'])
            ->whereHas('roles', function ($query) {
                $query->where('slug', 'provider');
            })
            // Temporarily commented out to show all providers
            // ->whereExists(function ($query) {
            //     $query->select(DB::raw(1))
            //         ->from('orders')
            //         ->whereColumn('provider_id', 'users.id')
            //         ->where('payment_status', '!=', 'paid');
            // })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function ($provider) {
                return [
                    'id' => $provider->id,
                    'name' => $provider->name, // This will use the accessor
                    'first_name' => $provider->first_name,
                    'last_name' => $provider->last_name,
                    'email' => $provider->email,
                    'npi_number' => $provider->npi_number,
                    'current_organization' => $provider->currentOrganization ? [
                        'id' => $provider->currentOrganization->id,
                        'name' => $provider->currentOrganization->name,
                    ] : null,
                ];
            });

        return Inertia::render('Admin/Payments/Index', [
            'providers' => $providers,
        ]);
    }

    /**
     * Get outstanding orders for a provider
     */
    public function getProviderOrders($providerId)
    {
        $orders = Order::where('provider_id', $providerId)
            ->where('payment_status', '!=', 'paid')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount ?? 0,
                    'paid_amount' => $order->paid_amount ?? 0,
                    'payment_status' => $order->payment_status ?? 'unpaid',
                    'outstanding_balance' => ($order->total_amount ?? 0) - ($order->paid_amount ?? 0),
                    'created_at' => $order->created_at,
                ];
            });

        return response()->json([
            'orders' => $orders
        ]);
    }

    /**
     * Record a payment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider_id' => 'required|exists:users,id',
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:check,wire,ach,credit_card,other',
            'reference_number' => 'nullable|string|max:100',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated, $request) {
            // Create payment record
            DB::table('payments')->insert([
                'provider_id' => $validated['provider_id'],
                'order_id' => $validated['order_id'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'],
                'payment_date' => $validated['payment_date'],
                'notes' => $validated['notes'],
                'status' => 'posted',
                'posted_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update order
            $order = Order::find($validated['order_id']);
            $order->paid_amount = ($order->paid_amount ?? 0) + $validated['amount'];
            
            // Update payment status
            if ($order->paid_amount >= $order->total_amount) {
                $order->payment_status = 'paid';
                $order->paid_at = now();
            } else {
                $order->payment_status = 'partial';
            }
            
            $order->save();

            // TODO: Add activity logging when package is installed
        });

        return redirect()->route('admin.payments.index')
            ->with('success', 'Payment recorded successfully.');
    }

    /**
     * Display payment history
     */
    public function history(Request $request)
    {
        $query = DB::table('payments')
            ->join('users as providers', 'payments.provider_id', '=', 'providers.id')
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->join('users as posted_by', 'payments.posted_by_user_id', '=', 'posted_by.id')
            ->select(
                'payments.*',
                DB::raw("CONCAT(providers.first_name, ' ', providers.last_name) as provider_name"),
                'providers.email as provider_email',
                'orders.order_number',
                DB::raw("CONCAT(posted_by.first_name, ' ', posted_by.last_name) as posted_by_name")
            );

        // Apply filters
        if ($request->filled('provider_id')) {
            $query->where('payments.provider_id', $request->input('provider_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('payments.payment_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('payments.payment_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payments.payment_method', $request->input('payment_method'));
        }

        $payments = $query->orderBy('payments.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get providers for filter dropdown
        $providers = User::whereHas('roles', function ($query) {
                $query->where('slug', 'provider');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return Inertia::render('Admin/Payments/History', [
            'payments' => $payments,
            'providers' => $providers,
            'filters' => $request->only(['provider_id', 'date_from', 'date_to', 'payment_method']),
        ]);
    }
}