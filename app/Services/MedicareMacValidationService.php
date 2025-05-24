<?php

namespace App\Services;

use App\Models\Order;
use App\Models\MedicareMacValidation;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MedicareMacValidationService
{
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
        'IN' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J8'],
        'MI' => ['contractor' => 'Wisconsin Physicians Service', 'jurisdiction' => 'J8'],

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

    /**
     * Validate Medicare compliance for an order
     */
    public function validateOrder(Order $order, string $validationType = 'wound_care_only'): MedicareMacValidation
    {
        Log::info('Starting Medicare MAC validation', ['order_id' => $order->id, 'validation_type' => $validationType]);

        // Get or create validation record
        $validation = MedicareMacValidation::firstOrCreate(
            ['order_id' => $order->id],
            [
                'validation_type' => $validationType,
                'facility_id' => $order->facility_id,
                'patient_id' => $order->patient_id ?? null,
                'validation_status' => 'pending',
                'daily_monitoring_enabled' => true,
            ]
        );

        // Set MAC contractor based on facility location
        $this->setMacContractor($validation, $order);

        // Perform validation checks
        $this->validateCoverage($validation, $order);
        $this->validateDocumentation($validation, $order);
        $this->validateFrequency($validation, $order);
        $this->validateMedicalNecessity($validation, $order);
        $this->validatePriorAuthorization($validation, $order);
        $this->validateBilling($validation, $order);

        // Update overall status
        $this->updateValidationStatus($validation);

        // Add audit entry
        $validation->addAuditEntry('validation_completed', [
            'validation_type' => $validationType,
            'compliance_score' => $validation->getComplianceScore()
        ]);

        return $validation;
    }

    /**
     * Set MAC contractor based on facility location
     */
    private function setMacContractor(MedicareMacValidation $validation, Order $order): void
    {
        $facility = $order->facility;
        $state = $facility->state ?? 'Unknown';

        $macInfo = $this->macContractors[$state] ?? [
            'contractor' => 'Unknown',
            'jurisdiction' => 'Unknown'
        ];

        $validation->update([
            'mac_contractor' => $macInfo['contractor'],
            'mac_jurisdiction' => $macInfo['jurisdiction'],
            'mac_region' => $state
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
}
