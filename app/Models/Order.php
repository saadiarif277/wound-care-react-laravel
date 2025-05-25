<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'organization_id',
        'facility_id',
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
}
