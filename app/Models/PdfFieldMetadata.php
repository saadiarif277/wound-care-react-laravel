<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use App\Models\Order\Manufacturer;
use App\Models\PdfFieldNameIndex;
use App\Models\FieldMappingValidationHistory;

class PdfFieldMetadata extends Model
{
    use HasFactory;

    protected $table = 'pdf_field_metadata';

    protected $fillable = [
        'docuseal_template_id',
        'manufacturer_id',
        'template_name',
        'template_version',
        'pdf_file_path',
        'pdf_file_hash',
        'pdf_last_modified',
        'pdf_page_count',
        'field_name',
        'field_name_normalized',
        'field_type',
        'field_subtype',
        'is_required',
        'is_readonly',
        'is_calculated',
        'field_validation',
        'field_options',
        'default_value',
        'max_length',
        'input_format',
        'page_number',
        'x_coordinate',
        'y_coordinate',
        'width',
        'height',
        'tab_order',
        'field_group',
        'parent_field',
        'related_fields',
        'medical_category',
        'business_purpose',
        'field_description',
        'common_values',
        'ai_suggestions',
        'confidence_score',
        'mapping_alternatives',
        'usage_frequency',
        'last_used_at',
        'extraction_method',
        'extraction_version',
        'extraction_metadata',
        'extraction_verified',
        'extracted_at',
        'field_last_modified',
        'field_definition_changed',
        'change_history',
    ];

    protected $casts = [
        'pdf_last_modified' => 'datetime',
        'field_validation' => 'array',
        'field_options' => 'array',
        'related_fields' => 'array',
        'common_values' => 'array',
        'ai_suggestions' => 'array',
        'mapping_alternatives' => 'array',
        'extraction_metadata' => 'array',
        'change_history' => 'array',
        'last_used_at' => 'datetime',
        'extracted_at' => 'datetime',
        'field_last_modified' => 'datetime',
        'is_required' => 'boolean',
        'is_readonly' => 'boolean',
        'is_calculated' => 'boolean',
        'extraction_verified' => 'boolean',
        'field_definition_changed' => 'boolean',
        'confidence_score' => 'decimal:4',
        'x_coordinate' => 'decimal:4',
        'y_coordinate' => 'decimal:4',
        'width' => 'decimal:4',
        'height' => 'decimal:4',
    ];

    /**
     * Relationships
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function fieldNameVariants(): HasMany
    {
        return $this->hasMany(PdfFieldNameIndex::class);
    }

    public function validationHistory(): HasMany
    {
        return $this->hasMany(FieldMappingValidationHistory::class);
    }

    /**
     * Query Scopes
     */
    public function scopeByManufacturer(Builder $query, string $manufacturerName): Builder
    {
        return $query->whereHas('manufacturer', function($q) use ($manufacturerName) {
            $q->where('name', 'like', "%{$manufacturerName}%");
        });
    }

    public function scopeByFieldType(Builder $query, string $fieldType): Builder
    {
        return $query->where('field_type', $fieldType);
    }

    public function scopeByMedicalCategory(Builder $query, string $category): Builder
    {
        return $query->where('medical_category', $category);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('extraction_verified', true);
    }

    public function scopeHighConfidence(Builder $query, float $threshold = 0.8): Builder
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    public function scopeRecentlyUsed(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_used_at', '>=', Carbon::now()->subDays($days));
    }

    public function scopeByFieldNamePattern(Builder $query, string $pattern): Builder
    {
        return $query->where(function($q) use ($pattern) {
            $q->where('field_name', 'like', "%{$pattern}%")
              ->orWhere('field_name_normalized', 'like', "%{$pattern}%");
        });
    }

    /**
     * Field Analysis Methods
     */
    public function isPatientField(): bool
    {
        return $this->medical_category === 'patient' || 
               str_contains(strtolower($this->field_name_normalized), 'patient');
    }

    public function isProviderField(): bool
    {
        return $this->medical_category === 'provider' || 
               str_contains(strtolower($this->field_name_normalized), 'provider') ||
               str_contains(strtolower($this->field_name_normalized), 'physician');
    }

    public function isInsuranceField(): bool
    {
        return $this->medical_category === 'insurance' || 
               str_contains(strtolower($this->field_name_normalized), 'insurance') ||
               str_contains(strtolower($this->field_name_normalized), 'policy');
    }

    public function isFacilityField(): bool
    {
        return $this->medical_category === 'facility' || 
               str_contains(strtolower($this->field_name_normalized), 'facility') ||
               str_contains(strtolower($this->field_name_normalized), 'clinic');
    }

    /**
     * Field Matching Methods
     */
    public function calculateSimilarity(string $inputFieldName): float
    {
        $normalizedInput = $this->normalizeFieldName($inputFieldName);
        
        // Exact match
        if ($normalizedInput === $this->field_name_normalized) {
            return 1.0;
        }
        
        // Check against variants
        $variants = $this->fieldNameVariants()->pluck('field_name_variant', 'similarity_score');
        foreach ($variants as $score => $variant) {
            if (strtolower($variant) === strtolower($inputFieldName)) {
                return (float) $score;
            }
        }
        
        // Calculate Levenshtein similarity
        $distance = levenshtein($normalizedInput, $this->field_name_normalized);
        $maxLength = max(strlen($normalizedInput), strlen($this->field_name_normalized));
        
        return $maxLength > 0 ? 1 - ($distance / $maxLength) : 0.0;
    }

    public function normalizeFieldName(string $fieldName): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $fieldName)));
    }

    /**
     * Usage Tracking
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_frequency');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Field Validation
     */
    public function validateFieldValue($value): array
    {
        $errors = [];
        
        // Required field validation
        if ($this->is_required && empty($value)) {
            $errors[] = "Field '{$this->field_name}' is required";
        }
        
        // Type-specific validation
        switch ($this->field_type) {
            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email format for field '{$this->field_name}'";
                }
                break;
                
            case 'phone':
                if ($value && !preg_match('/^[\+]?[0-9\-\(\)\s]+$/', $value)) {
                    $errors[] = "Invalid phone format for field '{$this->field_name}'";
                }
                break;
                
            case 'date':
                if ($value && !strtotime($value)) {
                    $errors[] = "Invalid date format for field '{$this->field_name}'";
                }
                break;
                
            case 'number':
                if ($value && !is_numeric($value)) {
                    $errors[] = "Field '{$this->field_name}' must be numeric";
                }
                break;
        }
        
        // Length validation
        if ($this->max_length && strlen($value) > $this->max_length) {
            $errors[] = "Field '{$this->field_name}' exceeds maximum length of {$this->max_length}";
        }
        
        // Custom validation rules
        if ($this->field_validation) {
            foreach ($this->field_validation as $rule => $ruleValue) {
                switch ($rule) {
                    case 'pattern':
                        if ($value && !preg_match($ruleValue, $value)) {
                            $errors[] = "Field '{$this->field_name}' does not match required pattern";
                        }
                        break;
                        
                    case 'min_length':
                        if ($value && strlen($value) < $ruleValue) {
                            $errors[] = "Field '{$this->field_name}' must be at least {$ruleValue} characters";
                        }
                        break;
                }
            }
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'field_name' => $this->field_name,
            'field_type' => $this->field_type
        ];
    }

    /**
     * AI Integration
     */
    public function generateAISuggestions(): array
    {
        $suggestions = [];
        
        // Generate semantic suggestions based on field name
        $baseName = strtolower($this->field_name_normalized);
        
        if (str_contains($baseName, 'name')) {
            $suggestions[] = ['type' => 'patient_name', 'confidence' => 0.8];
            $suggestions[] = ['type' => 'provider_name', 'confidence' => 0.7];
        }
        
        if (str_contains($baseName, 'date') || str_contains($baseName, 'dob')) {
            $suggestions[] = ['type' => 'date_of_birth', 'confidence' => 0.9];
            $suggestions[] = ['type' => 'service_date', 'confidence' => 0.7];
        }
        
        if (str_contains($baseName, 'phone')) {
            $suggestions[] = ['type' => 'patient_phone', 'confidence' => 0.9];
            $suggestions[] = ['type' => 'provider_phone', 'confidence' => 0.6];
        }
        
        return $suggestions;
    }

    /**
     * Static utility methods
     */
    public static function findSimilarFields(string $inputFieldName, float $threshold = 0.7): \Illuminate\Database\Eloquent\Collection
    {
        $normalized = strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $inputFieldName)));
        
        return static::where('field_name_normalized', 'like', "%{$normalized}%")
            ->orWhereHas('fieldNameVariants', function($query) use ($inputFieldName) {
                $query->where('field_name_variant', 'like', "%{$inputFieldName}%");
            })
            ->get()
            ->filter(function($field) use ($inputFieldName, $threshold) {
                return $field->calculateSimilarity($inputFieldName) >= $threshold;
            });
    }

    public static function getFieldsByManufacturerAndType(string $manufacturerName, string $fieldType): \Illuminate\Database\Eloquent\Collection
    {
        return static::byManufacturer($manufacturerName)
            ->byFieldType($fieldType)
            ->verified()
            ->highConfidence()
            ->get();
    }

    public static function getPopularFields(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::orderBy('usage_frequency', 'desc')
            ->verified()
            ->limit($limit)
            ->get();
    }
}
