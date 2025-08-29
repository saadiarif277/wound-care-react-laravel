<?php

namespace App\Services;

use App\Models\Order\ProductRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Order Detail Service
 *
 * Centralizes order data aggregation and formatting for frontend consumption.
 * Handles role-based data filtering and caching for improved performance.
 */
class OrderDetailService
{
    protected OrderDataService $orderDataService;
    protected FileService $fileService;

    public function __construct(OrderDataService $orderDataService, FileService $fileService)
    {
        $this->orderDataService = $orderDataService;
        $this->fileService = $fileService;
    }

    /**
     * Get comprehensive order details with role-based filtering
     */
    public function getOrderDetails(ProductRequest $order, ?User $user = null): array
    {
        $cacheKey = "order_details_{$order->id}_" . ($user?->id ?? 'guest');

        return Cache::remember($cacheKey, 300, function () use ($order, $user) {
            $comprehensiveData = $this->orderDataService->getOrderData($order);
            $roleRestrictions = $this->getRoleRestrictions($user);

            return [
                'basic' => $this->filterBasicData($comprehensiveData['basic'], $roleRestrictions),
                'patient' => $this->filterPatientData($comprehensiveData['patient'], $roleRestrictions),
                'provider' => $comprehensiveData['provider'],
                'facility' => $comprehensiveData['facility'],
                'insurance' => $comprehensiveData['insurance'],
                'product' => $this->filterProductData($comprehensiveData['product'], $roleRestrictions),
                'clinical' => $comprehensiveData['clinical'],
                'files' => $this->fileService->getOrderFiles($order),
                'documents' => $this->fileService->getOrderDocuments($order),
                'metadata' => $this->filterMetadata($comprehensiveData['metadata'], $roleRestrictions),
                'role_restrictions' => $roleRestrictions,
                'permissions' => $this->getUserPermissions($user),
            ];
        });
    }

    /**
     * Get frontend-ready order data with all necessary formatting
     */
    public function getFrontendOrderData(ProductRequest $order, ?User $user = null): array
    {
        $details = $this->getOrderDetails($order, $user);

        return [
            'id' => $details['basic']['id'],
            'order_number' => $details['basic']['order_number'],
            'patient_name' => $this->formatPatientName($order),
            'patient_display_id' => $order->patient_display_id,
            'provider_name' => $this->formatProviderName($order),
            'facility_name' => $this->formatFacilityName($order),
            'manufacturer_name' => $details['product']['manufacturer'],
            'product_name' => $details['product']['name'],
            'order_status' => $details['basic']['status'],
            'ivr_status' => $details['basic']['ivr_status'],
            'total_order_value' => $details['basic']['total_order_value'],
            'created_at' => $details['basic']['created_at'],
            'submitted_at' => $details['basic']['submitted_at'],
            'expected_service_date' => $details['basic']['expected_service_date'],
            'episode_id' => $details['basic']['episode_id'],
            'docuseal_submission_id' => $details['basic']['docuseal_submission_id'],
            'place_of_service' => $details['basic']['place_of_service'],
            'place_of_service_display' => $details['basic']['place_of_service_display'],
            'wound_type' => $details['clinical']['wound_type'],
            'wound_type_display' => $this->getWoundTypeDisplay($details['clinical']['wound_type']),

            // Structured data sections
            'patient' => $details['patient'],
            'insurance' => $details['insurance'],
            'product' => $details['product'],
            'clinical' => $details['clinical'],
            'provider' => $details['provider'],
            'facility' => $details['facility'],
            'attestations' => $details['metadata']['attestations'],
            'documents' => $details['documents'],

            // Enhanced file information
            'files' => $details['files'],

            // Metadata
            'fhir' => $details['metadata']['fhir_data'],
            'carrier' => $details['metadata']['carrier'],
            'tracking_number' => $details['metadata']['tracking_number'],
            'shipping_info' => $details['metadata']['shipping_info'],

            // Role-based access control
            'role_restrictions' => $details['role_restrictions'],
            'permissions' => $details['permissions'],
        ];
    }

    /**
     * Get role-based restrictions for the current user
     */
    private function getRoleRestrictions(?User $user): array
    {
        if (!$user) {
            return $this->getGuestRestrictions();
        }

        $userRole = $user->getPrimaryRole()?->slug ?? 'admin';

        return [
            'can_view_financials' => $user->can('view-financials') && $userRole !== 'office-manager',
            'can_see_discounts' => $user->can('view-discounts') && $userRole !== 'office-manager',
            'can_see_msc_pricing' => $user->can('view-msc-pricing') && $userRole !== 'office-manager',
            'can_see_order_totals' => $user->can('view-order-totals') && $userRole !== 'office-manager',
            'can_see_commission' => $user->can('view-commission') && $userRole !== 'office-manager',
            'pricing_access_level' => $userRole !== 'office-manager' ? 'full' : 'none',
            'commission_access_level' => $userRole !== 'office-manager' ? 'full' : 'none',
            'user_role' => $userRole,
        ];
    }

    /**
     * Get restrictions for guest/unauthenticated users
     */
    private function getGuestRestrictions(): array
    {
        return [
            'can_view_financials' => false,
            'can_see_discounts' => false,
            'can_see_msc_pricing' => false,
            'can_see_order_totals' => false,
            'can_see_commission' => false,
            'pricing_access_level' => 'none',
            'commission_access_level' => 'none',
            'user_role' => 'guest',
        ];
    }

    /**
     * Filter basic order data based on role restrictions
     */
    private function filterBasicData(array $basicData, array $restrictions): array
    {
        if (!$restrictions['can_see_order_totals']) {
            unset($basicData['total_order_value']);
        }

        return $basicData;
    }

    /**
     * Filter patient data (always sanitize PHI)
     */
    private function filterPatientData(array $patientData, array $restrictions): array
    {
        // Always remove sensitive PHI data unless explicitly authorized
        $sensitiveFields = ['phone', 'email', 'address'];

        foreach ($sensitiveFields as $field) {
            if (isset($patientData[$field]) && !$this->canViewPhiData($restrictions)) {
                $patientData[$field] = 'Protected Health Information';
            }
        }

        return $patientData;
    }

    /**
     * Filter product data based on role restrictions
     */
    private function filterProductData(array $productData, array $restrictions): array
    {
        if ($restrictions['pricing_access_level'] === 'none') {
            unset($productData['price'], $productData['cost']);
        }

        return $productData;
    }

    /**
     * Filter metadata based on role restrictions
     */
    private function filterMetadata(array $metadata, array $restrictions): array
    {
        if (!$restrictions['can_see_commission']) {
            unset($metadata['commission_data']);
        }

        return $metadata;
    }

    /**
     * Get user permissions for order operations
     */
    private function getUserPermissions(?User $user): array
    {
        if (!$user) {
            return [
                'can_update_status' => false,
                'can_view_ivr' => false,
                'can_upload_files' => false,
                'can_edit_order' => false,
            ];
        }

        return [
            'can_update_status' => $user->can('update-order-status'),
            'can_view_ivr' => $user->can('view-ivr-documents'),
            'can_upload_files' => $user->can('upload-order-files'),
            'can_edit_order' => $user->can('edit-orders'),
        ];
    }

    /**
     * Check if user can view PHI data
     */
    private function canViewPhiData(array $restrictions): bool
    {
        return in_array($restrictions['user_role'], ['admin', 'provider']);
    }

    /**
     * Format patient name from various sources
     */
    private function formatPatientName(ProductRequest $order): string
    {
        return $order->getFormattedPatientName();
    }

    /**
     * Format provider name
     */
    private function formatProviderName(ProductRequest $order): string
    {
        return $order->getFormattedProviderName();
    }

    /**
     * Format facility name
     */
    private function formatFacilityName(ProductRequest $order): string
    {
        return $order->getFormattedFacilityName();
    }

    /**
     * Get wound type display name
     */
    private function getWoundTypeDisplay(?string $woundType): string
    {
        if (!$woundType) {
            return '';
        }

        $descriptions = [
            'pressure_ulcer' => 'Pressure Ulcer',
            'diabetic_foot_ulcer' => 'Diabetic Foot Ulcer',
            'venous_leg_ulcer' => 'Venous Leg Ulcer',
            'arterial_ulcer' => 'Arterial Ulcer',
            'surgical_wound' => 'Surgical Wound',
            'trauma_wound' => 'Trauma Wound',
            'burn_wound' => 'Burn Wound',
            'other' => 'Other',
        ];

        return $descriptions[$woundType] ?? ucwords(str_replace('_', ' ', $woundType));
    }

    /**
     * Clear cache for specific order
     */
    public function clearOrderCache(int $orderId): void
    {
        $pattern = "order_details_{$orderId}_*";
        Cache::forget($pattern);
    }

    /**
     * Clear all order detail caches
     */
    public function clearAllOrderCaches(): void
    {
        Cache::flush();
    }
}
