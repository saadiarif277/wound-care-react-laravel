<?php

namespace App\Services;

use App\Models\SalesRep;
use App\Models\ProviderSalesAssignment;
use App\Models\FacilitySalesAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesAssignmentService
{
    /**
     * Assign a sales rep to a provider.
     */
    public function assignToProvider(
        string $providerFhirId,
        string $salesRepId,
        array $options = []
    ): ProviderSalesAssignment {
        try {
            DB::beginTransaction();

            // Check if primary assignment already exists
            if (($options['relationship_type'] ?? 'primary') === 'primary') {
                $this->endExistingPrimaryAssignment($providerFhirId);
            }

            // Create new assignment
            $assignment = ProviderSalesAssignment::create([
                'provider_fhir_id' => $providerFhirId,
                'sales_rep_id' => $salesRepId,
                'facility_id' => $options['facility_id'] ?? null,
                'relationship_type' => $options['relationship_type'] ?? 'primary',
                'commission_split_percentage' => $options['commission_split_percentage'] ?? 100,
                'override_commission_rate' => $options['override_commission_rate'] ?? null,
                'can_create_orders' => $options['can_create_orders'] ?? true,
                'assigned_from' => $options['assigned_from'] ?? now(),
                'assigned_until' => $options['assigned_until'] ?? null,
                'is_active' => true,
                'notes' => $options['notes'] ?? null,
            ]);

            DB::commit();
            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to assign sales rep to provider: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Assign a sales rep to a facility.
     */
    public function assignToFacility(
        string $facilityId,
        string $salesRepId,
        array $options = []
    ): FacilitySalesAssignment {
        try {
            DB::beginTransaction();

            // Create new assignment
            $assignment = FacilitySalesAssignment::create([
                'facility_id' => $facilityId,
                'sales_rep_id' => $salesRepId,
                'relationship_type' => $options['relationship_type'] ?? 'coordinator',
                'commission_split_percentage' => $options['commission_split_percentage'] ?? 0,
                'can_create_orders' => $options['can_create_orders'] ?? false,
                'can_view_all_providers' => $options['can_view_all_providers'] ?? true,
                'assigned_from' => $options['assigned_from'] ?? now(),
                'assigned_until' => $options['assigned_until'] ?? null,
                'is_active' => true,
                'notes' => $options['notes'] ?? null,
            ]);

            DB::commit();
            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to assign sales rep to facility: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * End existing primary assignment.
     */
    protected function endExistingPrimaryAssignment(string $providerFhirId): void
    {
        ProviderSalesAssignment::active()
            ->forProvider($providerFhirId)
            ->primary()
            ->update([
                'assigned_until' => now(),
                'is_active' => false,
            ]);
    }

    /**
     * Transfer providers from one rep to another.
     */
    public function transferProviders(
        string $fromRepId,
        string $toRepId,
        array $providerFhirIds,
        ?Carbon $effectiveDate = null
    ): int {
        $effectiveDate = $effectiveDate ?? now();
        $transferred = 0;

        try {
            DB::beginTransaction();

            foreach ($providerFhirIds as $providerFhirId) {
                // End current assignment
                ProviderSalesAssignment::active()
                    ->forProvider($providerFhirId)
                    ->where('sales_rep_id', $fromRepId)
                    ->update([
                        'assigned_until' => $effectiveDate,
                        'is_active' => false,
                    ]);

                // Create new assignment
                $this->assignToProvider($providerFhirId, $toRepId, [
                    'assigned_from' => $effectiveDate,
                    'notes' => "Transferred from rep {$fromRepId}",
                ]);

                $transferred++;
            }

            DB::commit();
            return $transferred;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to transfer providers: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get providers assigned to a sales rep.
     */
    public function getProvidersForRep(string $salesRepId, bool $activeOnly = true): Collection
    {
        $query = ProviderSalesAssignment::where('sales_rep_id', $salesRepId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->get()->map(function ($assignment) {
            // Here you would fetch provider details from FHIR
            // For now, returning the assignment with placeholder data
            return [
                'provider_fhir_id' => $assignment->provider_fhir_id,
                'assignment' => $assignment,
                'provider_name' => 'Provider Name', // Would come from FHIR
            ];
        });
    }

    /**
     * Get sales reps for a provider.
     */
    public function getRepsForProvider(string $providerFhirId, bool $activeOnly = true): Collection
    {
        $query = ProviderSalesAssignment::forProvider($providerFhirId)
            ->with('salesRep.user');

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get facilities assigned to a sales rep.
     */
    public function getFacilitiesForRep(string $salesRepId, bool $activeOnly = true): Collection
    {
        $query = FacilitySalesAssignment::where('sales_rep_id', $salesRepId)
            ->with('facility');

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * End an assignment.
     */
    public function endAssignment(string $assignmentId, string $type = 'provider'): void
    {
        $model = $type === 'provider' ? ProviderSalesAssignment::class : FacilitySalesAssignment::class;
        
        $assignment = $model::findOrFail($assignmentId);
        
        $assignment->update([
            'assigned_until' => now(),
            'is_active' => false,
        ]);
    }

    /**
     * Get assignment history.
     */
    public function getAssignmentHistory(
        string $entityId,
        string $entityType = 'provider',
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $model = $entityType === 'provider' ? ProviderSalesAssignment::class : FacilitySalesAssignment::class;
        $field = $entityType === 'provider' ? 'provider_fhir_id' : 'facility_id';

        $query = $model::where($field, $entityId)
            ->with('salesRep.user')
            ->orderBy('assigned_from', 'desc');

        if ($startDate) {
            $query->where('assigned_from', '>=', $startDate);
        }

        if ($endDate) {
            $query->where(function ($q) use ($endDate) {
                $q->whereNull('assigned_until')
                    ->orWhere('assigned_until', '<=', $endDate);
            });
        }

        return $query->get();
    }

    /**
     * Get territory coverage.
     */
    public function getTerritoryCoverage(string $territory): array
    {
        $reps = SalesRep::active()
            ->inTerritory($territory)
            ->with(['providerAssignments', 'facilityAssignments'])
            ->get();

        return [
            'territory' => $territory,
            'total_reps' => $reps->count(),
            'total_providers' => $reps->sum(fn($rep) => $rep->providerAssignments->count()),
            'total_facilities' => $reps->sum(fn($rep) => $rep->facilityAssignments->count()),
            'reps' => $reps->map(function ($rep) {
                return [
                    'id' => $rep->id,
                    'name' => $rep->full_name,
                    'provider_count' => $rep->providerAssignments->count(),
                    'facility_count' => $rep->facilityAssignments->count(),
                ];
            }),
        ];
    }
}