<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InsuranceCardScanned
{
    use Dispatchable, SerializesModels;
    
    public string $patientId;
    public array $extractedData;
    public ?string $scanMethod;
    
    public function __construct(string $patientId, array $extractedData, ?string $scanMethod = 'azure_ocr')
    {
        $this->patientId = $patientId;
        $this->extractedData = $extractedData;
        $this->scanMethod = $scanMethod;
    }
}
