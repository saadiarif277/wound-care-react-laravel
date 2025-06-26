<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use App\Models\Order\Order;
use App\Models\Insurance\MedicareMacValidation;
use App\Services\CmsCoverageApiService;
use App\Services\ValidationBuilderEngine;

/**
 * MacValidationService
 *
 * Centralised service for Medicare Administrative Contractor (MAC) look-ups
 * and wound-care specific LCD / coverage analysis.
 *
 * Controllers should depend on this class instead of duplicating logic.
 */
final class MacValidationService
{
    private const CACHE_TTL_MINUTES = 60 * 24; // one day
    
    /**
     * Common wound care CPT codes and their coverage requirements
     */
    private array $woundCareCptCodes = [
        '97597' => ['description' => 'Debridement, open wound', 'frequency_limit' => 'daily', 'requires_documentation' => ['wound_size', 'depth', 'drainage']],
        '97598' => ['description' => 'Debridement, additional 20 sq cm', 'frequency_limit' => 'daily', 'requires_documentation' => ['wound_size', 'depth']],
        '97602' => ['description' => 'Wound care management, non-selective', 'frequency_limit' => 'daily', 'requires_documentation' => ['wound_assessment']],
        '11042' => ['description' => 'Debridement, skin/subcutaneous tissue', 'frequency_limit' => 'as_needed', 'requires_documentation' => ['medical_necessity']],
        '11043' => ['description' => 'Debridement, muscle and/or fascia', 'frequency_limit' => 'as_needed', 'requires_documentation' => ['medical_necessity', 'depth_assessment']],
        '15271' => ['description' => 'Application of skin substitute graft', 'prior_auth_required' => true, 'requires_documentation' => ['failed_conservative_treatment']],
        '15272' => ['description' => 'Application of skin substitute graft, additional', 'prior_auth_required' => true, 'requires_documentation' => ['failed_conservative_treatment']],
    ];

    /**
     * Vascular procedure CPT codes
     */
    private array $vascularCptCodes = [
        '37228' => ['description' => 'Revascularization, tibial/peroneal', 'prior_auth_required' => true, 'requires_documentation' => ['abi_measurements', 'angiography']],
        '37229' => ['description' => 'Revascularization, tibial/peroneal, additional', 'prior_auth_required' => true, 'requires_documentation' => ['abi_measurements']],
        '37230' => ['description' => 'Revascularization, tibial/peroneal, with stent', 'prior_auth_required' => true, 'requires_documentation' => ['abi_measurements', 'angiography']],
        '37231' => ['description' => 'Revascularization, tibial/peroneal, additional with stent', 'prior_auth_required' => true, 'requires_documentation' => ['abi_measurements']],
        '35556' => ['description' => 'Bypass graft, femoral-popliteal', 'prior_auth_required' => true, 'requires_documentation' => ['angiography', 'failed_endovascular']],
        '35571' => ['description' => 'Bypass graft, popliteal-tibial', 'prior_auth_required' => true, 'requires_documentation' => ['angiography', 'tissue_loss']],
    ];
    
    private ?ValidationBuilderEngine $validationEngine = null;
    private ?CmsCoverageApiService $cmsService = null;
    
    /**
     * Constructor with optional service injections
     */
    public function __construct(
        ?ValidationBuilderEngine $validationEngine = null,
        ?CmsCoverageApiService $cmsService = null
    ) {
        $this->validationEngine = $validationEngine;
        $this->cmsService = $cmsService;
    }

    /**
     * Map state code to MAC contractor meta-data.
     * This is a trimmed example – populate from authoritative CMS data source.
     * @see https://www.cms.gov/medicare/coding/medicare-administrative-contractors
     */
    private array $stateContractorMap = [
        'TX' => ['contractor' => 'Novitas',   'jurisdiction' => 'JL', 'website' => 'https://www.novitas-solutions.com/', 'phone' => '1-855-252-8782'],
        'FL' => ['contractor' => 'First Coast','jurisdiction' => 'JH', 'website' => 'https://medicare.fcso.com/',       'phone' => '1-877-567-7259'],
        'CA' => ['contractor' => 'Noridian',  'jurisdiction' => 'JE', 'website' => 'https://med.noridianmedicare.com/','phone' => '1-855-609-9960'],
        // ... add remaining states
    ];

    /**
     * Return MAC contractor details for a given 2-letter state code.
     */
    public function getMacContractorByState(string $state): array
    {
        $state = strtoupper(trim($state));

        return $this->stateContractorMap[$state] ?? [
            'contractor'   => 'Unknown',
            'jurisdiction' => 'Unknown',
            'website'      => null,
            'phone'        => null,
        ];
    }

    /**
     * Example wrapper that calls the external CMS Coverage API with caching.
     * Replace URL / params with real CMS endpoints.
     *
     * @param array<int,string> $hcpcsCodes
     */
    public function performQuickCoverageCheck(array $hcpcsCodes, string $state): array
    {
        $cacheKey = 'cms_quick_check_' . md5(json_encode($hcpcsCodes) . $state);

        return Cache::remember($cacheKey, self::CACHE_TTL_MINUTES, function () use ($hcpcsCodes, $state) {
            try {
                $response = Http::timeout(15)
                    ->retry(2, 250)
                    ->get('https://api.cms.gov/coverage/quick', [
                        'codes' => implode(',', $hcpcsCodes),
                        'state' => $state,
                    ]);

                if ($response->failed()) {
                    Log::warning('CMS quick coverage check failed', [
                        'state' => $state,
                        'codes' => $hcpcsCodes,
                        'status' => $response->status(),
                    ]);

                    return ['success' => false, 'error' => 'cms_api_error'];
                }

                return ['success' => true] + $response->json();
            } catch (\Throwable $e) {
                Log::error('CMS quick coverage exception', ['message' => $e->getMessage()]);
                return ['success' => false, 'error' => $e->getMessage()];
            }
        });
    }

    /**
     * Example risk scoring reusable by multiple controllers.
     *
     * @param Collection<int, array{hcpcs_code:string|null, quantity:int, unit_price:float}> $products
     */
    public function calculateRiskScore(Collection $products, string $jurisdiction): array
    {
        $risk = 0;
        $factors = [];

        $highRisk = $products->filter(fn ($p) => isset($p['hcpcs_code']) && str_starts_with($p['hcpcs_code'], 'Q41'));
        if ($highRisk->isNotEmpty()) {
            $risk += 30;
            $factors[] = 'High-risk biological/CTP products';
        }

        if (in_array($jurisdiction, ['JL', 'JJ'], true)) {
            $risk += 10;
            $factors[] = 'Stricter MAC jurisdiction (' . $jurisdiction . ')';
        }

        $riskLevel = match (true) {
            $risk >= 70 => 'critical',
            $risk >= 50 => 'high',
            $risk >= 30 => 'medium',
            default     => 'low',
        };

        return [
            'score' => min($risk, 100),
            'level' => $riskLevel,
            'factors' => $factors,
        ];
    }
}
