import React, { useState } from 'react';
import { FiCheck, FiAlertCircle } from 'react-icons/fi';

interface ReviewSubmitStepProps {
  formData: any;
  updateFormData: (data: any) => void;
  onSubmit: () => void;
}

const ReviewSubmitStep: React.FC<ReviewSubmitStepProps> = ({
  formData,
  updateFormData,
  onSubmit
}) => {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async () => {
    try {
      setSubmitting(true);
      setError(null);

      // Create order summary for eCW
      const orderSummary = {
        order_data: {
          products: formData.selected_products.map((product: any) => ({
            name: product.name,
            quantity: product.units,
            code: product.q_code,
            size: product.graph_size
          })),
          clinical_summary: formData.clinical_assessment || '',
          order_date: new Date().toISOString()
        }
      };

      // Push order summary to eCW if connected
      if (formData.patient_ecw_id) {
        const response = await fetch(`/api/ecw/patients/${formData.patient_ecw_id}/order-summary`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/fhir+json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify(orderSummary)
        });

        if (!response.ok) {
          throw new Error('Failed to send order summary to eCW');
        }
      }

      // Proceed with normal submission
      await onSubmit();

    } catch (err) {
      console.error('Submission failed:', err);
      setError('Failed to submit order. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="bg-white shadow sm:rounded-lg">
        <div className="px-4 py-5 sm:p-6">
          <h3 className="text-lg font-medium leading-6 text-gray-900">
            Review and Submit Order
          </h3>

          {/* Patient Information */}
          <div className="mt-6">
            <h4 className="text-sm font-medium text-gray-900">Patient Information</h4>
            <dl className="mt-2 grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
              <div>
                <dt className="text-sm font-medium text-gray-500">Name</dt>
                <dd className="mt-1 text-sm text-gray-900">
                  {formData.patient_api_input.first_name} {formData.patient_api_input.last_name}
                </dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500">Date of Birth</dt>
                <dd className="mt-1 text-sm text-gray-900">{formData.patient_api_input.dob}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500">Member ID</dt>
                <dd className="mt-1 text-sm text-gray-900">{formData.patient_api_input.member_id}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500">Expected Service Date</dt>
                <dd className="mt-1 text-sm text-gray-900">{formData.expected_service_date}</dd>
              </div>
            </dl>
          </div>

          {/* Selected Products */}
          <div className="mt-6">
            <h4 className="text-sm font-medium text-gray-900">Selected Products</h4>
            <ul className="mt-2 divide-y divide-gray-200">
              {formData.selected_products.map((product: any, index: number) => (
                <li key={index} className="py-3">
                  <div className="flex items-start">
                    <div className="flex-grow">
                      <p className="text-sm font-medium text-gray-900">{product.name}</p>
                      <p className="text-sm text-gray-500">
                        Size: {product.graph_size} • Quantity: {product.units}
                      </p>
                    </div>
                    <div className="ml-4">
                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {product.q_code}
                      </span>
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          </div>

          {/* Clinical Assessment */}
          {formData.clinical_assessment && (
            <div className="mt-6">
              <h4 className="text-sm font-medium text-gray-900">Clinical Assessment</h4>
              <div className="mt-2 prose prose-sm text-gray-500">
                <p>{formData.clinical_assessment}</p>
              </div>
            </div>
          )}

          {/* Error Message */}
          {error && (
            <div className="mt-6 bg-red-50 border border-red-200 rounded-md p-4">
              <div className="flex">
                <FiAlertCircle className="h-5 w-5 text-red-400" />
                <div className="ml-3">
                  <h3 className="text-sm font-medium text-red-800">Error</h3>
                  <div className="mt-2 text-sm text-red-700">
                    <p>{error}</p>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Submit Button */}
          <div className="mt-6">
            <button
              type="button"
              onClick={handleSubmit}
              disabled={submitting}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
              {submitting ? (
                <>
                  <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Submitting...
                </>
              ) : (
                <>
                  <FiCheck className="-ml-1 mr-2 h-5 w-5" />
                  Submit Order
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReviewSubmitStep;
