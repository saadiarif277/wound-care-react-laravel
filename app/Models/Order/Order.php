<?php

namespace App\Models\Order;

use App\Models\Users\Organization\Organization;
use App\Models\Fhir\Facility;
use App\Models\Order\OrderItem; // Assuming OrderItem will be in App\Models\Order namespace
use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use App\Models\MscSalesRep;
use App\Traits\BelongsToOrganizationThrough;
use Illuminate\Database\Eloquent\Model;
use App\Models\PatientManufacturerIVREpisode;
// use App\Models\Document; // TODO: Uncomment and fix if Document model is created

class Order extends Model
{
    use BelongsToOrganizationThrough;

    // Define the parent relationship for organization scoping
    protected string $organizationThroughRelation = 'facility';

    protected $fillable = [
        // 'organization_id', // organization_id is derived via facility typically
        'facility_id',
        'provider_id', // Added provider_id
        'manufacturer_id',
        'sales_rep_id',
        'patient_fhir_id', // Reference to PHI in Azure FHIR, not actual PHI
        'patient_display_id',
        'azure_order_checklist_fhir_id', // Reference to order checklist in Azure FHIR
        'order_number',
        'status',
        'order_status',
        'action_required',
        'ivr_generation_status',
        'ivr_skip_reason',
        'ivr_generated_at',
        'ivr_sent_at',
        'ivr_confirmed_at',
        'approved_at',
        'denied_at',
        'sent_back_at',
        'submitted_to_manufacturer_at',
        'denial_reason',
        'send_back_notes',
        'approval_notes',
        'order_date',
        'total_amount',
        'total_order_value', // Alias for total_amount
        'paid_amount',
        'payment_status',
        'paid_at',
        'date_of_service',
        'submitted_at',
        'episode_id',
        'parent_order_id',
        'type',
        // NO PHI fields like patient name, DOB, etc.
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'total_order_value' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'action_required' => 'boolean',
        'date_of_service' => 'date',
        'submitted_at' => 'datetime',
        'ivr_generated_at' => 'datetime',
        'ivr_sent_at' => 'datetime',
        'ivr_confirmed_at' => 'datetime',
        'approved_at' => 'datetime',
        'denied_at' => 'datetime',
        'sent_back_at' => 'datetime',
        'submitted_to_manufacturer_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Order Status constants - matches PRD requirements
     */
    const STATUS_PENDING = 'Pending';
    const STATUS_SUBMITTED_TO_MANUFACTURER = 'Submitted to Manufacturer';
    const STATUS_CONFIRMED_BY_MANUFACTURER = 'Confirmed by Manufacturer';
    const STATUS_REJECTED = 'Rejected';
    const STATUS_CANCELED = 'Canceled';

    /**
     * Get the name of the parent relationship that contains organization_id
     */
    protected static function getOrganizationParentRelationName(): string
    {
        return 'facility';
    }

    /**
     * Get the name of the organization relationship on the parent
     */
    public function getOrganizationRelationName(): string
    {
        return 'organization';
    }

    // Relationships to non-PHI data only
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function salesRep()
    {
        return $this->belongsTo(MscSalesRep::class, 'sales_rep_id');
    }

    /**
     * Get the provider (User) associated with this order.
     */
    public function provider()
    {
        return $this->belongsTo(\App\Models\User::class, 'provider_id');
    }

    /**
     * Get the manufacturer associated with this order.
     */
    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    /**
     * Get the IVR episode associated with this order.
     */
    public function episode()
    {
        return $this->belongsTo(PatientManufacturerIVREpisode::class, 'episode_id');
    }

    /**
     * Get the payments for this order.
     */
    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }

    /**
     * Scope for orders requiring action
     */
    public function scopeRequiringAction($query)
    {
        return $query->where('action_required', true);
    }

    /**
     * Scope for orders by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('order_status', $status);
    }

    /**
     * Get products count attribute
     */
    public function getProductsCountAttribute()
    {
        return $this->items()->count();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Get the IVR episode (patient_manufacturer_ivr_episodes) associated with this order.
     */
    public function ivrEpisode()
    {
        // Reference the episode model directly
        return $this->belongsTo(PatientManufacturerIVREpisode::class, 'episode_id');
    }

    /**
     * Parent order for follow-up relationships.
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    /**
     * Follow-up orders.
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    /**
     * Get the products associated with this order.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
                    ->withPivot('quantity', 'unit_price', 'total_price', 'sizes')
                    ->withTimestamps();
    }

    /**
     * Get the notes for this order.
     */
    public function notes()
    {
        return $this->hasMany(\App\Models\OrderNote::class);
    }

    /**
     * Get the documents for this order.
     */
    public function documents()
    {
        return $this->hasMany(\App\Models\OrderDocument::class);
    }

    /**
     * Get the confirmation documents for this order.
     * TODO: Implement when Document model is available
     */
    // public function confirmationDocuments()
    // {
    //     return $this->hasMany(Document::class, 'documentable_id')
    //         ->where('documentable_type', 'order')
    //         ->where('type', 'confirmation');
    // }

    /**
     * Boot method to keep total_amount and total_order_value in sync
     */
    protected static function boot()
    {
        parent::boot();

        // Keep total_order_value in sync with total_amount
        static::saving(function ($order) {
            if ($order->isDirty('total_amount')) {
                $order->total_order_value = $order->total_amount;
            } elseif ($order->isDirty('total_order_value')) {
                $order->total_amount = $order->total_order_value;
            }
        });
    }
}
