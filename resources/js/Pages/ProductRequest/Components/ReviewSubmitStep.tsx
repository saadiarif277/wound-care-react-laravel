import React, { useState } from 'react';
import { Check, AlertCircle, Loader2 } from 'lucide-react';

interface ReviewSubmitStepProps {
  formData: any;
  updateFormData: (data: any) => void;
  onSubmit: () => void;
}

// Helper function to render a section
const Section: React.FC<{ title: string; children: React.ReactNode }> = ({ title, children }) => (
  <div className="mt-6 pt-6 border-t border-gray-200">
    <h4 className="text-base font-semibold text-gray-900 mb-3">{title}</h4>
    <div className="space-y-4">{children}</div>
  </div>
);

// Helper to display key-value pairs
const DetailItem: React.FC<{ label: string; value?: string | number | null | React.ReactNode }> = ({ label, value }) => {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  return (
    <div>
      <dt className="text-sm font-medium text-gray-500">{label}</dt>
      <dd className="mt-1 text-sm text-gray-900">{typeof value === 'string' || typeof value === 'number' ? value : <>{value}</>}</dd>
    </div>
  );
};

const ReviewSubmitStep: React.FC<ReviewSubmitStepProps> = ({
  formData,
  updateFormData,
  onSubmit
}) => {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleInternalSubmit = async () => {
    setSubmitting(true);
    setError(null);
    try {
      await onSubmit();
    } catch (err: any) {
      console.error('Submission failed in ReviewSubmitStep:', err);
      setError(err.message || 'Failed to submit order. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  // TODO: Fetch product details if selected_products only contains IDs
  // For now, assuming selected_products might have name, size, quantity (adjust as needed)
  const renderSelectedProducts = () => {
    if (!formData.selected_products || formData.selected_products.length === 0) {
      return <p className="text-sm text-gray-500">No products selected.</p>;
    }
    return (
      <ul className="divide-y divide-gray-200">
        {formData.selected_products.map((product: any, index: number) => (
          <li key={product.product_id || index} className="py-3">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm font-medium text-gray-900">{product.name || `Product ID: ${product.product_id}`}</p>
                <p className="text-sm text-gray-500">
                  {product.size ? `Size: ${product.size} • ` : ''}Quantity: {product.quantity}
                </p>
              </div>
              {product.q_code && (
                <span className="ml-4 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  {product.q_code}
                </span>
              )}
            </div>
          </li>
        ))}
      </ul>
    );
  };

  const renderClinicalData = () => {
    if (!formData.clinical_data || Object.keys(formData.clinical_data).length === 0) {
      return <p className="text-sm text-gray-500">No clinical assessment data provided.</p>;
    }
    // Basic rendering for now, ideally this would be more structured
    return (
      <div className="prose prose-sm max-w-none">
        <pre>{JSON.stringify(formData.clinical_data, null, 2)}</pre>
      </div>
    );
  };
  
  const renderMacValidationResults = () => {
    if (!formData.mac_validation_results) {
      return <p className="text-sm text-gray-500">MAC validation not yet performed or results unavailable.</p>;
    }
    const { status, score, issues } = formData.mac_validation_results;
    return (
      <div>
        <DetailItem label="Status" value={status} />
        <DetailItem label="Compliance Score" value={score !== undefined ? `${score}%` : 'N/A'} />
        {issues && issues.length > 0 && (
          <div className="mt-2">
            <p className="text-sm font-medium text-gray-700">Issues:</p>
            <ul className="list-disc pl-5 space-y-1">
              {issues.map((issue: any, index: number) => (
                <li key={index} className={`text-sm ${issue.type === 'error' ? 'text-red-600' : 'text-yellow-600'}`}>
                  {issue.message}
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    );
  };

  const renderEligibilityResults = () => {
    if (!formData.eligibility_results) {
      return <p className="text-sm text-gray-500">Eligibility check not yet performed or results unavailable.</p>;
    }
    const { status, prior_authorization_required, benefits } = formData.eligibility_results;
    return (
      <div>
        <DetailItem label="Status" value={status} />
        <DetailItem label="Prior Authorization Required" value={prior_authorization_required ? 'Yes' : 'No'} />
        {benefits && (
          <>
            <DetailItem label="Copay" value={benefits.copay !== undefined ? `$${benefits.copay}` : 'N/A'} />
            <DetailItem label="Deductible" value={benefits.deductible !== undefined ? `$${benefits.deductible}` : 'N/A'} />
          </>
        )}
      </div>
    );
  };

  const renderClinicalOpportunities = () => {
    if (!formData.clinical_opportunities || formData.clinical_opportunities.length === 0) {
      return <p className="text-sm text-gray-500">No clinical opportunities selected.</p>;
    }
    return (
      <ul className="divide-y divide-gray-200">
        {formData.clinical_opportunities.map((opp: any, index: number) => (
          <li key={opp.id || index} className="py-3">
            <p className="text-sm font-medium text-gray-900">{opp.service_name} ({opp.hcpcs_code})</p>
            <p className="text-sm text-gray-500">Est. Reimbursement: ${opp.estimated_reimbursement?.toFixed(2)}</p>
          </li>
        ))}
      </ul>
    );
  };

  return (
    <div className="space-y-6">
      <div className="bg-white shadow-xl sm:rounded-lg">
        <div className="px-4 py-5 sm:p-6">
          <h3 className="text-xl font-semibold leading-7 text-gray-900 mb-6">
            Review and Submit Order
          </h3>

          <Section title="Patient & Order Details">
            <div className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
              <DetailItem label="Patient Name" value={`${formData.patient_api_input?.first_name || ''} ${formData.patient_api_input?.last_name || ''}`} />
              <DetailItem label="Date of Birth" value={formData.patient_api_input?.dob} />
              <DetailItem label="Member ID" value={formData.patient_api_input?.member_id} />
              <DetailItem label="Gender" value={formData.patient_api_input?.gender} />
              <DetailItem label="Facility" value={formData.facility_id ? `ID: ${formData.facility_id}` : 'Not Selected'} /> {/* TODO: Display facility name */}
              <DetailItem label="Expected Service Date" value={formData.expected_service_date} />
              <DetailItem label="Payer Name" value={formData.payer_name} />
              <DetailItem label="Wound Type" value={formData.wound_type} />
            </div>
          </Section>

          <Section title="Clinical Assessment Summary">
            {renderClinicalData()}
          </Section>
          
          <Section title="Selected Products">
            {renderSelectedProducts()}
          </Section>

          <Section title="MAC Validation">
             {renderMacValidationResults()}
          </Section>

          <Section title="Eligibility Check">
            {renderEligibilityResults()}
          </Section>

          <Section title="Selected Clinical Opportunities">
            {renderClinicalOpportunities()}
          </Section>
          
          <Section title="Provider Notes">
            <textarea
              id="provider_notes"
              name="provider_notes"
              rows={4}
              className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              placeholder="Add any relevant notes for this order..."
              value={formData.provider_notes || ''}
              onChange={(e) => updateFormData({ provider_notes: e.target.value })}
            />
          </Section>

          {/* Error Message */}
          {error && (
            <div className="mt-6 bg-red-50 border border-red-200 rounded-md p-4">
              <div className="flex">
                <AlertCircle className="h-5 w-5 text-red-500 mr-3" />
                <div>
                  <h3 className="text-sm font-medium text-red-800">Submission Error</h3>
                  <p className="mt-1 text-sm text-red-700">{error}</p>
                </div>
              </div>
            </div>
          )}

          {/* Submit Button */}
          <div className="mt-8 pt-5 border-t border-gray-200 flex justify-end">
            <button
              type="button"
              onClick={handleInternalSubmit}
              disabled={submitting}
              className="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
            >
              {submitting ? (
                <>
                  <Loader2 className="animate-spin -ml-1 mr-3 h-5 w-5" />
                  Submitting...
                </>
              ) : (
                <>
                  <Check className="-ml-1 mr-2 h-5 w-5" />
                  Confirm and Submit Order
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
