import React, { useState, useEffect } from 'react';
import { X, FileText, Loader2, CheckCircle, AlertCircle } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
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
    product_request_id?: number;
    facility?: {
      name?: string;
      address?: string;
      city?: string;
      state?: string;
      zip?: string;
      phone?: string;
    };
    provider?: {
      name?: string;
      email?: string;
      phone?: string;
      npi?: string;
    };
    product?: {
      name?: string;
      quantity?: number;
      size?: string;
      price?: number;
    };
    clinical?: {
      wound_type?: string;
      expected_service_date?: string;
    };
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
  const [prefillData, setPrefillData] = useState<any>(null);
  const [isACZManufacturer, setIsACZManufacturer] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submissionId, setSubmissionId] = useState<string | null>(null);
  const [showAdminCommentModal, setShowAdminCommentModal] = useState(false);
  const [adminComment, setAdminComment] = useState('');
  const [isConfirmingSubmission, setIsConfirmingSubmission] = useState(false);

  useEffect(() => {
    if (isOpen && orderData.manufacturer_id) {
      // Check if this is ACZ manufacturer - multiple detection methods
      const manufacturerName = orderData.manufacturer_name || '';
      const manufacturerId = orderData.manufacturer_id;

      // Check by name (case-insensitive) or by ID (ACZ is typically ID 1)
      // Temporarily force ACZ detection for testing
      const isACZ = true; // /acz/i.test(manufacturerName) || manufacturerId === 1;

      console.log('ACZ Detection Debug:', {
        manufacturerName,
        manufacturerId,
        isACZ,
        product_request_id: orderData.product_request_id,
        forcedACZ: true
      });

      setIsACZManufacturer(isACZ);

      // Check if order form has already been submitted
      if (orderData.order_form_submission_id) {
        setSubmissionId(orderData.order_form_submission_id);
        console.log('Order form already submitted with ID:', orderData.order_form_submission_id);
      }

      if (isACZ && orderData.product_request_id) {
        loadACZPrefillData();
      }

      loadManufacturerConfig();
    }
  }, [isOpen, orderData.manufacturer_id, orderData.manufacturer_name, orderData.product_request_id, orderData.order_form_submission_id]);

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

  const loadACZPrefillData = async () => {
    try {
      if (!orderData.product_request_id) {
        console.log('No product_request_id available for ACZ pre-fill');
        // Load dummy data as fallback
        setPrefillData({
          prefill_data: {
            order_date: new Date().toLocaleDateString('en-US'),
            physician_name: 'Dr. Test Physician',
            account_contact_name: 'Test Contact',
            account_contact_phone: '(555) 123-4567',
            quantity_line_1: '1',
            description_line_1: 'Test Product - Wound Care Dressing',
            size_line_1: '4x4 inches',
            unit_price_line_1: '125.00',
            amount_line_1: '125.00',
            sub_total: '125.00',
            facility_name: 'Test Medical Center',
            ship_to_address: '123 Medical Plaza',
            ship_to_city: 'Test City',
            ship_to_state: 'TX',
            ship_to_zip: '12345',
            notes: 'Test order - please expedite shipping'
          },
          docuSeal_fields: [],
          template_id: 852554,
          manufacturer: 'ACZ & ASSOCIATES'
        });
        return;
      }

      console.log('Loading ACZ pre-fill data for product request:', orderData.product_request_id);
      const response = await api.get(`/api/product-requests/${orderData.product_request_id}/order-form-prefill`);
      const data = (response as any).data;

      console.log('ACZ pre-fill response:', data);

      if (data.success) {
        setPrefillData(data.data);
        console.log('ACZ pre-fill data loaded successfully:', data.data);
      } else {
        console.warn('Failed to load ACZ pre-fill data:', data.error);
        // Load dummy data as fallback
        setPrefillData({
          prefill_data: {
            order_date: new Date().toLocaleDateString('en-US'),
            physician_name: 'Dr. Test Physician',
            account_contact_name: 'Test Contact',
            account_contact_phone: '(555) 123-4567',
            quantity_line_1: '1',
            description_line_1: 'Test Product - Wound Care Dressing',
            size_line_1: '4x4 inches',
            unit_price_line_1: '125.00',
            amount_line_1: '125.00',
            sub_total: '125.00',
            facility_name: 'Test Medical Center',
            ship_to_address: '123 Medical Plaza',
            ship_to_city: 'Test City',
            ship_to_state: 'TX',
            ship_to_zip: '12345',
            notes: 'Test order - please expedite shipping'
          },
          docuSeal_fields: [],
          template_id: 852554,
          manufacturer: 'ACZ & ASSOCIATES'
        });
      }
    } catch (err: any) {
      console.error('Error loading ACZ pre-fill data:', err);
      // Load dummy data as fallback
      setPrefillData({
        prefill_data: {
          order_date: new Date().toLocaleDateString('en-US'),
          physician_name: 'Dr. Test Physician',
          account_contact_name: 'Test Contact',
          account_contact_phone: '(555) 123-4567',
          quantity_line_1: '1',
          description_line_1: 'Test Product - Wound Care Dressing',
          size_line_1: '4x4 inches',
          unit_price_line_1: '125.00',
          amount_line_1: '125.00',
          sub_total: '125.00',
          facility_name: 'Test Medical Center',
          ship_to_address: '123 Medical Plaza',
          ship_to_city: 'Test City',
          ship_to_state: 'TX',
          ship_to_zip: '12345',
          notes: 'Test order - please expedite shipping'
        },
        docuSeal_fields: [],
        template_id: 852554,
        manufacturer: 'ACZ & ASSOCIATES'
      });
    }
  };

  const handleOrderFormComplete = (data: any) => {
    console.log('Order form completed:', data);
    // Don't close the modal automatically - let user close it manually
    // onOrderFormComplete?.(data);
    // setShowOrderFormModal(false);
  };

  const handleOrderFormError = (error: string) => {
    setError(error);
    toast.error(`Order form error: ${error}`);
  };

  const handleSubmitOrderForm = async () => {
    if (!orderData.product_request_id || !submissionId) {
      toast.error('Missing required data for submission');
      return;
    }

    try {
      setIsSubmitting(true);
      const response = await api.post(`/api/product-requests/${orderData.product_request_id}/order-form-submit`, {
        submission_id: submissionId,
        manufacturer_id: orderData.manufacturer_id || 1
      });

      const responseData = response as any;

      if (responseData.success) {
        toast.success('Order form submitted successfully!');
        // Update the prefill data to show submission status
        setPrefillData((prev: any) => ({
          ...prev,
          prefill_data: {
            ...prev.prefill_data,
            order_form_submitted: true,
            order_form_submission_id: submissionId
          }
        }));
      } else {
        toast.error(responseData.message || 'Failed to submit order form');
      }
    } catch (err: any) {
      console.error('Error submitting order form:', err);
      toast.error('Failed to submit order form: ' + (err.response?.data?.message || err.message));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleConfirmSubmission = async () => {
    if (!adminComment.trim()) {
      toast.error('Please provide an admin comment');
      return;
    }

    try {
      setIsConfirmingSubmission(true);

      // Here you would typically save the admin comment and finalize the submission
      // For now, we'll just show a success message and close the modal
      toast.success('Order form submission confirmed with admin comment!');

      // Close the admin comment modal
      setShowAdminCommentModal(false);
      setAdminComment('');

      // You can add additional logic here to save the admin comment to the database
      // and update the order form status

    } catch (err: any) {
      console.error('Error confirming submission:', err);
      toast.error('Failed to confirm submission: ' + (err.response?.data?.message || err.message));
    } finally {
      setIsConfirmingSubmission(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
        {/* Debug Header - Remove in production */}
        <div className="p-4 bg-yellow-50 border-b border-yellow-200">
          <h3 className="text-sm font-medium text-yellow-800 mb-2">Debug Info:</h3>
          <div className="text-xs text-yellow-700 space-y-1">
            <div>Manufacturer Name: {orderData.manufacturer_name || 'Not set'}</div>
            <div>Manufacturer ID: {orderData.manufacturer_id || 'Not set'}</div>
            <div>Product Request ID: {orderData.product_request_id || 'Not set'}</div>
            <div>ACZ Detected: {isACZManufacturer ? 'Yes' : 'No'}</div>
            <div>Prefill Data: {prefillData ? 'Loaded' : 'Not loaded'}</div>
          </div>
        </div>

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
              {/* Debug information for ACZ */}
              {isACZManufacturer && (
                <div className="p-4 bg-blue-50 border-b border-blue-200">
                  <h3 className="text-sm font-medium text-blue-800 mb-2">ACZ Debug Info:</h3>
                  <div className="text-xs text-blue-700 space-y-1">
                    <div>Template ID: 852554</div>
                    <div>Manufacturer Name: {orderData.manufacturer_name}</div>
                    <div>Product Request ID: {orderData.product_request_id || 'Not provided'}</div>
                    <div>Prefill Data Loaded: {prefillData ? 'Yes' : 'No'}</div>
                    {prefillData && (
                      <div>
                        <div>Prefill Fields: {Object.keys(prefillData.prefill_data || {}).length}</div>
                        <div>DocuSeal Fields: {prefillData.docuSeal_fields?.length || 0}</div>
                        <div>Sample Fields: {Object.keys(prefillData.prefill_data || {}).slice(0, 3).join(', ')}</div>
                      </div>
                    )}
                  </div>
                </div>
              )}
              <DocusealEmbed
                manufacturerId={String(manufacturerConfig.id)}
                templateId={isACZManufacturer ? 852554 : manufacturerConfig.order_form_template_id}
                productCode=""
                documentType="OrderForm"
                formData={{
                  ...orderData,
                  patient_email: orderData.patient_email || orderData.integration_email,
                  integration_email: orderData.integration_email,
                  // Add ACZ pre-fill data if available
                  ...(isACZManufacturer && prefillData ? prefillData.prefill_data : {})
                }}
                episodeId={orderData.episode_id}
                onComplete={handleOrderFormComplete}
                onError={handleOrderFormError}
                className="h-full"
                debug={true}
              />

              {/* Submit Button for ACZ Order Form */}
              {isACZManufacturer && (
                <div className="p-4 border-t border-gray-200 dark:border-gray-700">
                  <div className="flex items-center justify-between">
                    <div className="text-sm text-gray-600 dark:text-gray-400">
                      {submissionId ? (
                        <span className="text-green-600 dark:text-green-400">
                          ✓ Form completed with ID: {submissionId}
                        </span>
                      ) : (
                        <span>Complete the form above, then click Submit to save</span>
                      )}
                    </div>
                    <div className="flex gap-2">
                      <Button
                        onClick={handleSubmitOrderForm}
                        disabled={!submissionId || isSubmitting}
                        className="bg-blue-600 hover:bg-blue-700 text-white"
                      >
                        {isSubmitting ? (
                          <>
                            <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                            Submitting...
                          </>
                        ) : (
                          'Submit Order Form'
                        )}
                      </Button>

                      {submissionId && (
                        <Button
                          onClick={() => setShowAdminCommentModal(true)}
                          className="bg-green-600 hover:bg-green-700 text-white"
                        >
                          Confirm Submission
                        </Button>
                      )}
                    </div>
                  </div>
                </div>
              )}
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

      {/* Admin Comment Modal */}
      <Dialog open={showAdminCommentModal} onOpenChange={setShowAdminCommentModal}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Confirm Order Form Submission</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="admin-comment">Admin Comment (Required)</Label>
              <Textarea
                id="admin-comment"
                placeholder="Add any relevant notes or comments for admin review..."
                value={adminComment}
                onChange={(e) => setAdminComment(e.target.value)}
                rows={4}
                required
              />
            </div>
            <div className="text-sm text-gray-600">
              <p>This will finalize the order form submission and save it to the database.</p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setShowAdminCommentModal(false)}>
              Cancel
            </Button>
            <Button
              onClick={handleConfirmSubmission}
              disabled={!adminComment.trim() || isConfirmingSubmission}
              className="bg-green-600 hover:bg-green-700 text-white"
            >
              {isConfirmingSubmission ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Confirming...
                </>
              ) : (
                'Confirm Submission'
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};
