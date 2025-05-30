<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Placeholder: Customize with actual facility fields
        return [
            'id' => $this->id,
            'name' => $this->name,
            'facility_type' => $this->whenNotNull($this->facility_type),
            'group_npi' => $this->whenNotNull($this->group_npi),
            'status' => $this->status,
            'address' => new AddressResource($this->whenLoaded('address')), // Assuming AddressResource and address relationship
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
