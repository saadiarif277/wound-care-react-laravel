<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\HealthData\DTO\SkinSubstituteChecklistInput; // For reference

class SkinSubstituteChecklistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $orderId = $this->route('orderId');
        if (!$orderId) {
            // If orderId is not in the route, deny access or handle as appropriate
            return false;
        }

        $order = \App\Models\Order\Order::find($orderId); // Use fully qualified name if Order model resolution is tricky
        if (!$order) {
            // Order not found, deny access or handle as appropriate (e.g., throw a 404 in controller)
            // For authorization, returning false is typical if the resource doesn't exist for the check.
            return false;
        }

        return $this->user()->can('addChecklist', $order);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patientName' => ['required', 'string', 'max:255'],
            'dateOfBirth' => ['required', 'date_format:Y-m-d'],
            'dateOfProcedure' => ['required', 'date_format:Y-m-d'],

            'hasDiabetes' => ['required', 'boolean'],
            'diabetesType' => ['nullable', 'string', Rule::in(['1', '2'])],
            'hasVenousStasisUlcer' => ['required', 'boolean'],
            'hasPressureUlcer' => ['required', 'boolean'],
            'pressureUlcerStage' => ['nullable', 'string', Rule::in(['1', '2', '3', '4', 'unstageable', 'suspected-deep-tissue-injury'])], // Assuming stages
            'location' => ['required', 'string', 'max:255'], // General diagnosis location/laterality
            'ulcerLocation' => ['required', 'string', 'max:255'], // Specific ulcer site

            'hba1cResult' => ['nullable', 'numeric'],
            'hba1cDate' => ['nullable', 'date_format:Y-m-d'],
            'albuminResult' => ['nullable', 'numeric'],
            'albuminDate' => ['nullable', 'date_format:Y-m-d'],
            'cbcPerformed' => ['nullable', 'boolean'],
            'crapResult' => ['nullable', 'numeric'], // Name from DTO, assuming it is CRP
            'hh' => ['nullable', 'string', 'max:50'], // Combined H&H
            'cultureDate' => ['nullable', 'date_format:Y-m-d'],
            'sedRate' => ['nullable', 'numeric'],
            'treated' => ['required', 'boolean'], // Infection treated

            'depth' => ['required', 'string', Rule::in(['full-thickness', 'partial-thickness'])],
            'ulcerDuration' => ['required', 'string', 'max:100'], // e.g., "3 weeks", "2 months"
            'exposedStructures' => ['nullable', 'array'],
            'exposedStructures.*' => ['string', Rule::in(['muscle', 'tendon', 'bone', 'joint', 'fascia', 'none'])], // Assuming possible values
            'length' => ['required', 'numeric', 'min:0'],
            'width' => ['required', 'numeric', 'min:0'],
            'woundDepth' => ['required', 'numeric', 'min:0'],
            'hasInfection' => ['required', 'boolean'],
            'hasNecroticTissue' => ['required', 'boolean'],
            'hasCharcotDeformity' => ['required', 'boolean'],
            'hasMalignancy' => ['required', 'boolean'],

            'abiResult' => ['nullable', 'numeric'],
            'abiDate' => ['nullable', 'date_format:Y-m-d'],
            'pedalPulsesResult' => ['nullable', 'string', 'max:255'],
            'pedalPulsesDate' => ['nullable', 'date_format:Y-m-d'],
            'tcpo2Result' => ['nullable', 'numeric'],
            'tcpo2Date' => ['nullable', 'date_format:Y-m-d'],
            'hasTriphasicWaveforms' => ['required', 'boolean'],
            'waveformResult' => ['nullable', 'string', 'max:255'],
            'waveformDate' => ['nullable', 'date_format:Y-m-d'],
            'imagingType' => ['nullable', 'string', Rule::in(['xray', 'ct', 'mri', 'none'])],

            'debridementPerformed' => ['required', 'boolean'],
            'moistDressingsApplied' => ['required', 'boolean'],
            'nonWeightBearing' => ['required', 'boolean'],
            'pressureReducingFootwear' => ['required', 'boolean'],
            'footwearType' => ['nullable', 'string', 'max:255'],
            'standardCompression' => ['required', 'boolean'],
            'currentHbot' => ['required', 'boolean'],
            'smokingStatus' => ['required', 'string', Rule::in(['smoker', 'previous-smoker', 'non-smoker'])],
            'smokingCounselingProvided' => ['nullable', 'boolean'],
            'receivingRadiationOrChemo' => ['required', 'boolean'],
            'takingImmuneModulators' => ['required', 'boolean'],
            'hasAutoimmuneDiagnosis' => ['required', 'boolean'],
            'pressureUlcerLeadingType' => ['nullable', 'string', Rule::in(['bed', 'wheelchair-cushion', 'other', 'unknown'])], // Assuming values
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patientName.required' => 'Patient name is required.',
            'dateOfBirth.required' => 'Date of birth is required.',
            'dateOfBirth.date_format' => 'Date of birth must be in YYYY-MM-DD format.',
            // Add more custom messages as needed for clarity
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * This method can be used to transform data before validation,
     * for example, converting empty strings to null for nullable boolean fields.
     */
    protected function prepareForValidation(): void
    {
        // Example: Convert empty strings to null for nullable boolean fields
        // to ensure 'nullable' and 'boolean' rules work as expected.
        $nullableBooleans = [
            'smokingCounselingProvided', 'cbcPerformed',
            // Add other fields that are boolean but can be submitted as empty strings
        ];

        foreach ($nullableBooleans as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
         // Ensure boolean fields are correctly interpreted if sent as strings "true"/"false"
        $booleanFields = [
            'hasDiabetes', 'hasVenousStasisUlcer', 'hasPressureUlcer', 'treated', 'hasInfection',
            'hasNecroticTissue', 'hasCharcotDeformity', 'hasMalignancy', 'hasTriphasicWaveforms',
            'debridementPerformed', 'moistDressingsApplied', 'nonWeightBearing',
            'pressureReducingFootwear', 'standardCompression', 'currentHbot',
            'smokingCounselingProvided', 'receivingRadiationOrChemo', 'takingImmuneModulators',
            'hasAutoimmuneDiagnosis', 'cbcPerformed'
            // Add all boolean fields from your rules() method
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                if (is_string($value)) {
                    if (strtolower($value) === 'true') {
                        $this->merge([$field => true]);
                    } elseif (strtolower($value) === 'false') {
                        $this->merge([$field => false]);
                    }
                    // If it's an empty string and the field is nullable, it will be handled
                    // by the nullableBooleans logic or pass as null if not in that list
                }
            }
        }
    }
} 