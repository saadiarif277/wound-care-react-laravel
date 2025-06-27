import React from 'react';
import { AlertCircle, Info, Stethoscope, Heart } from 'lucide-react';

interface PulmonaryWoundAssessmentFormProps {
  formData: any;
  updateClinicalData: (section: string, data: any) => void;
  activeSection: string;
  validationErrors: Record<string, string[]>;
}

const PulmonaryWoundAssessmentForm: React.FC<PulmonaryWoundAssessmentFormProps> = ({
  formData,
  updateClinicalData,
  activeSection,
  validationErrors
}) => {
  const clinicalData = formData.clinical_data || {};
  const errors = validationErrors[activeSection] || [];

  const renderPulmonaryHistory = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900 flex items-center">
        <Stethoscope className="h-5 w-5 mr-2 text-blue-600" />
        Pulmonary History
      </h3>

      {/* Primary Pulmonary Diagnosis */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Primary Pulmonary Diagnosis *
        </label>
        <select
          value={clinicalData.pulmonary_history?.primary_diagnosis || ''}
          onChange={(e) => updateClinicalData('pulmonary_history', { primary_diagnosis: e.target.value })}
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Select primary diagnosis</option>
          <option value="copd">COPD</option>
          <option value="asthma">Asthma</option>
          <option value="pulmonary_fibrosis">Pulmonary Fibrosis</option>
          <option value="pulmonary_hypertension">Pulmonary Hypertension</option>
          <option value="sleep_apnea">Sleep Apnea</option>
          <option value="lung_cancer">Lung Cancer</option>
          <option value="pneumonia">Pneumonia</option>
          <option value="other">Other</option>
        </select>
      </div>

      {/* Secondary Conditions */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Secondary Pulmonary Conditions
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
          {[
            'chronic_bronchitis', 'emphysema', 'bronchiectasis', 'interstitial_lung_disease',
            'pulmonary_embolism', 'pleural_effusion', 'pneumothorax', 'respiratory_failure'
          ].map((condition) => (
            <label key={condition} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.pulmonary_history?.secondary_conditions?.includes(condition) || false}
                onChange={(e) => {
                  const currentConditions = clinicalData.pulmonary_history?.secondary_conditions || [];
                  const newConditions = e.target.checked
                    ? [...currentConditions, condition]
                    : currentConditions.filter((c: string) => c !== condition);
                  updateClinicalData('pulmonary_history', { secondary_conditions: newConditions });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{condition.replace(/_/g, ' ')}</span>
            </label>
          ))}
        </div>
      </div>

      {/* Smoking History */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Smoking Status *
          </label>
          <select
            value={clinicalData.pulmonary_history?.smoking_status || ''}
            onChange={(e) => updateClinicalData('pulmonary_history', { smoking_status: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select status</option>
            <option value="never">Never smoker</option>
            <option value="former">Former smoker</option>
            <option value="current">Current smoker</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Pack Years
          </label>
          <input
            type="number"
            step="0.5"
            value={clinicalData.pulmonary_history?.pack_years || ''}
            onChange={(e) => updateClinicalData('pulmonary_history', { pack_years: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="0.0"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Quit Date (if former)
          </label>
          <input
            type="date"
            value={clinicalData.pulmonary_history?.quit_date || ''}
            onChange={(e) => updateClinicalData('pulmonary_history', { quit_date: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>

      {/* Current Medications */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Current Pulmonary Medications
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
          {[
            'bronchodilators', 'corticosteroids', 'oxygen_therapy', 'mucolytics',
            'antibiotics', 'antifibrotics', 'pulmonary_vasodilators', 'cpap_bipap'
          ].map((medication) => (
            <label key={medication} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.pulmonary_history?.medications?.includes(medication) || false}
                onChange={(e) => {
                  const currentMeds = clinicalData.pulmonary_history?.medications || [];
                  const newMeds = e.target.checked
                    ? [...currentMeds, medication]
                    : currentMeds.filter((m: string) => m !== medication);
                  updateClinicalData('pulmonary_history', { medications: newMeds });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{medication.replace(/_/g, ' ')}</span>
            </label>
          ))}
        </div>
      </div>

      {/* Functional Status */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            NYHA Class (if applicable)
          </label>
          <select
            value={clinicalData.pulmonary_history?.nyha_class || ''}
            onChange={(e) => updateClinicalData('pulmonary_history', { nyha_class: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select NYHA Class</option>
            <option value="I">Class I - No limitation</option>
            <option value="II">Class II - Slight limitation</option>
            <option value="III">Class III - Marked limitation</option>
            <option value="IV">Class IV - Severe limitation</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Exercise Tolerance
          </label>
          <select
            value={clinicalData.pulmonary_history?.exercise_tolerance || ''}
            onChange={(e) => updateClinicalData('pulmonary_history', { exercise_tolerance: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select tolerance</option>
            <option value="unlimited">Unlimited</option>
            <option value="moderate_limitation">Moderate limitation</option>
            <option value="severe_limitation">Severe limitation</option>
            <option value="bedbound">Bedbound</option>
          </select>
        </div>
      </div>
    </div>
  );

  const renderWoundDetails = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Wound Assessment</h3>

      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h4 className="text-sm font-medium text-blue-900">Pulmonary-Related Wound Considerations</h4>
            <p className="text-sm text-blue-700 mt-1">
              Consider how pulmonary conditions may affect wound healing through oxygenation, circulation, and mobility limitations.
            </p>
          </div>
        </div>
      </div>

      {/* Wound Etiology */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Wound Etiology *
        </label>
        <select
          value={clinicalData.wound_details?.etiology || ''}
          onChange={(e) => updateClinicalData('wound_details', { etiology: e.target.value })}
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Select etiology</option>
          <option value="pressure_related">Pressure-related (immobility)</option>
          <option value="venous_insufficiency">Venous insufficiency</option>
          <option value="arterial_insufficiency">Arterial insufficiency</option>
          <option value="diabetic">Diabetic</option>
          <option value="surgical">Surgical</option>
          <option value="traumatic">Traumatic</option>
          <option value="other">Other</option>
        </select>
      </div>

      {/* Wound Location and Size */}
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
            <option value="sacrum">Sacrum</option>
            <option value="coccyx">Coccyx</option>
            <option value="heel">Heel</option>
            <option value="ankle">Ankle</option>
            <option value="lower_leg">Lower leg</option>
            <option value="foot">Foot</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Wound Stage/Grade
          </label>
          <select
            value={clinicalData.wound_details?.stage || ''}
            onChange={(e) => updateClinicalData('wound_details', { stage: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select stage</option>
            <option value="stage_1">Stage 1</option>
            <option value="stage_2">Stage 2</option>
            <option value="stage_3">Stage 3</option>
            <option value="stage_4">Stage 4</option>
            <option value="unstageable">Unstageable</option>
            <option value="suspected_dti">Suspected DTI</option>
          </select>
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

      {/* Wound Characteristics */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Tissue Type
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
      </div>
    </div>
  );

  const renderTissueOxygenation = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900 flex items-center">
        <Heart className="h-5 w-5 mr-2 text-red-600" />
        Tissue Oxygenation Assessment
      </h3>

      <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h4 className="text-sm font-medium text-yellow-900">Critical for Wound Healing</h4>
            <p className="text-sm text-yellow-700 mt-1">
              Tissue oxygenation is crucial for wound healing, especially in patients with pulmonary conditions.
            </p>
          </div>
        </div>
      </div>

      {/* Oxygen Saturation */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Resting SpO2 (%) *
          </label>
          <input
            type="number"
            min="70"
            max="100"
            value={clinicalData.tissue_oxygenation?.resting_spo2 || ''}
            onChange={(e) => updateClinicalData('tissue_oxygenation', { resting_spo2: parseInt(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Exercise SpO2 (%)
          </label>
          <input
            type="number"
            min="70"
            max="100"
            value={clinicalData.tissue_oxygenation?.exercise_spo2 || ''}
            onChange={(e) => updateClinicalData('tissue_oxygenation', { exercise_spo2: parseInt(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Oxygen Requirement
          </label>
          <select
            value={clinicalData.tissue_oxygenation?.oxygen_requirement || ''}
            onChange={(e) => updateClinicalData('tissue_oxygenation', { oxygen_requirement: e.target.value })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select requirement</option>
            <option value="none">None</option>
            <option value="prn">PRN</option>
            <option value="continuous">Continuous</option>
            <option value="nocturnal">Nocturnal only</option>
          </select>
        </div>
      </div>

      {/* Transcutaneous Oxygen Measurement */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            TcPO2 at Wound Site (mmHg)
          </label>
          <input
            type="number"
            value={clinicalData.tissue_oxygenation?.tcpo2_wound || ''}
            onChange={(e) => updateClinicalData('tissue_oxygenation', { tcpo2_wound: parseInt(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Normal: >40 mmHg"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            TcPO2 Reference Site (mmHg)
          </label>
          <input
            type="number"
            value={clinicalData.tissue_oxygenation?.tcpo2_reference || ''}
            onChange={(e) => updateClinicalData('tissue_oxygenation', { tcpo2_reference: parseInt(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Chest reference"
          />
        </div>
      </div>

      {/* Arterial Blood Gas */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            pH
          </label>
          <input
            type="number"
            step="0.01"
            value={clinicalData.tissue_oxygenation?.ph || ''}
            onChange={(e) => updateClinicalData('tissue_oxygenation', { ph: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="7.35-7.45"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            PaO2 (mmHg)
          </label>
          <input
            type="number"
            value={clinicalData.tissue_oxygenation?.pao2 || ''}
            onChange={(e) => updateClinicalData('tissue_oxygenation', { pao2: parseInt(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="80-100"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            PaCO2 (mmHg)
          </label>
          <input
            type="number"
            value={clinicalData.tissue_oxygenation?.paco2 || ''}
            onChange={(e) => updateClinicalData('tissue_oxygenation', { paco2: parseInt(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="35-45"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            HCO3 (mEq/L)
          </label>
          <input
            type="number"
            step="0.1"
            value={clinicalData.tissue_oxygenation?.hco3 || ''}
            onChange={(e) => updateClinicalData('tissue_oxygenation', { hco3: parseFloat(e.target.value) })}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="22-26"
          />
        </div>
      </div>

      {/* Perfusion Assessment */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Perfusion Indicators
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
          {[
            'capillary_refill_normal', 'skin_temperature_warm', 'pulses_palpable',
            'skin_color_normal', 'edema_absent', 'pain_minimal'
          ].map((indicator) => (
            <label key={indicator} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.tissue_oxygenation?.perfusion_indicators?.includes(indicator) || false}
                onChange={(e) => {
                  const currentIndicators = clinicalData.tissue_oxygenation?.perfusion_indicators || [];
                  const newIndicators = e.target.checked
                    ? [...currentIndicators, indicator]
                    : currentIndicators.filter((i: string) => i !== indicator);
                  updateClinicalData('tissue_oxygenation', { perfusion_indicators: newIndicators });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{indicator.replace(/_/g, ' ')}</span>
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
            <h4 className="text-sm font-medium text-yellow-900">Pulmonary-Specific Considerations</h4>
            <p className="text-sm text-yellow-700 mt-1">
              Conservative care must address both wound healing and pulmonary optimization.
            </p>
          </div>
        </div>
      </div>

      {/* Duration of Conservative Care */}
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

      {/* Pulmonary Optimization */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Pulmonary Optimization Measures *
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
          {[
            'oxygen_therapy', 'bronchodilator_optimization', 'steroid_management', 'pulmonary_rehabilitation',
            'smoking_cessation', 'infection_treatment', 'secretion_clearance', 'ventilatory_support'
          ].map((measure) => (
            <label key={measure} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.conservative_care?.pulmonary_measures?.includes(measure) || false}
                onChange={(e) => {
                  const currentMeasures = clinicalData.conservative_care?.pulmonary_measures || [];
                  const newMeasures = e.target.checked
                    ? [...currentMeasures, measure]
                    : currentMeasures.filter((m: string) => m !== measure);
                  updateClinicalData('conservative_care', { pulmonary_measures: newMeasures });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{measure.replace(/_/g, ' ')}</span>
            </label>
          ))}
        </div>
      </div>

      {/* Wound Care Measures */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Wound Care Measures *
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
          {[
            'moist_wound_healing', 'debridement', 'infection_control', 'pressure_relief',
            'positioning', 'nutrition_optimization', 'mobility_enhancement', 'pain_management'
          ].map((measure) => (
            <label key={measure} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.conservative_care?.wound_measures?.includes(measure) || false}
                onChange={(e) => {
                  const currentMeasures = clinicalData.conservative_care?.wound_measures || [];
                  const newMeasures = e.target.checked
                    ? [...currentMeasures, measure]
                    : currentMeasures.filter((m: string) => m !== measure);
                  updateClinicalData('conservative_care', { wound_measures: newMeasures });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{measure.replace(/_/g, ' ')}</span>
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
          <option value="pulmonary_limitation">Limited by pulmonary status</option>
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
          placeholder="Describe specific treatments, duration, patient response, and how pulmonary status affects wound healing..."
        />
      </div>
    </div>
  );

  const renderCoordinatedCare = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Coordinated Care Planning</h3>

      {/* Multidisciplinary Team */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Multidisciplinary Team Members Involved
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
          {[
            'pulmonologist', 'wound_care_specialist', 'respiratory_therapist', 'physical_therapist',
            'occupational_therapist', 'dietitian', 'social_worker', 'case_manager'
          ].map((member) => (
            <label key={member} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.coordinated_care?.team_members?.includes(member) || false}
                onChange={(e) => {
                  const currentMembers = clinicalData.coordinated_care?.team_members || [];
                  const newMembers = e.target.checked
                    ? [...currentMembers, member]
                    : currentMembers.filter((m: string) => m !== member);
                  updateClinicalData('coordinated_care', { team_members: newMembers });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{member.replace(/_/g, ' ')}</span>
            </label>
          ))}
        </div>
      </div>

      {/* Care Coordination Goals */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Primary Care Goals
        </label>
        <div className="space-y-2">
          {[
            'optimize_oxygenation', 'improve_wound_healing', 'enhance_mobility', 'prevent_complications',
            'improve_quality_of_life', 'reduce_hospitalizations', 'patient_education', 'family_support'
          ].map((goal) => (
            <label key={goal} className="flex items-center">
              <input
                type="checkbox"
                checked={clinicalData.coordinated_care?.care_goals?.includes(goal) || false}
                onChange={(e) => {
                  const currentGoals = clinicalData.coordinated_care?.care_goals || [];
                  const newGoals = e.target.checked
                    ? [...currentGoals, goal]
                    : currentGoals.filter((g: string) => g !== goal);
                  updateClinicalData('coordinated_care', { care_goals: newGoals });
                }}
                className="mr-2"
              />
              <span className="text-sm capitalize">{goal.replace(/_/g, ' ')}</span>
            </label>
          ))}
        </div>
      </div>

      {/* Communication Plan */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Communication and Follow-up Plan
        </label>
        <textarea
          value={clinicalData.coordinated_care?.communication_plan || ''}
          onChange={(e) => updateClinicalData('coordinated_care', { communication_plan: e.target.value })}
          rows={4}
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Describe communication frequency, methods, and follow-up schedule..."
        />
      </div>
    </div>
  );

  // Render appropriate section based on activeSection
  const renderActiveSection = () => {
    switch (activeSection) {
      case 'pulmonary_history':
        return renderPulmonaryHistory();
      case 'wound_details':
        return renderWoundDetails();
      case 'tissue_oxygenation':
        return renderTissueOxygenation();
      case 'conservative_care':
        return renderConservativeCare();
      case 'coordinated_care':
        return renderCoordinatedCare();
      default:
        return renderPulmonaryHistory();
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

export default PulmonaryWoundAssessmentForm;
