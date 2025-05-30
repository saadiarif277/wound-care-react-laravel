<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SkinSubstituteChecklistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization logic will be handled by the controller action using $this->authorize()
        // or via middleware, so typically return true here if using policies for authorization.
        // For now, assuming authorization is handled elsewhere or is open for authenticated users.
        return true; 
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Patient Information (from DTO - often pre-filled or from other sources, but validate if submitted)
            'patientName' => 'required|string|max:255',
            'dateOfBirth' => 'required|date_format:Y-m-d',
            'dateOfProcedure' => 'required|date_format:Y-m-d',
            
            // Diagnosis
            'hasDiabetes' => 'required|boolean',
            'diabetesType' => [Rule::requiredIf($this->input('hasDiabetes') == true), 'nullable', Rule::in(['1', '2'])],
            'hasVenousStasisUlcer' => 'required|boolean',
            'hasPressureUlcer' => 'required|boolean',
            'pressureUlcerStage' => [Rule::requiredIf($this->input('hasPressureUlcer') == true), 'nullable', 'string', 'max:50'],
            'location' => 'required|string|max:255', // General diagnosis location/laterality
            'ulcerLocation' => 'required|string|max:255', // Specific ulcer site
            
            // Lab Results
            'hba1cResult' => 'nullable|numeric',
            'hba1cDate' => 'nullable|date_format:Y-m-d|required_with:hba1cResult',
            'albuminResult' => 'nullable|numeric',
            'albuminDate' => 'nullable|date_format:Y-m-d|required_with:albuminResult',
            'cbcPerformed' => 'nullable|boolean',
            'crapResult' => 'nullable|numeric', // Assuming crpResult
            'hh' => 'nullable|string|max:50',
            'cultureDate' => 'nullable|date_format:Y-m-d',
            'sedRate' => 'nullable|numeric',
            'treated' => 'required|boolean', // Infection treated
            
            // Wound Description
            'depth' => ['required', Rule::in(['full-thickness', 'partial-thickness'])],
            'ulcerDuration' => 'required|string|max:100',
            'exposedStructures' => 'nullable|array',
            'exposedStructures.*' => [Rule::in(['muscle', 'tendon', 'bone'])], // 'none' handled by empty array or specific logic
            'length' => 'required|numeric|min:0',
            'width' => 'required|numeric|min:0',
            'woundDepth' => 'required|numeric|min:0',
            'hasInfection' => 'required|boolean',
            'hasNecroticTissue' => 'required|boolean',
            'hasCharcotDeformity' => 'required|boolean',
            'hasMalignancy' => 'required|boolean',
            
            // Circulation Testing
            'abiResult' => 'nullable|numeric',
            'abiDate' => 'nullable|date_format:Y-m-d|required_with:abiResult',
            'pedalPulsesResult' => 'nullable|string|max:255',
            'pedalPulsesDate' => 'nullable|date_format:Y-m-d|required_with:pedalPulsesResult',
            'tcpo2Result' => 'nullable|numeric',
            'tcpo2Date' => 'nullable|date_format:Y-m-d|required_with:tcpo2Result',
            'hasTriphasicWaveforms' => 'required|boolean',
            'waveformResult' => 'nullable|string|max:255',
            'waveformDate' => 'nullable|date_format:Y-m-d|required_with:waveformResult',
            'imagingType' => ['nullable', Rule::in(['xray', 'ct', 'mri', 'none'])],
            
            // Conservative Treatment (Past 30 Days)
            'debridementPerformed' => 'required|boolean',
            'moistDressingsApplied' => 'required|boolean',
            'nonWeightBearing' => 'required|boolean',
            'pressureReducingFootwear' => 'required|boolean',
            'footwearType' => [Rule::requiredIf($this->input('pressureReducingFootwear') == true), 'nullable', 'string', 'max:255'],
            'standardCompression' => 'required|boolean',
            'currentHbot' => 'required|boolean',
            'smokingStatus' => ['required', Rule::in(['smoker', 'previous-smoker', 'non-smoker'])],
            'smokingCounselingProvided' => [Rule::requiredIf($this->input('smokingStatus') === 'smoker'), 'nullable', 'boolean'],
            'receivingRadiationOrChemo' => 'required|boolean',
            'takingImmuneModulators' => 'required|boolean',
            'hasAutoimmuneDiagnosis' => 'required|boolean',
            'pressureUlcerLeadingType' => [
                Rule::requiredIf(fn () => $this->input('hasPressureUlcer') == true && $this->input('wound_type') === 'pressure_ulcer'), // wound_type from main form context
                'nullable', 
                Rule::in(['bed', 'wheelchair-cushion'])
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'diabetesType.requiredIf' => 'The diabetes type is required when diabetes is indicated.',
            'pressureUlcerStage.requiredIf' => 'The pressure ulcer stage is required when pressure ulcer is indicated.',
            'footwearType.requiredIf' => 'The footwear type is required if pressure reducing footwear is used.',
            'smokingCounselingProvided.requiredIf' => 'Smoking cessation counseling status is required if patient is a smoker.',
            'pressureUlcerLeadingType.requiredIf' => 'The leading type for pressure ulcer is required if the main wound is a pressure ulcer and pressure ulcer is indicated in diagnosis.',
            // Add more custom messages as needed
        ];
    }
} 