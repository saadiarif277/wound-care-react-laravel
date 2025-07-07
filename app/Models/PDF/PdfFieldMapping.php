<?php

namespace App\Models\PDF;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfFieldMapping extends Model
{
    protected $fillable = [
        'template_id',
        'pdf_field_name',
        'data_source',
        'field_type',
        'transform_function',
        'default_value',
        'validation_rules',
        'options',
        'display_order',
        'is_required',
        'ai_suggested',
        'ai_confidence',
        'ai_suggestion_metadata'
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'options' => 'array',
        'is_required' => 'boolean',
        'ai_suggested' => 'boolean',
        'ai_confidence' => 'float',
        'ai_suggestion_metadata' => 'array'
    ];

    /**
     * Get the template this mapping belongs to
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ManufacturerPdfTemplate::class, 'template_id');
    }

    /**
     * Get the value from nested data using dot notation
     */
    public function extractValue($data)
    {
        // Handle direct array access
        if (is_array($data) && isset($data[$this->data_source])) {
            return $data[$this->data_source];
        }

        // Handle nested access with dot notation
        $keys = explode('.', $this->data_source);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } elseif (is_object($value) && isset($value->$key)) {
                $value = $value->$key;
            } else {
                return $this->default_value;
            }
        }

        return $value !== null ? $value : $this->default_value;
    }

    /**
     * Apply transformation to the value if specified
     */
    public function transformValue($value)
    {
        if (!$this->transform_function) {
            return $value;
        }

        // Check if it's a built-in transformation
        $transformClass = 'App\\Services\\PDF\\Transformers\\PdfDataTransformer';
        if (class_exists($transformClass) && method_exists($transformClass, $this->transform_function)) {
            return call_user_func([$transformClass, $this->transform_function], $value);
        }

        // Check if it's a custom transform function
        $customTransform = PdfTransformFunction::where('function_name', $this->transform_function)
            ->where('is_active', true)
            ->first();

        if ($customTransform) {
            return eval($customTransform->function_code);
        }

        return $value;
    }

    /**
     * Get the final value for PDF filling
     */
    public function getFinalValue($data)
    {
        $value = $this->extractValue($data);
        return $this->transformValue($value);
    }

    /**
     * Validate the value against rules
     */
    public function validateValue($value): array
    {
        $errors = [];

        if ($this->is_required && empty($value)) {
            $errors[] = "Field '{$this->pdf_field_name}' is required";
        }

        if ($this->validation_rules && !empty($value)) {
            foreach ($this->validation_rules as $rule => $params) {
                switch ($rule) {
                    case 'min_length':
                        if (strlen($value) < $params) {
                            $errors[] = "Field '{$this->pdf_field_name}' must be at least {$params} characters";
                        }
                        break;
                    case 'max_length':
                        if (strlen($value) > $params) {
                            $errors[] = "Field '{$this->pdf_field_name}' must not exceed {$params} characters";
                        }
                        break;
                    case 'pattern':
                        if (!preg_match($params, $value)) {
                            $errors[] = "Field '{$this->pdf_field_name}' has invalid format";
                        }
                        break;
                    case 'in':
                        if (!in_array($value, $params)) {
                            $errors[] = "Field '{$this->pdf_field_name}' must be one of: " . implode(', ', $params);
                        }
                        break;
                }
            }
        }

        return $errors;
    }

    /**
     * Scope to get required fields
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to get fields by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('field_type', $type);
    }

    /**
     * Scope to get AI-suggested fields
     */
    public function scopeAiSuggested($query)
    {
        return $query->where('ai_suggested', true);
    }

    /**
     * Scope to get high-confidence AI suggestions
     */
    public function scopeHighConfidenceAi($query, float $minConfidence = 0.8)
    {
        return $query->where('ai_suggested', true)
                     ->where('ai_confidence', '>=', $minConfidence);
    }

    /**
     * Get AI suggestion method from metadata
     */
    public function getAiMethodAttribute(): ?string
    {
        return $this->ai_suggestion_metadata['method'] ?? null;
    }

    /**
     * Get AI suggestion reason from metadata
     */
    public function getAiReasonAttribute(): ?string
    {
        return $this->ai_suggestion_metadata['reason'] ?? null;
    }
}