<?php

namespace App\Models\Order;

use App\Models\Users\Organization\Organization;
use App\Models\Fhir\Facility;
use App\Models\Order\OrderItem; // Assuming OrderItem will be in App\Models\Order namespace
use App\Models\Order\Manufacturer;
use App\Models\MscSalesRep;
use App\Traits\BelongsToOrganizationThrough;
use Illuminate\Database\Eloquent\Model;

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
        'paid_amount',
        'payment_status',
        'paid_at',
        'expected_service_date',
        'submitted_at',
        // NO PHI fields like patient name, DOB, etc.
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'action_required' => 'boolean',
        'expected_service_date' => 'date',
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
}
