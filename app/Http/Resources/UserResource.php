<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,

            // Re-added fields for frontend compatibility
            'name' => $this->name, // Uses the accessor from User model
            'owner' => $this->owner,
            'photo' => $this->photo ? url()->route('image', [
                'path' => $this->photo,
                'w' => 60,
                'h' => 60,
                'fit' => 'crop'
            ]) : null,
            'deleted_at' => $this->deleted_at,

            // Account relationship - conditionally loaded
            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                ];
            }),

            // Additional fields that may be needed
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Role information if loaded
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ];
                });
            }),
        ];
    }
}
