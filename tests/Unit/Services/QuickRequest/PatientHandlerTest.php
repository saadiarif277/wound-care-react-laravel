<?php

declare(strict_types=1);

namespace Tests\Unit\Services\QuickRequest;

use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\FhirService;
use App\Services\Compliance\PhiAuditService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PatientHandlerTest extends TestCase
{
    private PatientHandler $handler;
    private FhirService $fhirService;
    private PhiAuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fhirService = $this->createMock(FhirService::class);
        $this->auditService = $this->createMock(PhiAuditService::class);

        $this->handler = new PatientHandler($this->fhirService, $this->auditService);
    }

    public function test_handles_new_patient_creation(): void
    {
        $patientData = [
            'patient' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'middleName' => 'Michael',
                'dateOfBirth' => '1990-01-01',
                'gender' => 'male',
                'ssn' => '123-45-6789',
                'medicareNumber' => 'ABC123456789',
                'address' => [
                    'use' => 'home',
                    'type' => 'physical',
                    'line' => ['123 Main St'],
                    'city' => 'Anytown',
                    'state' => 'NY',
                    'postalCode' => '12345',
                ],
                'phone' => '555-123-4567',
                'email' => 'john.doe@example.com',
            ],
        ];

        $expectedFhirPatient = [
            'id' => 'patient-123',
            'resourceType' => 'Patient',
        ];

        $this->fhirService->expects($this->once())
            ->method('search')
            ->willReturn(['entry' => []]);

        $this->fhirService->expects($this->once())
            ->method('create')
            ->with('Patient', $this->anything())
            ->willReturn($expectedFhirPatient);

        $this->auditService->expects($this->once())
            ->method('logAccess')
            ->with('patient_created', 'patient-123', 1);

        $result = $this->handler->handle($patientData, 1);

        $this->assertEquals('patient-123', $result['patient_fhir_id']);
        $this->assertEquals('JODO123', $result['patient_display']);
    }

    public function test_handles_existing_patient_with_deduplication(): void
    {
        $patientData = [
            'patient' => [
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'dateOfBirth' => '1985-05-15',
                'gender' => 'female',
                'ssn' => '987-65-4321',
                'address' => [
                    'use' => 'home',
                    'type' => 'physical',
                    'line' => ['456 Oak Ave'],
                    'city' => 'Somewhere',
                    'state' => 'CA',
                    'postalCode' => '90210',
                ],
                'phone' => '555-987-6543',
            ],
        ];

        $existingPatient = [
            'id' => 'patient-existing-456',
            'resourceType' => 'Patient',
            'name' => [
                [
                    'family' => 'Smith',
                    'given' => ['Jane'],
                ],
            ],
        ];

        $this->fhirService->expects($this->once())
            ->method('search')
            ->willReturn([
                'entry' => [
                    ['resource' => $existingPatient],
                ],
            ]);

        $this->fhirService->expects($this->once())
            ->method('update')
            ->with('Patient', 'patient-existing-456', $this->anything())
            ->willReturn($existingPatient);

        $this->auditService->expects($this->once())
            ->method('logAccess')
            ->with('patient_updated', 'patient-existing-456', 1);

        $result = $this->handler->handle($patientData, 1);

        $this->assertEquals('patient-existing-456', $result['patient_fhir_id']);
        $this->assertEquals('JASM456', $result['patient_display']);
    }

    public function test_creates_patient_display_id_correctly(): void
    {
        $testCases = [
            ['John', 'Doe', 'JODO'],
            ['Jane', 'Smith', 'JASM'],
            ['Mary', 'Johnson', 'MAJO'],
            ['A', 'B', 'AB'],
            ['', 'Doe', 'DO'],
            ['John', '', 'JO'],
        ];

        foreach ($testCases as [$firstName, $lastName, $expectedPrefix]) {
            $patientData = [
                'patient' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'dateOfBirth' => '1990-01-01',
                    'gender' => 'other',
                    'address' => [
                        'use' => 'home',
                        'type' => 'physical',
                        'line' => ['123 Test St'],
                        'city' => 'Test',
                        'state' => 'TS',
                        'postalCode' => '12345',
                    ],
                    'phone' => '555-555-5555',
                ],
            ];

            $this->fhirService->method('search')->willReturn(['entry' => []]);
            $this->fhirService->method('create')->willReturn(['id' => 'test-id']);

            $result = $this->handler->handle($patientData, 1);

            $this->assertStringStartsWith($expectedPrefix, $result['patient_display']);
            $this->assertEquals(7, strlen($result['patient_display'])); // 4 letters + 3 digits
        }
    }

    public function test_handles_insurance_data(): void
    {
        $patientData = [
            'patient' => [
                'firstName' => 'Test',
                'lastName' => 'User',
                'dateOfBirth' => '2000-01-01',
                'gender' => 'male',
                'address' => [
                    'use' => 'home',
                    'type' => 'physical',
                    'line' => ['789 Test Ln'],
                    'city' => 'Testville',
                    'state' => 'TX',
                    'postalCode' => '77777',
                ],
                'phone' => '555-000-0000',
            ],
            'insurance' => [
                'primary' => [
                    'type' => 'medicare',
                    'policyNumber' => 'MED123456',
                    'subscriberId' => 'SUB123456',
                    'subscriberName' => 'Test User',
                    'subscriberRelationship' => 'self',
                    'effectiveDate' => '2024-01-01',
                    'payorName' => 'Medicare',
                ],
                'secondary' => [
                    'type' => 'private',
                    'policyNumber' => 'PRIV789012',
                    'subscriberId' => 'SUB789012',
                    'subscriberName' => 'Test User',
                    'subscriberRelationship' => 'self',
                    'effectiveDate' => '2024-01-01',
                    'payorName' => 'Blue Cross',
                ],
            ],
        ];

        $this->fhirService->method('search')->willReturn(['entry' => []]);
        $this->fhirService->method('create')
            ->willReturnCallback(function ($resourceType, $data) {
                return ['id' => strtolower($resourceType) . '-' . uniqid()];
            });

        $result = $this->handler->handle($patientData, 1);

        $this->assertArrayHasKey('patient_fhir_id', $result);
        $this->assertArrayHasKey('insurance_data', $result);
        $this->assertArrayHasKey('primary', $result['insurance_data']);
        $this->assertArrayHasKey('secondary', $result['insurance_data']);
        $this->assertArrayHasKey('coverage_fhir_id', $result['insurance_data']['primary']);
        $this->assertArrayHasKey('coverage_fhir_id', $result['insurance_data']['secondary']);
    }

    public function test_handles_fhir_service_errors(): void
    {
        $patientData = [
            'patient' => [
                'firstName' => 'Error',
                'lastName' => 'Test',
                'dateOfBirth' => '1990-01-01',
                'gender' => 'male',
                'address' => [
                    'use' => 'home',
                    'type' => 'physical',
                    'line' => ['123 Error St'],
                    'city' => 'Errorville',
                    'state' => 'ER',
                    'postalCode' => '99999',
                ],
                'phone' => '555-999-9999',
            ],
        ];

        $this->fhirService->method('search')
            ->willThrowException(new \Exception('FHIR service unavailable'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('FHIR service unavailable');

        $this->handler->handle($patientData, 1);
    }

    public function test_validates_required_fields(): void
    {
        $invalidData = [
            'patient' => [
                'firstName' => 'Test',
                // Missing required fields
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->handle($invalidData, 1);
    }
}
