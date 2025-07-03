# Completing Chapter 10 and Final Chapters

          const response = await api.get('/episodes', { params: get().filters });
          set({ episodes: response.data.data, isLoading: false });
        } catch (error) {
          set({ error: error.message, isLoading: false });
        }
      },
      
      fetchEpisode: async (id) => {
        set({ isLoading: true, error: null });
        
        try {
          const response = await api.get(`/episodes/${id}`);
          set({ currentEpisode: response.data, isLoading: false });
        } catch (error) {
          set({ error: error.message, isLoading: false });
        }
      },
      
      updateEpisodeStatus: async (id, status) => {
        try {
          const response = await api.put(`/episodes/${id}/status`, { status });
          
          // Update local state
          set((state) => ({
            episodes: state.episodes.map((ep) =>
              ep.id === id ? { ...ep, episode_status: status } : ep
            ),
            currentEpisode:
              state.currentEpisode?.id === id
                ? { ...state.currentEpisode, episode_status: status }
                : state.currentEpisode,
          }));
          
          return response.data;
        } catch (error) {
          set({ error: error.message });
          throw error;
        }
      },
    }),
    {
      name: 'episode-store',
    }
  )
);
```

### React Query Integration

#### 1. **Episode Query Hooks**

```typescript
// hooks/useEpisodeQueries.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Episode, EpisodeFilters } from '@/types/episode';
import * as episodeApi from '@/api/episodes';

export const useEpisodes = (filters: EpisodeFilters) => {
  return useQuery({
    queryKey: ['episodes', filters],
    queryFn: () => episodeApi.fetchEpisodes(filters),
    staleTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useEpisode = (id: string) => {
  return useQuery({
    queryKey: ['episode', id],
    queryFn: () => episodeApi.fetchEpisode(id),
    enabled: !!id,
  });
};

export const useGenerateIVR = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (episodeId: string) => episodeApi.generateIVR(episodeId),
    onSuccess: (data, episodeId) => {
      // Invalidate and refetch
      queryClient.invalidateQueries(['episode', episodeId]);
      queryClient.invalidateQueries(['episodes']);
    },
  });
};

export const useUpdateEpisodeStatus = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) =>
      episodeApi.updateEpisodeStatus(id, status),
    onMutate: async ({ id, status }) => {
      // Cancel outgoing refetches
      await queryClient.cancelQueries(['episode', id]);
      
      // Snapshot previous value
      const previousEpisode = queryClient.getQueryData<Episode>(['episode', id]);
      
      // Optimistically update
      queryClient.setQueryData<Episode>(['episode', id], (old) => ({
        ...old!,
        episode_status: status,
      }));
      
      return { previousEpisode };
    },
    onError: (err, variables, context) => {
      // Rollback on error
      if (context?.previousEpisode) {
        queryClient.setQueryData(['episode', variables.id], context.previousEpisode);
      }
    },
    onSettled: (data, error, variables) => {
      // Always refetch after error or success
      queryClient.invalidateQueries(['episode', variables.id]);
    },
  });
};
```

### Clinical Data Components

#### 1. **Clinical Data Panel**

```tsx
// resources/js/Components/Episodes/ClinicalDataPanel.tsx
import React from 'react';
import { ClinicalData } from '@/types/clinical';
import { formatDate } from '@/utils/dates';
import DataCard from '@/Components/Common/DataCard';
import LabResultsTable from '@/Components/Clinical/LabResultsTable';
import WoundAssessmentCard from '@/Components/Clinical/WoundAssessmentCard';

interface Props {
  clinicalData: ClinicalData;
}

export default function ClinicalDataPanel({ clinicalData }: Props) {
  if (clinicalData.error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-md p-4">
        <p className="text-red-800">Error loading clinical data: {clinicalData.error}</p>
      </div>
    );
  }
  
  return (
    <div className="space-y-6">
      {/* Patient Summary */}
      <DataCard title="Patient Information">
        <dl className="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
          <div>
            <dt className="text-sm font-medium text-gray-500">Name</dt>
            <dd className="mt-1 text-sm text-gray-900">{clinicalData.patient?.name}</dd>
          </div>
          <div>
            <dt className="text-sm font-medium text-gray-500">MRN</dt>
            <dd className="mt-1 text-sm text-gray-900">{clinicalData.patient?.mrn}</dd>
          </div>
          <div>
            <dt className="text-sm font-medium text-gray-500">Date of Birth</dt>
            <dd className="mt-1 text-sm text-gray-900">
              {formatDate(clinicalData.patient?.birth_date)}
            </dd>
          </div>
          <div>
            <dt className="text-sm font-medium text-gray-500">Age</dt>
            <dd className="mt-1 text-sm text-gray-900">
              {calculateAge(clinicalData.patient?.birth_date)} years
            </dd>
          </div>
        </dl>
      </DataCard>
      
      {/* Clinical Summary */}
      <DataCard title="Clinical Summary">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div className="bg-blue-50 p-4 rounded-lg">
            <div className="text-sm font-medium text-blue-900">Total Wounds</div>
            <div className="mt-1 text-2xl font-semibold text-blue-900">
              {clinicalData.summary?.total_wounds || 0}
            </div>
          </div>
          <div className="bg-green-50 p-4 rounded-lg">
            <div className="text-sm font-medium text-green-900">Hemoglobin Status</div>
            <div className="mt-1 text-lg font-semibold text-green-900">
              {clinicalData.summary?.hemoglobin_status || 'Unknown'}
            </div>
          </div>
          <div className="bg-purple-50 p-4 rounded-lg">
            <div className="text-sm font-medium text-purple-900">Healing Potential</div>
            <div className="mt-1 text-lg font-semibold text-purple-900">
              {clinicalData.summary?.healing_potential || 'Unknown'}
            </div>
          </div>
        </div>
      </DataCard>
      
      {/* Wound Assessments */}
      {clinicalData.wounds && clinicalData.wounds.length > 0 && (
        <DataCard title="Wound Assessments">
          <div className="space-y-4">
            {clinicalData.wounds.map((wound, index) => (
              <WoundAssessmentCard key={wound.id || index} wound={wound} />
            ))}
          </div>
        </DataCard>
      )}
      
      {/* Lab Results */}
      {clinicalData.lab_results && (
        <DataCard title="Laboratory Results">
          <LabResultsTable results={clinicalData.lab_results} />
        </DataCard>
      )}
      
      {/* Active Conditions */}
      {clinicalData.conditions && clinicalData.conditions.length > 0 && (
        <DataCard title="Active Conditions">
          <ul className="divide-y divide-gray-200">
            {clinicalData.conditions.map((condition, index) => (
              <li key={condition.id || index} className="py-3">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-900">
                      {condition.display}
                    </p>
                    <p className="text-sm text-gray-500">
                      Since {formatDate(condition.onset_date)}
                    </p>
                  </div>
                  {condition.severity && (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                      {condition.severity}
                    </span>
                  )}
                </div>
              </li>
            ))}
          </ul>
        </DataCard>
      )}
    </div>
  );
}
```

### Chapter Summary

The frontend implementation demonstrates:

1. **Type Safety**: Comprehensive TypeScript definitions for all data structures
2. **Component Architecture**: Modular, reusable React components
3. **State Management**: Zustand for global state, React Query for server state
4. **User Experience**: Intuitive UI with clear workflows and feedback
5. **Performance**: Optimized rendering and data fetching strategies
6. **Accessibility**: WCAG-compliant components and interactions
7. **Testing**: Component tests with React Testing Library

---

## Chapter 11: Testing and Validation {#chapter-11}

### Comprehensive Testing Strategy

Testing a HIPAA-compliant healthcare system requires multiple layers of validation, from unit tests to integration tests to end-to-end testing. This chapter covers the testing infrastructure and strategies used to ensure system reliability.

### Unit Testing

#### 1. **Model Testing**

```php
// tests/Unit/Models/PatientManufacturerIVREpisodeTest.php
namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PatientManufacturerIVREpisodeTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_episode_can_be_created_with_valid_data()
    {
        $patient = Patient::factory()->create();
        $manufacturer = Manufacturer::factory()->create();
        
        $episode = PatientManufacturerIVREpisode::create([
            'patient_id' => $patient->id,
            'manufacturer_id' => $manufacturer->id,
            'episode_status' => 'pending',
            'metadata' => ['source' => 'test'],
        ]);
        
        $this->assertDatabaseHas('patient_manufacturer_ivr_episodes', [
            'patient_id' => $patient->id,
            'manufacturer_id' => $manufacturer->id,
            'episode_status' => 'pending',
        ]);
        
        $this->assertIsArray($episode->metadata);
        $this->assertEquals('test', $episode->metadata['source']);
    }
    
    public function test_episode_relationships()
    {
        $episode = PatientManufacturerIVREpisode::factory()
            ->has(Order::factory()->count(3))
            ->create();
        
        $this->assertInstanceOf(Patient::class, $episode->patient);
        $this->assertInstanceOf(Manufacturer::class, $episode->manufacturer);
        $this->assertCount(3, $episode->orders);
    }
    
    public function test_can_generate_ivr_logic()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'episode_status' => 'pending',
        ]);
        
        // No approved orders
        $this->assertFalse($episode->canGenerateIVR());
        
        // Add approved order
        Order::factory()->create([
            'ivr_episode_id' => $episode->id,
            'status' => 'approved',
        ]);
        
        $episode->refresh();
        $this->assertTrue($episode->canGenerateIVR());
        
        // Change episode status
        $episode->update(['episode_status' => 'completed']);
        $this->assertFalse($episode->canGenerateIVR());
    }
    
    public function test_status_transition_constraints()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'episode_status' => 'pending',
        ]);
        
        $this->assertTrue($episode->canTransitionTo('ivr_generated'));
        $this->assertFalse($episode->canTransitionTo('completed'));
        
        $episode->update(['episode_status' => 'sent_to_manufacturer']);
        $this->assertTrue($episode->canTransitionTo('completed'));
        $this->assertFalse($episode->canTransitionTo('pending'));
    }
}
```

#### 2. **Service Testing**

```php
// tests/Unit/Services/EpisodeServiceTest.php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\EpisodeService;
use App\Services\FhirService;
use App\Models\Order;
use App\Models\PatientManufacturerIVREpisode;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpisodeServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private EpisodeService $service;
    private $mockFhirService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockFhirService = Mockery::mock(FhirService::class);
        $this->app->instance(FhirService::class, $this->mockFhirService);
        
        $this->service = app(EpisodeService::class);
    }
    
    public function test_create_or_update_episode_creates_new_episode()
    {
        $order = Order::factory()->create();
        
        $this->assertDatabaseCount('patient_manufacturer_ivr_episodes', 0);
        
        $episode = $this->service->createOrUpdateEpisode($order);
        
        $this->assertDatabaseCount('patient_manufacturer_ivr_episodes', 1);
        $this->assertEquals($order->patient_id, $episode->patient_id);
        $this->assertEquals($order->manufacturer_id, $episode->manufacturer_id);
        $this->assertEquals('pending', $episode->episode_status);
        $this->assertEquals($episode->id, $order->fresh()->ivr_episode_id);
    }
    
    public function test_create_or_update_episode_uses_existing_episode()
    {
        $existingEpisode = PatientManufacturerIVREpisode::factory()->create([
            'episode_status' => 'pending',
        ]);
        
        $order = Order::factory()->create([
            'patient_id' => $existingEpisode->patient_id,
            'manufacturer_id' => $existingEpisode->manufacturer_id,
        ]);
        
        $this->assertDatabaseCount('patient_manufacturer_ivr_episodes', 1);
        
        $episode = $this->service->createOrUpdateEpisode($order);
        
        $this->assertDatabaseCount('patient_manufacturer_ivr_episodes', 1);
        $this->assertEquals($existingEpisode->id, $episode->id);
        $this->assertEquals($episode->id, $order->fresh()->ivr_episode_id);
    }
    
    public function test_get_episode_clinical_data_success()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create();
        
        $mockPatientData = [
            'id' => 'patient-123',
            'name' => [['text' => 'John Doe']],
            'birthDate' => '1980-01-01',
        ];
        
        $mockObservations = [
            'resources' => [
                [
                    'id' => 'obs-1',
                    'code' => ['text' => 'Wound Assessment'],
                    'effectiveDateTime' => '2023-01-01',
                ],
            ],
        ];
        
        $this->mockFhirService
            ->shouldReceive('getResource')
            ->with('Patient', $episode->patient->fhir_id)
            ->once()
            ->andReturn($mockPatientData);
        
        $this->mockFhirService
            ->shouldReceive('searchResources')
            ->with('Observation', Mockery::any())
            ->once()
            ->andReturn($mockObservations);
        
        $this->mockFhirService
            ->shouldReceive('searchResources')
            ->with('Condition', Mockery::any())
            ->once()
            ->andReturn(['resources' => []]);
        
        $result = $this->service->getEpisodeClinicalData($episode);
        
        $this->assertArrayHasKey('patient', $result);
        $this->assertArrayHasKey('wounds', $result);
        $this->assertArrayHasKey('lab_results', $result);
        $this->assertArrayHasKey('conditions', $result);
        $this->assertArrayHasKey('summary', $result);
    }
    
    public function test_get_episode_clinical_data_handles_fhir_error()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create();
        
        $this->mockFhirService
            ->shouldReceive('getResource')
            ->andThrow(new \Exception('FHIR connection failed'));
        
        $result = $this->service->getEpisodeClinicalData($episode);
        
        $this->assertEquals('Unable to retrieve clinical data', $result['error']);
        $this->assertNull($result['patient']);
        $this->assertEmpty($result['wounds']);
    }
}
```

### Integration Testing

#### 1. **Episode Workflow Test**

```php
// tests/Feature/EpisodeWorkflowTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\Integration\DocusealIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class EpisodeWorkflowTest extends TestCase
{
    use RefreshDatabase;
    
    private User $admin;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        
        // Mock external services
        $mockDocuseal = Mockery::mock(DocusealIntegrationService::class);
        $mockDocuseal->shouldReceive('createSubmission')
            ->andReturn([
                'id' => 'docuseal-123',
                'url' => 'https://docuseal.com/submission/123',
            ]);
        $this->app->instance(DocusealIntegrationService::class, $mockDocuseal);
    }
    
    public function test_complete_episode_workflow()
    {
        // Step 1: Create orders
        $orders = Order::factory()
            ->count(3)
            ->create([
                'patient_id' => Patient::factory(),
                'manufacturer_id' => Manufacturer::factory(),
                'status' => 'pending',
            ]);
        
        // Step 2: Orders should create episode
        $this->actingAs($this->admin)
            ->post(route('admin.orders.approve', $orders->first()))
            ->assertRedirect();
        
        $episode = PatientManufacturerIVREpisode::first();
        $this->assertNotNull($episode);
        $this->assertEquals('pending', $episode->episode_status);
        
        // Step 3: Generate IVR
        $this->actingAs($this->admin)
            ->post(route('admin.episodes.generate-ivr', $episode))
            ->assertRedirect()
            ->assertSessionHas('success');
        
        $episode->refresh();
        $this->assertEquals('ivr_generated', $episode->episode_status);
        $this->assertNotNull($episode->docuseal_submission_id);
        
        // Step 4: Send to manufacturer
        $this->actingAs($this->admin)
            ->post(route('admin.episodes.send-to-manufacturer', $episode))
            ->assertRedirect()
            ->assertSessionHas('success');
        
        $episode->refresh();
        $this->assertEquals('sent_to_manufacturer', $episode->episode_status);
        $this->assertNotNull($episode->sent_to_manufacturer_at);
        
        // Step 5: Complete episode
        $this->actingAs($this->admin)
            ->post(route('admin.episodes.complete', $episode))
            ->assertRedirect()
            ->assertSessionHas('success');
        
        $episode->refresh();
        $this->assertEquals('completed', $episode->episode_status);
        $this->assertNotNull($episode->completed_at);
        
        // Verify all orders are completed
        foreach ($orders as $order) {
            $this->assertEquals('completed', $order->fresh()->status);
        }
    }
    
    public function test_episode_permissions()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'episode_status' => 'pending',
        ]);
        
        $provider = User::factory()->create(['role' => 'provider']);
        
        // Provider cannot generate IVR
        $this->actingAs($provider)
            ->post(route('admin.episodes.generate-ivr', $episode))
            ->assertForbidden();
        
        // Admin can generate IVR
        $this->actingAs($this->admin)
            ->post(route('admin.episodes.generate-ivr', $episode))
            ->assertRedirect();
    }
    
    public function test_episode_state_validation()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'episode_status' => 'completed',
        ]);
        
        // Cannot generate IVR for completed episode
        $this->actingAs($this->admin)
            ->post(route('admin.episodes.generate-ivr', $episode))
            ->assertRedirect()
            ->assertSessionHas('error');
        
        $episode->refresh();
        $this->assertEquals('completed', $episode->episode_status);
    }
}
```

#### 2. **FHIR Integration Test**

```php
// tests/Feature/FhirIntegrationTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Services\FhirService;
use App\Services\PatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class FhirIntegrationTest extends TestCase
{
    use RefreshDatabase;
    
    private PatientService $patientService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Only run FHIR tests if configured
        if (empty(config('azure.health_data_services.fhir_endpoint'))) {
            $this->markTestSkipped('FHIR endpoint not configured');
        }
        
        $this->patientService = app(PatientService::class);
    }
    
    public function test_create_patient_in_fhir()
    {
        $patientData = [
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'M',
            'birth_date' => '1990-01-01',
            'phone' => '555-0123',
            'email' => 'test@example.com',
            'address_line_1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TX',
            'zip_code' => '12345',
        ];
        
        $result = $this->patientService->createPatient($patientData);
        
        $this->assertArrayHasKey('fhir_id', $result);
        $this->assertArrayHasKey('display_id', $result);
        $this->assertArrayHasKey('patient', $result);
        
        // Verify patient exists in FHIR
        $fhirService = app(FhirService::class);
        $patient = $fhirService->getResource('Patient', $result['fhir_id']);
        
        $this->assertEquals('Patient', $patient['resourceType']);
        $this->assertEquals($result['fhir_id'], $patient['id']);
    }
    
    public function test_search_patients_in_fhir()
    {
        // Create test patient first
        $this->patientService->createPatient([
            'first_name' => 'Searchable',
            'last_name' => 'TestPatient',
            'gender' => 'F',
            'birth_date' => '1985-05-15',
        ]);
        
        // Search by name
        $results = $this->patientService->searchPatients([
            'name' => 'Searchable',
        ]);
        
        $this->assertArrayHasKey('patients', $results);
        $this->assertArrayHasKey('total', $results);
        $this->assertGreaterThan(0, $results['total']);
        
        $found = false;
        foreach ($results['patients'] as $patient) {
            if (str_contains($patient['name'][0]['given'][0] ?? '', 'Searchable')) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue($found, 'Created patient not found in search results');
    }
    
    public function test_clinical_bundle_creation()
    {
        $patient = $this->patientService->createPatient([
            'first_name' => 'Bundle',
            'last_name' => 'Test',
            'gender' => 'M',
            'birth_date' => '1970-01-01',
        ]);
        
        $assessmentData = [
            'patient_id' => $patient['fhir_id'],
            'assessment_date' => now()->toDateString(),
            'wound_type' => 'pressure_ulcer',
            'wound_location' => 'Sacral region',
            'wound_measurements' => [
                'length' => 5.2,
                'width' => 3.8,
                'depth' => 1.5,
            ],
            'lab_results' => [
                [
                    'test_type' => 'hemoglobin',
                    'value' => 12.5,
                    'unit' => 'g/dL',
                    'unit_code' => 'g/dL',
                    'collection_date' => now()->subDays(2)->toDateString(),
                ],
            ],
        ];
        
        $bundleService = app(\App\Services\ClinicalBundleService::class);
        $result = $bundleService->createClinicalAssessmentBundle($assessmentData);
        
        $this->assertEquals('Bundle', $result['resourceType']);
        $this->assertArrayHasKey('entry', $result);
        $this->assertGreaterThan(0, count($result['entry']));
    }
}
```

### Frontend Testing

#### 1. **Component Testing**

```typescript
// tests/Components/Episodes/EpisodeTable.test.tsx
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { InertiaProvider } from '@inertiajs/react';
import EpisodeTable from '@/Components/Episodes/EpisodeTable';
import { Episode } from '@/types/episode';

const mockEpisodes: Episode[] = [
  {
    id: '123e4567-e89b-12d3-a456-426614174000',
    patient_id: 'patient-1',
    manufacturer_id: 'manufacturer-1',
    episode_status: 'pending',
    ivr_status: null,
    created_at: '2023-01-01T00:00:00Z',
    updated_at: '2023-01-01T00:00:00Z',
    patient: {
      id: 'patient-1',
      display_id: 'PT001',
      first_name: 'John',
      last_name: 'Doe',
    },
    manufacturer: {
      id: 'manufacturer-1',
      name: 'ACME Medical',
    },
    orders: [
      { id: 'order-1', order_number: 'ORD-001', status: 'pending' },
      { id: 'order-2', order_number: 'ORD-002', status: 'approved' },
    ],
  },
];

describe('EpisodeTable', () => {
  const mockOnGenerateIVR = jest.fn();
  
  beforeEach(() => {
    mockOnGenerateIVR.mockClear();
  });
  
  it('renders episode data correctly', () => {
    render(
      <InertiaProvider>
        <EpisodeTable episodes={mockEpisodes} onGenerateIVR={mockOnGenerateIVR} />
      </InertiaProvider>
    );
    
    // Check episode ID is displayed
    expect(screen.getByText('123e4567...')).toBeInTheDocument();
    
    // Check patient info
    expect(screen.getByText('PT001')).toBeInTheDocument();
    expect(screen.getByText('John Doe')).toBeInTheDocument();
    
    // Check manufacturer
    expect(screen.getByText('ACME Medical')).toBeInTheDocument();
    
    // Check order count
    expect(screen.getByText('2 orders')).toBeInTheDocument();
    
    // Check status badge
    expect(screen.getByText('pending')).toBeInTheDocument();
  });
  
  it('shows Generate IVR button for pending episodes', () => {
    render(
      <InertiaProvider>
        <EpisodeTable episodes={mockEpisodes} onGenerateIVR={mockOnGenerateIVR} />
      </InertiaProvider>
    );
    
    const generateButton = screen.getByText('Generate IVR');
    expect(generateButton).toBeInTheDocument();
    
    fireEvent.click(generateButton);
    expect(mockOnGenerateIVR).toHaveBeenCalledWith(mockEpisodes[0]);
  });
  
  it('does not show Generate IVR button for non-pending episodes', () => {
    const completedEpisode = {
      ...mockEpisodes[0],
      episode_status: 'completed',
    };
    
    render(
      <InertiaProvider>
        <EpisodeTable episodes={[completedEpisode]} onGenerateIVR={mockOnGenerateIVR} />
      </InertiaProvider>
    );
    
    expect(screen.queryByText('Generate IVR')).not.toBeInTheDocument();
  });
});
```

### End-to-End Testing

#### 1. **Cypress Test Suite**

```typescript
// cypress/e2e/episode-workflow.cy.ts
describe('Episode Workflow E2E', () => {
  beforeEach(() => {
    cy.login('admin@example.com', 'password');
  });
  
  it('completes full episode workflow', () => {
    // Navigate to episodes
    cy.visit('/admin/episodes');
    
    // Filter for pending episodes
    cy.get('[data-testid="status-filter"]').select('pending');
    
    // Click on first episode
    cy.get('[data-testid="episode-row"]').first().click();
    
    // Verify episode details page
    cy.url().should('include', '/episodes/');
    cy.get('[data-testid="episode-header"]').should('contain', 'Episode Details');
    
    // Check clinical data tab
    cy.get('[data-testid="clinical-tab"]').click();
    cy.get('[data-testid="patient-info"]').should('be.visible');
    cy.get('[data-testid="wound-assessments"]').should('be.visible');
    
    // Generate IVR
    cy.get('[data-testid="generate-ivr-btn"]').click();
    cy.get('[data-testid="confirm-dialog"]').within(() => {
      cy.get('button').contains('Confirm').click();
    });
    
    // Wait for IVR generation
    cy.get('[data-testid="toast-success"]').should('contain', 'IVR generated successfully');
    cy.get('[data-testid="episode-status"]').should('contain', 'IVR Generated');
    
    // Send to manufacturer
    cy.get('[data-testid="send-to-manufacturer-btn"]').should('be.enabled');
    cy.get('[data-testid="send-to-manufacturer-btn"]').click();
    cy.get('[data-testid="confirm-dialog"]').within(() => {
      cy.get('button').contains('Confirm').click();
    });
    
    // Verify status update
    cy.get('[data-testid="toast-success"]').should('contain', 'Sent to manufacturer');
    cy.get('[data-testid="episode-status"]').should('contain', 'Sent to Manufacturer');
    
    // Complete episode
    cy.get('[data-testid="complete-episode-btn"]').click();
    cy.get('[data-testid="confirm-dialog"]').within(() => {
      cy.get('button').contains('Confirm').click();
    });
    
    // Verify completion
    cy.get('[data-testid="toast-success"]').should('contain', 'Episode completed');
    cy.get('[data-testid="episode-status"]').should('contain', 'Completed');
    
    // Verify all action buttons are disabled
    cy.get('[data-testid="generate-ivr-btn"]').should('not.exist');
    cy.get('[data-testid="send-to-manufacturer-btn"]').should('not.exist');
    cy.get('[data-testid="complete-episode-btn"]').should('not.exist');
  });
  
  it('handles errors gracefully', () => {
    // Intercept API call and force error
    cy.intercept('POST', '/api/episodes/*/generate-ivr', {
      statusCode: 500,
      body: { message: 'Server error' },
    }).as('generateIVRError');
    
    cy.visit('/admin/episodes');
    cy.get('[data-testid="episode-row"]').first().click();
    
    // Try to generate IVR
    cy.get('[data-testid="generate-ivr-btn"]').click();
    cy.get('[data-testid="confirm-dialog"]').within(() => {
      cy.get('button').contains('Confirm').click();
    });
    
    // Verify error handling
    cy.wait('@generateIVRError');
    cy.get('[data-testid="toast-error"]').should('contain', 'Failed to generate IVR');
    cy.get('[data-testid="episode-status"]').should('contain', 'Pending');
  });
});
```

### Security Testing

#### 1. **HIPAA Compliance Tests**

```php
// tests/Feature/HipaaComplianceTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\FhirAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HipaaComplianceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_all_patient_access_is_audited()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        
        $this->actingAs($user);
        
        // Access patient data
        $response = $this->get(route('api.patients.show', $patient));
        $response->assertOk();
        
        // Verify audit log created
        $auditLog = FhirAuditLog::where('user_id', $user->id)
            ->where('resource_type', 'Patient')
            ->where('resource_id', $patient->fhir_id)
            ->where('action', 'read')
            ->first();
        
        $this->assertNotNull($auditLog);
        $this->assertEquals($user->email, $auditLog->user_email);
        $this->assertEquals(request()->ip(), $auditLog->ip_address);
    }
    
    public function test_phi_is_not_stored_locally()
    {
        $patient = Patient::factory()->create();
        
        // Check database doesn't contain PHI
        $this->assertDatabaseMissing('patients', [
            'id' => $patient->id,
            'ssn' => $patient->ssn, // Should not exist
        ]);
        
        // Only FHIR ID and display ID should be stored
        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'fhir_id' => $patient->fhir_id,
            'display_id' => $patient->display_id,
        ]);
        
        // Verify no PHI in JSON columns
        $this->assertStringNotContainsString(
            $patient->first_name,
            json_encode($patient->getAttributes())
        );
    }
    
    public function test_minimum_necessary_access_enforced()
    {
        $billingUser = User::factory()->create(['role' => 'billing']);
        $patient = Patient::factory()->create();
        
        $this->actingAs($billingUser);
        
        // Billing user accesses patient
        $response = $this->get(route('api.patients.show', $patient));
        $response->assertOk();
        
        $data = $response->json();
        
        // Billing should only see limited fields
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('insurance', $data);
        $this->assertArrayNotHasKey('clinical_notes', $data);
        $this->assertArrayNotHasKey('wound_assessments', $data);
    }
    
    public function test_encryption_in_transit()
    {
        // Verify HTTPS enforcement
        $response = $this->get('http://localhost/api/patients', [], [
            'HTTP_X_FORWARDED_PROTO' => 'http',
        ]);
        
        $response->assertRedirect();
        $this->assertStringStartsWith('https://', $response->headers->get('Location'));
    }
    
    public function test_session_timeout_after_inactivity()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user);
        
        // Access protected resource
        $this->get(route('api.patients.index'))->assertOk();
        
        // Simulate 31 minutes of inactivity (assuming 30-minute timeout)
        $this->travel(31)->minutes();
        
        // Next request should require re-authentication
        $response = $this->get(route('api.patients.index'));
        $response->assertUnauthorized();
    }
}
```

### Performance Testing

#### 1. **Load Testing Script**

```php
// tests/Performance/EpisodeLoadTest.php
namespace Tests\Performance;

use Tests\TestCase;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class EpisodeLoadTest extends TestCase
{
    public function test_episode_index_performance()
    {
        // Create test data
        $this->createTestEpisodes(1000);
        
        // Measure query performance
        $startTime = microtime(true);
        
        $episodes = PatientManufacturerIVREpisode::with([
            'patient:id,display_id,first_name,last_name',
            'manufacturer:id,name',
            'orders' => function ($query) {
                $query->select('id', 'ivr_episode_id', 'order_number', 'status');
            }
        ])
        ->paginate(20);
        
        $queryTime = microtime(true) - $startTime;
        
        // Assert performance requirements
        $this->assertLessThan(0.5, $queryTime, 'Episode index query took too long');
        $this->assertCount(20, $episodes);
        
        // Check query count (N+1 prevention)
        DB::enableQueryLog();
        
        foreach ($episodes as $episode) {
            $episode->patient->display_id;
            $episode->manufacturer->name;
            $episode->orders->count();
        }
        
        $queries = count(DB::getQueryLog());
        $this->assertLessThan(5, $queries, 'Too many queries executed (N+1 problem)');
    }
    
    public function test_clinical_data_retrieval_performance()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create();
        
        // Mock FHIR service for consistent timing
        $this->mock(\App\Services\FhirService::class, function ($mock) {
            $mock->shouldReceive('getResource')->andReturn([
                'id' => 'patient-123',
                'name' => [['text' => 'Test Patient']],
            ]);
            
            $mock->shouldReceive('searchResources')->andReturn([
                'resources' => [],
                'total' => 0,
            ]);
        });
        
        $service = app(\App\Services\EpisodeService::class);
        
        $startTime = microtime(true);
        $clinicalData = $service->getEpisodeClinicalData($episode);
        $retrievalTime = microtime(true) - $startTime;
        
        $this->assertLessThan(2.0, $retrievalTime, 'Clinical data retrieval took too long');
        $this->assertArrayHasKey('patient', $clinicalData);
        $this->assertArrayHasKey('wounds', $clinicalData);
    }
    
    private function createTestEpisodes(int $count): void
    {
        $episodes = PatientManufacturerIVREpisode::factory()
            ->count($count)
            ->create();
        
        foreach ($episodes as $episode) {
            Order::factory()
                ->count(rand(1, 5))
                ->create(['ivr_episode_id' => $episode->id]);
        }
    }
}
```

### Chapter Summary

Testing a HIPAA-compliant healthcare system requires:

1. **Unit Tests**: Validate individual components and business logic
2. **Integration Tests**: Ensure systems work together correctly
3. **E2E Tests**: Validate complete user workflows
4. **Security Tests**: Verify HIPAA compliance requirements
5. **Performance Tests**: Ensure system meets performance requirements
6. **Load Tests**: Validate system behavior under stress
7. **Audit Tests**: Confirm all access is properly logged

---

## Chapter 12: Production Readiness {#chapter-12}

### Deploying to Production

This final chapter covers the essential steps and considerations for deploying a HIPAA-compliant healthcare system to production, including infrastructure setup, monitoring, backup strategies, and ongoing maintenance.

### Production Infrastructure

#### 1. **Azure Infrastructure Setup**

```yaml
# infrastructure/azure-resources.yaml
resource_groups:
  production:
    name: msc-health-prod-rg
    location: eastus
    
  disaster_recovery:
    name: msc-health-dr-rg
    location: westus

health_data_services:
  workspace:
    name: msc-health-prod-workspace
    resource_group: msc-health-prod-rg
    
  fhir_service:
    name: msc-fhir-prod
    kind: fhir-R4
    features:
      - export
      - import
      - convert-data
      
app_services:
  main_app:
    name: msc-health-app-prod
    plan: P2v3
    always_on: true
    health_check_path: /api/health
    
  worker_app:
    name: msc-health-worker-prod
    plan: P1v3
    always_on: true

databases:
  supabase:
    instance: msc-health-prod.supabase.co
    connection_pooling: enabled
    max_connections: 100
    
  redis:
    name: msc-health-redis-prod
    sku: P1
    clustering: enabled

monitoring:
  application_insights:
    name: msc-health-insights-prod
    sampling_percentage: 100
    
  log_analytics:
    name: msc-health-logs-prod
    retention_days: 90
```

#### 2. **Deployment Configuration**

```php
// config/production.php
return [
    'app' => [
        'debug' => false,
        'env' => 'production',
        'url' => 'https://mscwoundcare.com',
    ],
    
    'session' => [
        'secure' => true,
        'http_only' => true,
        'same_site' => 'strict',
        'lifetime' => 30, // 30 minutes for HIPAA compliance
    ],
    
    'cache' => [
        'default' => 'redis',
        'prefix' => 'msc_prod',
    ],
    
    'queue' => [
        'default' => 'redis',
        'connections' => [
            'redis' => [
                'retry_after' => 90,
                'block_for' => 5,
            ],
        ],
    ],
    
    'logging' => [
        'default' => 'stack',
        'channels' => [
            'stack' => [
                'driver' => 'stack',
                'channels' => ['daily', 'slack', 'azure'],
                'ignore_exceptions' => false,
            ],
        ],
    ],
    
    'security' => [
        'rate_limit' => 60, // requests per minute
        'max_login_attempts' => 5,
        'lockout_duration' => 15, // minutes
    ],
];
```

### Deployment Pipeline

#### 1. **GitHub Actions Workflow**

```yaml
# .github/workflows/deploy-production.yml
name: Deploy to Production

on:
  push:
    tags:
      - 'v*'

env:
  AZURE_WEBAPP_NAME: msc-health-app-prod
  PHP_VERSION: '8.3'
  NODE_VERSION: '18.x'

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql
          coverage: xdebug
          
      - name: Install dependencies
        run: |
          composer install --prefer-dist --no-progress
          npm ci
          
      - name: Run tests
        run: |
          php artisan test --parallel
          npm run test
          
      - name: Run security checks
        run: |
          composer audit
          npm audit --production
          
  build:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Build application
        run: |
          composer install --optimize-autoloader --no-dev
          npm ci --production
          npm run build
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache
          
      - name: Create deployment artifact
        run: |
          tar -czf deploy.tar.gz --exclude=node_modules .
          
      - name: Upload artifact
        uses: actions/upload-artifact@v3
        with:
          name: deploy-artifact
          path: deploy.tar.gz
          
  deploy:
    needs: build
    runs-on: ubuntu-latest
    environment: production
    steps:
      - name: Download artifact
        uses: actions/download-artifact@v3
        with:
          name: deploy-artifact
          
      - name: Deploy to Azure
        uses: azure/webapps-deploy@v2
        with:
          app-name: ${{ env.AZURE_WEBAPP_NAME }}
          publish-profile: ${{ secrets.AZURE_WEBAPP_PUBLISH_PROFILE }}
          package: deploy.tar.gz
          
      - name: Run migrations
        run: |
          az webapp ssh --name ${{ env.AZURE_WEBAPP_NAME }} \
            --resource-group msc-health-prod-rg \
            --command "cd /home/site/wwwroot && php artisan migrate --force"
          
      - name: Clear caches
        run: |
          az webapp ssh --name ${{ env.AZURE_WEBAPP_NAME }} \
            --resource-group msc-health-prod-rg \
            --command "cd /home/site/wwwroot && php artisan cache:clear"
          
      - name: Health check
        run: |
          curl -f https://mscwoundcare.com/api/health || exit 1
```

### Monitoring and Alerting

#### 1. **Application Monitoring**

```php
// app/Services/Monitoring/HealthCheckService.php
namespace App\Services\Monitoring;

class HealthCheckService
{
    public function check(): array
    {
        $checks = [];
        
        // Database connectivity
        $checks['database'] = $this->checkDatabase();
        
        // Redis connectivity
        $checks['redis'] = $this->checkRedis();
        
        // FHIR service
        $checks['fhir'] = $this->checkFhir();
        
        // Queue processing
        $checks['queue'] = $this->checkQueue();
        
        // Disk space
        $checks['disk'] = $this->checkDiskSpace();
        
        // SSL certificate
        $checks['ssl'] = $this->checkSSLCertificate();
        
        $overallStatus = collect($checks)->every(fn($check) => $check['status'] === 'healthy') 
            ? 'healthy' 
            : 'unhealthy';
        
        return [
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version'),
        ];
    }
    
    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'latency_ms' => DB::getQueryLog()[0]['time'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    private function checkFhir(): array
    {
        try {
            $fhirService = app(\App\Services\FhirService::class);
            $startTime = microtime(true);
            
            $metadata = $fhirService->healthCheck();
            
            $latency = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => $metadata['status'],
                'message' => 'FHIR service operational',
                'latency_ms' => round($latency, 2),
                'fhir_version' => $metadata['fhir_version'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'FHIR service unavailable',
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

#### 2. **Azure Monitor Alerts**

```json
// monitoring/alerts.json
{
  "alerts": [
    {
      "name": "High Error Rate",
      "description": "Alert when error rate exceeds 5%",
      "condition": {
        "query": "requests | where success == false | summarize errorRate = count() * 100.0 / toscalar(requests | count()) by bin(timestamp, 5m)",
        "threshold": 5,
        "operator": "GreaterThan",
        "timeAggregation": "Average",
        "windowSize": "PT5M"
      },
      "severity": 2,
      "actions": ["email", "sms"]
    },
    {
      "name": "Slow Response Time",
      "description": "Alert when average response time exceeds 2 seconds",
      "condition": {
        "metric": "Http Server Request Duration",
        "threshold": 2000,
        "operator": "GreaterThan",
        "aggregation": "Average",
        "windowSize": "PT5M"
      },
      "severity": 3,
      "actions": ["email"]
    },
    {
      "name": "FHIR Service Unavailable",
      "description": "Alert when FHIR service is not responding",
      "condition": {
        "query": "customEvents | where name == 'FhirHealthCheck' and customDimensions.status == 'unhealthy'",
        "threshold": 1,
        "operator": "GreaterThan",
        "windowSize": "PT1M"
      },
      "severity": 1,
      "actions": ["email", "sms", "webhook"]
    },
    {
      "name": "Failed Login Attempts",
      "description": "Alert on suspicious login activity",
      "condition": {
        "query": "customEvents | where name == 'LoginFailed' | summarize count() by bin(timestamp, 5m), user_tostring(customDimensions.ip)",
        "threshold": 10,
        "operator": "GreaterThan",
        "windowSize": "PT5M"
      },
      "severity": 2,
      "actions": ["email", "security_team"]
    }
  ]
}
```

### Backup and Disaster Recovery

#### 1. **Backup Strategy**

```php
// app/Console/Commands/BackupHealthData.php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupHealthData extends Command
{
    protected $signature = 'backup:health-data {--type=full}';
    protected $description = 'Backup health data including FHIR resources';
    
    public function handle(): int
    {
        $type = $this->option('type');
        
        $this->info("Starting {$type} backup...");
        
        // Backup operational database
        $this->backupDatabase();
        
        // Export FHIR data
        $this->exportFhirData();
        
        // Backup audit logs
        $this->backupAuditLogs();
        
        // Verify backup integrity
        $this->verifyBackup();
        
        // Upload to secure storage
        $this->uploadToStorage();
        
        // Clean old backups
        $this->cleanOldBackups();
        
        $this->info('Backup completed successfully');
        
        return 0;
    }
    
    private function exportFhirData(): void
    {
        $this->info('Exporting FHIR data...');
        
        $fhirService = app(\App\Services\FhirService::class);
        
        // Use FHIR $export operation
        $exportResult = $fhirService->executeOperation('$export', [
            '_type' => 'Patient,Observation,Condition,DocumentReference',
            '_since' => now()->subDay()->toIso8601String(),
        ]);
        
        // Poll for completion
        while ($exportResult['status'] !== 'completed') {
            sleep(5);
            $exportResult = $fhirService->checkExportStatus($exportResult['id']);
        }
        
        // Download exported files
        foreach ($exportResult['output'] as $file) {
            $this->downloadExportFile($file['url'], $file['type']);
        }
    }
    
    private function verifyBackup(): void
    {
        $this->info('Verifying backup integrity...');
        
        // Check file checksums
        $files = Storage::disk('backup')->files(now()->format('Y-m-d'));
        
        foreach ($files as $file) {
            $checksum = hash_file('sha256', Storage::disk('backup')->path($file));
            $this->info("Verified: {$file} - {$checksum}");
        }
    }
}
```

#### 2. **Disaster Recovery Plan**

```yaml
# disaster-recovery/dr-plan.yaml
disaster_recovery_plan:
  rto: 4 hours  # Recovery Time Objective
  rpo: 1 hour   # Recovery Point Objective
  
  scenarios:
    regional_outage:
      trigger: "Azure East US region unavailable"
      steps:
        1: "Activate West US disaster recovery site"
        2: "Update DNS to point to DR site"
        3: "Restore latest FHIR backup"
        4: "Verify system functionality"
        5: "Notify users of temporary URL if needed"
      
    data_corruption:
      trigger: "Data integrity issues detected"
      steps:
        1: "Isolate affected systems"
        2: "Identify corruption scope"
        3: "Restore from last known good backup"
        4: "Replay audit logs to recover recent changes"
        5: "Verify data integrity"
        
    security_breach:
      trigger: "Unauthorized access detected"
      steps:
        1: "Activate incident response team"
        2: "Isolate affected systems"
        3: "Reset all credentials"
        4: "Review audit logs"
        5: "Notify compliance officer"
        6: "Begin breach notification process"
  
  testing_schedule:
    - type: "Backup restoration"
      frequency: "Monthly"
      
    - type: "Regional failover"
      frequency: "Quarterly"
      
    - type: "Full DR simulation"
      frequency: "Annually"
```

### Performance Optimization

#### 1. **Database Optimization**

```sql
-- Optimize episode queries
CREATE INDEX idx_episodes_patient_manufacturer 
ON patient_manufacturer_ivr_episodes(patient_id, manufacturer_id, episode_status);

CREATE INDEX idx_episodes_status_created 
ON patient_manufacturer_ivr_episodes(episode_status, created_at DESC);

CREATE INDEX idx_orders_episode 
ON orders(ivr_episode_id) 
WHERE ivr_episode_id IS NOT NULL;

-- Optimize audit log queries
CREATE INDEX idx_audit_logs_user_date 
ON fhir_audit_logs(user_id, created_at DESC);

CREATE INDEX idx_audit_logs_resource 
ON fhir_audit_logs(resource_type, resource_id, created_at DESC);

-- Partitioning for large tables
ALTER TABLE fhir_audit_logs 
PARTITION BY RANGE (created_at) (
    PARTITION p_2024_q1 VALUES LESS THAN ('2024-04-01'),
    PARTITION p_2024_q2 VALUES LESS THAN ('2024-07-01'),
    PARTITION p_2024_q3 VALUES LESS THAN ('2024-10-01'),
    PARTITION p_2024_q4 VALUES LESS THAN ('2025-01-01'),
    PARTITION p_future VALUES LESS THAN (MAXVALUE)
);
```

#### 2. **Caching Strategy**

```php
// app/Services/CacheService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    private array $ttls = [
        'patient_data' => 300,      // 5 minutes
        'episode_list' => 60,       // 1 minute
        'clinical_data' => 600,     // 10 minutes
        'manufacturer_list' => 3600, // 1 hour
    ];
    
    public function remember(string $key, callable $callback, ?string $type = null): mixed
    {
        $ttl = $this->ttls[$type] ?? 300;
        
        return Cache::tags($this->getTags($type))->remember(
            $this->prefixKey($key),
            $ttl,
            $callback
        );
    }
    
    public function invalidate(string $type): void
    {
        Cache::tags($this->getTags($type))->flush();
    }
    
    private function getTags(string $type): array
    {
        $tags = ['app'];
        
        switch ($type) {
            case 'patient_data':
                $tags[] = 'patients';
                break;
            case 'episode_list':
                $tags[] = 'episodes';
                break;
            case 'clinical_data':
                $tags[] = 'clinical';
                $tags[] = 'fhir';
                break;
        }
        
        return $tags;
    }
    
    private function prefixKey(string $key): string
    {
        return sprintf(
            'msc:%s:%s',
            config('app.env'),
            $key
        );
    }
}
```
