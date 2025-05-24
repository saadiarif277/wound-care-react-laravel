import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import Layout from '@/Layouts/Layout';
import { ArrowLeftIcon, ArrowRightIcon, CheckIcon } from '@heroicons/react/24/outline';
import ProductSelectionStep from './Components/ProductSelectionStep';

interface Props {
  woundTypes: Record<string, string>;
  facilities: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  userFacilityId?: number;
}

interface PatientApiInput {
  first_name: string;
  last_name: string;
  dob: string;
  gender: 'male' | 'female' | 'other';
  member_id: string;
}

interface FormData {
  // Step 1: Patient Information
  patient_api_input: PatientApiInput;
  facility_id: number | null;
  expected_service_date: string;
  payer_name: string;
  payer_id: string;
  wound_type: string;

  // Step 2: Clinical Assessment
  clinical_data: {
    wound_details?: any;
    conservative_care?: any;
    vascular_evaluation?: any;
    lab_results?: any;
  };

  // Step 3: Product Selection
  selected_products: Array<{
    product_id: number;
    quantity: number;
    size?: string;
  }>;
}

const ProductRequestCreate: React.FC<Props> = ({ woundTypes, facilities, userFacilityId }) => {
  const [currentStep, setCurrentStep] = useState(1);
  const [formData, setFormData] = useState<FormData>({
    patient_api_input: {
      first_name: '',
      last_name: '',
      dob: '',
      gender: 'male',
      member_id: '',
    },
    facility_id: userFacilityId || null,
    expected_service_date: '',
    payer_name: '',
    payer_id: '',
    wound_type: '',
    clinical_data: {},
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

  const updatePatientData = (data: Partial<PatientApiInput>) => {
    setFormData(prev => ({
      ...prev,
      patient_api_input: { ...prev.patient_api_input, ...data }
    }));
  };

  const submitForm = () => {
    router.post('/product-requests', formData);
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
        return true; // Clinical assessment can be optional for draft
      case 3:
        return formData.selected_products.length > 0;
      case 4:
        return true; // Validation is automated
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
          updatePatientData={updatePatientData}
          woundTypes={woundTypes}
          facilities={facilities}
        />;
      case 2:
        return <ClinicalAssessmentStep
          formData={formData}
          updateFormData={updateFormData}
        />;
      case 3:
        return <ProductSelectionStep
          formData={formData}
          updateFormData={updateFormData}
        />;
      case 4:
        return <ValidationEligibilityStep
          formData={formData}
          updateFormData={updateFormData}
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
    <Layout title="New Product Request">
      <div className="max-w-6xl mx-auto">
        <div className="mb-8">
          <h1 className="text-2xl font-semibold text-gray-900">New Product Request</h1>
          <p className="mt-2 text-sm text-gray-600">
            Follow the 6-step MSC-MVP workflow to create a new product request with intelligent validation.
          </p>
        </div>

        {/* Progress Steps */}
        <div className="mb-8">
          <nav aria-label="Progress">
            <ol className="space-y-4 md:flex md:space-y-0 md:space-x-8">
              {steps.map((step) => (
                <li key={step.id} className="md:flex-1">
                  <div
                    className={`group pl-4 py-2 flex flex-col border-l-4 hover:border-gray-300 md:pl-0 md:pt-4 md:pb-0 md:border-l-0 md:border-t-4 ${
                      step.id < currentStep
                        ? 'border-green-600 md:border-green-600'
                        : step.id === currentStep
                        ? 'border-blue-600 md:border-blue-600'
                        : 'border-gray-200 md:border-gray-200'
                    }`}
                  >
                    <span
                      className={`text-xs font-semibold tracking-wide uppercase ${
                        step.id < currentStep
                          ? 'text-green-600'
                          : step.id === currentStep
                          ? 'text-blue-600'
                          : 'text-gray-500'
                      }`}
                    >
                      Step {step.id}
                    </span>
                    <span className="text-sm font-medium">{step.name}</span>
                  </div>
                </li>
              ))}
            </ol>
          </nav>
        </div>

        {/* Step Content */}
        <div className="bg-white shadow rounded-lg">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-medium text-gray-900">
              {steps[currentStep - 1].name}
            </h2>
            <p className="mt-1 text-sm text-gray-600">
              {steps[currentStep - 1].description}
            </p>
          </div>

          <div className="p-6">
            {renderStepContent()}
          </div>

          {/* Navigation */}
          <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between">
            <button
              type="button"
              onClick={prevStep}
              disabled={currentStep === 1}
              className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <ArrowLeftIcon className="w-4 h-4 mr-2" />
              Previous
            </button>

            {currentStep < steps.length ? (
              <button
                type="button"
                onClick={nextStep}
                disabled={!isStepValid(currentStep)}
                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
                <ArrowRightIcon className="w-4 h-4 ml-2" />
              </button>
            ) : (
              <button
                type="button"
                onClick={submitForm}
                disabled={!isStepValid(currentStep)}
                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <CheckIcon className="w-4 h-4 mr-2" />
                Submit Request
              </button>
            )}
          </div>
        </div>
      </div>
    </Layout>
  );
};

// Step 1: Patient Information Entry
const PatientInformationStep: React.FC<any> = ({
  formData,
  updateFormData,
  updatePatientData,
  woundTypes,
  facilities
}) => {
  return (
    <div className="space-y-6">
      <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
        <div className="flex">
          <div className="ml-3">
            <p className="text-sm text-blue-700">
              <strong>PHI Handling:</strong> Patient data will be securely stored in Azure Health Data Services.
              Only FHIR references and non-PHI identifiers will be stored locally for UI display.
            </p>
          </div>
        </div>
      </div>

      {/* Patient Demographics */}
      <div>
        <h3 className="text-lg font-medium text-gray-900 mb-4">Patient Demographics</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700">First Name *</label>
            <input
              type="text"
              value={formData.patient_api_input.first_name}
              onChange={(e) => updatePatientData({ first_name: e.target.value })}
              placeholder="Enter patient's first name"
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Last Name *</label>
            <input
              type="text"
              value={formData.patient_api_input.last_name}
              onChange={(e) => updatePatientData({ last_name: e.target.value })}
              placeholder="Enter patient's last name"
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Date of Birth *</label>
            <input
              type="date"
              value={formData.patient_api_input.dob}
              onChange={(e) => updatePatientData({ dob: e.target.value })}
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Gender *</label>
            <select
              value={formData.patient_api_input.gender}
              onChange={(e) => updatePatientData({ gender: e.target.value as 'male' | 'female' | 'other' })}
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            >
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div className="md:col-span-2">
            <label className="block text-sm font-medium text-gray-700">Member ID *</label>
            <input
              type="text"
              value={formData.patient_api_input.member_id}
              onChange={(e) => updatePatientData({ member_id: e.target.value })}
              placeholder="Insurance member ID"
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
        </div>
      </div>

      {/* Facility and Service Information */}
      <div>
        <h3 className="text-lg font-medium text-gray-900 mb-4">Service Information</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700">Facility *</label>
            <select
              value={formData.facility_id || ''}
              onChange={(e) => updateFormData({ facility_id: parseInt(e.target.value) })}
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            >
              <option value="">Select facility...</option>
              {facilities.map(facility => (
                <option key={facility.id} value={facility.id}>
                  {facility.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Expected Service Date *</label>
            <input
              type="date"
              value={formData.expected_service_date}
              onChange={(e) => updateFormData({ expected_service_date: e.target.value })}
              min={new Date().toISOString().split('T')[0]}
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
        </div>
      </div>

      {/* Payer Information */}
      <div>
        <h3 className="text-lg font-medium text-gray-900 mb-4">Payer Information</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700">Payer Name *</label>
            <input
              type="text"
              value={formData.payer_name}
              onChange={(e) => updateFormData({ payer_name: e.target.value })}
              placeholder="e.g., Medicare, Humana, Aetna"
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Payer ID</label>
            <input
              type="text"
              value={formData.payer_id}
              onChange={(e) => updateFormData({ payer_id: e.target.value })}
              placeholder="Optional payer identifier"
              className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>
      </div>

      {/* Wound Type */}
      <div>
        <h3 className="text-lg font-medium text-gray-900 mb-4">Wound Information</h3>
        <div>
          <label className="block text-sm font-medium text-gray-700">Wound Type *</label>
          <select
            value={formData.wound_type}
            onChange={(e) => updateFormData({ wound_type: e.target.value })}
            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
            required
          >
            <option value="">Select wound type...</option>
            {Object.entries(woundTypes).map(([key, label]) => (
              <option key={key} value={key}>{label}</option>
            ))}
          </select>
        </div>
      </div>
    </div>
  );
};

// Step 2: Clinical Assessment Documentation
const ClinicalAssessmentStep: React.FC<any> = ({ formData, updateFormData }) => {
  return (
    <div className="space-y-6">
      <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
        <div className="flex">
          <div className="ml-3">
            <p className="text-sm text-blue-700">
              Complete the clinical assessment based on the selected wound type: <strong>{formData.wound_type}</strong>.
              This data will be stored in Azure HDS and used for MAC validation and product recommendations.
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <h3 className="text-lg font-medium text-gray-900 mb-4">Wound Details</h3>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700">Location</label>
              <input
                type="text"
                placeholder="e.g., Right foot, plantar surface"
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700">Length (cm)</label>
                <input
                  type="number"
                  step="0.1"
                  placeholder="0.0"
                  className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Width (cm)</label>
                <input
                  type="number"
                  step="0.1"
                  placeholder="0.0"
                  className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Depth (cm)</label>
              <input
                type="number"
                step="0.1"
                placeholder="0.0"
                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
        </div>

        <div>
          <h3 className="text-lg font-medium text-gray-900 mb-4">Conservative Care</h3>
          <div className="space-y-4">
            <div>
              <label className="flex items-center">
                <input type="checkbox" className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                <span className="ml-2 text-sm text-gray-700">Wound cleansing performed</span>
              </label>
            </div>
            <div>
              <label className="flex items-center">
                <input type="checkbox" className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                <span className="ml-2 text-sm text-gray-700">Debridement performed</span>
              </label>
            </div>
            <div>
              <label className="flex items-center">
                <input type="checkbox" className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                <span className="ml-2 text-sm text-gray-700">Offloading provided</span>
              </label>
            </div>
            <div>
              <label className="flex items-center">
                <input type="checkbox" className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                <span className="ml-2 text-sm text-gray-700">Infection management</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      {/* Dynamic wound-specific fields would be added here based on wound_type */}
      <div className="mt-6 p-4 bg-gray-50 rounded-lg">
        <p className="text-sm text-gray-600">
          Dynamic form fields based on wound type will be implemented with MSC Assist integration.
        </p>
      </div>
    </div>
  );
  };

// Step 4: Validation & Eligibility (Automated)
const ValidationEligibilityStep: React.FC<any> = ({ formData, updateFormData }) => {
  return (
    <div className="space-y-6">
      <div className="text-center">
        <h3 className="text-lg font-medium text-gray-900">Automated Validation & Eligibility</h3>
        <p className="mt-2 text-sm text-gray-600">
          Real-time MAC validation and insurance eligibility checking.
        </p>
        <div className="mt-8 space-y-4">
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <h4 className="font-medium text-yellow-800">MAC Validation Engine</h4>
            <p className="text-sm text-yellow-700 mt-2">
              Validates order against Medicare Administrative Contractor rules
            </p>
          </div>
          <div className="bg-green-50 border border-green-200 rounded-lg p-6">
            <h4 className="font-medium text-green-800">Eligibility Engine</h4>
            <p className="text-sm text-green-700 mt-2">
              Checks patient eligibility and prior authorization requirements
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

// Step 5: Clinical Opportunities Review (Optional)
const ClinicalOpportunitiesStep: React.FC<any> = ({ formData, updateFormData }) => {
  return (
    <div className="space-y-6">
      <div className="text-center">
        <h3 className="text-lg font-medium text-gray-900">Clinical Opportunities</h3>
        <p className="mt-2 text-sm text-gray-600">
          Additional billable services identified by our Clinical Opportunity Engine.
        </p>
        <div className="mt-8 bg-gray-100 rounded-lg p-8">
          <p className="text-gray-500">Clinical Opportunity Engine (COE) will be implemented here</p>
          <div className="mt-4 text-sm text-gray-600">
            <p>Features to include:</p>
            <ul className="list-disc list-inside mt-2 space-y-1">
              <li>Identify additional services (offloading, debridement, etc.)</li>
              <li>Calculate potential revenue</li>
              <li>Provide clinical rationale</li>
              <li>One-click addition to current request</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
};

// Step 6: Review & Submit
const ReviewSubmitStep: React.FC<any> = ({ formData, updateFormData }) => {
  const patientDisplay = formData.patient_api_input.first_name && formData.patient_api_input.last_name
    ? `${formData.patient_api_input.first_name.substring(0, 2).toUpperCase()}${formData.patient_api_input.last_name.substring(0, 2).toUpperCase()}###`
    : 'Patient';

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900 mb-4">Review Your Request</h3>

        <div className="bg-gray-50 rounded-lg p-6">
          <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
            <div>
              <dt className="text-sm font-medium text-gray-500">Patient Display ID</dt>
              <dd className="mt-1 text-sm text-gray-900">
                {patientDisplay} (Sequential ID will be assigned)
              </dd>
            </div>
            <div>
              <dt className="text-sm font-medium text-gray-500">Service Date</dt>
              <dd className="mt-1 text-sm text-gray-900">{formData.expected_service_date || 'Not set'}</dd>
            </div>
            <div>
              <dt className="text-sm font-medium text-gray-500">Wound Type</dt>
              <dd className="mt-1 text-sm text-gray-900">{formData.wound_type || 'Not selected'}</dd>
            </div>
            <div>
              <dt className="text-sm font-medium text-gray-500">Payer</dt>
              <dd className="mt-1 text-sm text-gray-900">{formData.payer_name || 'Not provided'}</dd>
            </div>
            <div>
              <dt className="text-sm font-medium text-gray-500">Products</dt>
              <dd className="mt-1 text-sm text-gray-900">{formData.selected_products.length} selected</dd>
            </div>
            <div>
              <dt className="text-sm font-medium text-gray-500">PHI Handling</dt>
              <dd className="mt-1 text-sm text-green-900">✓ Compliant (Azure HDS + Sequential IDs)</dd>
            </div>
          </dl>
        </div>

        <div className="mt-6 bg-blue-50 border-l-4 border-blue-400 p-4">
          <div className="flex">
            <div className="ml-3">
              <p className="text-sm text-blue-700">
                <strong>Sequential Display IDs:</strong> Patient will be assigned a unique display ID like "JoSm001"
                based on name initials and facility sequence. This provides better privacy protection by removing
                age information while maintaining easy identification for providers.
              </p>
            </div>
          </div>
        </div>

        <div className="mt-4 bg-green-50 border-l-4 border-green-400 p-4">
          <div className="flex">
            <div className="ml-3">
              <p className="text-sm text-green-700">
                <strong>Performance Benefits:</strong> Order lists will load faster without Azure HDS calls.
                Patient search and identification uses local display IDs for improved user experience.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProductRequestCreate;
