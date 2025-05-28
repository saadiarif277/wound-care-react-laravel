<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Assuming any authenticated admin user can create an organization.
        // Adjust based on actual authorization logic (e.g., specific permissions).
        return auth()->check() && auth()->user()->hasRole('admin'); // Example, ensure User model has hasRole()
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Rules based on common fields for an organization and the controller usage.
        // These should be adjusted to match the actual 'organizations' table schema.
        return [
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:50|unique:organizations,tax_id', // Example: unique tax ID
            'type' => 'nullable|string|max:100', // e.g., 'Hospital', 'Clinic Group'
            'status' => 'nullable|string|in:active,pending,inactive', // Example statuses
            // 'primary_contact' => 'nullable|array', // If primary contact details are passed
            // 'primary_contact.first_name' => 'required_with:primary_contact|string|max:255',
            // 'primary_contact.last_name' => 'required_with:primary_contact|string|max:255',
            // 'primary_contact.email' => 'required_with:primary_contact|email|max:255',
            // 'primary_contact.phone' => 'nullable|string|max:20',
            'sales_rep_id' => 'nullable|exists:users,id', // Assuming sales_rep is a user
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tax_id.unique' => 'This Tax ID is already registered.',
            'sales_rep_id.exists' => 'The selected sales representative is invalid.',
        ];
    }
}
