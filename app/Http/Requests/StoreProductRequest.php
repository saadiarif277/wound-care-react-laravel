<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user && $user->hasPermission('manage-products');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => 'required|string|unique:msc_products,sku',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'manufacturer' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'national_asp' => 'nullable|numeric|min:0',
            'price_per_sq_cm' => 'nullable|numeric|min:0',
            'q_code' => 'nullable|string|max:10',
            'mue' => 'nullable|integer|min:0',
            'available_sizes' => 'nullable|array',
            'available_sizes.*' => 'string|max:20',
            'size_options' => 'nullable|array',
            'size_options.*' => 'string|max:20',
            'size_pricing' => 'nullable|array',
            'size_unit' => 'nullable|string|in:in,cm',
            'graph_type' => 'nullable|string|max:255',
            'image_url' => 'nullable|url',
            'document_urls' => 'nullable|array',
            'document_urls.*' => 'url',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'This SKU is already in use by another product.',
            'sku.required' => 'SKU is required for product identification.',
            'name.required' => 'Product name is required.',
            'name.max' => 'Product name cannot exceed 255 characters.',
            'q_code.max' => 'Q-code cannot exceed 10 characters.',
            'national_asp.min' => 'National ASP must be a positive value.',
            'price_per_sq_cm.min' => 'Price per square cm must be a positive value.',
            'mue.min' => 'MUE must be a positive integer.',
            'commission_rate.min' => 'Commission rate must be at least 0%.',
            'commission_rate.max' => 'Commission rate cannot exceed 100%.',
            'size_unit.in' => 'Size unit must be either "in" (inches) or "cm" (centimeters).',
            'image_url.url' => 'Image URL must be a valid URL.',
            'document_urls.*.url' => 'All document URLs must be valid URLs.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sku' => 'SKU',
            'q_code' => 'Q-code',
            'national_asp' => 'National ASP',
            'price_per_sq_cm' => 'price per square cm',
            'mue' => 'MUE (Maximum Units of Eligibility)',
            'commission_rate' => 'commission rate',
            'size_unit' => 'size unit',
            'image_url' => 'image URL',
            'document_urls' => 'document URLs',
        ];
    }
}
