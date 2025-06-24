<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderReviewPageController extends Controller
{
    /**
     * Display the order review page.
     */
    public function show(Request $request, string $orderId)
    {
        $order = Order::findOrFail($orderId);
        
        // Check authorization
        $this->authorize('view', $order);
        
        return Inertia::render('Orders/Review', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status ?? 'draft'
            ]
        ]);
    }
}