<?php

namespace App\Observers;

use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "updating" event.
     * Track status changes before they are saved
     */
    public function updating(Order $order): void
    {
        // Check if order_status is being changed
        if ($order->isDirty('order_status')) {
            $previousStatus = $order->getOriginal('order_status');
            $newStatus = $order->order_status;
            
            // Log status change to history table
            try {
                DB::table('order_status_history')->insert([
                    'order_id' => $order->id,
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus,
                    'notes' => $order->status_notes ?? null,
                    'changed_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to log order status change', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Log initial status
        try {
            DB::table('order_status_history')->insert([
                'order_id' => $order->id,
                'previous_status' => null,
                'new_status' => $order->order_status,
                'notes' => 'Order created',
                'changed_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log initial order status', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}