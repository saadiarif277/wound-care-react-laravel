import React from 'react';
import { AlertCircle, Info, Upload } from 'lucide-react';
import { SkinSubstituteChecklistInput } from '@/services/fhir/SkinSubstituteChecklistMapper'; // Assuming this path is correct

// UI Section Keys, matching those in ClinicalAssessmentStep.tsx
const SSP_UI_SECTIONS = {
  DIAGNOSIS: 'ssp_checklist_diagnosis',
  LAB_RESULTS: 'ssp_checklist_lab_results',
  WOUND_DESCRIPTION: 'ssp_checklist_wound',
  CIRCULATION: 'ssp_checklist_circulation',
  CONSERVATIVE_TREATMENT: 'ssp_checklist_conservative',
  // CLINICAL_PHOTOS: 'ssp_checklist_photos',
} as const;

type SspUiSectionKey = typeof SSP_UI_SECTIONS[keyof typeof SSP_UI_SECTIONS];
// Note: ClinicalPhotosData is not directly a key of SkinSubstituteChecklistInput based on its current definition
// So, if 'clinical_photos' is passed as activeSection, it needs special handling or its own data structure in the input type.

interface WoundCareAssessmentFormProps {
  formData: Partial<SkinSubstituteChecklistInput>; // Receives the entire (partial) checklist input
  handleChange: (fieldName: keyof SkinSubstituteChecklistInput, value: any) => void; // New handler prop
  activeSection: SspUiSectionKey | 'clinical_photos'; // UI key telling which part to render
  validationErrors?: Record<string, string[]>;
  parentWoundType?: string;
}

const inputBaseClasses = "mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm";

const WoundCareAssessmentForm: React.FC<WoundCareAssessmentFormProps> = ({
  formData,
  handleChange, // Use new handler
  activeSection,
  validationErrors,
  parentWoundType
}) => {

  // No need for local handleChange, use props.handleChange directly
  // No need for local handleCheckboxChange, can be adapted or done inline with props.handleChange

  const errorsForSection = validationErrors?.[activeSection] || []; // Errors are keyed by activeSection (UI key)

  // --- RENDER FUNCTIONS FOR EACH LOGICAL UI SECTION ---
  // These now read from the top-level formData (Partial<SkinSubstituteChecklistInput>)

  const renderSSPDiagnosis = () => {
    // Fields are directly on formData, e.g., formData.dateOfProcedure
    return (
      <div className="space-y-4">
        <h3 className="text-lg font-medium text-gray-900">Diagnosis (SSP)</h3>
        <div><label className="block text-sm font-medium text-gray-700">Date of Procedure</label><input type="date" value={formData.dateOfProcedure || ''} onChange={(e) => handleChange('dateOfProcedure', e.target.value)} className={inputBaseClasses} /></div>
        <fieldset><legend className="text-sm font-medium text-gray-700">Diagnosis Type</legend>
          <div className="mt-2 space-y-2 sm:space-y-0 sm:flex sm:items-center sm:gap-4">
            <label className="flex items-center"><input type="radio" name="hasDiabetes" value="true" checked={formData.hasDiabetes === true} onChange={() => handleChange('hasDiabetes', true)} className="mr-1.5"/> Diabetes</label>
            {formData.hasDiabetes && (
                <>
                <label className="flex items-center"><input type="radio" name="diabetesType" value="1" checked={formData.diabetesType === '1'} onChange={() => handleChange('diabetesType', '1')} className="mr-1.5"/> Type 1</label>
                <label className="flex items-center"><input type="radio" name="diabetesType" value="2" checked={formData.diabetesType === '2'} onChange={() => handleChange('diabetesType', '2')} className="mr-1.5"/> Type 2</label>
                </>
            )}
          </div>
          <div className="mt-2 space-y-2">
            <label className="flex items-center"><input type="checkbox" checked={formData.hasVenousStasisUlcer || false} onChange={(e) => handleChange('hasVenousStasisUlcer', e.target.checked)} className="mr-1.5"/> Venous Stasis Ulcer</label>
            <label className="flex items-center"><input type="checkbox" checked={formData.hasPressureUlcer || false} onChange={(e) => handleChange('hasPressureUlcer', e.target.checked)} className="mr-1.5"/> Pressure Ulcer</label>
          </div>
        </fieldset>
        {formData.hasPressureUlcer && (
            <div><label className="block text-sm font-medium text-gray-700">Pressure Ulcer Stage</label><input type="text" value={formData.pressureUlcerStage || ''} onChange={(e) => handleChange('pressureUlcerStage', e.target.value)} className={inputBaseClasses} placeholder="Stage I-IV, Unstageable, DTI"/></div>
        )}
        <div><label className="block text-sm font-medium text-gray-700">Diagnosis Location/Laterality (General)</label><input type="text" value={formData.location || ''} onChange={(e) => handleChange('location', e.target.value)} className={inputBaseClasses} placeholder="e.g., Right, Left, Bilateral Medial Malleolus"/></div>
        <div><label className="block text-sm font-medium text-gray-700">Specific Ulcer Location</label><input type="text" value={formData.ulcerLocation || ''} onChange={(e) => handleChange('ulcerLocation', e.target.value)} className={inputBaseClasses} placeholder="e.g., Plantar aspect of great toe"/></div>
      </div>
    );
  };

  const renderSSPLabResults = () => {
    return (
      <div className="space-y-4">
        <h3 className="text-lg font-medium text-gray-900">Lab Results (SSP)</h3>
        <div className="grid md:grid-cols-2 gap-x-4 gap-y-2">
          <div><label className="block text-sm font-medium text-gray-700">HbA1c result (%)</label><input type="number" step="0.1" value={formData.hba1cResult === null ? '' : formData.hba1cResult || ''} onChange={e => handleChange('hba1cResult', e.target.value ? parseFloat(e.target.value) : null)} className={inputBaseClasses} /></div>
          <div><label className="block text-sm font-medium text-gray-700">Date of HbA1c Lab</label><input type="date" value={formData.hba1cDate || ''} onChange={e => handleChange('hba1cDate', e.target.value)} className={inputBaseClasses} /></div>
        </div>
        <div className="grid md:grid-cols-2 gap-x-4 gap-y-2">
          <div><label className="block text-sm font-medium text-gray-700">Albumin result (g/dL)</label><input type="number" step="0.1" value={formData.albuminResult === null ? '' : formData.albuminResult || ''} onChange={e => handleChange('albuminResult', e.target.value ? parseFloat(e.target.value) : null)} className={inputBaseClasses} /></div>
          <div><label className="block text-sm font-medium text-gray-700">Date of Albumin Lab</label><input type="date" value={formData.albuminDate || ''} onChange={e => handleChange('albuminDate', e.target.value)} className={inputBaseClasses} /></div>
        </div>
        <h4 className="text-md font-medium text-gray-800 mt-3 mb-1">Additional Clinical (If Applicable)</h4>
        <label className="flex items-center"><input type="checkbox" checked={formData.cbcPerformed || false} onChange={(e) => handleChange('cbcPerformed', e.target.checked)} className="mr-1.5"/> CBC Performed</label>
        <div className="grid md:grid-cols-2 gap-x-4 gap-y-2">
            <div><label className="block text-sm font-medium text-gray-700">H&H:</label><input type="text" value={formData.hh || ''} onChange={e => handleChange('hh', e.target.value)} className={inputBaseClasses}/></div>
            <div><label className="block text-sm font-medium text-gray-700">Sed Rate:</label><input type="number" value={formData.sedRate === null ? '' : formData.sedRate || ''} onChange={e => handleChange('sedRate', e.target.value ? parseFloat(e.target.value) : null)} className={inputBaseClasses}/></div>
        </div>
        <div><label className="block text-sm font-medium text-gray-700">CRP (mg/L):</label><input type="number" step="0.1" value={formData.crapResult === null ? '' : formData.crapResult || ''} onChange={e => handleChange('crapResult', e.target.value ? parseFloat(e.target.value) : null)} className={inputBaseClasses}/></div>
        <div><label className="block text-sm font-medium text-gray-700">Culture date:</label><input type="date" value={formData.cultureDate || ''} onChange={e => handleChange('cultureDate', e.target.value)} className={inputBaseClasses}/></div>
        <div><label className="block text-sm font-medium text-gray-700">Treated for infection?</label>
          <div className="mt-2 space-x-4">
            <label className="flex items-center"><input type="radio" name="infection_treated" value="yes" checked={formData.treated === true} onChange={() => handleChange('treated', true)} className="mr-1.5"/> Yes</label>
            <label className="flex items-center"><input type="radio" name="infection_treated" value="no" checked={formData.treated === false} onChange={() => handleChange('treated', false)} className="mr-1.5"/> No</label>
          </div>
        </div>
      </div>
    );
  };

  const renderSSPWoundDescription = () => {
    return (
      <div className="space-y-4">
        <h3 className="text-lg font-medium text-gray-900">Wound Description (SSP)</h3>
        <div><label className="block text-sm font-medium text-gray-700">Specific Ulcer Location (anatomic site):</label><input type="text" value={formData.ulcerLocation || ''} onChange={e => handleChange('ulcerLocation', e.target.value)} className={inputBaseClasses}/></div>
        <div><label className="block text-sm font-medium text-gray-700">Wound Depth Classification:</label>
          <div className="mt-2 space-x-4">
            <label className="flex items-center"><input type="radio" name="depth_type" value="full-thickness" checked={formData.depth === 'full-thickness'} onChange={() => handleChange('depth', 'full-thickness')} className="mr-1.5"/> Full thickness</label>
            <label className="flex items-center"><input type="radio" name="depth_type" value="partial-thickness" checked={formData.depth === 'partial-thickness'} onChange={() => handleChange('depth', 'partial-thickness')} className="mr-1.5"/> Partial Thickness</label>
          </div>
        </div>
        <div><label className="block text-sm font-medium text-gray-700">Ulcer Duration:</label><input type="text" value={formData.ulcerDuration || ''} onChange={e => handleChange('ulcerDuration', e.target.value)} className={inputBaseClasses} placeholder="e.g., 3 weeks"/></div>
        <div><label className="block text-sm font-medium text-gray-700">Exposed Structures (select all that apply):</label>
          <div className="mt-2 grid grid-cols-2 gap-2">
            {(['muscle', 'tendon', 'bone', 'none'] as const).map(structure => (
                <label key={structure} className="flex items-center">
                    <input type="checkbox" checked={(formData.exposedStructures || []).includes(structure)}
                           onChange={(e) => {
                                const current = formData.exposedStructures || [];
                                const newValues = e.target.checked ? [...current, structure] : current.filter(s => s !== structure);
                                handleChange('exposedStructures', newValues);
                           }} className="mr-1.5" /> {structure.charAt(0).toUpperCase() + structure.slice(1)}
                </label>
            ))}
          </div>
        </div>
        <div><label className="block text-sm font-medium text-gray-700">Wound Measurements (cm):</label>
            <div className="grid grid-cols-3 gap-2 mt-1">
                <input type="number" placeholder="L" value={formData.length || ''} onChange={e => handleChange('length', parseFloat(e.target.value) || 0)} className={inputBaseClasses}/>
                <input type="number" placeholder="W" value={formData.width || ''} onChange={e => handleChange('width', parseFloat(e.target.value) || 0)} className={inputBaseClasses}/>
                <input type="number" placeholder="Depth" value={formData.woundDepth || ''} onChange={e => handleChange('woundDepth', parseFloat(e.target.value) || 0)} className={inputBaseClasses}/>
            </div>
        </div>
        {/* Conditional Wagner Grade - parentWoundType comes from ClinicalAssessmentStep -> Create.tsx -> formData.wound_type */}
        {parentWoundType === 'diabetic_foot_ulcer' && (
           <div><label className="block text-sm font-medium text-gray-700">Wagner Grade (DFU only)</label>
           {/* Add select for Wagner Grade, reading from/writing to a field like formData.wagner_grade (needs to be added to SkinSubstituteChecklistInput.wound) */}
           <select value={(formData as any).wagner_grade || ''} onChange={(e) => handleChange('wagner_grade' as any, e.target.value)} className={inputBaseClasses}>
             <option value="">Select</option><option value="0">0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option>
           </select>
           </div>
        )}
        <div><label className="block text-sm font-medium text-gray-700">Evidence of Infection/Osteomyelitis:</label><div className="mt-2 space-x-4"><label className="flex items-center"><input type="radio" name="hasInfection" value="yes" checked={formData.hasInfection === true} onChange={() => handleChange('hasInfection', true)} className="mr-1.5"/> Yes</label><label className="flex items-center"><input type="radio" name="hasInfection" value="no" checked={formData.hasInfection === false} onChange={() => handleChange('hasInfection', false)} className="mr-1.5"/> No</label></div></div>
        <div><label className="block text-sm font-medium text-gray-700">Evidence of necrotic tissue:</label><div className="mt-2 space-x-4"><label className="flex items-center"><input type="radio" name="hasNecroticTissue" value="yes" checked={formData.hasNecroticTissue === true} onChange={() => handleChange('hasNecroticTissue', true)} className="mr-1.5"/> Yes</label><label className="flex items-center"><input type="radio" name="hasNecroticTissue" value="no" checked={formData.hasNecroticTissue === false} onChange={() => handleChange('hasNecroticTissue', false)} className="mr-1.5"/> No</label></div></div>
        <div><label className="block text-sm font-medium text-gray-700">Active Charcot deformity:</label><div className="mt-2 space-x-4"><label className="flex items-center"><input type="radio" name="hasCharcotDeformity" value="yes" checked={formData.hasCharcotDeformity === true} onChange={() => handleChange('hasCharcotDeformity', true)} className="mr-1.5"/> Yes</label><label className="flex items-center"><input type="radio" name="hasCharcotDeformity" value="no" checked={formData.hasCharcotDeformity === false} onChange={() => handleChange('hasCharcotDeformity', false)} className="mr-1.5"/> No</label></div></div>
        <div><label className="block text-sm font-medium text-gray-700">Known or suspected malignancy:</label><div className="mt-2 space-x-4"><label className="flex items-center"><input type="radio" name="hasMalignancy" value="yes" checked={formData.hasMalignancy === true} onChange={() => handleChange('hasMalignancy', true)} className="mr-1.5"/> Yes</label><label className="flex items-center"><input type="radio" name="hasMalignancy" value="no" checked={formData.hasMalignancy === false} onChange={() => handleChange('hasMalignancy', false)} className="mr-1.5"/> No</label></div></div>
        {/* TODO: Add inputs for tissue_type, exudate_amount, exudate_type, infection_signs if they are to be kept from original SSP_WoundDescriptionData and added to SkinSubstituteChecklistInput.wound */}

      </div>
    );
  };

  const renderSSPCirculation = () => {
    return (
      <div className="space-y-4">
        <h3 className="text-lg font-medium text-gray-900">Circulation Assessment (SSP)</h3>
        <div className="grid md:grid-cols-2 gap-x-4 gap-y-2">
            <div><label className="block text-sm font-medium text-gray-700">ABI result:</label><input type="number" step="0.01" value={formData.abiResult === null ? '' : formData.abiResult || ''} onChange={e => handleChange('abiResult', e.target.value ? parseFloat(e.target.value) : null)} className={inputBaseClasses}/></div>
            <div><label className="block text-sm font-medium text-gray-700">ABI Date:</label><input type="date" value={formData.abiDate || ''} onChange={e => handleChange('abiDate', e.target.value)} className={inputBaseClasses}/></div>
        </div>
        <div className="grid md:grid-cols-2 gap-x-4 gap-y-2">
            <div><label className="block text-sm font-medium text-gray-700">Pedal pulses result:</label><input type="text" value={formData.pedalPulsesResult || ''} onChange={e => handleChange('pedalPulsesResult', e.target.value)} className={inputBaseClasses}/></div>
            <div><label className="block text-sm font-medium text-gray-700">Pedal Pulses Date:</label><input type="date" value={formData.pedalPulsesDate || ''} onChange={e => handleChange('pedalPulsesDate', e.target.value)} className={inputBaseClasses}/></div>
        </div>
        <div className="grid md:grid-cols-2 gap-x-4 gap-y-2">
            <div><label className="block text-sm font-medium text-gray-700">TcPO2 (mmHg) (≥30):</label><input type="number" value={formData.tcpo2Result === null ? '' : formData.tcpo2Result || ''} onChange={e => handleChange('tcpo2Result', e.target.value ? parseFloat(e.target.value) : null)} className={inputBaseClasses}/></div>
            <div><label className="block text-sm font-medium text-gray-700">TcPO2 Date:</label><input type="date" value={formData.tcpo2Date || ''} onChange={e => handleChange('tcpo2Date', e.target.value)} className={inputBaseClasses}/></div>
        </div>
        <div><label className="block text-sm font-medium text-gray-700">Doppler: Triphasic/Biphasic waveforms at ankle?</label>
          <div className="mt-2 space-x-4">
            <label className="flex items-center"><input type="radio" name="hasTriphasicWaveforms" value="yes" checked={formData.hasTriphasicWaveforms === true} onChange={() => handleChange('hasTriphasicWaveforms', true)} className="mr-1.5"/> Yes</label>
            <label className="flex items-center"><input type="radio" name="hasTriphasicWaveforms" value="no" checked={formData.hasTriphasicWaveforms === false} onChange={() => handleChange('hasTriphasicWaveforms', false)} className="mr-1.5"/> No</label>
          </div>
        </div>
        <div className="grid md:grid-cols-2 gap-x-4 gap-y-2">
            <div><label className="block text-sm font-medium text-gray-700">Doppler Result Notes:</label><input type="text" value={formData.waveformResult || ''} onChange={e => handleChange('waveformResult', e.target.value)} className={inputBaseClasses}/></div>
            <div><label className="block text-sm font-medium text-gray-700">Doppler Date:</label><input type="date" value={formData.waveformDate || ''} onChange={e => handleChange('waveformDate', e.target.value)} className={inputBaseClasses}/></div>
        </div>
        <div><label className="block text-sm font-medium text-gray-700">Imaging:</label>
          <div className="mt-2 space-x-4">
            <label className="flex items-center"><input type="radio" name="imagingType" value="xray" checked={formData.imagingType === 'xray'} onChange={() => handleChange('imagingType', 'xray')} className="mr-1.5"/> X-ray</label>
            <label className="flex items-center"><input type="radio" name="imagingType" value="ct" checked={formData.imagingType === 'ct'} onChange={() => handleChange('imagingType', 'ct')} className="mr-1.5"/> CT Scan</label>
            <label className="flex items-center"><input type="radio" name="imagingType" value="mri" checked={formData.imagingType === 'mri'} onChange={() => handleChange('imagingType', 'mri')} className="mr-1.5"/> MRI</label>
            <label className="flex items-center"><input type="radio" name="imagingType" value="none" checked={formData.imagingType === 'none'} onChange={() => handleChange('imagingType', 'none')} className="mr-1.5"/> None</label>
          </div>
        </div>
      </div>
    );
  };

  const renderSSPConservativeMeasures = () => {
    return (
      <div className="space-y-4">
        <h3 className="text-lg font-medium text-gray-900">Conservative Treatment (Past 30 Days) (SSP)</h3>
        <label className="flex items-center"><input type="checkbox" name="debridementPerformed" checked={formData.debridementPerformed || false} onChange={(e) => handleChange('debridementPerformed', e.target.checked)} className="mr-1.5"/> Debridement of necrotic tissue was performed</label>
        <label className="flex items-center"><input type="checkbox" name="moistDressingsApplied" checked={formData.moistDressingsApplied || false} onChange={(e) => handleChange('moistDressingsApplied', e.target.checked)} className="mr-1.5"/> Application of dressings to maintain a moist wound environment</label>
        <div><label className="flex items-center"><input type="checkbox" name="nonWeightBearing" checked={formData.nonWeightBearing || false} onChange={(e) => handleChange('nonWeightBearing', e.target.checked)} className="mr-1.5"/> Non-weight bearing regimen</label>
          {/* PHP DTO doesn't nest footwearType under nonWeightBearing. Original TS ChecklistInput had pressureReducingFootwear as object. PHP has pressureReducingFootwear (bool) and footwearType (string?) */}
        </div>
        <div><label className="flex items-center"><input type="checkbox" name="pressureReducingFootwear" checked={formData.pressureReducingFootwear || false} onChange={(e) => handleChange('pressureReducingFootwear', e.target.checked)} className="mr-1.5"/> Uses pressure-reducing footwear</label>
          {formData.pressureReducingFootwear && (<input type="text" placeholder="Type of footwear" value={formData.footwearType || ''} onChange={e => handleChange('footwearType', e.target.value)} className={`${inputBaseClasses} mt-1 ml-6`} />)}
        </div>
        <div><label className="block text-sm font-medium text-gray-700">Used Standard compression therapy for venous stasis ulcers:</label>
          <div className="mt-2 space-x-4">
            {/* PHP DTO has standardCompression (boolean) and doesn't have a VSU specific or NA option directly */}
            <label className="flex items-center"><input type="radio" name="standardCompression" value="yes" checked={formData.standardCompression === true} onChange={() => handleChange('standardCompression', true)} className="mr-1.5"/> Yes</label>
            <label className="flex items-center"><input type="radio" name="standardCompression" value="no" checked={formData.standardCompression === false} onChange={() => handleChange('standardCompression', false)} className="mr-1.5"/> No</label>
            {/* N/A if non VSU needs to be inferred or handled by form logic based on primary diagnosis */}
          </div>
        </div>
        <div><label className="block text-sm font-medium text-gray-700">Current HBOT:</label><div className="mt-2 space-x-4"><label className="flex items-center"><input type="radio" name="currentHbot" value="yes" checked={formData.currentHbot === true} onChange={() => handleChange('currentHbot', true)} className="mr-1.5"/> Yes</label><label className="flex items-center"><input type="radio" name="currentHbot" value="no" checked={formData.currentHbot === false} onChange={() => handleChange('currentHbot', false)} className="mr-1.5"/> No</label></div></div>
        <div><label className="block text-sm font-medium text-gray-700">Smoking Status:</label>
          <div className="mt-2 space-x-4">
            <label className="flex items-center"><input type="radio" name="smokingStatus" value="smoker" checked={formData.smokingStatus === 'smoker'} onChange={() => handleChange('smokingStatus', 'smoker')} className="mr-1.5"/> Smoker</label>
            <label className="flex items-center"><input type="radio" name="smokingStatus" value="previous-smoker" checked={formData.smokingStatus === 'previous-smoker'} onChange={() => handleChange('smokingStatus', 'previous-smoker')} className="mr-1.5"/> Previous smoker</label>
            <label className="flex items-center"><input type="radio" name="smokingStatus" value="non-smoker" checked={formData.smokingStatus === 'non-smoker'} onChange={() => handleChange('smokingStatus', 'non-smoker')} className="mr-1.5"/> Non-Smoker</label>
          </div>
        </div>
        {formData.smokingStatus === 'smoker' && (
            <div><label className="block text-sm font-medium text-gray-700">If Smoker, has patient been counselled on smoking cessation?</label><div className="mt-2 space-x-4"><label className="flex items-center"><input type="radio" name="smokingCounselingProvided" value="yes" checked={formData.smokingCounselingProvided === true} onChange={() => handleChange('smokingCounselingProvided', true)} className="mr-1.5"/> Yes</label><label className="flex items-center"><input type="radio" name="smokingCounselingProvided" value="no" checked={formData.smokingCounselingProvided === false} onChange={() => handleChange('smokingCounselingProvided', false)} className="mr-1.5"/> No</label></div></div>
        )}
        <div><label className="block text-sm font-medium text-gray-700">Is Patient receiving radiation therapy or chemotherapy?</label><div className="mt-2 space-x-4"><label className="flex items-center"><input type="radio" name="receivingRadiationOrChemo" value="yes" checked={formData.receivingRadiationOrChemo === true} onChange={() => handleChange('receivingRadiationOrChemo', true)} className="mr-1.5"/> Yes</label><label className="flex items-center"><input type="radio" name="receivingRadiationOrChemo" value="no" checked={formData.receivingRadiationOrChemo === false} onChange={() => handleChange('receivingRadiationOrChemo', false)} className="mr-1.5"/> No</label></div></div>
        <div><label className="block text-sm font-medium text-gray-700">Is Patient taking medications considered to be immune system modulators?</label><div className="mt-2 space-x-4"><label className="flex items-center"><input type="radio" name="takingImmuneModulators" value="yes" checked={formData.takingImmuneModulators === true} onChange={() => handleChange('takingImmuneModulators', true)} className="mr-1.5"/> Yes</label><label className="flex items-center"><input type="radio" name="takingImmuneModulators" value="no" checked={formData.takingImmuneModulators === false} onChange={() => handleChange('takingImmuneModulators', false)} className="mr-1.5"/> No</label></div></div>
        <div><label className="block text-sm font-medium text-gray-700">Does Patient have an autoimmune connective tissue disease diagnosis?</label><div className="mt-2 space-x-4"><label className="flex items-center"><input type="radio" name="hasAutoimmuneDiagnosis" value="yes" checked={formData.hasAutoimmuneDiagnosis === true} onChange={() => handleChange('hasAutoimmuneDiagnosis', true)} className="mr-1.5"/> Yes</label><label className="flex items-center"><input type="radio" name="hasAutoimmuneDiagnosis" value="no" checked={formData.hasAutoimmuneDiagnosis === false} onChange={() => handleChange('hasAutoimmuneDiagnosis', false)} className="mr-1.5"/> No</label></div></div>
        {parentWoundType === 'pressure_ulcer' && (
            <div><label className="block text-sm font-medium text-gray-700">If pressure ulcer present what is the leading type:</label>
              <div className="mt-2 space-x-4">
                <label className="flex items-center"><input type="radio" name="pressureUlcerLeadingType" value="bed" checked={formData.pressureUlcerLeadingType === 'bed'} onChange={() => handleChange('pressureUlcerLeadingType', 'bed')} className="mr-1.5"/> Bed</label>
                <label className="flex items-center"><input type="radio" name="pressureUlcerLeadingType" value="wheelchair-cushion" checked={formData.pressureUlcerLeadingType === 'wheelchair-cushion'} onChange={() => handleChange('pressureUlcerLeadingType', 'wheelchair-cushion')} className="mr-1.5"/> Wheel chair cushion</label>
              </div>
            </div>
        )}
      </div>
    );
  };

  const renderClinicalPhotos = () => {
    // Assuming clinical_photos is a key in SkinSubstituteChecklistInput that maps to ClinicalPhotosData
    // const data = formData as Partial<SkinSubstituteChecklistInput['clinical_photos']>; // This would require clinical_photos key
    const photoNotes = (formData as any).photo_notes; // Example if notes are stored flatly

    return (
      <div className="space-y-4">
      <h3 className="text-lg font-medium text-gray-900">Clinical Photos</h3>
        <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
          <Upload className="mx-auto h-12 w-12 text-gray-400" />
            <label htmlFor="photo-upload" className="cursor-pointer block text-sm font-medium text-blue-600 hover:text-blue-500 mt-2">
                Upload wound photos
                <input id="photo-upload" name="photo-upload" type="file" multiple accept="image/*" className="sr-only" />
            </label>
            <p className="text-xs text-gray-500 mt-1">PNG, JPG, GIF up to 10MB</p>
        </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Notes for Photos</label>
            <textarea value={photoNotes || ''} onChange={e => handleChange('photo_notes' as any, e.target.value)} rows={3} className={`${inputBaseClasses} mt-1`} placeholder="Optional notes about the photos..."></textarea>
      </div>
    </div>
  );
  };

  // Main render logic based on activeSection (which is SspUiSectionKey for wound_care)
  const renderActiveSectionContent = () => {
    switch (activeSection) {
      case SSP_UI_SECTIONS.DIAGNOSIS: return renderSSPDiagnosis();
      case SSP_UI_SECTIONS.LAB_RESULTS: return renderSSPLabResults();
      case SSP_UI_SECTIONS.WOUND_DESCRIPTION: return renderSSPWoundDescription();
      case SSP_UI_SECTIONS.CIRCULATION: return renderSSPCirculation();
      case SSP_UI_SECTIONS.CONSERVATIVE_TREATMENT: return renderSSPConservativeMeasures();
      case 'clinical_photos': return renderClinicalPhotos(); // Generic key if used
      default:
        return <div>Select a section. (Unknown section: {activeSection})</div>;
    }
  };

  return (
    <div className="space-y-6">
      {errorsForSection.length > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
          <div className="flex items-start">
            <AlertCircle className="h-5 w-5 text-red-600 mt-0.5 mr-3 flex-shrink-0" />
            <div>
              <h4 className="text-sm font-medium text-red-900">Please correct the following errors:</h4>
              <ul className="text-sm text-red-700 mt-1 list-disc list-inside">
                {errorsForSection.map((error, index) => (
                  <li key={index}>{error}</li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}
      {renderActiveSectionContent()}
    </div>
  );
};

export default WoundCareAssessmentForm;
