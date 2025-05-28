<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'entity_type' => [
                'required',
                'string',
                Rule::in(['organization', 'facility', 'user'])
            ],
            'entity_id' => [
                'required',
                'string'
            ],
            'document_type' => [
                'required',
                'string',
                Rule::in([
                    'license',
                    'certification', 
                    'insurance',
                    'w9',
                    'tax_document',
                    'credential',
                    'agreement',
                    'policy'
                ])
            ],
            'document' => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimes:pdf,doc,docx,jpg,jpeg,png,tiff',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png,image/tiff'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'document.mimes' => 'The document must be a PDF, Word document, or image file (JPG, PNG, TIFF).',
            'document.mimetypes' => 'The document file type is not supported for security reasons.',
            'document.max' => 'The document file size cannot exceed 10MB.',
            'entity_type.in' => 'The entity type must be organization, facility, or user.',
            'document_type.in' => 'The document type is not valid for this upload.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'entity_type' => 'entity type',
            'entity_id' => 'entity ID',
            'document_type' => 'document type'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional security validation
            if ($this->hasFile('document')) {
                $file = $this->file('document');
                
                // Check for suspicious file names
                if (preg_match('/[<>:"|?*\\\\\/]/', $file->getClientOriginalName())) {
                    $validator->errors()->add('document', 'The file name contains invalid characters.');
                }
                
                // Check file extension against MIME type
                $allowedMimes = [
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'tiff' => 'image/tiff'
                ];
                
                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType = $file->getMimeType();
                
                if (isset($allowedMimes[$extension]) && $allowedMimes[$extension] !== $mimeType) {
                    $validator->errors()->add('document', 'The file extension does not match the file content.');
                }
            }
        });
    }
} 