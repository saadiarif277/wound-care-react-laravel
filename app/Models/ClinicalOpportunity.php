<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Fhir\Patient;
use App\Models\User;

class ClinicalOpportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'provider_id',
        'rule_id',
        'type',
        'category',
        'priority',
        'title',
        'description',
        'confidence_score',
        'composite_score',
        'data',
        'status',
        'identified_at',
        'resolved_at',
        'last_action_at',
        'action_count',
        'outcome_data',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'data' => 'array',
        'outcome_data' => 'array',
        'confidence_score' => 'float',
        'composite_score' => 'float',
        'priority' => 'integer',
        'action_count' => 'integer',
        'identified_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_action_at' => 'datetime'
    ];

    /**
     * Get the patient associated with this opportunity
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    /**
     * Get the provider who identified this opportunity
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * Get the user who created this opportunity
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this opportunity
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get actions taken for this opportunity
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ClinicalOpportunityAction::class);
    }

    /**
     * Scope for active opportunities
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['identified', 'action_taken'])
                    ->whereNull('resolved_at');
    }

    /**
     * Scope for opportunities by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for high priority opportunities
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', '>=', 7);
    }

    /**
     * Scope for opportunities requiring action
     */
    public function scopeRequiringAction($query)
    {
        return $query->where('status', 'identified')
                    ->where('confidence_score', '>=', 0.7);
    }

    /**
     * Get formatted priority label
     */
    public function getPriorityLabelAttribute(): string
    {
        if ($this->priority >= 9) return 'Critical';
        if ($this->priority >= 7) return 'High';
        if ($this->priority >= 5) return 'Medium';
        return 'Low';
    }

    /**
     * Get formatted status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'identified' => 'Identified',
            'action_taken' => 'Action Taken',
            'resolved' => 'Resolved',
            'dismissed' => 'Dismissed',
            default => 'Unknown'
        };
    }

    /**
     * Check if opportunity is still valid
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['identified', 'action_taken']) 
               && empty($this->resolved_at);
    }

    /**
     * Mark opportunity as resolved
     */
    public function markAsResolved(array $outcomeData = []): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'outcome_data' => array_merge($this->outcome_data ?? [], $outcomeData)
        ]);
    }

    /**
     * Dismiss opportunity
     */
    public function dismiss(string $reason = null): void
    {
        $this->update([
            'status' => 'dismissed',
            'resolved_at' => now(),
            'outcome_data' => array_merge($this->outcome_data ?? [], [
                'dismissal_reason' => $reason,
                'dismissed_at' => now()->toISOString()
            ])
        ]);
    }

    /**
     * Get days since identification
     */
    public function getDaysSinceIdentificationAttribute(): int
    {
        return $this->identified_at->diffInDays(now());
    }

    /**
     * Get recommended actions from data
     */
    public function getRecommendedActionsAttribute(): array
    {
        return $this->data['actions'] ?? [];
    }

    /**
     * Get evidence from data
     */
    public function getEvidenceAttribute(): array
    {
        return $this->data['evidence'] ?? [];
    }

    /**
     * Get potential impact from data
     */
    public function getPotentialImpactAttribute(): array
    {
        return $this->data['potential_impact'] ?? [];
    }
}