import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { ArrowLeft, ArrowRight, Check, Layout } from 'lucide-react';
import ProductSelectionStep from './Components/ProductSelectionStep';
import ClinicalAssessmentStep from './Components/ClinicalAssessmentStep';
import ValidationEligibilityStep from './Components/ValidationEligibilityStep';
import ClinicalOpportunitiesStep from './Components/ClinicalOpportunitiesStep';
import PatientInformationStep from './Components/PatientInformationStep';
import { SkinSubstituteChecklistInput } from '@/services/fhir/SkinSubstituteChecklistMapper';

interface Props {
  woundTypes: Record<string, string>;
  facilities: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  userFacilityId?: number;
  userSpecialty?: string;
}

interface PatientApiInput {
  first_name: string;
  last_name: string;
  dob: string;
  gender?: 'male' | 'female' | 'other' | 'unknown';
  member_id?: string;
  id?: string;
}

interface FormData {
  // Step 1: Patient Information
  patient_api_input: PatientApiInput;
  facility_id: number | null;
  expected_service_date: string;
  payer_name: string;
  payer_id: string;
  wound_type: string;

  // Step 2: Clinical Assessment - Now using SkinSubstituteChecklistInput
  clinical_data?: Partial<SkinSubstituteChecklistInput>;

  // Step 3: Product Selection
  selected_products: Array<{
    product_id: number;
    quantity: number;
    size?: string;
  }>;

  // Step 4: Validation Results
  mac_validation_results?: any;
  mac_validation_status?: string;
  eligibility_results?: any;
  eligibility_status?: string;

  // Step 5: Clinical Opportunities
  clinical_opportunities?: any[];

  // Step 6: Final Review
  provider_notes?: string;
}

// Define an initial state for the checklist data conforming to Partial<SkinSubstituteChecklistInput>
const initialSkinSubstituteChecklistData: Partial<SkinSubstituteChecklistInput> = {
  patientName: '', // Will be populated from patient_api_input or context
  dateOfBirth: '', // Will be populated from patient_api_input or context
  dateOfProcedure: '',
  diagnosis: {
    diabetes: { present: false },
    venousStasisUlcer: false,
    pressureUlcer: { present: false },
    location: '',
    ulcerLocation: '',
  },
  labResults: {
    cbcPerformed: false,
    treated: false,
  },
  wound: {
    // Provide defaults for required fields in wound, or make them optional in SkinSubstituteChecklistInput if appropriate
    location: '',
    depth: 'partial-thickness', // Default example
    duration: '',
    exposedStructures: [],
    measurements: { length: 0, width: 0, depth: 0, totalArea: 0 },
    infectionEvidence: false,
    necroticTissue: false,
    activeCharcot: false,
    hasMalignancy: false, // Corrected from suspectedMalignancy to match PHP DTO if it was hasMalignancy
  },
  circulation: { // Optional, but good to initialize its potentially used sub-fields if accessed directly
    dopplerWaveforms: { triphasicBiphasic: false, result: '', date: ''}
  },
  conservativeTreatment: {
    debridementPerformed: false,
    moistDressingsApplied: false,
    nonWeightBearing: false,
    pressureReducingFootwear: false, // This is a boolean in the DTO
    // footwearType is separate
    standardCompression: false,
    currentHbot: false,
    smokingStatus: 'non-smoker',
    receivingRadiationOrChemo: false,
    takingImmuneModulators: false,
    hasAutoimmuneDiagnosis: false,
  },
  // provider info likely handled at final submission, not part of this specific data object during step-by-step form fill
};

const ProductRequestCreate: React.FC<Props> = ({
  woundTypes,
  facilities,
  userFacilityId,
  userSpecialty = 'wound_care_specialty'
}) => {
  const { props } = usePage<any>();
  const userRole = props.userRole || 'provider';

  const [currentStep, setCurrentStep] = useState(1);
  const [formData, setFormData] = useState<FormData>({
    patient_api_input: {
      first_name: '',
      last_name: '',
      dob: '',
      // gender: 'unknown', // Handled by PatientInformationStep, defaults there
      // member_id: '', // Handled by PatientInformationStep
    },
    facility_id: userFacilityId || null,
    expected_service_date: '',
    payer_name: '',
    payer_id: '',
    wound_type: '',
    clinical_data: initialSkinSubstituteChecklistData, // Initialize with the structured default
    selected_products: [],
  });

  // 6-step MSC-MVP workflow
  const steps = [
    { id: 1, name: 'Patient Information', description: 'Capture patient demographics and payer information' },
    { id: 2, name: 'Clinical Assessment', description: 'Document wound-specific clinical data with dynamic forms' },
    { id: 3, name: 'Product Selection', description: 'Intelligent product recommendations based on clinical context' },
    { id: 4, name: 'Validation & Eligibility', description: 'Real-time MAC validation and insurance eligibility checking' },
    { id: 5, name: 'Clinical Opportunities', description: 'Identify additional billable services using COE' },
    { id: 6, name: 'Review & Submit', description: 'Final review with comprehensive summary' },
  ];

  const nextStep = () => {
    if (currentStep < steps.length) {
      setCurrentStep(currentStep + 1);
    }
  };

  const prevStep = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1);
    }
  };

  const updateFormData = (data: Partial<FormData>) => {
    setFormData(prev => ({ ...prev, ...data }));
  };

  const submitForm = () => {
    router.post('/product-requests', formData as any);
  };

  const isStepValid = (step: number): boolean => {
    switch (step) {
      case 1:
        return !!(
          formData.patient_api_input.first_name &&
          formData.patient_api_input.last_name &&
          formData.patient_api_input.dob &&
          formData.patient_api_input.member_id &&
          formData.facility_id &&
          formData.expected_service_date &&
          formData.payer_name &&
          formData.wound_type
        );
      case 2:
        // Check if required clinical sections are completed based on specialty
        const clinicalData = formData.clinical_data;
        if (userSpecialty === 'pulmonology') {
          return !!(clinicalData?.pulmonary_history && clinicalData?.wound_details && clinicalData?.tissue_oxygenation);
        } else if (userSpecialty === 'vascular_surgery') {
          return !!(clinicalData?.vascular_history && clinicalData?.wound_details && clinicalData?.vascular_evaluation);
        } else {
          return !!(clinicalData?.wound_details && clinicalData?.conservative_care);
        }
      case 3:
        return formData.selected_products.length > 0;
      case 4:
        // Validation step - require at least MAC validation to be attempted
        return !!(formData.mac_validation_status);
      case 5:
        return true; // Clinical opportunities are optional
      case 6:
        return true; // Final review
      default:
        return false;
    }
  };

  const renderStepContent = () => {
    switch (currentStep) {
      case 1:
        return <PatientInformationStep
          formData={formData}
          updateFormData={updateFormData}
          woundTypes={woundTypes}
          facilities={facilities}
        />;
      case 2:
        return <ClinicalAssessmentStep
          formData={formData}
          updateFormData={updateFormData}
          userSpecialty={userSpecialty}
        />;
      case 3:
        return <ProductSelectionStep
          formData={formData}
          updateFormData={updateFormData}
          userRole={userRole}
        />;
      case 4:
        return <ValidationEligibilityStep
          formData={formData}
          updateFormData={updateFormData}
          userSpecialty={userSpecialty}
        />;
      case 5:
        return <ClinicalOpportunitiesStep
          formData={formData}
          updateFormData={updateFormData}
        />;
      case 6:
        return <ReviewSubmitStep
          formData={formData}
          updateFormData={updateFormData}
        />;
      default:
        return null;
    }
  };

  return (
    <MainLayout title="New Product Request">
      <div className="min-h-screen bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div className="max-w-6xl mx-auto py-4 sm:py-6">
          {/* Header - Mobile Optimized */}
          <div className="mb-4 sm:mb-8">
            <h1 className="text-xl sm:text-2xl font-semibold text-gray-900">New Product Request</h1>
            <p className="mt-1 sm:mt-2 text-sm text-gray-600">
              Follow the 6-step MSC-MVP workflow to create a new product request with intelligent validation.
            </p>
            {userSpecialty && (
              <div className="mt-2">
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  {userSpecialty.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())} Specialty
                </span>
              </div>
            )}
          </div>

          {/* Progress Steps - Mobile Optimized */}
          <div className="mb-6 sm:mb-8">
            {/* Mobile Progress - Horizontal scroll for steps */}
            <div className="block sm:hidden">
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-900">Step {currentStep} of {steps.length}</span>
                <span className="text-sm text-gray-500">{Math.round((currentStep / steps.length) * 100)}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div
                  className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                  style={{ width: `${(currentStep / steps.length) * 100}%` }}
                ></div>
              </div>
              <div className="mt-2 text-sm font-medium text-gray-900">
                {steps[currentStep - 1]?.name}
              </div>
              <div className="text-xs text-gray-500">
                {steps[currentStep - 1]?.description}
              </div>
            </div>

            {/* Desktop Progress - Full step indicator */}
            <nav aria-label="Progress" className="hidden sm:block">
              <ol className="space-y-4 md:flex md:space-y-0 md:space-x-4 lg:space-x-8 overflow-x-auto">
                {steps.map((step) => (
                  <li key={step.id} className="md:flex-1 min-w-0">
                    <div
                      className={`group pl-4 py-2 flex flex-col border-l-4 hover:border-gray-300 md:pl-0 md:pt-4 md:pb-0 md:border-l-0 md:border-t-4 transition-colors ${
                        step.id < currentStep
                          ? 'border-green-600 md:border-green-600'
                          : step.id === currentStep
                          ? 'border-blue-600 md:border-blue-600'
                          : 'border-gray-200 md:border-gray-200'
                      }`}
                    >
                      <span className={`text-xs font-semibold tracking-wide uppercase ${
                        step.id < currentStep
                          ? 'text-green-600'
                          : step.id === currentStep
                          ? 'text-blue-600'
                          : 'text-gray-500'
                      }`}>
                        Step {step.id}
                      </span>
                      <span className={`text-sm font-medium ${
                        step.id <= currentStep ? 'text-gray-900' : 'text-gray-500'
                      }`}>
                        {step.name}
                      </span>
                      {step.id < currentStep && (
                        <div className="mt-1 flex items-center">
                          <Check className="h-4 w-4 text-green-600" />
                        </div>
                      )}
                    </div>
                  </li>
                ))}
              </ol>
            </nav>
          </div>

          {/* Step Content - Mobile Optimized Container */}
          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
            <div className="p-4 sm:p-6 lg:p-8">
              {renderStepContent()}
            </div>

            {/* Navigation Footer - Mobile Optimized */}
            <div className="border-t border-gray-200 px-4 py-3 sm:px-6 sm:py-4 bg-gray-50">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                <div className="flex space-x-3">
                  {currentStep > 1 && (
                    <button
                      type="button"
                      onClick={prevStep}
                      className="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                    >
                      <ArrowLeft className="h-4 w-4 mr-2" />
                      Previous
                    </button>
                  )}
                </div>

                <div className="flex space-x-3">
                  <button
                    type="button"
                    className="flex-1 sm:flex-none px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                  >
                    Save Draft
                  </button>

                  {currentStep < steps.length ? (
                    <button
                      type="button"
                      onClick={nextStep}
                      disabled={!isStepValid(currentStep)}
                      className="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                    >
                      Next
                      <ArrowRight className="h-4 w-4 ml-2" />
                    </button>
                  ) : (
                    <button
                      type="button"
                      onClick={submitForm}
                      disabled={!isStepValid(currentStep)}
                      className="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                    >
                      <Check className="h-4 w-4 mr-2" />
                      Submit Request
                    </button>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

// Remove or comment out the stubbed/inline PatientInformationStep and ReviewSubmitStep components
// if they exist below this point, as they should be imported from their own files.

// const PatientInformationStep: React.FC<any> = ({ ... }) => { ... };
// const ReviewSubmitStep: React.FC<any> = ({ ... }) => { ... };

export default ProductRequestCreate;
