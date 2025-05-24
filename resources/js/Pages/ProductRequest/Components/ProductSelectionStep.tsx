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

  return (
    <ProductSelector
      selectedProducts={formData.selected_products}
      onProductsChange={handleProductsChange}
      recommendationContext={formData.wound_type}
      productRequestId={productRequestId}
      userRole={userRole}
      title={productRequestId ? "Product Selection with AI Clinical Recommendations" : "Product Selection with AI Recommendations"}
      description={productRequestId
        ? "AI-powered recommendations based on clinical assessment, patient factors, and evidence-based protocols"
        : "Based on your clinical assessment, choose optimal products for the patient's needs"
      }
    />
  );
};



export default ProductSelectionStep;
