import { useState } from 'react';
import { router } from '@inertiajs/react';
import OrderReviewSummary from './OrderReviewSummary';

interface QuickRequestFormData {
  // This interface matches the form data structure
  [key: string]: any;
}

interface Step6Props {
  formData: QuickRequestFormData;
  updateFormData: (data: Partial<QuickRequestFormData>) => void;
  products: Array<any>;
  providers: Array<any>;
  facilities: Array<any>;
  errors: Record<string, string>;
  onSubmit: () => void;
  isSubmitting: boolean;
  orderId?: string;
}

export default function Step6ReviewSubmit({
  formData,
  products,
  errors,
  onSubmit,
  orderId
}: Step6Props) {
  // If we don't have an order ID yet, create a temporary one
  const currentOrderId = orderId || 'new';

  const handleEdit = (section: string) => {
    // In the Quick Request flow, navigate to the appropriate step
    const stepMap: Record<string, number> = {
      'patient-insurance': 2,
      'clinical-billing': 4,
      'product-selection': 5,
      'ivr-form': 7
    };

    const step = stepMap[section];
    if (step) {
      // Navigate to the specific step
      router.visit(`/quick-request/create?step=${step}`);
    }
  };

  const handleSubmit = async () => {
    // Call the parent's onSubmit which should handle the actual submission
    return new Promise((resolve, reject) => {
      try {
        onSubmit();
        resolve(true);
      } catch (error) {
        reject(error);
      }
    });
  };

  return (
    <OrderReviewSummary
      orderId={currentOrderId}
      isPreSubmission={true}
      onEdit={handleEdit}
      onSubmit={handleSubmit}
    />
  );
}