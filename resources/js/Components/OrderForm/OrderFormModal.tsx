import React, { useState, useEffect } from 'react';
import { X, FileText, Loader2, CheckCircle, AlertCircle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { DocusealEmbed } from '@/Components/QuickRequest/DocusealEmbed';
import api from '@/lib/api';
import { toast } from 'sonner';

interface OrderFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  orderId: string;
  orderData: {
    id: string;
    order_number: string;
    manufacturer_name: string;
    manufacturer_id?: number;
    patient_name?: string;
    patient_email?: string;
    integration_email?: string;
    episode_id?: number;
    ivr_status?: string;
    order_form_status?: string;
    docuseal_submission_id?: string;
    order_form_submission_id?: string;
  };
  onOrderFormComplete?: (data: any) => void;
}

export const OrderFormModal: React.FC<OrderFormModalProps> = ({
  isOpen,
  onClose,
  orderId,
  orderData,
  onOrderFormComplete
}) => {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isCompleted, setIsCompleted] = useState(false);
  const [manufacturerConfig, setManufacturerConfig] = useState<any>(null);

  useEffect(() => {
    if (isOpen && orderData.manufacturer_id) {
      loadManufacturerConfig();
    }
  }, [isOpen, orderData.manufacturer_id]);

  const loadManufacturerConfig = async () => {
    try {
      setIsLoading(true);
      setError(null);

      // Load manufacturer configuration
      const response = await api.get(`/api/v1/manufacturers/${orderData.manufacturer_id}`);
      const config = (response as any).data.data; // The API returns data in a data property

      if (!config.order_form_template_id) {
        setError('No order form template configured for this manufacturer');
        return;
      }

      setManufacturerConfig(config);
    } catch (err: any) {
      console.error('Error loading manufacturer config:', err);
      setError(err.response?.data?.message || 'Failed to load manufacturer configuration');
    } finally {
      setIsLoading(false);
    }
  };

  const handleOrderFormComplete = (data: any) => {
    setIsCompleted(true);
    toast.success('Order form completed successfully!');
    onOrderFormComplete?.(data);

    // Close modal after a short delay
    setTimeout(() => {
      onClose();
    }, 2000);
  };

  const handleOrderFormError = (error: string) => {
    setError(error);
    toast.error(`Order form error: ${error}`);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3">
            <div className="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30">
              <FileText className="w-6 h-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-gray-900 dark:text-white">
                Order Form
              </h2>
              <p className="text-sm text-gray-600 dark:text-gray-400">
                {orderData.manufacturer_name} • Order #{orderData.order_number}
              </p>
            </div>
          </div>
          <button
            title="Close Order Form"
            onClick={onClose}
            className="p-2 rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-hidden">
          {isLoading ? (
            <div className="flex items-center justify-center p-12">
              <div className="flex flex-col items-center">
                <Loader2 className="w-8 h-8 animate-spin text-blue-600 mb-4" />
                <p className="text-gray-600 dark:text-gray-400">Loading order form...</p>
              </div>
            </div>
          ) : error ? (
            <div className="p-6">
              <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>{error}</AlertDescription>
              </Alert>
              <div className="mt-4">
                <Button onClick={onClose} variant="ghost">
                  Close
                </Button>
              </div>
            </div>
          ) : isCompleted ? (
            <div className="flex items-center justify-center p-12">
              <div className="flex flex-col items-center text-center">
                <CheckCircle className="w-16 h-16 text-green-500 mb-4" />
                <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
                  Order Form Completed
                </h3>
                <p className="text-gray-600 dark:text-gray-400">
                  Your order form has been successfully submitted.
                </p>
              </div>
            </div>
          ) : manufacturerConfig ? (
            <div className="h-full">
              <DocusealEmbed
                manufacturerId={String(manufacturerConfig.id)}
                templateId={manufacturerConfig.order_form_template_id}
                productCode=""
                documentType="OrderForm"
                formData={{
                  ...orderData,
                  patient_email: orderData.patient_email || orderData.integration_email,
                  integration_email: orderData.integration_email
                }}
                episodeId={orderData.episode_id}
                onComplete={handleOrderFormComplete}
                onError={handleOrderFormError}
                className="h-full"
                debug={false}
              />
            </div>
          ) : (
            <div className="flex items-center justify-center p-12">
              <div className="flex flex-col items-center">
                <AlertCircle className="w-8 h-8 text-gray-400 mb-4" />
                <p className="text-gray-600 dark:text-gray-400">No order form template available</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
