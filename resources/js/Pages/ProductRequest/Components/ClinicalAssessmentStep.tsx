import React, { useState, useEffect } from 'react';
import { AlertCircle, ChevronRight, Info, Loader2 } from 'lucide-react';
import WoundCareAssessmentForm from './ClinicalAssessment/WoundCareAssessmentForm';
import PulmonaryWoundAssessmentForm from './ClinicalAssessment/PulmonaryWoundAssessmentForm';
import VascularAssessmentForm from './ClinicalAssessment/VascularAssessmentForm';

interface ClinicalAssessmentStepProps {
  formData: any;
  updateFormData: (data: any) => void;
  userSpecialty?: string;
}

const ClinicalAssessmentStep: React.FC<ClinicalAssessmentStepProps> = ({
  formData,
  updateFormData,
  userSpecialty = 'wound_care_specialty'
}) => {
  const [activeSection, setActiveSection] = useState('wound_details');
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});
  const [isValidating, setIsValidating] = useState(false);

  // Determine which assessment form to show based on specialty and wound type
  const getAssessmentForm = () => {
    // Check if patient has pulmonary conditions
    const hasPulmonaryConditions = formData.clinical_data?.medical_history?.pulmonary_conditions?.length > 0;

    // Check provider specialty
    if (userSpecialty === 'pulmonology' || hasPulmonaryConditions) {
      return 'pulmonary_wound';
    }

    if (userSpecialty === 'vascular_surgery' || formData.wound_type === 'arterial_ulcer') {
      return 'vascular';
    }

    // Default to standard wound care assessment
    return 'wound_care';
  };

  const assessmentType = getAssessmentForm();

  // Define sections based on assessment type
  const getSections = () => {
    switch (assessmentType) {
      case 'pulmonary_wound':
        return [
          { id: 'pulmonary_history', label: 'Pulmonary History', required: true },
          { id: 'wound_details', label: 'Wound Assessment', required: true },
          { id: 'tissue_oxygenation', label: 'Tissue Oxygenation', required: true },
          { id: 'conservative_care', label: 'Conservative Care', required: true },
          { id: 'coordinated_care', label: 'Coordinated Care Planning', required: false },
        ];
      case 'vascular':
        return [
          { id: 'vascular_history', label: 'Vascular History', required: true },
          { id: 'wound_details', label: 'Wound Assessment', required: true },
          { id: 'vascular_evaluation', label: 'Vascular Studies', required: true },
          { id: 'conservative_care', label: 'Conservative Care', required: true },
          { id: 'lab_results', label: 'Laboratory Values', required: false },
        ];
      default:
        return [
          { id: 'wound_details', label: 'Wound Details', required: true },
          { id: 'conservative_care', label: 'Conservative Care', required: true },
          { id: 'vascular_evaluation', label: 'Vascular Evaluation', required: false },
          { id: 'lab_results', label: 'Lab Results', required: false },
          { id: 'clinical_photos', label: 'Clinical Photos', required: false },
        ];
    }
  };

  const sections = getSections();

  // Validate current section
  const validateSection = async (sectionId: string) => {
    setIsValidating(true);
    try {
      // Call validation API based on assessment type
      const response = await fetch('/api/v1/validation-builder/validate-section', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          section: sectionId,
          data: formData.clinical_data[sectionId] || {},
          wound_type: formData.wound_type,
          assessment_type: assessmentType,
        }),
      });

      const result = await response.json();

      if (result.errors) {
        setValidationErrors(prev => ({
          ...prev,
          [sectionId]: result.errors
        }));
      } else {
        setValidationErrors(prev => {
          const newErrors = { ...prev };
          delete newErrors[sectionId];
          return newErrors;
        });
      }
    } catch (error) {
      console.error('Validation error:', error);
    } finally {
      setIsValidating(false);
    }
  };

  // Update clinical data
  const updateClinicalData = (section: string, data: any) => {
    updateFormData({
      clinical_data: {
        ...formData.clinical_data,
        [section]: {
          ...formData.clinical_data?.[section],
          ...data
        }
      }
    });
  };

  // Calculate section completion
  const getSectionCompletion = (sectionId: string) => {
    const sectionData = formData.clinical_data?.[sectionId];
    if (!sectionData) return 0;

    const fields = Object.keys(sectionData);
    const filledFields = fields.filter(field =>
      sectionData[field] !== null &&
      sectionData[field] !== '' &&
      sectionData[field] !== undefined
    );

    return fields.length > 0 ? Math.round((filledFields.length / fields.length) * 100) : 0;
  };

  // Render the appropriate assessment form
  const renderAssessmentForm = () => {
    switch (assessmentType) {
      case 'pulmonary_wound':
        return (
          <PulmonaryWoundAssessmentForm
            formData={formData}
            updateClinicalData={updateClinicalData}
            activeSection={activeSection}
            validationErrors={validationErrors}
          />
        );
      case 'vascular':
        return (
          <VascularAssessmentForm
            formData={formData}
            updateClinicalData={updateClinicalData}
            activeSection={activeSection}
            validationErrors={validationErrors}
          />
        );
      default:
        return (
          <WoundCareAssessmentForm
            formData={formData}
            updateClinicalData={updateClinicalData}
            activeSection={activeSection}
            validationErrors={validationErrors}
          />
        );
    }
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

      {/* Assessment Type Indicator */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h3 className="text-sm font-medium text-blue-900">
              {assessmentType === 'pulmonary_wound' && 'Pulmonary & Wound Care Assessment'}
              {assessmentType === 'vascular' && 'Vascular Surgery Assessment'}
              {assessmentType === 'wound_care' && 'Standard Wound Care Assessment'}
            </h3>
            <p className="text-sm text-blue-700 mt-1">
              This assessment has been selected based on your specialty and the patient's condition.
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Section Navigation */}
        <div className="lg:col-span-1">
          <nav className="space-y-1">
            {sections.map((section) => {
              const completion = getSectionCompletion(section.id);
              const hasErrors = validationErrors[section.id]?.length > 0;

              return (
                <button
                  key={section.id}
                  onClick={() => {
                    setActiveSection(section.id);
                    validateSection(section.id);
                  }}
                  className={`w-full text-left px-3 py-2 rounded-md transition-colors ${
                    activeSection === section.id
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
                        activeSection === section.id ? 'text-blue-700' : 'text-gray-400'
                      }`} />
                    </div>
                  </div>
                </button>
              );
            })}
          </nav>

          {/* Overall Progress */}
          <div className="mt-6 p-4 bg-gray-50 rounded-lg">
            <h4 className="text-sm font-medium text-gray-700 mb-2">Overall Progress</h4>
            <div className="space-y-2">
              {sections.map((section) => {
                const completion = getSectionCompletion(section.id);
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

        {/* Form Content */}
        <div className="lg:col-span-3">
          <div className="bg-white rounded-lg border border-gray-200 p-6">
            {isValidating && (
              <div className="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10">
                <Loader2 className="h-8 w-8 text-blue-600 animate-spin" />
              </div>
            )}

            {renderAssessmentForm()}
          </div>
        </div>
      </div>

      {/* MSC Assist Integration */}
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
