<?php

namespace App\Http\Requests;

use App\Models\AccessRequest;
use Illuminate\Foundation\Http\FormRequest;

class AccessRequestStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Anyone can submit an access request
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:access_requests,email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'requested_role' => 'required|in:' . implode(',', array_keys(AccessRequest::ROLES)),
            'request_notes' => 'nullable|string|max:1000',

            // Provider fields
            'npi_number' => 'required_if:requested_role,provider|nullable|string|max:255',
            'medical_license' => 'required_if:requested_role,provider|nullable|string|max:255',
            'license_state' => 'required_if:requested_role,provider|nullable|string|max:2',
            'specialization' => 'nullable|string|max:255',
            'facility_name' => 'required_if:requested_role,provider,office_manager|nullable|string|max:255',
            'facility_address' => 'required_if:requested_role,provider,office_manager|nullable|string|max:500',

            // Office Manager fields
            'manager_name' => 'required_if:requested_role,office_manager|nullable|string|max:255',
            'manager_email' => 'required_if:requested_role,office_manager|nullable|email|max:255',

            // MSC Rep fields
            'territory' => 'required_if:requested_role,msc_rep,msc_subrep|nullable|string|max:255',
            'manager_contact' => 'required_if:requested_role,msc_rep|nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0|max:50',

            // MSC SubRep fields
            'main_rep_name' => 'required_if:requested_role,msc_subrep|nullable|string|max:255',
            'main_rep_email' => 'required_if:requested_role,msc_subrep|nullable|email|max:255',

            // MSC Admin fields
            'department' => 'required_if:requested_role,msc_admin|nullable|string|max:255',
            'supervisor_name' => 'required_if:requested_role,msc_admin|nullable|string|max:255',
            'supervisor_email' => 'required_if:requested_role,msc_admin|nullable|email|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered or has an existing access request.',
            'requested_role.required' => 'Please select a role.',
            'requested_role.in' => 'Please select a valid role.',

            // Provider-specific messages
            'npi_number.required_if' => 'NPI number is required for healthcare providers.',
            'medical_license.required_if' => 'Medical license number is required for healthcare providers.',
            'license_state.required_if' => 'License state is required for healthcare providers.',
            'facility_name.required_if' => 'Facility name is required for this role.',
            'facility_address.required_if' => 'Facility address is required for this role.',

            // Office Manager-specific messages
            'manager_name.required_if' => 'Practice manager name is required for office managers.',
            'manager_email.required_if' => 'Practice manager email is required for office managers.',

            // MSC Rep-specific messages
            'territory.required_if' => 'Territory is required for sales representatives.',
            'manager_contact.required_if' => 'Manager contact is required for MSC representatives.',

            // MSC SubRep-specific messages
            'main_rep_name.required_if' => 'Main representative name is required for sub-representatives.',
            'main_rep_email.required_if' => 'Main representative email is required for sub-representatives.',

            // MSC Admin-specific messages
            'department.required_if' => 'Department is required for MSC administrators.',
            'supervisor_name.required_if' => 'Supervisor name is required for MSC administrators.',
            'supervisor_email.required_if' => 'Supervisor email is required for MSC administrators.',
        ];
    }
}
