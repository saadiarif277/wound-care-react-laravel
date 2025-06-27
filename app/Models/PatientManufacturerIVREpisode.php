<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use App\Models\DocuSeal\DocuSealSubmission;
use App\Traits\UsesEpisodeCache;

class PatientManufacturerIVREpisode extends Model
{
    use HasFactory, HasUuids, UsesEpisodeCache;

    protected $table = 'patient_manufacturer_ivr_episodes';

    protected $fillable = [
        'patient_id',
        'patient_fhir_id',
        'patient_display_id',
        'manufacturer_id',
        'status',
        'ivr_status',
        'verification_date',
        'expiration_date',
        'docuseal_submission_id',
        'docuseal_submission_url',
        'metadata',
        'completed_at',
        // Order Form fields
        'order_form_status',
        'order_form_submission_id',
        'order_form_completed_at',
        'forms_metadata',
    ];

    protected $casts = [
        'verification_date' => 'datetime',
        'expiration_date' => 'datetime',
        'completed_at' => 'datetime',
        'order_form_completed_at' => 'datetime',
        'metadata' => 'array',
        'forms_metadata' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_READY_FOR_REVIEW = 'ready_for_review';
    const STATUS_IVR_SENT = 'ivr_sent';
    const STATUS_IVR_VERIFIED = 'ivr_verified';
    const STATUS_SENT_TO_MANUFACTURER = 'sent_to_manufacturer';
    const STATUS_TRACKING_ADDED = 'tracking_added';
    const STATUS_COMPLETED = 'completed';

    /**
     * IVR Status constants
     */
    const IVR_STATUS_PROVIDER_COMPLETED = 'provider_completed';
    const IVR_STATUS_ADMIN_REVIEWED = 'admin_reviewed';
    const IVR_STATUS_VERIFIED = 'verified';
    const IVR_STATUS_EXPIRED = 'expired';

    /**
     * Get the manufacturer for this episode
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Get the orders associated with this episode
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'episode_id');
    }

    /**
     * Get the DocuSeal submission
     */
    public function docusealSubmission(): BelongsTo
    {
        return $this->belongsTo(DocuSealSubmission::class, 'docuseal_submission_id');
    }

    /**
     * Get formatted docuseal data
     */
    public function getDocusealAttribute()
    {
        return [
            'status' => $this->docusealSubmission?->status,
            'signed_documents' => $this->getSignedDocuments(),
            'audit_log_url' => $this->docusealSubmission?->audit_log_url,
            'last_synced_at' => $this->docusealSubmission?->last_synced_at?->toIso8601String(),
        ];
    }

    /**
     * Get signed documents
     */
    protected function getSignedDocuments()
    {
        if (!$this->docusealSubmission || !$this->docusealSubmission->documents) {
            return [];
        }

        return collect($this->docusealSubmission->documents)->map(function ($doc) {
            return [
                'id' => $doc['id'] ?? uniqid(),
                'filename' => $doc['filename'] ?? $doc['name'] ?? 'Document',
                'name' => $doc['name'] ?? $doc['filename'] ?? 'Document',
                'url' => $doc['url'] ?? '#',
            ];
        })->toArray();
    }

    /**
     * Get total order value
     */
    public function getTotalOrderValueAttribute()
    {
        return $this->orders->sum('total_amount');
    }

    /**
     * Get orders count
     */
    public function getOrdersCountAttribute()
    {
        return $this->orders->count();
    }

    /**
     * Check if action is required
     */
    public function getActionRequiredAttribute()
    {
        return in_array($this->status, [
            self::STATUS_READY_FOR_REVIEW,
            self::STATUS_IVR_SENT,
        ]) || $this->ivr_status === self::IVR_STATUS_EXPIRED;
    }

    /**
     * Get audit log
     */
    public function getAuditLogAttribute()
    {
        // Simulated audit log - in production, this would come from an audit table
        return [
            [
                'id' => 1,
                'action' => 'Episode Created',
                'actor' => 'Provider',
                'timestamp' => $this->created_at->toIso8601String(),
                'notes' => 'Provider submitted orders with IVR',
            ],
            [
                'id' => 2,
                'action' => 'IVR Reviewed',
                'actor' => 'Admin',
                'timestamp' => $this->updated_at->toIso8601String(),
                'notes' => 'Admin reviewed and approved IVR',
            ],
        ];
    }

    /**
     * Scope for episodes needing review
     */
    public function scopeNeedingReview($query)
    {
        return $query->where('status', self::STATUS_READY_FOR_REVIEW);
    }

    /**
     * Scope for episodes with expiring IVRs
     */
    public function scopeExpiringIvr($query, $days = 30)
    {
        return $query->where('expiration_date', '<=', now()->addDays($days))
                     ->where('expiration_date', '>', now());
    }

    /**
     * Scope for completed episodes
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Mark episode as reviewed
     */
    public function markAsReviewed()
    {
        $this->update([
            'status' => self::STATUS_IVR_VERIFIED,
            'ivr_status' => self::IVR_STATUS_ADMIN_REVIEWED,
        ]);
    }

    /**
     * Send to manufacturer
     */
    public function sendToManufacturer()
    {
        $this->update([
            'status' => self::STATUS_SENT_TO_MANUFACTURER,
        ]);
    }

    /**
     * Add tracking information
     */
    public function addTracking($trackingNumber, $carrier = null)
    {
        $this->update([
            'status' => self::STATUS_TRACKING_ADDED,
            'metadata' => array_merge($this->metadata ?? [], [
                'tracking_number' => $trackingNumber,
                'carrier' => $carrier,
                'tracking_added_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
