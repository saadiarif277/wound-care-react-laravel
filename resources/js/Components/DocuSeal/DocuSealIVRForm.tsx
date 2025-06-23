import React from 'react';
import { DocuSealEmbed } from '@/Components/QuickRequest/DocuSealEmbed';

interface DocuSealIVRFormProps {
  formData: any;
  templateId?: string;
  onComplete?: (submissionId: string) => void;
  onError?: (error: string) => void;
  episodeId?: string;
}

export default function DocuSealIVRForm({
  formData,
  templateId,
  onComplete,
  onError,
  episodeId
}: DocuSealIVRFormProps) {
  // Extract manufacturer ID from the form data
  const getManufacturerId = () => {
    // Try multiple ways to get manufacturer ID
    
    // 1. Direct manufacturer_id in formData
    if (formData.manufacturer_id) {
      return String(formData.manufacturer_id);
    }
    
    // 2. From product_manufacturer field
    if (formData.product_manufacturer) {
      // If it's already an ID
      if (!isNaN(Number(formData.product_manufacturer))) {
        return String(formData.product_manufacturer);
      }
    }
    
    // 3. From selected products with full product data
    if (formData.selected_products && formData.selected_products.length > 0) {
      const firstProduct = formData.selected_products[0];
      if (firstProduct.product && firstProduct.product.manufacturer_id) {
        return String(firstProduct.product.manufacturer_id);
      }
    }
    
    // 4. From product details
    if (formData.product_details && formData.product_details.length > 0) {
      const details = formData.product_details[0];
      if (details.manufacturer_id) {
        return String(details.manufacturer_id);
      }
    }
    
    // 5. Fallback - this should not happen in production
    console.warn('Could not extract manufacturer ID from form data', formData);
    return '1';
  };

  const manufacturerId = getManufacturerId();
  const productCode = formData.product_code || formData.product_details?.[0]?.code || '';

  return (
    <DocuSealEmbed
      manufacturerId={manufacturerId}
      productCode={productCode}
      onComplete={onComplete}
      onError={onError}
      className="w-full h-full min-h-[600px]"
    />
  );
}
