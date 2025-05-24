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
}

const ProductSelectionStep: React.FC<Props> = ({ formData, updateFormData }) => {
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
      title="Product Selection with AI Recommendations"
      description="Based on your clinical assessment, choose optimal products for the patient's needs"
    />
  );
};



export default ProductSelectionStep;
