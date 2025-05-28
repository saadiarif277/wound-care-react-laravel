<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
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
            'name' => $this->name,
            'tax_id' => $this->whenNotNull($this->tax_id),
            'type' => $this->whenNotNull($this->type),
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Example of including relationships if loaded
            'facilities' => FacilityResource::collection($this->whenLoaded('facilities')),
            'sales_rep' => new UserResource($this->whenLoaded('salesRep')), // Assuming a UserResource exists

            // Placeholder for onboarding status from the controller logic if not directly on model
            // 'onboarding_status' => $this->when(isset($this->onboarding_status), $this->onboarding_status),
        ];
    }
}
