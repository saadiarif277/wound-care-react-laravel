<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalOpportunityAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinical_opportunity_id',
        'user_id',
        'action_type',
        'action_data',
        'result',
        'status',
        'notes'
    ];

    protected $casts = [
        'action_data' => 'array',
        'result' => 'array'
    ];

    /**
     * Get the opportunity this action belongs to
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(ClinicalOpportunity::class, 'clinical_opportunity_id');
    }

    /**
     * Get the user who performed this action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted action type label
     */
    public function getActionTypeLabelAttribute(): string
    {
        return match($this->action_type) {
            'order_product' => 'Product Order',
            'schedule_assessment' => 'Assessment Scheduled',
            'refer_specialist' => 'Specialist Referral',
            'update_care_plan' => 'Care Plan Updated',
            'dismiss' => 'Opportunity Dismissed',
            default => ucfirst(str_replace('_', ' ', $this->action_type))
        };
    }

    /**
     * Check if action was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed' || 
               ($this->result['status'] ?? '') === 'success';
    }
}