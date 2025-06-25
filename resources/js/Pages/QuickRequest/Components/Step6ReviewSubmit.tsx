import { useState } from 'react';
import { router } from '@inertiajs/react';
import OrderReviewSummary from './OrderReviewSummary';

interface Step6Props {
  // Removed unused formData to eliminate lint warning
  products: Array<any>;
  providers: Array<any>;
  facilities: Array<any>;
  errors: Record<string, string>;
  onSubmit: () => void;
  isSubmitting: boolean;
  orderId?: string;
}

export default function Step6ReviewSubmit({
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

  const handleSubmit = async (): Promise<void> => {
    try {
      await Promise.resolve(onSubmit());
    } catch (error) {
      // You might want to surface error handling here
      throw error;
    }
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