<?php

namespace App\Http\Resources\QuickRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class TaskResource extends JsonResource
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
            'fhir_task_id' => $this->fhir_task_id,
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'title' => $this->title,
            'description' => $this->description,
            'assigned_to' => new UserResource($this->whenLoaded('assignee')),
            'assigned_role' => $this->assigned_role,
            'due_date' => $this->due_date?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completed_by' => new UserResource($this->whenLoaded('completer')),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => new UserResource($this->whenLoaded('canceller')),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'is_overdue' => $this->due_date && $this->due_date->isPast() && !$this->completed_at,
            'can' => [
                'update' => $request->user()?->can('update', $this->resource),
                'complete' => $request->user()?->can('complete', $this->resource),
                'cancel' => $request->user()?->can('cancel', $this->resource),
                'reassign' => $request->user()?->can('reassign', $this->resource),
            ],
        ];
    }
}