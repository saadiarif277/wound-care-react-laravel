<?php

namespace App\Models\Fhir;

use App\Models\Order\Order;
// Patient, Practitioner, Encounter will be in the same App\Models\Fhir namespace
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentReference extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'author_id', // reference to practitioner
        'azure_fhir_id', // Reference to FHIR DocumentReference resource
        'status', // current, superseded, entered-in-error
        'doc_status', // preliminary, final, amended, corrected, appended, cancelled, entered-in-error
        'type_code',
        'type_display',
        'category_code',
        'category_display',
        'date',
        'description',
        'security_label',
        'content_attachment_content_type',
        'content_attachment_language',
        'content_attachment_data', // base64Binary data
        'content_attachment_url', // URL to document
        'content_attachment_size',
        'content_attachment_hash',
        'content_attachment_title',
        'content_attachment_creation',
        'context_encounter_id',
        'context_period_start',
        'context_period_end',
        // MSC Wound Care Extensions
        'order_checklist_type',
        'order_checklist_version',
        'document_source',
        'azure_storage_url',
        'is_order_checklist',
    ];

    protected $casts = [
        'date' => 'datetime',
        'content_attachment_creation' => 'datetime',
        'context_period_start' => 'datetime',
        'context_period_end' => 'datetime',
        'content_attachment_size' => 'integer',
        'is_order_checklist' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the patient this document belongs to
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the author (practitioner) of this document
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'author_id');
    }

    /**
     * Get the encounter context for this document
     */
    public function contextEncounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'context_encounter_id');
    }

    /**
     * Get orders that reference this document as order checklist
     */
    public function ordersAsChecklist()
    {
        return $this->hasMany(Order::class, 'azure_order_checklist_fhir_id', 'azure_fhir_id');
    }

    /**
     * Scope for current documents
     */
    public function scopeCurrent($query)
    {
        return $query->where('status', 'current');
    }

    /**
     * Scope for final documents
     */
    public function scopeFinal($query)
    {
        return $query->where('doc_status', 'final');
    }

    /**
     * Scope for order checklists
     */
    public function scopeOrderChecklists($query)
    {
        return $query->where('is_order_checklist', true);
    }

    /**
     * Scope by document type
     */
    public function scopeByType($query, $typeCode)
    {
        return $query->where('type_code', $typeCode);
    }

    /**
     * Get document URL (prefer Azure storage URL over attachment URL)
     */
    public function getDocumentUrlAttribute(): ?string
    {
        return $this->azure_storage_url ?: $this->content_attachment_url;
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        if (!$this->content_attachment_size) {
            return 'Unknown size';
        }

        $size = $this->content_attachment_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Check if document has content
     */
    public function hasContent(): bool
    {
        return !empty($this->content_attachment_data) ||
               !empty($this->content_attachment_url) ||
               !empty($this->azure_storage_url);
    }

    /**
     * Get document display name
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->content_attachment_title) {
            return $this->content_attachment_title;
        }

        if ($this->description) {
            return $this->description;
        }

        return $this->type_display ?: 'Document';
    }
}
