<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RolePermissionUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasPermission('manage-rbac');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
            'reason' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'permission_ids.required' => 'At least one permission must be selected.',
            'permission_ids.array' => 'Permissions must be provided as an array.',
            'permission_ids.*.exists' => 'One or more selected permissions are invalid.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
        ];
    }
}
