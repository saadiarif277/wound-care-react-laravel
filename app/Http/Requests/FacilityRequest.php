<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FacilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'facility_type' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
            'zip_code' => ['required', 'string', 'regex:/^\d{5}(-\d{4})?$/'],
            'phone' => ['nullable', 'string', 'regex:/^\+?1?\d{10,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'npi' => ['nullable', 'string', 'regex:/^\d{10}$/', 'unique:facilities,npi'],
            'group_npi' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'tax_id' => ['nullable', 'string', 'regex:/^\d{2}-\d{7}$/'],
            'ptan' => ['nullable', 'string', 'max:50'],
            'medicare_admin_contractor' => ['nullable', 'string', 'max:255'],
            'default_place_of_service' => ['nullable', 'in:11,12,31,32'],
            'business_hours' => ['nullable', 'array'],
            'active' => ['boolean'],
        ];

        // If updating, make NPI unique except for current record
        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            $facilityId = $this->route('facility') ? $this->route('facility')->id : $this->route('id');
            $rules['npi'] = ['nullable', 'string', 'regex:/^\d{10}$/', 'unique:facilities,npi,' . $facilityId];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'npi.regex' => 'The NPI must be exactly 10 digits.',
            'npi.unique' => 'This NPI is already registered to another facility.',
            'group_npi.regex' => 'The Group NPI must be exactly 10 digits.',
            'tax_id.regex' => 'The Tax ID must be in the format XX-XXXXXXX.',
            'zip_code.regex' => 'The ZIP code must be in the format XXXXX or XXXXX-XXXX.',
            'phone.regex' => 'Please enter a valid phone number.',
            'default_place_of_service.in' => 'Place of service must be one of: 11 (Office), 12 (Home), 31 (SNF), 32 (Nursing Facility).',
        ];
    }

    /**
     * Get custom attribute names.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'npi' => 'NPI',
            'group_npi' => 'Group NPI',
            'tax_id' => 'Tax ID',
            'ptan' => 'PTAN',
            'medicare_admin_contractor' => 'Medicare Administrative Contractor',
            'default_place_of_service' => 'Default Place of Service',
        ];
    }
}