<?php

namespace App\Services\FuzzyMapping;

use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TemplateSelectionService
{
    /**
     * Get the appropriate DocuSeal template based on manufacturer and context
     */
    public function selectTemplate(array $context): ?DocusealTemplate
    {
        // Extract manufacturer ID from context
        $manufacturerId = $this->extractManufacturerId($context);
        
        if (!$manufacturerId) {
            Log::warning('No manufacturer ID found in context', $context);
            return $this->getGenericTemplate();
        }
        
        // Try to get manufacturer-specific template
        $template = $this->getManufacturerTemplate($manufacturerId, $context['document_type'] ?? 'IVR');
        
        if (!$template) {
            Log::warning('No template found for manufacturer', [
                'manufacturer_id' => $manufacturerId,
                'document_type' => $context['document_type'] ?? 'IVR'
            ]);
            return $this->getGenericTemplate();
        }
        
        return $template;
    }
    
    /**
     * Extract manufacturer ID from various context sources
     */
    private function extractManufacturerId(array $context): ?int
    {
        // Priority 1: Direct manufacturer_id
        if (isset($context['manufacturer_id'])) {
            return (int) $context['manufacturer_id'];
        }
        
        // Priority 2: From product
        if (isset($context['product_id'])) {
            $product = DB::table('products')
                ->where('id', $context['product_id'])
                ->first();
            
            if ($product) {
                return (int) $product->manufacturer_id;
            }
        }
        
        // Priority 3: From product code/Q-code
        if (isset($context['product_code']) || isset($context['q_code'])) {
            $query = DB::table('products');
            
            if (isset($context['product_code'])) {
                $query->where('product_code', $context['product_code']);
            } elseif (isset($context['q_code'])) {
                $query->where('q_code', $context['q_code']);
            }
            
            $product = $query->first();
            if ($product) {
                return (int) $product->manufacturer_id;
            }
        }
        
        // Priority 4: From manufacturer name (fuzzy match)
        if (isset($context['manufacturer_name'])) {
            return $this->findManufacturerByName($context['manufacturer_name']);
        }
        
        // Priority 5: From episode
        if (isset($context['episode_id'])) {
            $episode = DB::table('patient_manufacturer_ivr_episodes')
                ->where('id', $context['episode_id'])
                ->first();
            
            if ($episode) {
                return (int) $episode->manufacturer_id;
            }
        }
        
        return null;
    }
    
    /**
     * Get manufacturer-specific template
     */
    private function getManufacturerTemplate(int $manufacturerId, string $documentType = 'IVR'): ?DocusealTemplate
    {
        // Cache key for performance
        $cacheKey = "docuseal_template_{$manufacturerId}_{$documentType}";
        
        return Cache::remember($cacheKey, 3600, function () use ($manufacturerId, $documentType) {
            // First try to get the default template for this manufacturer
            $template = DocusealTemplate::where('manufacturer_id', $manufacturerId)
                ->where('document_type', $documentType)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();
            
            // If no default, get any active template for this manufacturer
            if (!$template) {
                $template = DocusealTemplate::where('manufacturer_id', $manufacturerId)
                    ->where('document_type', $documentType)
                    ->where('is_active', true)
                    ->orderBy('updated_at', 'desc')
                    ->first();
            }
            
            return $template;
        });
    }
    
    /**
     * Get a generic template as fallback
     */
    private function getGenericTemplate(): ?DocusealTemplate
    {
        // DON'T default to Advanced Solution - use a true generic template
        return DocusealTemplate::whereNull('manufacturer_id')
            ->where('document_type', 'IVR')
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Find manufacturer by name using fuzzy matching
     */
    private function findManufacturerByName(string $name): ?int
    {
        $name = strtolower(trim($name));
        
        // Exact match first
        $manufacturer = Manufacturer::whereRaw('LOWER(name) = ?', [$name])->first();
        if ($manufacturer) {
            return $manufacturer->id;
        }
        
        // Common variations
        $variations = [
            'acz' => ['acz & associates', 'acz distribution', 'acz associates'],
            'advanced' => ['advanced solution', 'advanced solutions'],
            'biowound' => ['biowound', 'biowound solutions', 'bio wound'],
            'extremity' => ['extremity care', 'extremity care llc'],
            'centurion' => ['centurion', 'centurion therapeutics'],
            'imbed' => ['imbed', 'imbed microlyte'],
        ];
        
        foreach ($variations as $key => $values) {
            if (str_contains($name, $key)) {
                foreach ($values as $variant) {
                    $manufacturer = Manufacturer::whereRaw('LOWER(name) = ?', [$variant])->first();
                    if ($manufacturer) {
                        return $manufacturer->id;
                    }
                }
            }
        }
        
        // Fuzzy match using LIKE
        $manufacturer = Manufacturer::whereRaw('LOWER(name) LIKE ?', ["%{$name}%"])->first();
        
        return $manufacturer ? $manufacturer->id : null;
    }
}
