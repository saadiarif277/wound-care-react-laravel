<?php

namespace App\Services\HealthData\DTO;

// Carbon can be used if date manipulation/formatting is needed beyond simple strings.
// use Carbon\Carbon;

class SkinSubstituteChecklistInput
{
    // Patient Information (Matches TS Interface based on PHP DTO provided by user)
    public string $patientName = '';
    public string $dateOfBirth = ''; // YYYY-MM-DD
    public string $dateOfProcedure = ''; // YYYY-MM-DD
    
    // Diagnosis (Matches TS Interface based on PHP DTO)
    public bool $hasDiabetes = false;
    public ?string $diabetesType = null; // '1' or '2'
    public bool $hasVenousStasisUlcer = false;
    public bool $hasPressureUlcer = false;
    public ?string $pressureUlcerStage = null;
    public string $location = ''; // General diagnosis location/laterality from DTO
    public string $ulcerLocation = ''; // Specific ulcer site from DTO
    
    // Lab Results (Matches TS Interface based on PHP DTO)
    public ?float $hba1cResult = null;
    public ?string $hba1cDate = null; // YYYY-MM-DD
    public ?float $albuminResult = null;
    public ?string $albuminDate = null; // YYYY-MM-DD
    public ?bool $cbcPerformed = null;
    public ?float $crapResult = null; // Typo in user DTO, should likely be crpResult
    public ?string $hh = null; // Combined H&H string as per DTO
    // public ?float $hematocritResult = null; // DTO had hh as string, not separate hematocrit
    public ?string $cultureDate = null; // YYYY-MM-DD
    public ?float $sedRate = null;
    public bool $treated = false; // Infection treated
    
    // Wound Description (Matches TS Interface based on PHP DTO)
    public string $depth = 'full-thickness'; // 'full-thickness' or 'partial-thickness'
    public string $ulcerDuration = '';
    public array $exposedStructures = []; // string[]: ['muscle', 'tendon', 'bone'] - DTO was string[]
    public float $length = 0.0;
    public float $width = 0.0;
    public float $woundDepth = 0.0; // Numeric wound depth from DTO
    public bool $hasInfection = false;
    public bool $hasNecroticTissue = false;
    public bool $hasCharcotDeformity = false;
    public bool $hasMalignancy = false;
    
    // Circulation Testing (Matches TS Interface based on PHP DTO)
    public ?float $abiResult = null;
    public ?string $abiDate = null; // YYYY-MM-DD
    public ?string $pedalPulsesResult = null;
    public ?string $pedalPulsesDate = null; // YYYY-MM-DD
    public ?float $tcpo2Result = null;
    public ?string $tcpo2Date = null; // YYYY-MM-DD
    public bool $hasTriphasicWaveforms = false; 
    public ?string $waveformResult = null;
    public ?string $waveformDate = null; // YYYY-MM-DD
    public ?string $imagingType = null; // 'xray', 'ct', 'mri', 'none'
    
    // Conservative Treatment (Past 30 Days) (Matches TS Interface based on PHP DTO)
    public bool $conservativeCareProvided = false;
    public int $conservativeCareWeeks = 0;
    public array $conservativeCareTypes = []; // Array of treatment types: ['offloading', 'dressings', 'compression', etc.]
    public bool $debridementPerformed = false;
    public bool $moistDressingsApplied = false;
    public bool $nonWeightBearing = false;
    public bool $pressureReducingFootwear = false;
    public ?string $footwearType = null;
    public bool $standardCompression = false;
    public bool $currentHbot = false;
    public string $smokingStatus = 'non-smoker'; // 'smoker', 'previous-smoker', 'non-smoker'
    public ?bool $smokingCounselingProvided = null;
    public bool $receivingRadiationOrChemo = false;
    public bool $takingImmuneModulators = false;
    public bool $hasAutoimmuneDiagnosis = false;
    public ?string $pressureUlcerLeadingType = null; // 'bed', 'wheelchair-cushion'

    /**
     * Constructor to allow partial initialization
     * All properties have defaults or are nullable to support flexible instantiation
     */
    public function __construct(array $data = [])
    {
        // Initialize with defaults, then override with provided data
        foreach ($data as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }

    /**
     * Create from request array (typically validated request data)
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        
        foreach ($dto->getPublicProperties() as $property => $type) {
            if (array_key_exists($property, $data)) {
                $value = $data[$property];
                // Basic type casting based on property type, can be expanded
                if (str_starts_with($type, '?')) {
                    $type = substr($type, 1);
                }
                if ($value === null && str_starts_with(gettype($dto->$property), '?')) {
                     $dto->$property = null;
                } elseif ($type === 'bool' || $type === 'boolean') {
                    $dto->$property = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                } elseif ($type === 'float' || $type === 'double') {
                    $dto->$property = is_numeric($value) ? (float)$value : null;
                } elseif ($type === 'int' || $type === 'integer') {
                    $dto->$property = is_numeric($value) ? (int)$value : null;
                } elseif ($type === 'array' && is_array($value)) {
                    $dto->$property = $value;
                } elseif ($type === 'string') {
                    $dto->$property = (string)$value;
                } else {
                    // For ?string, ?float etc. if value is null, it's already handled by initial check
                    // If not null, try direct assignment or specific parsing if needed (e.g. Carbon for dates)
                    $dto->$property = $value;
                }
            }
        }
        return $dto;
    }

    /**
     * Convert to array for serialization or other uses
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Helper to get public properties and their types (simplified)
     * For more robust reflection, ReflectionClass would be used.
     */
    private function getPublicProperties(): array
    {
        $reflection = new \ReflectionClass($this);
        $props = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $docComment = $prop->getDocComment();
            $type = 'mixed'; // Default
            if ($docComment && preg_match('/@var\s+([^\s]+)/', $docComment, $matches)) {
                $type = $matches[1];
            }
            $props[$prop->getName()] = $type;
        }
        return $props;
    }
} 