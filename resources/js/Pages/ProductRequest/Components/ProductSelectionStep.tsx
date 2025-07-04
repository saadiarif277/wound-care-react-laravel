import React from 'react';
import ProductSelector from '@/Components/ProductCatalog/ProductSelector';

interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product?: any;
}

interface Props {
  formData: {
    selected_products: SelectedProduct[];
    wound_type?: string;
    clinical_data?: any;
  };
  updateFormData: (data: any) => void;
  productRequestId?: number; // For AI recommendations when editing existing request
  userRole?: string; // For role-based pricing display
}

const ProductSelectionStep: React.FC<Props> = ({ formData, updateFormData, productRequestId, userRole = 'provider' }) => {
  const handleProductsChange = (products: SelectedProduct[]) => {
    updateFormData({
      selected_products: products
    });
  };

  // Define proper role restrictions for providers
  const getRoleRestrictions = () => {
    switch (userRole) {
      case 'provider':
        return {
          can_view_financials: false, // This controls commission visibility - providers can't see commission
          can_see_discounts: true,     // Providers CAN see discounts
          can_see_msc_pricing: true,   // Providers CAN see MSC pricing
          can_see_order_totals: true,  // Providers CAN see order totals
          pricing_access_level: 'full',
          commission_access_level: 'none' // Hide commission from providers
        };
      case 'office_manager':
        return {
          can_view_financials: false,
          can_see_discounts: false,
          can_see_msc_pricing: false,
          can_see_order_totals: false,
          pricing_access_level: 'none', // No pricing visibility at all
          commission_access_level: 'none'
        };
      case 'msc-admin':
      case 'msc-rep':
      case 'superadmin':
        return {
          can_view_financials: true,
          can_see_discounts: true,
          can_see_msc_pricing: true,
          can_see_order_totals: true,
          pricing_access_level: 'full',
          commission_access_level: 'full'
        };
      default:
        // Default to most restrictive
        return {
          can_view_financials: false,
          can_see_discounts: false,
          can_see_msc_pricing: false,
          can_see_order_totals: false,
          pricing_access_level: 'national_asp_only',
          commission_access_level: 'none'
        };
    }
  };

  return (
    <ProductSelector
      selectedProducts={formData.selected_products}
      onProductsChange={handleProductsChange}
      recommendationContext={formData.wound_type}
      productRequestId={productRequestId}
      roleRestrictions={getRoleRestrictions()} // Pass proper role restrictions
      title={productRequestId ? "Product Selection with AI Clinical Recommendations" : "Product Selection with AI Recommendations"}
      description={productRequestId
        ? "AI-powered recommendations based on clinical assessment, patient factors, and evidence-based protocols"
        : "Based on your clinical assessment, choose optimal products for the patient's needs"
      }
    />
  );
};



export default ProductSelectionStep;
