<?php

namespace App\Models\Order;

use App\Models\Users\Organization\Organization;
use App\Models\Fhir\Facility;
use App\Models\Order\OrderItem; // Assuming OrderItem will be in App\Models\Order namespace
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
        'sales_rep_id',
        'patient_fhir_id', // Reference to PHI in Azure FHIR, not actual PHI
        'azure_order_checklist_fhir_id', // Reference to order checklist in Azure FHIR
        'order_number',
        'status',
        'order_date',
        'total_amount',
        // NO PHI fields like patient name, DOB, etc.
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2',
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
}
