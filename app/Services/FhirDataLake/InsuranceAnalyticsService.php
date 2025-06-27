<?php

namespace App\Services\FhirDataLake;

use App\Models\FhirAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class InsuranceAnalyticsService
{
    /**
     * Get insurance verification funnel metrics
     */
    public function getVerificationFunnel($dateFrom, $dateTo): Collection
    {
        return DB::table('fhir_audit_logs')
            ->whereBetween('recorded_at', [$dateFrom, $dateTo])
            ->where('event_type', 'insurance_verification')
            ->select('event_subtype', DB::raw('count(*) as count'))
            ->groupBy('event_subtype')
            ->orderByRaw("
                CASE event_subtype
                    WHEN 'card_scan' THEN 1
                    WHEN 'coverage_created' THEN 2
                    WHEN 'eligibility_check' THEN 3
                    WHEN 'ivr_started' THEN 4
                    WHEN 'ivr_completed' THEN 5
                    ELSE 6
                END
            ")
            ->get();
    }
}