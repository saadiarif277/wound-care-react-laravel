import React from 'react';
import { AlertCircle, Info, Activity } from 'lucide-react';

interface VascularAssessmentFormProps {
  formData: any;
  updateClinicalData: (section: string, data: any) => void;
  activeSection: string;
  validationErrors: Record<string, string[]>;
}

const VascularAssessmentForm: React.FC<VascularAssessmentFormProps> = ({
  formData,
  updateClinicalData,
  activeSection,
  validationErrors
}) => {
  const clinicalData = formData.clinical_data || {};
  const errors = validationErrors[activeSection] || [];

  const renderVascularHistory = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900 flex items-center">
        <Activity className="h-5 w-5 mr-2 text-red-600" />
        Vascular History
      </h3>

      {/* Primary Vascular Diagnosis */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Primary Vascular Diagnosis *
        </label>
        <select
          value={clinicalData.vascular_history?.primary_diagnosis || ''}
          onChange={(e) => updateClinicalData('vascular_history', { primary_diagnosis: e.target.value })}
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Select primary diagnosis</option>
          <option value="peripheral_arterial_disease">Peripheral Arterial Disease</option>
          <option value="chronic_venous_insufficiency">Chronic Venous Insufficiency</option>
          <option value="deep_vein_thrombosis">Deep Vein Thrombosis</option>
          <option value="arterial_occlusion">Arterial Occlusion</option>
          <option value="aneurysm">Aneurysm</option>
          <option value="other">Other</option>
        </select>
      </div>

      {/* Risk Factors */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Vascular Risk Factors
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
          {[
            'diabetes', 'hypertension', 'hyperlipidemia', 'smoking',
            'obesity', 'family_history', 'sedentary_lifestyle', 'advanced_age'
          ].map((factor) => (
            <label key={factor} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.vascular_history?.risk_factors?.includes(factor) || false}
                onChange={(e) => {
                  const currentFactors = clinicalData.vascular_history?.risk_factors || [];
                  const newFactors = e.target.checked
                    ? [...currentFactors, factor]
                    : currentFactors.filter((f: string) => f !== factor);
                  updateClinicalData('vascular_history', { risk_factors: newFactors });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{factor.replace(/_/g, ' ')}</span>
            </label>
          ))}
        </div>
      </div>
    </div>
  );

  // Render appropriate section based on activeSection
  const renderActiveSection = () => {
    switch (activeSection) {
      case 'vascular_history':
        return renderVascularHistory();
      default:
        return renderVascularHistory();
    }
  };

  return (
    <div className="space-y-6">
      {/* Validation Errors */}
      {errors.length > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-start">
            <AlertCircle className="h-5 w-5 text-red-600 mt-0.5 mr-3 flex-shrink-0" />
            <div>
              <h4 className="text-sm font-medium text-red-900">Validation Errors</h4>
              <ul className="text-sm text-red-700 mt-1 list-disc list-inside">
                {errors.map((error, index) => (
                  <li key={index}>{error}</li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}

      {renderActiveSection()}
    </div>
  );
};

export default VascularAssessmentForm;
