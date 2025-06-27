<?php

declare(strict_types=1);

namespace App\Http\Resources\QuickRequest;

use App\Http\Resources\DocumentResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpisodeResource extends JsonResource
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
            'patient_fhir_id' => $this->patient_fhir_id,
            'practitioner_fhir_id' => $this->practitioner_fhir_id,
            'organization_fhir_id' => $this->organization_fhir_id,
            'episode_of_care_fhir_id' => $this->episode_of_care_fhir_id,
            'status' => $this->status,
            'patient_display' => $this->patient_display,
            'manufacturer' => [
                'id' => $this->manufacturer_id,
                'name' => $this->manufacturer?->name,
                'code' => $this->manufacturer?->code,
            ],
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'updated_by' => new UserResource($this->whenLoaded('updater')),
            'approved_by' => new UserResource($this->whenLoaded('approver')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'statistics' => $this->when($request->has('include_stats'), [
                'total_orders' => $this->orders_count ?? $this->orders->count(),
                'pending_tasks' => $this->pending_tasks_count ?? $this->tasks->where('status', 'pending')->count(),
                'days_since_created' => $this->created_at->diffInDays(now()),
            ]),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource),
                'delete' => $request->user()?->can('delete', $this->resource),
                'approve' => $request->user()?->can('approve', $this->resource),
                'cancel' => $request->user()?->can('cancel', $this->resource),
                'add_order' => $request->user()?->can('addOrder', $this->resource),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => config('app.api_version', 'v1'),
                'timestamp' => now()->toIso8601String(),
                'request_id' => $request->header('X-Request-ID', uniqid('req_')),
            ],
        ];
    }
}