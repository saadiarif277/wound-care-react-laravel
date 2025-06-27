<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class FhirException extends Exception
{
    private string $issueCode;
    private array $operationOutcome;

    public function __construct(
        string $message = '',
        string $issueCode = 'exception',
        array $operationOutcome = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->issueCode = $issueCode;
        $this->operationOutcome = $operationOutcome;
    }

    /**
     * Get the FHIR issue code
     */
    public function getIssueCode(): string
    {
        return $this->issueCode;
    }

    /**
     * Get the OperationOutcome resource
     */
    public function getOperationOutcome(): array
    {
        if (!empty($this->operationOutcome)) {
            return $this->operationOutcome;
        }

        return [
            'resourceType' => 'OperationOutcome',
            'issue' => [
                [
                    'severity' => 'error',
                    'code' => $this->issueCode,
                    'details' => [
                        'text' => $this->getMessage(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Create from OperationOutcome resource
     */
    public static function fromOperationOutcome(array $operationOutcome): self
    {
        $message = 'FHIR operation failed';
        $issueCode = 'exception';

        if (isset($operationOutcome['issue'][0])) {
            $issue = $operationOutcome['issue'][0];
            $message = $issue['details']['text'] ?? $issue['diagnostics'] ?? $message;
            $issueCode = $issue['code'] ?? $issueCode;
        }

        return new self($message, $issueCode, $operationOutcome);
    }
}