<?php

namespace App\Models\Order;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class MscProductRecommendationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'msc_product_recommendation_rules';

    protected $fillable = [
        'name',
        'description',
        'priority',
        'is_active',
        'wound_type',
        'wound_stage',
        'wound_depth',
        'conditions',
        'recommended_msc_product_qcodes_ranked',
        'reasoning_templates',
        'default_size_suggestion_key',
        'contraindications',
        'clinical_evidence',
        'created_by_user_id',
        'last_updated_by_user_id',
        'effective_date',
        'expiration_date'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'conditions' => 'array',
        'recommended_msc_product_qcodes_ranked' => 'array',
        'reasoning_templates' => 'array',
        'contraindications' => 'array',
        'clinical_evidence' => 'array',
        'effective_date' => 'date',
        'expiration_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        $now = Carbon::now()->toDateString();
        return $query->where(function ($q) use ($now) {
            $q->whereNull('effective_date')
              ->orWhere('effective_date', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('expiration_date')
              ->orWhere('expiration_date', '>=', $now);
        });
    }

    public function scopeOrderByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function scopeForWoundType($query, $woundType)
    {
        return $query->where(function ($q) use ($woundType) {
            $q->where('wound_type', $woundType)
              ->orWhereNull('wound_type');
        });
    }

    // Business Logic Methods
    public function matchesContext(array $context): bool
    {
        $conditions = $this->conditions ?? [];

        foreach ($conditions as $field => $expectedValue) {
            $contextValue = data_get($context, $field);

            if (is_array($expectedValue)) {
                // Handle array conditions (e.g., wagner_grade: [3, 4, 5])
                if (isset($expectedValue['min']) || isset($expectedValue['max'])) {
                    // Handle range conditions
                    if (isset($expectedValue['min']) && $contextValue < $expectedValue['min']) {
                        return false;
                    }
                    if (isset($expectedValue['max']) && $contextValue > $expectedValue['max']) {
                        return false;
                    }
                } else {
                    // Handle array membership
                    if (!in_array($contextValue, $expectedValue)) {
                        return false;
                    }
                }
            } else {
                // Handle exact match
                if ($contextValue !== $expectedValue) {
                    return false;
                }
            }
        }

        return true;
    }

    public function hasContraindications(array $context): bool
    {
        $contraindications = $this->contraindications ?? [];

        foreach ($contraindications as $condition) {
            $contextValue = data_get($context, $condition);
            if ($contextValue === true || $contextValue === 'severe' || $contextValue === 'yes') {
                return true;
            }
        }

        return false;
    }

    public function getRecommendedProducts(): array
    {
        return $this->recommended_msc_product_qcodes_ranked ?? [];
    }

    public function generateReasoning(string $qCode, array $context): string
    {
        $products = $this->getRecommendedProducts();
        $productRec = collect($products)->firstWhere('q_code', $qCode);

        if (!$productRec || !isset($productRec['reasoning_key'])) {
            return 'Recommended based on clinical criteria and best practices.';
        }

        $reasoningKey = $productRec['reasoning_key'];
        $templates = $this->reasoning_templates ?? [];

        if (!isset($templates[$reasoningKey])) {
            return 'Recommended based on clinical criteria and best practices.';
        }

        $template = $templates[$reasoningKey];

        // Replace placeholders with context values
        $reasoning = preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($context) {
            $key = $matches[1];
            $value = data_get($context, $key);

            if (is_array($value)) {
                return implode(', ', $value);
            }

            return $value ?? $key;
        }, $template);

        return $reasoning;
    }

    public function isEffective($date = null): bool
    {
        $checkDate = $date ? Carbon::parse($date) : Carbon::now();

        if ($this->effective_date && $checkDate->lt(Carbon::parse($this->effective_date))) {
            return false;
        }

        if ($this->expiration_date && $checkDate->gt(Carbon::parse($this->expiration_date))) {
            return false;
        }

        return true;
    }
}
