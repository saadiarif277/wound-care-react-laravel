<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Placeholder: Customize with actual address fields
        return [
            'id' => $this->id,
            'street_1' => $this->street_1,
            'street_2' => $this->whenNotNull($this->street_2),
            'city' => $this->city,
            'state_province' => $this->state_province,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'address_type' => $this->address_type,
        ];
    }
}
