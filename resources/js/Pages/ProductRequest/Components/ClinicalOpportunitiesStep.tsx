import React, { useState, useEffect } from 'react';
import { DollarSign, Plus, Info, CheckCircle, X } from 'lucide-react';
import api from '@/lib/api';

interface ClinicalOpportunitiesStepProps {
  formData: any;
  updateFormData: (data: any) => void;
}

interface ClinicalOpportunity {
  id: string;
  service_name: string;
  hcpcs_code: string;
  description: string;
  clinical_rationale: string;
  estimated_reimbursement: number;
  frequency: string;
  requirements: string[];
  contraindications?: string[];
  selected: boolean;
}

const ClinicalOpportunitiesStep: React.FC<ClinicalOpportunitiesStepProps> = ({
  formData,
  updateFormData
}) => {
  const [opportunities, setOpportunities] = useState<ClinicalOpportunity[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [selectedOpportunities, setSelectedOpportunities] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  // Scan for clinical opportunities based on assessment
  const scanOpportunities = async () => {
    try {
      setIsLoading(true);
      setError(null);

              const response = await api.post('/api/v1/clinical-opportunities/scan', {
        clinical_data: formData.clinical_data,
        wound_type: formData.wound_type,
        patient_data: formData.patient_api_input,
        selected_products: formData.selected_products,
      });

              const result = response;

      if (result.success) {
        setOpportunities(result.data.opportunities || []);
      } else {
        // Mock opportunities for demo
        setOpportunities(getMockOpportunities());
      }
    } catch (err: any) {
      console.error('Clinical opportunities scan error:', err);
      // Mock opportunities for demo
      setOpportunities(getMockOpportunities());
    } finally {
      setIsLoading(false);
    }
  };

  // Mock opportunities based on wound type and clinical data
  const getMockOpportunities = (): ClinicalOpportunity[] => {
    const mockOpportunities: ClinicalOpportunity[] = [];

    // DFU-specific opportunities
    if (formData.wound_type === 'diabetic_foot_ulcer') {
      mockOpportunities.push({
        id: 'offloading_dme',
        service_name: 'Offloading DME',
        hcpcs_code: 'L4631',
        description: 'Toe filler, foam or silicone gel, each',
        clinical_rationale: 'Patient has diabetic foot ulcer requiring pressure redistribution for optimal healing.',
        estimated_reimbursement: 125.00,
        frequency: 'As needed',
        requirements: ['DFU diagnosis', 'Pressure point identification', 'Provider prescription'],
        selected: false
      });

      if (formData.clinical_data?.lab_results?.hba1c > 7) {
        mockOpportunities.push({
          id: 'diabetes_education',
          service_name: 'Diabetes Self-Management Training',
          hcpcs_code: 'G0108',
          description: 'Diabetes outpatient self-management training services',
          clinical_rationale: 'HbA1c > 7% indicates need for enhanced diabetes management education.',
          estimated_reimbursement: 85.00,
          frequency: 'Initial + follow-up',
          requirements: ['Diabetes diagnosis', 'Provider referral', 'Individual or group setting'],
          selected: false
        });
      }
    }

    // Vascular-related opportunities
    if (formData.clinical_data?.vascular_evaluation?.abi_right < 0.9 ||
        formData.clinical_data?.vascular_evaluation?.abi_left < 0.9) {
      mockOpportunities.push({
        id: 'vascular_study',
        service_name: 'Arterial Duplex Study',
        hcpcs_code: '93922',
        description: 'Limited bilateral noninvasive physiologic studies of upper or lower extremity arteries',
        clinical_rationale: 'ABI < 0.9 indicates peripheral arterial disease requiring further vascular assessment.',
        estimated_reimbursement: 245.00,
        frequency: 'As clinically indicated',
        requirements: ['Abnormal ABI', 'Vascular symptoms', 'Provider order'],
        selected: false
      });
    }

    // Infection-related opportunities
    if (formData.clinical_data?.wound_details?.infection_signs?.length > 0) {
      mockOpportunities.push({
        id: 'wound_culture',
        service_name: 'Wound Culture',
        hcpcs_code: '87070',
        description: 'Culture, bacterial; any source except blood, anaerobic',
        clinical_rationale: 'Signs of infection present requiring culture to guide antibiotic therapy.',
        estimated_reimbursement: 35.00,
        frequency: 'As needed',
        requirements: ['Signs of infection', 'Sterile collection technique', 'Provider order'],
        selected: false
      });
    }

    // Debridement opportunities
    if (formData.clinical_data?.wound_details?.tissue_type === 'slough' ||
        formData.clinical_data?.wound_details?.tissue_type === 'eschar') {
      mockOpportunities.push({
        id: 'debridement',
        service_name: 'Wound Debridement',
        hcpcs_code: '11042',
        description: 'Debridement, subcutaneous tissue; first 20 sq cm or less',
        clinical_rationale: 'Non-viable tissue present requiring debridement for optimal wound healing.',
        estimated_reimbursement: 180.00,
        frequency: 'As needed',
        requirements: ['Non-viable tissue', 'Appropriate setting', 'Provider qualification'],
        selected: false
      });
    }

    return mockOpportunities;
  };

  // Auto-scan when component mounts
  useEffect(() => {
    if (formData.clinical_data && Object.keys(formData.clinical_data).length > 0) {
      scanOpportunities();
    }
  }, []);

  // Toggle opportunity selection
  const toggleOpportunity = (opportunityId: string) => {
    const newSelected = selectedOpportunities.includes(opportunityId)
      ? selectedOpportunities.filter(id => id !== opportunityId)
      : [...selectedOpportunities, opportunityId];

    setSelectedOpportunities(newSelected);

    // Update form data
    const selectedOpportunityData = opportunities.filter(opp =>
      newSelected.includes(opp.id)
    );

    updateFormData({
      clinical_opportunities: selectedOpportunityData
    });
  };

  // Calculate total estimated value
  const totalEstimatedValue = opportunities
    .filter(opp => selectedOpportunities.includes(opp.id))
    .reduce((sum, opp) => sum + opp.estimated_reimbursement, 0);

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-lg sm:text-xl font-semibold text-gray-900 mb-4">Clinical Opportunities</h2>
        <p className="text-sm text-gray-600 mb-6">
          Review additional billable services identified based on your clinical assessment.
          These opportunities can improve patient outcomes while maximizing appropriate reimbursement.
        </p>
      </div>

      {/* Information Banner */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h3 className="text-sm font-medium text-blue-900">Clinical Opportunity Engine</h3>
            <p className="text-sm text-blue-700 mt-1">
              Our AI analyzes your clinical documentation to identify evidence-based additional services
              that may benefit the patient and are supported by the clinical findings.
            </p>
          </div>
        </div>
      </div>

      {/* Scan Button */}
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Identified Opportunities</h3>
        <button
          onClick={scanOpportunities}
          disabled={isLoading}
          className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
        >
          {isLoading ? (
            <>
              <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-2"></div>
              Scanning...
            </>
          ) : (
            <>
              <Plus className="h-4 w-4 mr-2" />
              Rescan Opportunities
            </>
          )}
        </button>
      </div>

      {/* Opportunities List */}
      {opportunities.length > 0 ? (
        <div className="space-y-4">
          {opportunities.map((opportunity) => (
            <div
              key={opportunity.id}
              className={`border rounded-lg p-4 transition-colors ${
                selectedOpportunities.includes(opportunity.id)
                  ? 'border-green-300 bg-green-50'
                  : 'border-gray-200 bg-white hover:border-gray-300'
              }`}
            >
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      checked={selectedOpportunities.includes(opportunity.id)}
                      onChange={() => toggleOpportunity(opportunity.id)}
                      className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-3"
                    />
                    <div>
                      <h4 className="text-sm font-medium text-gray-900">
                        {opportunity.service_name}
                      </h4>
                      <p className="text-xs text-gray-500">
                        {opportunity.hcpcs_code} • {opportunity.frequency}
                      </p>
                    </div>
                  </div>

                  <p className="text-sm text-gray-700 mt-2 ml-7">
                    {opportunity.description}
                  </p>

                  <div className="mt-3 ml-7">
                    <h5 className="text-xs font-medium text-gray-900 mb-1">Clinical Rationale:</h5>
                    <p className="text-xs text-gray-600">{opportunity.clinical_rationale}</p>
                  </div>

                  {/* Requirements */}
                  <div className="mt-3 ml-7">
                    <h5 className="text-xs font-medium text-gray-900 mb-1">Requirements:</h5>
                    <ul className="text-xs text-gray-600 list-disc list-inside">
                      {opportunity.requirements.map((req, index) => (
                        <li key={index}>{req}</li>
                      ))}
                    </ul>
                  </div>

                  {/* Contraindications */}
                  {opportunity.contraindications && opportunity.contraindications.length > 0 && (
                    <div className="mt-2 ml-7">
                      <h5 className="text-xs font-medium text-red-900 mb-1">Contraindications:</h5>
                      <ul className="text-xs text-red-600 list-disc list-inside">
                        {opportunity.contraindications.map((contra, index) => (
                          <li key={index}>{contra}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>

                <div className="ml-4 text-right">
                  <div className="flex items-center text-green-600">
                    <DollarSign className="h-4 w-4" />
                    <span className="text-sm font-medium">
                      {opportunity.estimated_reimbursement.toFixed(2)}
                    </span>
                  </div>
                  <p className="text-xs text-gray-500 mt-1">Est. reimbursement</p>
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="text-center py-8">
          <div className="text-gray-400 mb-4">
            <CheckCircle className="h-12 w-12 mx-auto" />
          </div>
          <h3 className="text-sm font-medium text-gray-900 mb-2">No Additional Opportunities Identified</h3>
          <p className="text-sm text-gray-500">
            Based on the current clinical assessment, no additional billable services were identified.
            This may change as you complete more of the clinical documentation.
          </p>
        </div>
      )}

      {/* Summary */}
      {selectedOpportunities.length > 0 && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
          <div className="flex items-center justify-between">
            <div>
              <h4 className="text-sm font-medium text-green-900">
                Selected Opportunities ({selectedOpportunities.length})
              </h4>
              <p className="text-sm text-green-700 mt-1">
                These services will be added to your order for consideration.
              </p>
            </div>
            <div className="text-right">
              <div className="flex items-center text-green-600">
                <DollarSign className="h-5 w-5" />
                <span className="text-lg font-semibold">
                  {totalEstimatedValue.toFixed(2)}
                </span>
              </div>
              <p className="text-xs text-green-600">Total estimated value</p>
            </div>
          </div>
        </div>
      )}

      {/* Action Buttons */}
      <div className="flex justify-between items-center pt-4">
        <button
          onClick={() => {
            setSelectedOpportunities([]);
            updateFormData({ clinical_opportunities: [] });
          }}
          className="text-sm text-gray-500 hover:text-gray-700"
        >
          Clear All Selections
        </button>

        <div className="text-sm text-gray-500">
          {selectedOpportunities.length} of {opportunities.length} opportunities selected
        </div>
      </div>
    </div>
  );
};

export default ClinicalOpportunitiesStep;
