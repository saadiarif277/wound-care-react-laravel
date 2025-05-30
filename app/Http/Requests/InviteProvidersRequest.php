<?php

namespace App\Http\Requests;

use App\Models\Fhir\Facility;
use App\Models\Users\Organization\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InviteProvidersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user is authenticated and has permission to invite providers
        if (!Auth::check() || !Auth::user()->hasPermission('invite-providers')) {
            return false;
        }

        // Get organization ID from route parameter
        $organizationId = $this->route('organizationId');

        if (!$organizationId) {
            return false;
        }

        // Verify organization exists
        $organization = Organization::find($organizationId);
        if (!$organization) {
            return false;
        }

        // Check if user has permission to manage this specific organization
        $user = Auth::user();

        // MSC Admins can invite providers to any organization
        if ($user->hasPermission('manage-all-organizations')) {
            return true;
        }

        // Office Managers can only invite providers to their own organization
        if ($user->hasPermission('manage-organization') && $user->organization_id == $organizationId) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = $this->route('organizationId');

        return [
            'providers' => 'required|array|min:1',
            'providers.*.email' => [
                'required',
                'email',
                'max:255',
                // Prevent duplicate emails within the request
                'distinct:ignore_case',
                // Prevent inviting existing users
                Rule::unique('users', 'email'),
                // Prevent duplicate pending invitations
                Rule::unique('provider_invitations', 'email')->where(function ($query) use ($organizationId) {
                    return $query->where('organization_id', $organizationId)
                                 ->whereIn('status', ['pending', 'sent']);
                }),
            ],
            'providers.*.first_name' => 'required|string|max:255',
            'providers.*.last_name' => 'required|string|max:255',
            'providers.*.facilities' => 'nullable|array',
            'providers.*.facilities.*' => [
                'integer',
                Rule::exists('facilities', 'id')->where(function ($query) use ($organizationId) {
                    // Ensure the facility belongs to the organization in the route parameter
                    $query->where('organization_id', $organizationId);
                }),
            ],
            'providers.*.roles' => 'nullable|array',
            'providers.*.roles.*' => 'string|in:provider,staff',
            'providers.*.specialty' => 'nullable|string|max:255',
            'providers.*.npi' => [
                'nullable',
                'digits:10',
                'distinct', // Prevent duplicate NPIs within the request
                Rule::unique('users', 'npi'), // Prevent duplicate NPIs in database
            ],
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
            'providers.*.email.distinct' => 'Duplicate email addresses are not allowed in the same invitation.',
            'providers.*.email.unique' => 'This email address is already registered or has a pending invitation.',
            'providers.*.first_name.required' => 'The provider\'s first name is required.',
            'providers.*.last_name.required' => 'The provider\'s last name is required.',
            'providers.*.facilities.*.integer' => 'Invalid facility ID provided.',
            'providers.*.facilities.*.exists' => 'One or more selected facilities are invalid or do not belong to this organization.',
            'providers.*.roles.*.in' => 'Invalid role selected for a provider.',
            'providers.*.npi.digits' => 'NPI must be exactly 10 digits.',
            'providers.*.npi.distinct' => 'Duplicate NPI numbers are not allowed in the same invitation.',
            'providers.*.npi.unique' => 'This NPI number is already registered.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean and format NPI numbers
        if ($this->has('providers')) {
            $providers = $this->input('providers');

            foreach ($providers as $index => $provider) {
                if (isset($provider['npi'])) {
                    // Remove any non-digit characters from NPI
                    $providers[$index]['npi'] = preg_replace('/\D/', '', $provider['npi']);
                }

                // Trim and normalize email
                if (isset($provider['email'])) {
                    $providers[$index]['email'] = strtolower(trim($provider['email']));
                }
            }

            $this->merge(['providers' => $providers]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $organizationId = $this->route('organizationId');

            // Additional validation: Ensure organization exists and user has access
            if ($organizationId) {
                $organization = Organization::find($organizationId);
                if (!$organization) {
                    $validator->errors()->add('organization', 'The specified organization does not exist.');
                }
            }

            // Additional validation: Check facility assignments make sense
            if ($this->has('providers')) {
                $organizationFacilityIds = Facility::where('organization_id', $organizationId)->pluck('id')->toArray();

                foreach ($this->input('providers') as $index => $provider) {
                    if (isset($provider['facilities'])) {
                        foreach ($provider['facilities'] as $facilityIndex => $facilityId) {
                            if (!in_array($facilityId, $organizationFacilityIds)) {
                                $validator->errors()->add(
                                    "providers.{$index}.facilities.{$facilityIndex}",
                                    'The selected facility does not belong to this organization.'
                                );
                            }
                        }
                    }
                }
            }
        });
    }
}
