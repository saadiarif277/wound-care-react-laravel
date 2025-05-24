<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MscProductRecommendationRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'priority', // Higher number = higher priority
        'is_active',
        'wound_type', // DFU, VLU, PrU, etc.
        'wound_stage',
        'wound_depth',
        'conditions', // JSON - complex matching conditions
        'recommended_msc_product_qcodes_ranked', // JSON array with Q-codes, ranks, and reasoning
        'reasoning_templates', // JSON map for generating human-readable explanations
        'default_size_suggestion_key', // MATCH_WOUND_AREA, STANDARD_2x2, etc.
        'contraindications', // JSON array of conditions that exclude this rule
        'clinical_evidence', // JSON with supporting evidence/studies
        'created_by_user_id',
        'last_updated_by_user_id',
        'effective_date',
        'expiration_date',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'conditions' => 'array',
        'recommended_msc_product_qcodes_ranked' => 'array',
        'reasoning_templates' => 'array',
        'contraindications' => 'array',
        'clinical_evidence' => 'array',
        'effective_date' => 'date',
        'expiration_date' => 'date',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the user who created this rule
     */
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user who last updated this rule
     */
    public function lastUpdatedByUser()
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    /**
     * Scope for active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for current rules (within effective dates)
     */
    public function scopeCurrent($query)
    {
        $today = now()->toDate();
        return $query->where('effective_date', '<=', $today)
                    ->where(function($q) use ($today) {
                        $q->whereNull('expiration_date')
                          ->orWhere('expiration_date', '>=', $today);
                    });
    }

    /**
     * Scope by wound type
     */
    public function scopeByWoundType($query, $woundType)
    {
        return $query->where('wound_type', $woundType);
    }

    /**
     * Scope by priority (highest first)
     */
    public function scopeOrderByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if rule is currently applicable
     */
    public function isCurrentlyApplicable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $today = now()->toDate();

        if ($this->effective_date && $this->effective_date > $today) {
            return false;
        }

        if ($this->expiration_date && $this->expiration_date < $today) {
            return false;
        }

        return true;
    }

    /**
     * Get recommended products with rankings
     */
    public function getRecommendedProducts(): array
    {
        return $this->recommended_msc_product_qcodes_ranked ?? [];
    }

    /**
     * Get primary recommended product (highest ranked)
     */
    public function getPrimaryRecommendedProduct(): ?array
    {
        $products = $this->getRecommendedProducts();

        if (empty($products)) {
            return null;
        }

        // Sort by rank (lower number = higher priority)
        usort($products, function($a, $b) {
            return ($a['rank'] ?? 999) <=> ($b['rank'] ?? 999);
        });

        return $products[0] ?? null;
    }

    /**
     * Generate reasoning for a specific product recommendation
     */
    public function generateReasoning(string $qCode, array $context = []): string
    {
        $templates = $this->reasoning_templates ?? [];

        // Find the template for this Q-code
        $template = $templates[$qCode] ?? $templates['default'] ?? 'Recommended based on clinical criteria.';

        // Simple template variable replacement
        foreach ($context as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    /**
     * Check if conditions match given context
     */
    public function matchesContext(array $context): bool
    {
        $conditions = $this->conditions ?? [];

        foreach ($conditions as $field => $expectedValue) {
            $contextValue = $context[$field] ?? null;

            if (is_array($expectedValue)) {
                // Check if context value is in the expected array
                if (!in_array($contextValue, $expectedValue)) {
                    return false;
                }
            } else {
                // Direct match
                if ($contextValue !== $expectedValue) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check for contraindications
     */
    public function hasContraindications(array $context): bool
    {
        $contraindications = $this->contraindications ?? [];

        foreach ($contraindications as $field => $contraindicatedValue) {
            $contextValue = $context[$field] ?? null;

            if (is_array($contraindicatedValue)) {
                if (in_array($contextValue, $contraindicatedValue)) {
                    return true;
                }
            } else {
                if ($contextValue === $contraindicatedValue) {
                    return true;
                }
            }
        }

        return false;
    }
}
