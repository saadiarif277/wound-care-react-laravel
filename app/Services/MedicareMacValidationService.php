<?php

namespace App\Services;

use App\Models\Order;
use App\Models\MedicareMacValidation;
use App\Services\ValidationBuilderEngine;
use App\Services\CmsCoverageApiService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MedicareMacValidationService
{
    private ValidationBuilderEngine $validationEngine;
    private CmsCoverageApiService $cmsService;

    /**
     * MAC Contractor mappings by state/region
     */
    private array $macContractors = [
        // MAC Jurisdiction J1 (J1)
        'CA' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'J1'],
        'NV' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'J1'],
        'HI' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'J1'],
        'AK' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'J1'],
        'WA' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'J1'],
        'OR' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'J1'],
        'ID' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'J1'],
        'UT' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'J1'],
        'AZ' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'J1'],

        // MAC Jurisdiction J5 (J5)
        'IA' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J5'],
        'KS' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J5'],
        'MO' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J5'],
        'NE' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J5'],

        // MAC Jurisdiction J6 (J6)
        'IL' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J6'],
        'IN' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J6'],
        'MI' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J6'],
        'MN' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J6'],
        'WI' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J6'],

        // MAC Jurisdiction J8 (J8)
        // Note: J8 covers specific DME jurisdictions

        // MAC Jurisdiction JH (JH)
        'NY' => ['contractor' => 'CGS Administrators', 'jurisdiction' => 'JH'],

        // MAC Jurisdiction JJ (JJ)
        'GA' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ'],
        'SC' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ'],
        'WV' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ'],
        'VA' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ'],
        'NC' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ'],

        // MAC Jurisdiction JL (JL)
        'DE' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'JL'],
        'DC' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'JL'],
        'MD' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'JL'],
        'NJ' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'JL'],
        'PA' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'JL'],
    ];

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

    public function __construct(
        ValidationBuilderEngine $validationEngine,
        CmsCoverageApiService $cmsService
    ) {
        $this->validationEngine = $validationEngine;
        $this->cmsService = $cmsService;
    }

    /**
     * Get MAC contractor information by state
     * 
     * @param string $state Two-letter state code
     * @return array
     */
    public function getMacContractorByState(string $state): array
    {
        $state = strtoupper($state);
        
        if (isset($this->macContractors[$state])) {
            return $this->macContractors[$state];
        }
        
        // Default to JL (Novitas) if state not found
        return [
            'contractor' => 'Novitas Solutions',
            'jurisdiction' => 'JL'
        ];
    }

    /**
     * Validate Medicare compliance for an order
     */
    public function validateOrder(Order $order, string $validationType = 'wound_care_only', ?string $providerSpecialty = null): MedicareMacValidation
    {
        Log::info('Starting Medicare MAC validation', [
            'order_id' => $order->id,
            'validation_type' => $validationType,
            'provider_specialty' => $providerSpecialty
        ]);

        // Determine provider specialty from order or facility
        $specialty = $this->determineProviderSpecialty($order, $providerSpecialty);
        $providerNpi = $this->getProviderNpi($order);

        // Get or create validation record
        $validation = MedicareMacValidation::firstOrCreate(
            ['order_id' => $order->id],
            [
                'validation_type' => $validationType,
                'facility_id' => $order->facility_id,
                'patient_fhir_id' => $order->patient_fhir_id,
                'validation_status' => 'pending',
                'daily_monitoring_enabled' => true,
                'provider_specialty' => $specialty,
                'provider_npi' => $providerNpi,
            ]
        );

        // Set MAC contractor based on facility location
        $this->setMacContractor($validation, $order);

        // Get live CMS coverage data for the specialty
        $state = $validation->mac_region;
        $cmsLcds = $this->cmsService->getLCDsBySpecialty($specialty, $state);
        $cmsNcds = $this->cmsService->getNCDsBySpecialty($specialty);

        // Use ValidationBuilderEngine for comprehensive validation
        $validationResults = $this->validationEngine->validateOrder($order, $specialty);

        // Set specialty-specific requirements enhanced with CMS data
        $specialtyRequirements = $this->getSpecialtyRequirements($specialty, $validationType);
        $specialtyRequirements['cms_lcds'] = $cmsLcds;
        $specialtyRequirements['cms_ncds'] = $cmsNcds;
        $specialtyRequirements['validation_results'] = $validationResults;

        $validation->update([
            'specialty_requirements' => $specialtyRequirements
        ]);

        // Perform validation checks (enhanced with CMS data)
        $this->validateCoverage($validation, $order);
        $this->validateDocumentation($validation, $order);
        $this->validateFrequency($validation, $order);
        $this->validateMedicalNecessity($validation, $order);
        $this->validatePriorAuthorization($validation, $order);
        $this->validateBilling($validation, $order);

        // Add CMS-specific validations
        $this->validateCmsCompliance($validation, $order, $cmsLcds, $cmsNcds);

        // Update overall status
        $this->updateValidationStatus($validation);

        // Add audit entry
        $validation->addAuditEntry('validation_completed', [
            'validation_type' => $validationType,
            'compliance_score' => $validation->getComplianceScore(),
            'cms_lcds_found' => count($cmsLcds),
            'cms_ncds_found' => count($cmsNcds),
            'validation_engine_results' => $validationResults
        ]);

        return $validation;
    }

    /**
     * Set MAC contractor based on facility location
     */
    private function setMacContractor(MedicareMacValidation $validation, Order $order): void
    {
        // Use patient address for MAC contractor determination (REQUIRED for Medicare billing)
        $patientZip = $order->patient->zip_code ?? $order->patient->postal_code ?? null;
        $patientState = $order->patient->state ?? null;

        // Fallback to facility if patient address is not available
        if (!$patientState && !$patientZip) {
            $facility = $order->facility;
            $patientState = $facility->state ?? 'Unknown';
            Log::warning('Using facility address for MAC determination due to missing patient address', [
                'order_id' => $order->id,
                'patient_id' => $order->patient->id ?? null
            ]);
        }

        // Get MAC contractor based on patient address
        $macInfo = $this->getMacContractorByPatientZip($patientZip, $patientState);

        $validation->update([
            'mac_contractor' => $macInfo['contractor'],
            'mac_jurisdiction' => $macInfo['jurisdiction'],
            'mac_region' => $patientState,
            'patient_zip_code' => $patientZip,
            'addressing_method' => $macInfo['addressing_method'] ?? 'patient_address'
        ]);
    }

    /**
     * Validate coverage requirements
     */
    private function validateCoverage(MedicareMacValidation $validation, Order $order): void
    {
        $coverageMet = true;
        $coverageNotes = [];
        $coveragePolicies = [];

        // Check order items for coverage
        foreach ($order->orderItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            // Check if product has proper coding
            if (empty($product->q_code) && empty($product->cpt_code)) {
                $coverageMet = false;
                $coverageNotes[] = "Product {$product->name} missing CPT/HCPCS codes";
            }

            // Specific coverage policies for wound care
            if ($this->isWoundCareProduct($product)) {
                $coveragePolicies[] = 'Medicare Wound Care LCD';

                // Check for chronic wound documentation
                if (!$this->hasChronicWoundDocumentation($order)) {
                    $coverageMet = false;
                    $coverageNotes[] = 'Chronic wound documentation required for coverage';
                }
            }
        }

        $validation->update([
            'coverage_met' => $coverageMet,
            'coverage_notes' => implode('; ', $coverageNotes),
            'coverage_policies' => $coveragePolicies
        ]);
    }

    /**
     * Validate documentation requirements
     */
    private function validateDocumentation(MedicareMacValidation $validation, Order $order): void
    {
        $requiredDocs = $this->getRequiredDocumentation($order, $validation->validation_type);
        $missingDocs = [];
        $documentationStatus = [];

        foreach ($requiredDocs as $docType) {
            $hasDoc = $this->checkDocumentationExists($order, $docType);
            $documentationStatus[$docType] = $hasDoc ? 'present' : 'missing';

            if (!$hasDoc) {
                $missingDocs[] = $docType;
            }
        }

        $validation->update([
            'documentation_complete' => empty($missingDocs),
            'required_documentation' => $requiredDocs,
            'missing_documentation' => $missingDocs,
            'documentation_status' => $documentationStatus
        ]);
    }

    /**
     * Validate frequency compliance
     */
    private function validateFrequency(MedicareMacValidation $validation, Order $order): void
    {
        $frequencyCompliant = true;
        $frequencyNotes = [];

        // Check if there are recent orders for the same patient with similar procedures
        $recentOrders = Order::where('patient_id', $order->patient_id)
            ->where('id', '!=', $order->id)
            ->where('date_of_service', '>=', now()->subDays(30))
            ->with('orderItems.product')
            ->get();

        foreach ($order->orderItems as $item) {
            $product = $item->product;
            if (!$product || !$product->cpt_code) continue;

            $cptInfo = $this->woundCareCptCodes[$product->cpt_code] ?? null;
            if (!$cptInfo) continue;

            // Check frequency limits
            $recentSameProcedures = $recentOrders->flatMap->orderItems
                ->filter(function ($recentItem) use ($product) {
                    return $recentItem->product && $recentItem->product->cpt_code === $product->cpt_code;
                });

            if ($recentSameProcedures->count() > $this->getFrequencyLimit($cptInfo['frequency_limit'])) {
                $frequencyCompliant = false;
                $frequencyNotes[] = "Frequency limit exceeded for {$product->cpt_code}";
            }
        }

        $validation->update([
            'frequency_compliant' => $frequencyCompliant,
            'frequency_notes' => implode('; ', $frequencyNotes)
        ]);
    }

    /**
     * Validate medical necessity
     */
    private function validateMedicalNecessity(MedicareMacValidation $validation, Order $order): void
    {
        $medicalNecessityMet = true;
        $necessityNotes = [];

        // Check for medical necessity documentation
        if (!$this->hasDiagnosisSupport($order)) {
            $medicalNecessityMet = false;
            $necessityNotes[] = 'Appropriate diagnosis codes required';
        }

        // Check for wound progression documentation
        if ($validation->validation_type !== 'vascular_only') {
            if (!$this->hasWoundProgressionDocumentation($order)) {
                $medicalNecessityMet = false;
                $necessityNotes[] = 'Wound progression documentation required';
            }
        }

        $validation->update([
            'medical_necessity_met' => $medicalNecessityMet,
            'medical_necessity_notes' => implode('; ', $necessityNotes)
        ]);
    }

    /**
     * Validate prior authorization requirements
     */
    private function validatePriorAuthorization(MedicareMacValidation $validation, Order $order): void
    {
        $priorAuthRequired = false;
        $priorAuthObtained = false;

        // Check if any procedures require prior auth
        foreach ($order->orderItems as $item) {
            $product = $item->product;
            if (!$product || !$product->cpt_code) continue;

            $procedureInfo = $this->woundCareCptCodes[$product->cpt_code] ??
                           $this->vascularCptCodes[$product->cpt_code] ?? null;

            if ($procedureInfo && ($procedureInfo['prior_auth_required'] ?? false)) {
                $priorAuthRequired = true;
                break;
            }
        }

        // If prior auth required, check if obtained
        if ($priorAuthRequired) {
            // This would typically check against a prior auth system or manual entry
            $priorAuthObtained = $this->checkPriorAuthStatus($order);
        }

        $validation->update([
            'prior_auth_required' => $priorAuthRequired,
            'prior_auth_obtained' => $priorAuthObtained || !$priorAuthRequired
        ]);
    }

    /**
     * Validate billing compliance
     */
    private function validateBilling(MedicareMacValidation $validation, Order $order): void
    {
        $billingCompliant = true;
        $billingIssues = [];
        $estimatedReimbursement = 0;

        foreach ($order->orderItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            // Check for proper coding
            if (empty($product->cpt_code) && empty($product->q_code)) {
                $billingCompliant = false;
                $billingIssues[] = "Missing billing codes for {$product->name}";
            }

            // Estimate reimbursement (simplified - would integrate with fee schedules)
            $estimatedReimbursement += $this->estimateReimbursement($product->cpt_code ?? $product->q_code);
        }

        // Determine reimbursement risk
        $risk = 'low';
        if (!empty($billingIssues)) {
            $risk = 'high';
        } elseif ($validation->validation_type === 'vascular_wound_care') {
            $risk = 'medium';
        }

        $validation->update([
            'billing_compliant' => $billingCompliant,
            'billing_issues' => $billingIssues,
            'estimated_reimbursement' => $estimatedReimbursement,
            'reimbursement_risk' => $risk
        ]);
    }

    /**
     * Validate compliance with CMS LCDs and NCDs
     */
    private function validateCmsCompliance(MedicareMacValidation $validation, Order $order, array $lcds, array $ncds): void
    {
        $complianceMet = true;
        $complianceNotes = [];
        $applicablePolicies = [];

        // Check LCD compliance
        foreach ($lcds as $lcd) {
            $applicable = $this->isLcdApplicableToOrder($lcd, $order);
            if ($applicable) {
                $applicablePolicies[] = [
                    'type' => 'LCD',
                    'document_id' => $lcd['documentId'] ?? 'unknown',
                    'title' => $lcd['documentTitle'] ?? 'Unknown LCD'
                ];

                // Get detailed LCD information
                $lcdDetails = $this->cmsService->getLCDDetails($lcd['documentId'] ?? '');
                if ($lcdDetails) {
                    $complianceCheck = $this->checkLcdCompliance($order, $lcdDetails);
                    if (!$complianceCheck['compliant']) {
                        $complianceMet = false;
                        $complianceNotes = array_merge($complianceNotes, $complianceCheck['issues']);
                    }
                }
            }
        }

        // Check NCD compliance
        foreach ($ncds as $ncd) {
            $applicable = $this->isNcdApplicableToOrder($ncd, $order);
            if ($applicable) {
                $applicablePolicies[] = [
                    'type' => 'NCD',
                    'document_id' => $ncd['documentId'] ?? 'unknown',
                    'title' => $ncd['documentTitle'] ?? 'Unknown NCD'
                ];

                // Get detailed NCD information
                $ncdDetails = $this->cmsService->getNCDDetails($ncd['documentId'] ?? '');
                if ($ncdDetails) {
                    $complianceCheck = $this->checkNcdCompliance($order, $ncdDetails);
                    if (!$complianceCheck['compliant']) {
                        $complianceMet = false;
                        $complianceNotes = array_merge($complianceNotes, $complianceCheck['issues']);
                    }
                }
            }
        }

        // Update validation with CMS compliance results
        $validation->update([
            'cms_compliance_met' => $complianceMet,
            'cms_compliance_notes' => implode('; ', $complianceNotes),
            'applicable_cms_policies' => $applicablePolicies
        ]);
    }

    /**
     * Check if LCD is applicable to the order
     */
    private function isLcdApplicableToOrder(array $lcd, Order $order): bool
    {
        // Check if LCD contains relevant CPT codes or product categories
        $lcdTitle = strtolower($lcd['documentTitle'] ?? '');
        $orderItems = $order->orderItems;

        foreach ($orderItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            // Check for wound care related products
            if ($this->isWoundCareProduct($product)) {
                if (str_contains($lcdTitle, 'wound') ||
                    str_contains($lcdTitle, 'ulcer') ||
                    str_contains($lcdTitle, 'skin substitute') ||
                    str_contains($lcdTitle, 'cellular')) {
                    return true;
                }
            }

            // Check CPT codes
            if ($product->cpt_code && str_contains($lcdTitle, $product->cpt_code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if NCD is applicable to the order
     */
    private function isNcdApplicableToOrder(array $ncd, Order $order): bool
    {
        // Similar logic to LCD but for national policies
        $ncdTitle = strtolower($ncd['documentTitle'] ?? '');
        $orderItems = $order->orderItems;

        foreach ($orderItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            if ($this->isWoundCareProduct($product)) {
                if (str_contains($ncdTitle, 'wound') ||
                    str_contains($ncdTitle, 'ulcer') ||
                    str_contains($ncdTitle, 'skin substitute')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check LCD compliance for order
     */
    private function checkLcdCompliance(Order $order, array $lcdDetails): array
    {
        $compliant = true;
        $issues = [];

        // Basic compliance check - would be enhanced with actual LCD parsing
        // This is a simplified version that can be expanded

        if (!$this->hasChronicWoundDocumentation($order)) {
            $compliant = false;
            $issues[] = 'Chronic wound documentation required per LCD';
        }

        if (!$this->hasDiagnosisSupport($order)) {
            $compliant = false;
            $issues[] = 'Supporting diagnosis documentation required per LCD';
        }

        return [
            'compliant' => $compliant,
            'issues' => $issues
        ];
    }

    /**
     * Check NCD compliance for order
     */
    private function checkNcdCompliance(Order $order, array $ncdDetails): array
    {
        $compliant = true;
        $issues = [];

        // Basic NCD compliance check
        // This would be enhanced with actual NCD parsing logic

        if (!$this->hasWoundProgressionDocumentation($order)) {
            $compliant = false;
            $issues[] = 'Wound progression documentation required per NCD';
        }

        return [
            'compliant' => $compliant,
            'issues' => $issues
        ];
    }

    /**
     * Update overall validation status
     */
    private function updateValidationStatus(MedicareMacValidation $validation): void
    {
        $status = 'validated';

        if (!$validation->isCompliant()) {
            $missingItems = $validation->getMissingComplianceItems();

            // Determine if it's a failure or needs review
            $criticalMissing = ['Coverage requirements not met', 'Prior authorization required but not obtained'];
            $hasCriticalIssues = !empty(array_intersect($missingItems, $criticalMissing));

            $status = $hasCriticalIssues ? 'failed' : 'requires_review';
        }

        $validation->update([
            'validation_status' => $status,
            'validated_at' => now(),
            'validated_by' => 'system',
            'next_validation_due' => now()->addDays(30)
        ]);
    }

    /**
     * Run daily monitoring for all enabled validations
     */
    public function runDailyMonitoring(): array
    {
        $results = [
            'processed' => 0,
            'revalidated' => 0,
            'new_issues' => 0,
            'resolved_issues' => 0
        ];

        // Get validations due for daily monitoring
        $validations = MedicareMacValidation::dailyMonitoring()
            ->whereDate('last_monitored_at', '<', now()->toDateString())
            ->orWhereNull('last_monitored_at')
            ->with(['order.orderItems.product', 'order.facility'])
            ->get();

        foreach ($validations as $validation) {
            try {
                $previousStatus = $validation->validation_status;

                // Re-run validation
                $this->validateOrder($validation->order, $validation->validation_type);

                $validation->refresh();
                $newStatus = $validation->validation_status;

                // Track changes
                if ($previousStatus !== $newStatus) {
                    $results['revalidated']++;

                    if ($newStatus === 'failed' || $newStatus === 'requires_review') {
                        $results['new_issues']++;
                    } elseif ($newStatus === 'validated') {
                        $results['resolved_issues']++;
                    }
                }

                $validation->update(['last_monitored_at' => now()]);
                $results['processed']++;

            } catch (\Exception $e) {
                Log::error('Daily monitoring failed for validation', [
                    'validation_id' => $validation->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Helper methods
     */
    private function isWoundCareProduct($product): bool
    {
        return str_contains(strtolower($product->category ?? ''), 'wound') ||
               str_contains(strtolower($product->name ?? ''), 'wound') ||
               in_array($product->cpt_code, array_keys($this->woundCareCptCodes));
    }

    private function hasChronicWoundDocumentation(Order $order): bool
    {
        // This would check for proper ICD-10 codes indicating chronic wounds
        // For now, simplified check
        return !empty($order->diagnosis_codes);
    }

    private function getRequiredDocumentation(Order $order, string $validationType): array
    {
        $docs = ['physician_orders', 'patient_assessment'];

        if ($validationType === 'vascular_wound_care' || $validationType === 'vascular_only') {
            $docs = array_merge($docs, ['angiography', 'abi_measurements', 'vascular_assessment']);
        }

        if ($validationType === 'wound_care_only' || $validationType === 'vascular_wound_care') {
            $docs = array_merge($docs, ['wound_measurement', 'photography', 'treatment_plan']);
        }

        return $docs;
    }

    private function checkDocumentationExists(Order $order, string $docType): bool
    {
        // This would integrate with document management system
        // For now, simplified check based on order data
        return match($docType) {
            'physician_orders' => !empty($order->physician_notes),
            'patient_assessment' => !empty($order->patient_notes),
            'wound_measurement' => !empty($order->wound_details),
            default => false
        };
    }

    private function getFrequencyLimit(string $frequencyType): int
    {
        return match($frequencyType) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            'as_needed' => 999,
            default => 1
        };
    }

    private function hasDiagnosisSupport(Order $order): bool
    {
        return !empty($order->diagnosis_codes) || !empty($order->primary_diagnosis);
    }

    private function hasWoundProgressionDocumentation(Order $order): bool
    {
        return !empty($order->wound_details) || !empty($order->treatment_notes);
    }

    private function checkPriorAuthStatus(Order $order): bool
    {
        // This would integrate with prior auth system
        return !empty($order->prior_auth_number);
    }

    private function estimateReimbursement(string $code): float
    {
        // Simplified reimbursement estimation - would integrate with fee schedules
        $estimates = [
            '97597' => 89.50,
            '97598' => 45.20,
            '97602' => 65.30,
            '11042' => 156.80,
            '11043' => 289.40,
            '15271' => 1250.00,
            '15272' => 625.00,
            '37228' => 2850.00,
            '37229' => 1425.00,
        ];

        return $estimates[$code] ?? 0;
    }

    /**
     * Determine provider specialty from order or explicit parameter
     */
    private function determineProviderSpecialty(Order $order, ?string $explicitSpecialty): ?string
    {
        if ($explicitSpecialty) {
            return $explicitSpecialty;
        }

        // Try to get specialty from facility users
        $facilityUsers = $order->facility->users ?? collect();
        foreach ($facilityUsers as $user) {
            // Check credentials JSON for specialty information
            $credentials = $user->credentials ?? [];
            if (isset($credentials['specialty'])) {
                return $this->normalizeSpecialty($credentials['specialty']);
            }
        }

        // Fallback to facility type
        $facilityType = $order->facility->facility_type ?? '';
        return $this->inferSpecialtyFromFacilityType($facilityType);
    }

    /**
     * Get provider NPI from order
     */
    private function getProviderNpi(Order $order): ?string
    {
        // Try to get from facility users with primary relationship
        $facilityUsers = $order->facility->users ?? collect();
        foreach ($facilityUsers as $user) {
            if ($user->pivot->is_primary ?? false) {
                return $user->npi_number;
            }
        }

        // Fallback to facility NPI
        return $order->facility->npi;
    }

    /**
     * Normalize specialty names to standard values
     */
    private function normalizeSpecialty(?string $specialty): ?string
    {
        if (!$specialty) return null;

        $specialty = strtolower(trim($specialty));

        return match(true) {
            str_contains($specialty, 'vascular') && str_contains($specialty, 'surgery') => 'vascular_surgery',
            str_contains($specialty, 'interventional') && str_contains($specialty, 'radiology') => 'interventional_radiology',
            str_contains($specialty, 'cardiology') => 'cardiology',
            str_contains($specialty, 'wound') => 'wound_care_specialty',
            str_contains($specialty, 'podiatry') => 'podiatry',
            str_contains($specialty, 'plastic') && str_contains($specialty, 'surgery') => 'plastic_surgery',
            default => $specialty
        };
    }

    /**
     * Infer specialty from facility type
     */
    private function inferSpecialtyFromFacilityType(string $facilityType): ?string
    {
        $facilityType = strtolower($facilityType);

        return match(true) {
            str_contains($facilityType, 'vascular') => 'vascular_surgery',
            str_contains($facilityType, 'cardiology') => 'cardiology',
            str_contains($facilityType, 'wound') => 'wound_care_specialty',
            str_contains($facilityType, 'surgery') => 'surgery_general',
            default => null
        };
    }

    /**
     * Get specialty-specific requirements based on your vascular questionnaire
     */
    private function getSpecialtyRequirements(?string $specialty, string $validationType): array
    {
        return match($specialty) {
            'vascular_surgery' => [
                'patient_info_required' => [
                    'primary_diagnosis_icd10',
                    'secondary_diagnoses',
                    'insurance_verification',
                    'advance_beneficiary_notice'
                ],
                'facility_info_required' => [
                    'facility_npi',
                    'facility_type',
                    'treating_vascular_specialist',
                    'provider_specialty_verification'
                ],
                'medical_history_assessment' => [
                    'diabetes_status',
                    'hypertension',
                    'coronary_artery_disease',
                    'current_medications',
                    'functional_status',
                    'previous_vascular_procedures'
                ],
                'vascular_assessment_required' => [
                    'symptoms_documentation',
                    'pulse_examination',
                    'abi_measurements',
                    'rutherford_classification',
                    'ceap_classification'
                ],
                'diagnostic_studies' => [
                    'duplex_ultrasound',
                    'ct_angiography',
                    'digital_subtraction_angiography',
                    'tcpo2_measurements'
                ],
                'laboratory_values' => [
                    'hemoglobin',
                    'platelet_count',
                    'coagulation_studies',
                    'renal_function',
                    'hba1c_for_diabetics'
                ],
                'procedure_specific_requirements' => $this->getVascularProcedureRequirements(),
                'mac_coverage_verification' => [
                    'mac_jurisdiction_check',
                    'lcd_documentation_requirements',
                    'prior_authorization_determination',
                    'cpt_hcpcs_validation'
                ],
                'monitoring_frequency' => 'daily',
                'compliance_thresholds' => [
                    'documentation_completeness' => 95,
                    'prior_auth_compliance' => 100,
                    'billing_accuracy' => 98
                ]
            ],
            'interventional_radiology' => [
                'required_documentation' => [
                    'diagnostic_imaging_reports',
                    'contrast_allergy_screening',
                    'renal_function_clearance',
                    'radiation_safety_protocols'
                ],
                'procedure_categories' => ['imaging_guided_procedures', 'vascular_interventions'],
                'monitoring_frequency' => 'per_procedure',
                'prior_auth_threshold' => 'high_complexity'
            ],
            'cardiology' => [
                'required_documentation' => [
                    'ecg_results',
                    'echocardiogram',
                    'stress_test_results',
                    'cardiac_catheterization_reports'
                ],
                'procedure_categories' => ['cardiac_interventions'],
                'monitoring_frequency' => 'per_procedure'
            ],
            'wound_care_specialty' => [
                'wound_documentation_required' => [
                    'wound_type_classification',
                    'wound_measurements',
                    'wound_photography',
                    'treatment_history',
                    'healing_progression'
                ],
                'procedure_categories' => ['wound_care_only'],
                'monitoring_frequency' => 'weekly'
            ],
            default => [
                'required_documentation' => ['physician_orders', 'patient_assessment'],
                'procedure_categories' => ['general'],
                'monitoring_frequency' => 'monthly'
            ]
        };
    }

    /**
     * Get vascular procedure-specific requirements from your questionnaire
     */
    private function getVascularProcedureRequirements(): array
    {
        return [
            'peripheral_vascular_angioplasty' => [
                'target_vessel_documentation',
                'lesion_length_measurement',
                'stenosis_percentage',
                'calcification_assessment',
                'prior_intervention_history'
            ],
            'carotid_endarterectomy' => [
                'stenosis_percentage_verification',
                'symptomatic_status',
                'contralateral_assessment'
            ],
            'aaa_repair' => [
                'aneurysm_size_measurement',
                'anatomical_suitability',
                'approach_justification'
            ],
            'arteriovenous_fistula' => [
                'vein_mapping_results',
                'allen_test_documentation',
                'access_site_planning'
            ],
            'varicose_vein_treatment' => [
                'conservative_therapy_documentation',
                'reflux_measurements',
                'symptom_severity_assessment'
            ],
            'vascular_wound_care' => [
                'wound_etiology_classification',
                'wound_measurements',
                'previous_treatment_documentation',
                'healing_potential_assessment'
            ]
        ];
    }

    /**
     * CORRECTED: Validate order using patient address for MAC jurisdiction and facility address for place of service
     */
    public function validateOrderWithCorrectAddressing(array $orderData, array $patientData, array $facilityData): array
    {
        try {
            // CORRECT: Use patient address for MAC jurisdiction determination
            $macJurisdiction = $this->getMacContractorByPatientAddress($patientData);

            // Use facility address for place of service and CMS-1500 requirements
            $placeOfService = [
                'address' => $facilityData['address'],
                'city' => $facilityData['city'],
                'state' => $facilityData['state'],
                'zip_code' => $facilityData['zip_code'],
                'facility_type' => $facilityData['facility_type'],
                'npi' => $facilityData['npi'],
                'pos_code' => $this->mapFacilityTypeToPlaceOfServiceCode($facilityData['facility_type'])
            ];

            // Check for DME expatriate exception
            $isDmeExpatriate = $this->isDmeExpatriate($patientData, $orderData);
            if ($isDmeExpatriate) {
                // Use supplier/facility location for MAC jurisdiction in expatriate cases
                $macJurisdiction = $this->getMacContractorBySupplierAddress($facilityData);
                $macJurisdiction['expatriate_exception_applied'] = true;
            }

            // Perform CMS Coverage API call using correct addressing
            $coverageResult = $this->checkCmsCoverageWithCorrectAddressing(
                $orderData,
                $patientData,
                $placeOfService,
                $macJurisdiction
            );

            // Run MAC validation rules
            $validationResult = $this->validateMacRequirements($orderData, $macJurisdiction);

            return [
                'mac_jurisdiction' => $macJurisdiction,
                'patient_address_used_for_mac' => !$isDmeExpatriate,
                'facility_address_used_for_pos' => true,
                'place_of_service' => $placeOfService,
                'coverage_determination' => $coverageResult,
                'validation_results' => $validationResult,
                'cms_1500_compliant' => true,
                'validated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('MAC validation with correct addressing failed', [
                'facility_id' => $facilityData['id'] ?? null,
                'order_id' => $orderData['id'] ?? null,
                'patient_state' => $patientData['state'] ?? null,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * CORRECT: Get MAC contractor based on patient's permanent address
     */
    private function getMacContractorByPatientAddress(array $patientData): array
    {
        $patientState = $patientData['state'] ?? null;
        $patientZip = $patientData['zip_code'] ?? $patientData['postal_code'] ?? null;

        return $this->getMacContractorByPatientZip($patientZip, $patientState);
    }

    /**
     * Get MAC contractor based on patient ZIP code and state with enhanced ZIP lookup
     */
    private function getMacContractorByPatientZip(?string $zipCode, ?string $state): array
    {
        if (!$state) {
            throw new \InvalidArgumentException('Patient state is required for MAC jurisdiction determination');
        }

        // Get base MAC info from state
        $macInfo = $this->getMacContractorByState($state, 'patient_address');

        // If we have a ZIP code, check for special jurisdictions or cross-border areas
        if ($zipCode) {
            $zipBasedMac = $this->getMacContractorByZipCode($zipCode, $state);
            if ($zipBasedMac['contractor'] !== 'Unknown') {
                $macInfo = array_merge($macInfo, $zipBasedMac);
                $macInfo['addressing_method'] = 'zip_code_specific';
            } else {
                $macInfo['addressing_method'] = 'state_based';
            }
            $macInfo['patient_zip_code'] = $zipCode;
        } else {
            $macInfo['addressing_method'] = 'state_based_no_zip';
        }

        return $macInfo;
    }

    /**
     * Get MAC contractor for specific ZIP codes that may cross state boundaries
     * or have special MAC jurisdictions (like border areas or military bases)
     */
    private function getMacContractorByZipCode(string $zipCode, string $state): array
    {
        $zipPrefix = substr($zipCode, 0, 5); // Use 5-digit ZIP

        // Special ZIP code mappings for cross-border areas or special jurisdictions
        // These are areas where ZIP codes cross MAC jurisdiction boundaries
        $specialZipMappings = [
            // Examples of special ZIP jurisdictions (would be populated with actual CMS data)

            // Connecticut/New York border area where some CTs are served by NY MAC
            '06830' => ['contractor' => 'National Government Services', 'jurisdiction' => 'J6'], // Greenwich, CT

            // DC Metro area complications
            '20090' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'J5'], // DC area
            '20092' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'J5'], // DC area

            // Kansas City metro spans multiple states
            '64108' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JM'], // Kansas City, MO
            '66101' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JM'], // Kansas City, KS

            // Add more special cases as identified
        ];

        if (isset($specialZipMappings[$zipPrefix])) {
            $result = $specialZipMappings[$zipPrefix];
            $result['zip_override_reason'] = 'Special jurisdiction mapping';
            return $result;
        }

        // No special mapping found - use state-based determination
        return ['contractor' => 'Unknown', 'jurisdiction' => 'Unknown'];
    }

    /**
     * Get MAC contractor based on supplier/facility address (for DME expatriate cases)
     */
    private function getMacContractorBySupplierAddress(array $facilityData): array
    {
        $facilityState = $facilityData['state'] ?? null;

        if (!$facilityState) {
            throw new \InvalidArgumentException('Facility state is required for MAC jurisdiction determination in expatriate cases');
        }

        return $this->getMacContractorByState($facilityState, 'supplier_address');
    }

    /**
     * Check if this is a DME expatriate case
     */
    private function isDmeExpatriate(array $patientData, array $orderData): bool
    {
        // Check if patient address indicates expatriate status
        $isExpatriate = $this->isPatientExpatriate($patientData);

        // Check if order contains DME items
        $isDmeOrder = $this->isDmeOrder($orderData);

        return $isExpatriate && $isDmeOrder;
    }

    /**
     * Check if patient is an expatriate based on address
     */
    private function isPatientExpatriate(array $patientData): bool
    {
        $patientState = $patientData['state'] ?? '';
        $patientCountry = $patientData['country'] ?? 'US';

        // Check for non-US addresses or military/diplomatic addresses
        if ($patientCountry !== 'US') {
            return true;
        }

        // Check for military addresses (APO, FPO, DPO)
        $militaryStates = ['AA', 'AE', 'AP'];
        if (in_array(strtoupper($patientState), $militaryStates)) {
            return true;
        }

        return false;
    }

    /**
     * Check if order contains DME items
     */
    private function isDmeOrder(array $orderData): bool
    {
        $procedureCodes = $orderData['procedure_codes'] ?? [];

        // DME procedure codes typically start with A, E, K, L
        foreach ($procedureCodes as $code) {
            $codePrefix = substr($code, 0, 1);
            if (in_array($codePrefix, ['A', 'E', 'K', 'L'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enhanced CMS Coverage check with correct addressing
     */
    private function checkCmsCoverageWithCorrectAddressing(
        array $orderData,
        array $patientData,
        array $placeOfService,
        array $macJurisdiction
    ): array {
        try {
            // Prepare CMS Coverage API request with correct addressing
            $coverageRequest = [
                'mac_jurisdiction' => $macJurisdiction['jurisdiction'],

                // Patient address for MAC jurisdiction
                'beneficiary_address' => [
                    'address' => $patientData['address'],
                    'city' => $patientData['city'],
                    'state' => $patientData['state'],
                    'zip' => $patientData['zip']
                ],

                // Facility address for place of service
                'place_of_service' => [
                    'code' => $placeOfService['pos_code'],
                    'address' => $placeOfService['address'],
                    'city' => $placeOfService['city'],
                    'state' => $placeOfService['state'],
                    'zip' => $placeOfService['zip_code'],
                    'npi' => $placeOfService['npi']
                ],

                'procedure_codes' => $orderData['procedure_codes'] ?? [],
                'diagnosis_codes' => $orderData['diagnosis_codes'] ?? [],
                'service_date' => $orderData['expected_service_date'] ?? null
            ];

            // Make CMS Coverage API call
            $response = $this->cmsService->checkCoverageWithAddressing($coverageRequest);

            return [
                'coverage_status' => $response['covered'] ?? false,
                'coverage_details' => $response['details'] ?? [],
                'documentation_requirements' => $response['documentation'] ?? [],
                'prior_auth_required' => $response['prior_authorization_required'] ?? false,
                'cms_response' => $response,
                'addressing_method' => 'correct_mac_patient_address'
            ];

        } catch (\Exception $e) {
            Log::warning('CMS Coverage API call with correct addressing failed', [
                'patient_state' => $patientData['state'] ?? null,
                'facility_state' => $placeOfService['state'] ?? null,
                'mac_jurisdiction' => $macJurisdiction['jurisdiction'] ?? null,
                'error' => $e->getMessage()
            ]);

            return [
                'coverage_status' => null,
                'coverage_details' => [],
                'documentation_requirements' => [],
                'prior_auth_required' => null,
                'error' => $e->getMessage(),
                'addressing_method' => 'failed'
            ];
        }
    }

    /**
     * Enhanced MAC contractor mapping with jurisdiction details
     */
    private function getMacContractorByState(string $state, string $addressType = 'patient_address'): array
    {
        // Comprehensive MAC contractor mapping based on CMS jurisdictions
        $macContractors = [
            // Jurisdiction J5 (Novitas Solutions)
            'DE' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'J5', 'phone' => '1-855-202-4900'],
            'DC' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'J5', 'phone' => '1-855-202-4900'],
            'MD' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'J5', 'phone' => '1-855-202-4900'],
            'PA' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'J5', 'phone' => '1-855-202-4900'],
            'NJ' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'J5', 'phone' => '1-855-202-4900'],

            // Jurisdiction JH (Novitas Solutions)
            'AR' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'JH', 'phone' => '1-855-609-9960'],
            'LA' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'JH', 'phone' => '1-855-609-9960'],
            'MS' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'JH', 'phone' => '1-855-609-9960'],
            'TX' => ['contractor' => 'Novitas Solutions', 'jurisdiction' => 'JH', 'phone' => '1-855-609-9960'],

            // Jurisdiction JF (Noridian Healthcare Solutions)
            'CA' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JF', 'phone' => '1-855-609-9960'],
            'HI' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JF', 'phone' => '1-855-609-9960'],
            'NV' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JF', 'phone' => '1-855-609-9960'],
            'AS' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JF', 'phone' => '1-855-609-9960'],
            'GU' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JF', 'phone' => '1-855-609-9960'],

            // Jurisdiction JE (Noridian Healthcare Solutions)
            'AK' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],
            'AZ' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],
            'ID' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],
            'MT' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],
            'ND' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],
            'OR' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],
            'SD' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],
            'UT' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],
            'WA' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],
            'WY' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'],

            // Jurisdiction JM (WPS Health Solutions)
            'IA' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JM', 'phone' => '1-855-632-7873'],
            'KS' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JM', 'phone' => '1-855-632-7873'],
            'MO' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JM', 'phone' => '1-855-632-7873'],
            'NE' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JM', 'phone' => '1-855-632-7873'],

            // Jurisdiction JN (WPS Health Solutions)
            'IL' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JN', 'phone' => '1-855-632-7873'],
            'IN' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JN', 'phone' => '1-855-632-7873'],
            'MI' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JN', 'phone' => '1-855-632-7873'],
            'MN' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JN', 'phone' => '1-855-632-7873'],
            'OH' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JN', 'phone' => '1-855-632-7873'],
            'WI' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JN', 'phone' => '1-855-632-7873'],

            // Jurisdiction J8 (Palmetto GBA)
            'NC' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'J8', 'phone' => '1-855-609-9960'],
            'SC' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'J8', 'phone' => '1-855-609-9960'],
            'VA' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'J8', 'phone' => '1-855-609-9960'],
            'WV' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'J8', 'phone' => '1-855-609-9960'],

            // Jurisdiction JJ (Palmetto GBA)
            'AL' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ', 'phone' => '1-855-609-9960'],
            'GA' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ', 'phone' => '1-855-609-9960'],
            'TN' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ', 'phone' => '1-855-609-9960'],

            // Jurisdiction JL (First Coast Service Options)
            'FL' => ['contractor' => 'First Coast Service Options', 'jurisdiction' => 'JL', 'phone' => '1-855-609-9960'],
            'PR' => ['contractor' => 'First Coast Service Options', 'jurisdiction' => 'JL', 'phone' => '1-855-609-9960'],
            'VI' => ['contractor' => 'First Coast Service Options', 'jurisdiction' => 'JL', 'phone' => '1-855-609-9960'],

            // Jurisdiction JK (CGS Administrators)
            'KY' => ['contractor' => 'CGS Administrators', 'jurisdiction' => 'JK', 'phone' => '1-855-609-9960'],

            // Jurisdiction J6 (National Government Services)
            'CT' => ['contractor' => 'National Government Services', 'jurisdiction' => 'J6', 'phone' => '1-855-609-9960'],
            'MA' => ['contractor' => 'National Government Services', 'jurisdiction' => 'J6', 'phone' => '1-855-609-9960'],
            'ME' => ['contractor' => 'National Government Services', 'jurisdiction' => 'J6', 'phone' => '1-855-609-9960'],
            'NH' => ['contractor' => 'National Government Services', 'jurisdiction' => 'J6', 'phone' => '1-855-609-9960'],
            'NY' => ['contractor' => 'National Government Services', 'jurisdiction' => 'J6', 'phone' => '1-855-609-9960'],
            'RI' => ['contractor' => 'National Government Services', 'jurisdiction' => 'J6', 'phone' => '1-855-609-9960'],
            'VT' => ['contractor' => 'National Government Services', 'jurisdiction' => 'J6', 'phone' => '1-855-609-9960'],

            // Military addresses (for expatriate handling)
            'AA' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JN', 'phone' => '1-855-632-7873'], // Armed Forces Americas
            'AE' => ['contractor' => 'WPS Health Solutions', 'jurisdiction' => 'JN', 'phone' => '1-855-632-7873'], // Armed Forces Europe
            'AP' => ['contractor' => 'Noridian Healthcare Solutions', 'jurisdiction' => 'JE', 'phone' => '1-855-609-9960'], // Armed Forces Pacific
        ];

        $macInfo = $macContractors[strtoupper($state)] ?? [
            'contractor' => 'Unknown',
            'jurisdiction' => 'Unknown',
            'phone' => 'Contact CMS for jurisdiction information'
        ];

        // Add common fields and address type tracking
        return array_merge($macInfo, [
            'website' => $this->getMacWebsite($macInfo['contractor']),
            'coverage_determination_process' => 'LCD/NCD Review Required',
            'state' => strtoupper($state),
            'address_type_used' => $addressType,
            'cms_1500_compliant' => true
        ]);
    }

    /**
     * Map facility type to CMS place of service code
     */
    private function mapFacilityTypeToPlaceOfServiceCode(string $facilityType): string
    {
        return match(strtolower($facilityType)) {
            'hospital inpatient' => '21',
            'hospital outpatient' => '22',
            'clinic', 'wound care center' => '11',
            'ambulatory surgery center' => '24',
            'skilled nursing facility' => '31',
            'home health' => '12',
            'emergency room' => '23',
            default => '11' // Default to office
        };
    }

    /**
     * Validate MAC requirements for the order
     */
    private function validateMacRequirements(array $orderData, array $macJurisdiction): array
    {
        $validationResults = [
            'mac_contractor' => $macJurisdiction['contractor'],
            'jurisdiction' => $macJurisdiction['jurisdiction'],
            'requirements_met' => true,
            'issues' => [],
            'warnings' => []
        ];

        // Check procedure codes for MAC jurisdiction
        $procedureCodes = $orderData['procedure_codes'] ?? [];
        foreach ($procedureCodes as $code) {
            if (!$this->isCodeValidForJurisdiction($code, $macJurisdiction['jurisdiction'])) {
                $validationResults['issues'][] = "Procedure code {$code} may not be covered in jurisdiction {$macJurisdiction['jurisdiction']}";
                $validationResults['requirements_met'] = false;
            }
        }

        return $validationResults;
    }

    /**
     * Check if procedure code is valid for MAC jurisdiction
     */
    private function isCodeValidForJurisdiction(string $code, string $jurisdiction): bool
    {
        // This would contain jurisdiction-specific code validation logic
        // For now, return true as a placeholder
        return true;
    }

    /**
     * Get MAC contractor website
     */
    private function getMacWebsite(string $contractor): string
    {
        return match($contractor) {
            'Novitas Solutions' => 'https://www.novitas-solutions.com/',
            'Noridian Healthcare Solutions' => 'https://med.noridianmedicare.com/',
            'WPS Health Solutions' => 'https://www.wpsmedicare.com/',
            'Palmetto GBA' => 'https://www.palmettogba.com/',
            'First Coast Service Options' => 'https://medicare.fcso.com/',
            'CGS Administrators' => 'https://www.cgsmedicare.com/',
            'National Government Services' => 'https://www.ngsmedicare.com/',
            default => 'https://www.cms.gov/'
        };
    }
}
