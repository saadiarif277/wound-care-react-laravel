<?php

namespace App\Http\Requests\QuickRequest;

use Illuminate\Foundation\Http\FormRequest;

class SubmitOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->hasPermission('create-product-requests');
    }

    public function rules(): array
    {
        return [
            'formData' => 'required|array',
            'formData.selected_products' => 'required|array|min:1',
            'formData.selected_products.*.product_id' => 'required|exists:msc_products,id',
            'formData.selected_products.*.quantity' => 'required|integer|min:1',
            'formData.provider_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = $this->user();
                    if (!$user) return;

                    // Providers can only create orders for themselves
                    if ($user->hasRole('provider') && $value != $user->id) {
                        $fail('Providers can only create orders for themselves.');
                    }
                    // Office managers can create orders for providers in their organization
                    if ($user->hasRole('office-manager')) {
                        $provider = \App\Models\User::find($value);
                        if (!$provider || !$provider->hasRole('provider')) {
                            $fail('Selected provider is not valid.');
                        }
                        // Check if provider is in the same organization
                        $sharedOrganizations = $user->organizations()->pluck('organizations.id')
                            ->intersect($provider->organizations()->pluck('organizations.id'));
                        if ($sharedOrganizations->isEmpty()) {
                            $fail('Office managers can only create orders for providers in their organization.');
                        }
                    }
                },
            ],
            'formData.facility_id' => 'required|exists:facilities,id',
            'formData.patient_first_name' => 'required|string|max:255',
            'formData.patient_last_name' => 'required|string|max:255',
            'formData.patient_dob' => 'required|date|before:today',
            'formData.wound_type' => 'required|string',
            'formData.wound_location' => 'required|string',
            'formData.primary_insurance_name' => 'required|string',
            'formData.primary_member_id' => 'required|string',
            'formData.docuseal_submission_id' => 'nullable|string',
            'formData.ivr_document_url' => 'nullable|string',
            'episodeData' => 'sometimes|array',
            'adminNote' => 'sometimes|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'formData.selected_products.required' => 'At least one product must be selected',
            'formData.selected_products.*.product_id.exists' => 'Selected product does not exist',
            'formData.provider_id.exists' => 'Selected provider does not exist',
            'formData.facility_id.exists' => 'Selected facility does not exist',
            'formData.patient_dob.before' => 'Patient date of birth must be in the past',
            'formData.wound_type.required' => 'Wound type is required',
            'formData.wound_location.required' => 'Wound location is required',
            'formData.primary_insurance_name.required' => 'Primary insurance name is required',
            'formData.primary_member_id.required' => 'Primary insurance member ID is required',
        ];
    }
}
