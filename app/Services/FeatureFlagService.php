<?php

namespace App\Services;

class FeatureFlagService
{
    /**
     * Check if a feature flag is enabled
     *
     * @param string $flag The feature flag key (e.g., 'fhir.patient_handler_enabled')
     * @return bool
     */
    public static function isEnabled(string $flag): bool
    {
        return config("features.{$flag}", false);
    }

    /**
     * Check if FHIR service is enabled globally
     *
     * @return bool
     */
    public static function isFhirEnabled(): bool
    {
        return self::isEnabled('fhir.enabled');
    }

    /**
     * Check if FHIR operations are enabled for a specific handler
     *
     * @param string $handler The handler name (patient, provider, insurance, clinical, order)
     * @return bool
     */
    public static function isFhirHandlerEnabled(string $handler): bool
    {
        return self::isEnabled("fhir.{$handler}_handler_enabled");
    }

    /**
     * Check if episode cache warming is enabled
     *
     * @return bool
     */
    public static function isEpisodeCacheWarmingEnabled(): bool
    {
        return self::isEnabled('fhir.episode_cache_warming_enabled');
    }

    /**
     * Check if FHIR debug mode is enabled
     *
     * @return bool
     */
    public static function isFhirDebugMode(): bool
    {
        return self::isEnabled('fhir.debug_mode');
    }

    /**
     * Check if DocuSeal integration is enabled
     *
     * @return bool
     */
    public static function isDocusealEnabled(): bool
    {
        return self::isEnabled('docuseal.integration_enabled');
    }

    /**
     * Check if order polling is enabled
     *
     * @return bool
     */
    public static function isOrderPollingEnabled(): bool
    {
        return self::isEnabled('polling.order_polling_enabled');
    }

    /**
     * Get all FHIR feature flags status
     *
     * @return array
     */
    public static function getFhirStatus(): array
    {
        return [
            'service_enabled' => self::isFhirEnabled(),
            'patient_handler' => self::isFhirHandlerEnabled('patient'),
            'provider_handler' => self::isFhirHandlerEnabled('provider'),
            'insurance_handler' => self::isFhirHandlerEnabled('insurance'),
            'clinical_handler' => self::isFhirHandlerEnabled('clinical'),
            'order_handler' => self::isFhirHandlerEnabled('order'),
            'episode_cache_warming' => self::isEpisodeCacheWarmingEnabled(),
            'debug_mode' => self::isFhirDebugMode(),
        ];
    }
} 