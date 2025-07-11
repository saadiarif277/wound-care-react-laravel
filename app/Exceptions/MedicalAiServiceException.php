<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class MedicalAiServiceException extends Exception
{
    protected $statusCode;
    protected $errorType;
    protected $details;

    public function __construct($message = "", $statusCode = 500, Exception $previous = null, $errorType = 'UnknownError', $details = [])
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errorType = $errorType;
        $this->details = $details;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getErrorType()
    {
        return $this->errorType;
    }

    public function getDetails()
    {
        return $this->details;
    }

    public function report()
    {
        // Log the exception with context
        Log::error('MedicalAiServiceException: ' . $this->getMessage(), [
            'status_code' => $this->statusCode,
            'error_type' => $this->errorType,
            'details' => $this->details,
            'trace' => $this->getTraceAsString(),
        ]);
    }
}
