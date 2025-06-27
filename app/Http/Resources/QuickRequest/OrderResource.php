<?php

namespace App\Http\Resources\QuickRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'episode_id' => $this->episode_id,
            'based_on' => $this->based_on,
            'type' => $this->type,
            'status' => $this->status,
            'device_request_fhir_id' => $this->device_request_fhir_id,
            'details' => $this->details,
            'products' => $this->formatProducts(),
            'delivery_info' => $this->details['delivery_info'] ?? null,
            'clinical_info' => $this->details['clinical_info'] ?? null,
            'parent_order' => new OrderResource($this->whenLoaded('parentOrder')),
            'follow_up_orders' => OrderResource::collection($this->whenLoaded('followUpOrders')),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'updated_by' => new UserResource($this->whenLoaded('updater')),
            'fulfilled_by' => new UserResource($this->whenLoaded('fulfiller')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'tracking' => $this->when($this->tracking_number, [
                'carrier' => $this->carrier,
                'tracking_number' => $this->tracking_number,
                'shipped_at' => $this->shipped_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
            ]),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource),
                'cancel' => $request->user()?->can('cancel', $this->resource),
                'fulfill' => $request->user()?->can('fulfill', $this->resource),
                'create_follow_up' => $request->user()?->can('createFollowUp', $this->resource),
            ],
        ];
    }

    /**
     * Format products array
     */
    private function formatProducts(): array
    {
        $products = $this->details['products'] ?? [];
        
        return collect($products)->map(function ($product) {
            return [
                'id' => $product['id'] ?? null,
                'name' => $product['name'] ?? null,
                'code' => $product['code'] ?? null,
                'category' => $product['category'] ?? null,
                'quantity' => $product['quantity'] ?? 0,
                'frequency' => $product['frequency'] ?? null,
                'sizes' => collect($product['sizes'] ?? [])->filter(fn($size) => ($size['quantity'] ?? 0) > 0)->values(),
                'modifiers' => $product['modifiers'] ?? [],
                'special_instructions' => $product['special_instructions'] ?? null,
            ];
        })->toArray();
    }
}