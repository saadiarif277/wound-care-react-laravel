<?php

namespace App\Services\QuickRequest;

use App\DataTransferObjects\ProductSelectionData;
use App\Models\Order\Product;
use Illuminate\Support\Facades\Log;

class QuickRequestCalculationService
{
    public function calculateOrderTotal(ProductSelectionData $productSelection): array
    {
        $subtotal = 0;
        $totalQuantity = 0;
        $itemBreakdown = [];

        foreach ($productSelection->selectedProducts as $productData) {
            $product = Product::find($productData['product_id']);
            
            if (!$product) {
                Log::warning('Product not found for calculation', [
                    'product_id' => $productData['product_id']
                ]);
                continue;
            }

            $quantity = $productData['quantity'] ?? 1;
            // Fix: Use the correct price field that exists on the model
            $unitPrice = $product->price_per_sq_cm ?? 0;
            $itemTotal = $unitPrice * $quantity;

            $subtotal += $itemTotal;
            $totalQuantity += $quantity;

            $itemBreakdown[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $itemTotal,
                'size' => $productData['size'] ?? null,
            ];
        }

        // Calculate tax (8% for example)
        $tax = $subtotal * 0.08;

        // Calculate shipping (free over $500, otherwise $15)
        $shipping = $subtotal > 500 ? 0 : 15.00;

        $total = $subtotal + $tax + $shipping;

        Log::info('Order total calculated', [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total,
            'item_count' => count($itemBreakdown)
        ]);

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shipping, 2),
            'total' => round($total, 2),
            'total_quantity' => $totalQuantity,
            'item_breakdown' => $itemBreakdown,
        ];
    }

    public function calculateProductPricing(int $productId, int $quantity, ?string $size = null): array
    {
        $product = Product::find($productId);
        
        if (!$product) {
            return [
                'unit_price' => 0,
                'total_price' => 0,
                'error' => 'Product not found'
            ];
        }

        $unitPrice = $product->price_per_sq_cm ?? 0;
        $totalPrice = $unitPrice * $quantity;

        return [
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'product_name' => $product->name,
            'size' => $size,
            'quantity' => $quantity,
        ];
    }
} 