<?php

namespace App\Services;

use App\Models\Order\ProductRequest;
use App\Services\DocuSealFieldFormatterService;

class OrderFieldMappingService
{
    private array $fieldMappings;
    private DocuSealFieldFormatterService $fieldFormatter;

    public function __construct(DocuSealFieldFormatterService $fieldFormatter)
    {
        $this->fieldMappings = config('order-field-mappings') ?? [];
        $this->fieldFormatter = $fieldFormatter;
    }

    /**
     * Map product request/order data to order form fields
     */
    public function mapOrderToFields(ProductRequest $productRequest, string $orderFormKey): array
    {
        $formConfig = $this->getOrderFormConfig($orderFormKey);
        if (!$formConfig) {
            throw new \Exception("Unknown order form: {$orderFormKey}");
        }
        $mappings = $formConfig['field_mappings'] ?? [];
        $mappedFields = [];
        foreach ($mappings as $formFieldName => $systemField) {
            $value = $this->getFieldValue($productRequest, $systemField);
            if ($value !== null) {
                $fieldType = $this->fieldFormatter->detectFieldType($formFieldName, $value);
                $formattedValue = $this->fieldFormatter->formatFieldValue($value, $fieldType);
                $mappedFields[$formFieldName] = $formattedValue;
            }
        }
        // Add any order-specific formatting here if needed
        return $mappedFields;
    }

    private function getOrderFormConfig(string $orderFormKey): ?array
    {
        return $this->fieldMappings['order_forms'][$orderFormKey] ?? null;
    }

    private function getFieldValue(ProductRequest $productRequest, string $fieldName): mixed
    {
        // Implement similar to IvrFieldMappingService, but for order fields
        // For now, just return null
        return null;
    }
}
