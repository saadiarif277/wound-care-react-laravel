import React from 'react';
import { AlertCircle, Info, Upload } from 'lucide-react';

interface WoundCareAssessmentFormProps {
  formData: any;
  updateClinicalData: (section: string, data: any) => void;
  activeSection: string;
  validationErrors: Record<string, string[]>;
}

const WoundCareAssessmentForm: React.FC<WoundCareAssessmentFormProps> = ({
  formData,
  updateClinicalData,
  activeSection,
  validationErrors
}) => {
  const clinicalData = formData.clinical_data || {};
  const errors = validationErrors[activeSection] || [];

  const renderWoundDetails = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Wound Details</h3>

      {/* Wound Location */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Wound Location *
          </label>
          <select
            value={clinicalData.wound_details?.location || ''}
            onChange={(e) => updateClinicalData('wound_details', { location: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select location</option>
            <option value="right_foot">Right Foot</option>
            <option value="left_foot">Left Foot</option>
            <option value="right_leg">Right Leg</option>
            <option value="left_leg">Left Leg</option>
            <option value="sacrum">Sacrum</option>
            <option value="heel">Heel</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Specific Anatomical Site
          </label>
          <input
            type="text"
            value={clinicalData.wound_details?.anatomical_site || ''}
            onChange={(e) => updateClinicalData('wound_details', { anatomical_site: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="e.g., plantar aspect of great toe"
          />
        </div>
      </div>

      {/* Wound Measurements */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Length (cm) *
          </label>
          <input
            type="number"
            step="0.1"
            value={clinicalData.wound_details?.length || ''}
            onChange={(e) => updateClinicalData('wound_details', { length: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Width (cm) *
          </label>
          <input
            type="number"
            step="0.1"
            value={clinicalData.wound_details?.width || ''}
            onChange={(e) => updateClinicalData('wound_details', { width: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Depth (cm)
          </label>
          <input
            type="number"
            step="0.1"
            value={clinicalData.wound_details?.depth || ''}
            onChange={(e) => updateClinicalData('wound_details', { depth: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>

      {/* Wagner Grade for DFU */}
      {formData.wound_type === 'diabetic_foot_ulcer' && (
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Wagner Grade *
          </label>
          <select
            value={clinicalData.wound_details?.wagner_grade || ''}
            onChange={(e) => updateClinicalData('wound_details', { wagner_grade: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select Wagner Grade</option>
            <option value="0">Grade 0 - No open lesion</option>
            <option value="1">Grade 1 - Superficial ulcer</option>
            <option value="2">Grade 2 - Deep ulcer to tendon/bone</option>
            <option value="3">Grade 3 - Deep ulcer with osteomyelitis</option>
            <option value="4">Grade 4 - Localized gangrene</option>
            <option value="5">Grade 5 - Extensive gangrene</option>
          </select>
        </div>
      )}

      {/* Wound Duration */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Wound Duration *
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input
            type="number"
            value={clinicalData.wound_details?.duration_value || ''}
            onChange={(e) => updateClinicalData('wound_details', { duration_value: parseInt(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Duration"
          />
          <select
            value={clinicalData.wound_details?.duration_unit || ''}
            onChange={(e) => updateClinicalData('wound_details', { duration_unit: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select unit</option>
            <option value="days">Days</option>
            <option value="weeks">Weeks</option>
            <option value="months">Months</option>
            <option value="years">Years</option>
          </select>
        </div>
      </div>

      {/* Tissue Type */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Predominant Tissue Type *
        </label>
        <select
          value={clinicalData.wound_details?.tissue_type || ''}
          onChange={(e) => updateClinicalData('wound_details', { tissue_type: e.target.value })}
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Select tissue type</option>
          <option value="granulation">Granulation (red)</option>
          <option value="slough">Slough (yellow)</option>
          <option value="eschar">Eschar (black)</option>
          <option value="epithelial">Epithelial (pink)</option>
          <option value="mixed">Mixed</option>
        </select>
      </div>

      {/* Exudate */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Exudate Amount
          </label>
          <select
            value={clinicalData.wound_details?.exudate_amount || ''}
            onChange={(e) => updateClinicalData('wound_details', { exudate_amount: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select amount</option>
            <option value="none">None</option>
            <option value="minimal">Minimal</option>
            <option value="moderate">Moderate</option>
            <option value="heavy">Heavy</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Exudate Type
          </label>
          <select
            value={clinicalData.wound_details?.exudate_type || ''}
            onChange={(e) => updateClinicalData('wound_details', { exudate_type: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select type</option>
            <option value="serous">Serous</option>
            <option value="serosanguinous">Serosanguinous</option>
            <option value="sanguinous">Sanguinous</option>
            <option value="purulent">Purulent</option>
          </select>
        </div>
      </div>

      {/* Signs of Infection */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Signs of Infection
        </label>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
          {[
            'erythema', 'warmth', 'swelling', 'pain', 'purulent_drainage',
            'odor', 'delayed_healing', 'friable_granulation'
          ].map((sign) => (
            <label key={sign} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.wound_details?.infection_signs?.includes(sign) || false}
                onChange={(e) => {
                  const currentSigns = clinicalData.wound_details?.infection_signs || [];
                  const newSigns = e.target.checked
                    ? [...currentSigns, sign]
                    : currentSigns.filter((s: string) => s !== sign);
                  updateClinicalData('wound_details', { infection_signs: newSigns });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{sign.replace('_', ' ')}</span>
            </label>
          ))}
        </div>
      </div>
    </div>
  );

  const renderConservativeCare = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Conservative Care</h3>

      <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h4 className="text-sm font-medium text-yellow-900">MAC Requirement</h4>
            <p className="text-sm text-yellow-700 mt-1">
              Medicare requires documentation of at least 4 weeks of conservative care before advanced wound care products.
            </p>
          </div>
        </div>
      </div>

      {/* Conservative Care Duration */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Duration of Conservative Care *
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input
            type="number"
            value={clinicalData.conservative_care?.duration_value || ''}
            onChange={(e) => updateClinicalData('conservative_care', { duration_value: parseInt(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Duration"
          />
          <select
            value={clinicalData.conservative_care?.duration_unit || ''}
            onChange={(e) => updateClinicalData('conservative_care', { duration_unit: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select unit</option>
            <option value="days">Days</option>
            <option value="weeks">Weeks</option>
            <option value="months">Months</option>
          </select>
        </div>
      </div>

      {/* Conservative Treatments */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Conservative Treatments Attempted *
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
          {[
            'moist_wound_healing', 'debridement', 'infection_control', 'offloading',
            'compression_therapy', 'nutritional_support', 'glycemic_control', 'vascular_assessment'
          ].map((treatment) => (
            <label key={treatment} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.conservative_care?.treatments?.includes(treatment) || false}
                onChange={(e) => {
                  const currentTreatments = clinicalData.conservative_care?.treatments || [];
                  const newTreatments = e.target.checked
                    ? [...currentTreatments, treatment]
                    : currentTreatments.filter((t: string) => t !== treatment);
                  updateClinicalData('conservative_care', { treatments: newTreatments });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{treatment.replace(/_/g, ' ')}</span>
            </label>
          ))}
        </div>
      </div>

      {/* Response to Conservative Care */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Response to Conservative Care *
        </label>
        <select
          value={clinicalData.conservative_care?.response || ''}
          onChange={(e) => updateClinicalData('conservative_care', { response: e.target.value })}
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Select response</option>
          <option value="no_improvement">No improvement</option>
          <option value="minimal_improvement">Minimal improvement</option>
          <option value="stalled_healing">Stalled healing</option>
          <option value="deterioration">Deterioration</option>
        </select>
      </div>

      {/* Detailed Notes */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Detailed Notes on Conservative Care
        </label>
        <textarea
          value={clinicalData.conservative_care?.notes || ''}
          onChange={(e) => updateClinicalData('conservative_care', { notes: e.target.value })}
          rows={4}
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Describe specific treatments, duration, and patient response..."
        />
      </div>
    </div>
  );

  const renderVascularEvaluation = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Vascular Evaluation</h3>

      {/* ABI */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Ankle-Brachial Index (ABI) - Right
          </label>
          <input
            type="number"
            step="0.01"
            value={clinicalData.vascular_evaluation?.abi_right || ''}
            onChange={(e) => updateClinicalData('vascular_evaluation', { abi_right: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="0.00"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Ankle-Brachial Index (ABI) - Left
          </label>
          <input
            type="number"
            step="0.01"
            value={clinicalData.vascular_evaluation?.abi_left || ''}
            onChange={(e) => updateClinicalData('vascular_evaluation', { abi_left: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="0.00"
          />
        </div>
      </div>

      {/* Pulses */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Pedal Pulses
        </label>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <span className="text-sm font-medium text-gray-600">Right Foot</span>
            <select
              value={clinicalData.vascular_evaluation?.pulse_right || ''}
              onChange={(e) => updateClinicalData('vascular_evaluation', { pulse_right: e.target.value })}
              className="w-full mt-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Select</option>
              <option value="absent">Absent</option>
              <option value="diminished">Diminished</option>
              <option value="normal">Normal</option>
            </select>
          </div>
          <div>
            <span className="text-sm font-medium text-gray-600">Left Foot</span>
            <select
              value={clinicalData.vascular_evaluation?.pulse_left || ''}
              onChange={(e) => updateClinicalData('vascular_evaluation', { pulse_left: e.target.value })}
              className="w-full mt-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Select</option>
              <option value="absent">Absent</option>
              <option value="diminished">Diminished</option>
              <option value="normal">Normal</option>
            </select>
          </div>
        </div>
      </div>

      {/* Vascular Studies */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Additional Vascular Studies
        </label>
        <div className="space-y-2">
          {[
            'doppler_ultrasound', 'arterial_duplex', 'venous_duplex',
            'transcutaneous_oxygen', 'angiography'
          ].map((study) => (
            <label key={study} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.vascular_evaluation?.studies?.includes(study) || false}
                onChange={(e) => {
                  const currentStudies = clinicalData.vascular_evaluation?.studies || [];
                  const newStudies = e.target.checked
                    ? [...currentStudies, study]
                    : currentStudies.filter((s: string) => s !== study);
                  updateClinicalData('vascular_evaluation', { studies: newStudies });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{study.replace(/_/g, ' ')}</span>
            </label>
          ))}
        </div>
      </div>
    </div>
  );

  const renderLabResults = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Laboratory Results</h3>

      {/* Diabetes-specific labs */}
      {formData.wound_type === 'diabetic_foot_ulcer' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              HbA1c (%)
            </label>
            <input
              type="number"
              step="0.1"
              value={clinicalData.lab_results?.hba1c || ''}
              onChange={(e) => updateClinicalData('lab_results', { hba1c: parseFloat(e.target.value) })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Random Glucose (mg/dL)
            </label>
            <input
              type="number"
              value={clinicalData.lab_results?.glucose || ''}
              onChange={(e) => updateClinicalData('lab_results', { glucose: parseInt(e.target.value) })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>
      )}

      {/* General labs */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Albumin (g/dL)
          </label>
          <input
            type="number"
            step="0.1"
            value={clinicalData.lab_results?.albumin || ''}
            onChange={(e) => updateClinicalData('lab_results', { albumin: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Prealbumin (mg/dL)
          </label>
          <input
            type="number"
            step="0.1"
            value={clinicalData.lab_results?.prealbumin || ''}
            onChange={(e) => updateClinicalData('lab_results', { prealbumin: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Total Protein (g/dL)
          </label>
          <input
            type="number"
            step="0.1"
            value={clinicalData.lab_results?.total_protein || ''}
            onChange={(e) => updateClinicalData('lab_results', { total_protein: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>
    </div>
  );

  const renderClinicalPhotos = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Clinical Photos</h3>

      <div className="border-2 border-dashed border-gray-300 rounded-lg p-6">
        <div className="text-center">
          <Upload className="mx-auto h-12 w-12 text-gray-400" />
          <div className="mt-4">
            <label htmlFor="photo-upload" className="cursor-pointer">
              <span className="mt-2 block text-sm font-medium text-gray-900">
                Upload wound photos
              </span>
              <span className="mt-1 block text-sm text-gray-500">
                PNG, JPG, GIF up to 10MB each
              </span>
            </label>
            <input
              id="photo-upload"
              name="photo-upload"
              type="file"
              multiple
              accept="image/*"
              className="sr-only"
            />
          </div>
        </div>
      </div>

      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h4 className="text-sm font-medium text-blue-900">Photo Guidelines</h4>
            <ul className="text-sm text-blue-700 mt-1 list-disc list-inside">
              <li>Include a ruler or measuring device for scale</li>
              <li>Take photos from multiple angles</li>
              <li>Ensure good lighting and focus</li>
              <li>Remove any patient identifiers</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );

  // Render appropriate section based on activeSection
  const renderActiveSection = () => {
    switch (activeSection) {
      case 'wound_details':
        return renderWoundDetails();
      case 'conservative_care':
        return renderConservativeCare();
      case 'vascular_evaluation':
        return renderVascularEvaluation();
      case 'lab_results':
        return renderLabResults();
      case 'clinical_photos':
        return renderClinicalPhotos();
      default:
        return renderWoundDetails();
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

export default WoundCareAssessmentForm;
