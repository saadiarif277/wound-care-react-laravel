<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteProvidersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Assuming admin users or users with specific permission can invite providers.
        // This should also check if the admin belongs to or manages the target organizationId from the route.
        return auth()->check() && auth()->user()->hasRole('admin'); // Placeholder
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'providers' => 'required|array|min:1',
            'providers.*.email' => 'required|email|max:255',
            'providers.*.first_name' => 'required|string|max:255',
            'providers.*.last_name' => 'required|string|max:255',
            'providers.*.facilities' => 'nullable|array',
            'providers.*.facilities.*' => [
                'integer',
                // Rule::exists('facilities', 'id')->where(function ($query) {
                //     // Ensure the facility belongs to the organization in the route parameter
                //     $query->where('organization_id', $this->route('organizationId')); // 'organizationId' is placeholder for actual route param name
                // }),
            ],
            'providers.*.roles' => 'nullable|array',
            'providers.*.roles.*' => 'string|in:provider,staff', // Example roles
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'providers.required' => 'At least one provider must be specified for invitation.',
            'providers.array' => 'Provider details must be provided in an array format.',
            'providers.*.email.required' => 'The provider email is required.',
            'providers.*.email.email' => 'Please provide a valid email address for the provider.',
            'providers.*.first_name.required' => 'The provider\'s first name is required.',
            'providers.*.last_name.required' => 'The provider\'s last name is required.',
            'providers.*.facilities.*.integer' => 'Invalid facility ID provided.',
            // 'providers.*.facilities.*.exists' => 'One or more selected facilities are invalid or do not belong to this organization.',
            'providers.*.roles.*.in' => 'Invalid role selected for a provider.',
        ];
    }
}
