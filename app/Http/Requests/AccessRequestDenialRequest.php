<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccessRequestDenialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user has permission to approve access requests
        return $this->user() && $this->user()->hasPermission('approve-access-requests');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'admin_notes' => 'required|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'admin_notes.required' => 'Please provide a reason for denying this access request.',
            'admin_notes.max' => 'Admin notes cannot exceed 1000 characters.',
        ];
    }
}
