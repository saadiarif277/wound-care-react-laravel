import React, { useState, useEffect } from 'react';
import { AlertCircle, ChevronRight, Info, Loader2 } from 'lucide-react';
import WoundCareAssessmentForm from './ClinicalAssessment/WoundCareAssessmentForm';
import PulmonaryWoundAssessmentForm from './ClinicalAssessment/PulmonaryWoundAssessmentForm';
import VascularAssessmentForm from './ClinicalAssessment/VascularAssessmentForm';
import { apiPost, handleApiResponse } from '@/lib/api';
import { SkinSubstituteChecklistInput } from '@/services/fhir/SkinSubstituteChecklistMapper';

// Define UI Section Keys for the SSP Checklist
const SSP_UI_SECTIONS = {
  DIAGNOSIS: 'ssp_checklist_diagnosis',
  LAB_RESULTS: 'ssp_checklist_lab_results',
  WOUND_DESCRIPTION: 'ssp_checklist_wound',
  CIRCULATION: 'ssp_checklist_circulation',
  CONSERVATIVE_TREATMENT: 'ssp_checklist_conservative',
  // CLINICAL_PHOTOS: 'ssp_checklist_photos', // If it becomes a distinct section for UI
} as const; // Use 'as const' for literal types

type SspUiSectionKey = typeof SSP_UI_SECTIONS[keyof typeof SSP_UI_SECTIONS];

export type GenericSectionKey =
  'wound_details' | 'conservative_care' | 'vascular_evaluation' | 'lab_results' |
  'pulmonary_history' | 'tissue_oxygenation' | 'coordinated_care' | 'clinical_photos';

export type UiSectionKey = SspUiSectionKey | GenericSectionKey;

interface ParentFormData {
  patient_api_input: any;
  facility_id: number | null;
  expected_service_date: string;
  payer_name: string;
  payer_id: string;
  wound_type: string;
  clinical_data?: Partial<SkinSubstituteChecklistInput>;
  legacy_generic_assessment_data?: Record<GenericSectionKey, any>; // For other assessment types
  [key: string]: any;
}

interface ClinicalAssessmentStepProps {
  formData: ParentFormData;
  updateFormData: (data: Partial<ParentFormData>) => void;
  userSpecialty?: string;
}

const ClinicalAssessmentStep: React.FC<ClinicalAssessmentStepProps> = ({
  formData,
  updateFormData,
  userSpecialty = 'wound_care_specialty'
}) => {

  const getAssessmentFormType = (): 'wound_care' | 'pulmonary_wound' | 'vascular' => {
    const isPulmonaryRelated = userSpecialty === 'pulmonology';
    if (isPulmonaryRelated) return 'pulmonary_wound';
    if (userSpecialty === 'vascular_surgery' || formData.wound_type === 'arterial_ulcer') return 'vascular';
    return 'wound_care';
  };
  const assessmentType = getAssessmentFormType();

  const getDefaultActiveUiSection = (): UiSectionKey => {
    if (assessmentType === 'wound_care') return SSP_UI_SECTIONS.DIAGNOSIS;
    if (assessmentType === 'pulmonary_wound') return 'pulmonary_history';
    return SSP_UI_SECTIONS.DIAGNOSIS;
  };

  const [activeUiSection, setActiveUiSection] = useState<UiSectionKey>(getDefaultActiveUiSection());
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});
  const [isValidating, setIsValidating] = useState(false);

  useEffect(() => {
    setActiveUiSection(getDefaultActiveUiSection());
  }, [assessmentType]);

  const getSections = (): Array<{ id: UiSectionKey; label: string; required: boolean }> => {
    switch (assessmentType) {
      case 'wound_care':
        return [
          { id: SSP_UI_SECTIONS.DIAGNOSIS, label: 'Diagnosis (SSP)', required: true },
          { id: SSP_UI_SECTIONS.LAB_RESULTS, label: 'Lab Results (SSP)', required: true },
          { id: SSP_UI_SECTIONS.WOUND_DESCRIPTION, label: 'Wound Description (SSP)', required: true },
          { id: SSP_UI_SECTIONS.CIRCULATION, label: 'Circulation (SSP)', required: true },
          { id: SSP_UI_SECTIONS.CONSERVATIVE_TREATMENT, label: 'Conservative Measures (SSP)', required: true },
          // { id: SSP_UI_SECTIONS.CLINICAL_PHOTOS, label: 'Clinical Photos', required: false },
        ];
      case 'pulmonary_wound':
        return [
          { id: 'pulmonary_history', label: 'Pulmonary History', required: true },
          { id: 'wound_details', label: 'Wound Assessment (Pulm)', required: true },
          { id: 'tissue_oxygenation', label: 'Tissue Oxygenation', required: true },
          { id: 'conservative_care', label: 'Conservative Care (Pulm)', required: true },
          { id: 'coordinated_care', label: 'Coordinated Care Planning', required: false },
        ];
      case 'vascular':
        return [
          { id: 'wound_details', label: 'Wound Assessment (Vasc)', required: true },
          { id: 'vascular_evaluation', label: 'Vascular Studies', required: true },
          { id: 'conservative_care', label: 'Conservative Care (Vasc)', required: true },
          { id: 'lab_results', label: 'Laboratory Values (Vasc)', required: false },
        ];
      default:
        return [];
    }
  };

  const sections = getSections();

  const validateSection = async (uiSectionKey: UiSectionKey) => {
    setIsValidating(true);
    try {
      let dataToValidate = {};
      const sectionKeyForApi = String(uiSectionKey);

      if (assessmentType === 'wound_care') {
        dataToValidate = formData.clinical_data || {};
      } else {
        dataToValidate = (formData as any)[`legacy_${uiSectionKey as string}`] || {};
      }

      const response = await apiPost('/api/v1/validation-builder/validate-section', {
        section: sectionKeyForApi,
        data: dataToValidate,
        wound_type: formData.wound_type,
        assessment_type: assessmentType,
      });
      const result = await handleApiResponse(response);
      if (result.errors) {
        setValidationErrors(prev => ({ ...prev, [sectionKeyForApi]: result.errors }));
      } else {
        setValidationErrors(prev => { const newErrors = { ...prev }; delete newErrors[sectionKeyForApi]; return newErrors; });
      }
    } catch (error) { console.error('Validation error:', error); } finally { setIsValidating(false); }
  };

  const handleChecklistInputChange = (
    fieldName: keyof SkinSubstituteChecklistInput,
    value: any
  ) => {
    updateFormData({
      clinical_data: {
        ...(formData.clinical_data || {}),
        [fieldName]: value,
      } as Partial<SkinSubstituteChecklistInput>,
    });
  };

  const getSectionCompletion = (uiSectionKey: UiSectionKey) => {
    if (assessmentType === 'wound_care') {
      const dataKey = uiSectionKey as SspUiSectionKey;
      const sectionData = (formData.clinical_data && dataKey in formData.clinical_data) ? formData.clinical_data[dataKey] : null;

      if (typeof sectionData !== 'object' || sectionData === null) return 0;

      const keys = Object.keys(sectionData as Record<string, any>); // Cast to Record for Object.keys
      if (keys.length === 0) return 0;

      let filledCount = 0;
      for (const key of keys) {
        const value = (sectionData as Record<string, any>)[key];
        let isFilled = true;
        if (value === null || value === '' || value === undefined) isFilled = false;
        if (Array.isArray(value) && value.length === 0) isFilled = false;
        if (typeof value === 'object' && !Array.isArray(value) && value !== null && Object.keys(value).length === 0) isFilled = false;
        if (isFilled) filledCount++;
      }
      return Math.round((filledCount / keys.length) * 100);
    }
    return 0;
  };

  const renderAssessmentForm = () => {
    if (assessmentType === 'wound_care') {
      return (
        <WoundCareAssessmentForm
          formData={formData.clinical_data || {}}
          activeSection={activeUiSection as SspUiSectionKey}
          handleChange={handleChecklistInputChange}
          validationErrors={validationErrors}
          parentWoundType={formData.wound_type}
        />
      );
    } else if (assessmentType === 'pulmonary_wound' || assessmentType === 'vascular') {
        const currentGenericSectionData = (formData as any)[`legacy_${activeUiSection as string}`] || {};
        const FormComponent = assessmentType === 'pulmonary_wound' ? PulmonaryWoundAssessmentForm : VascularAssessmentForm;
        return (
          <FormComponent
            formData={currentGenericSectionData}
            updateClinicalData={(data: any) => updateFormData({ [`legacy_${activeUiSection as string}`]: data }) }
            activeSection={activeUiSection as string}
            validationErrors={validationErrors[activeUiSection as string] ? {[activeUiSection as string]: validationErrors[activeUiSection as string]} : {}}
          />
        );
    }
    return <div>Select an assessment type or section.</div>;
  };

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-lg sm:text-xl font-semibold text-gray-900 mb-4">Clinical Assessment</h2>
        <p className="text-sm text-gray-600 mb-6">
          Complete the clinical assessment based on your specialty and the patient's wound type.
          Required sections must be completed before proceeding.
        </p>
      </div>

      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h3 className="text-sm font-medium text-blue-900">
              {assessmentType === 'pulmonary_wound' && 'Pulmonary & Wound Care Assessment'}
              {assessmentType === 'vascular' && 'Vascular Surgery Assessment'}
              {assessmentType === 'wound_care' && 'Skin Substitute Pre-Application Checklist'}
            </h3>
            <p className="text-sm text-blue-700 mt-1">
              This assessment has been selected based on your specialty and the patient's condition.
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div className="lg:col-span-1">
          <nav className="space-y-1">
            {sections.map((section) => {
              const completion = getSectionCompletion(section.id as UiSectionKey);
              const hasErrors = validationErrors[section.id as string]?.length > 0;
              return (
                <button
                  key={section.id}
                  onClick={() => {
                    setActiveUiSection(section.id as UiSectionKey);
                    validateSection(section.id as UiSectionKey);
                  }}
                  className={`w-full text-left px-3 py-2 rounded-md transition-colors ${
                    activeUiSection === section.id
                      ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-700'
                      : 'text-gray-600 hover:bg-gray-50'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <span className="text-sm font-medium">{section.label}</span>
                      {section.required && (
                        <span className="text-xs text-red-500 ml-1">*</span>
                      )}
                    </div>
                    <div className="flex items-center space-x-2">
                      {hasErrors && (
                        <AlertCircle className="h-4 w-4 text-red-500" />
                      )}
                      {completion > 0 && (
                        <span className={`text-xs ${
                          completion === 100 ? 'text-green-600' : 'text-gray-500'
                        }`}>
                          {completion}%
                        </span>
                      )}
                      <ChevronRight className={`h-4 w-4 ${
                        activeUiSection === section.id ? 'text-blue-700' : 'text-gray-400'
                      }`} />
                    </div>
                  </div>
                </button>
              );
            })}
          </nav>

          <div className="mt-6 p-4 bg-gray-50 rounded-lg">
            <h4 className="text-sm font-medium text-gray-700 mb-2">Overall Progress</h4>
            <div className="space-y-2">
              {sections.map((section) => {
                const completion = getSectionCompletion(section.id as UiSectionKey);
                return (
                  <div key={section.id}>
                    <div className="flex justify-between text-xs text-gray-600 mb-1">
                      <span>{section.label}</span>
                      <span>{completion}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className={`h-2 rounded-full transition-all duration-300 ${
                          completion === 100 ? 'bg-green-600' : 'bg-blue-600'
                        }`}
                        style={{ width: `${completion}%` }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        <div className="lg:col-span-3">
          <div className="bg-white rounded-lg border border-gray-200 p-6 relative">
            {isValidating && (
              <div className="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10 rounded-lg">
                <Loader2 className="h-8 w-8 text-blue-600 animate-spin" />
              </div>
            )}
            {renderAssessmentForm()}
          </div>
        </div>
      </div>

      <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h3 className="text-sm font-medium text-yellow-900">MSC Assist Available</h3>
            <p className="text-sm text-yellow-700 mt-1">
              Need help? MSC Assist can provide real-time guidance for completing the clinical assessment
              based on your wound type and payer requirements.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ClinicalAssessmentStep;
