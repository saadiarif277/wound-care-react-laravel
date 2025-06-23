# Final Chapters - Continuation from Chapter 7

            // Clinical Data
            'primary_diagnosis' => $clinicalData['primary_condition']['display'] ?? '',
            'wound_type' => $clinicalData['wound_type'] ?? '',
            'wound_location' => $clinicalData['wound_location'] ?? '',
            'wound_size' => $this->formatWoundSize($clinicalData['wound_measurements']),
            
            // Lab Values
            'hemoglobin_value' => $clinicalData['lab_results']['hemoglobin'] ?? '',
            'hba1c_value' => $clinicalData['lab_results']['hba1c'] ?? '',
            'albumin_value' => $clinicalData['lab_results']['albumin'] ?? '',
            
            // Treatment History
            'conservative_treatments' => implode(', ', $clinicalData['treatments'] ?? []),
            'treatment_duration' => $clinicalData['treatment_duration'] ?? '',
        ];
        
        return $mapping;
    }
    
    private function createDocuSealSubmission(string $templateId, array $data): array
    {
        $client = new Client([
            'base_uri' => $this->config['docuseal_api_url'],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['docuseal_api_key'],
                'Content-Type' => 'application/json',
            ],
        ]);
        
        $response = $client->post('/submissions', [
            'json' => [
                'template_id' => $templateId,
                'send_email' => false,
                'fields' => array_map(function ($value, $key) {
                    return [
                        'name' => $key,
                        'default_value' => $value,
                    ];
                }, $data, array_keys($data)),
            ],
        ]);
        
        return json_decode($response->getBody(), true);
    }
}
```

### Insurance Integration

#### 1. **Eligibility Verification Service**

```php
// app/Services/Integration/InsuranceEligibilityService.php
namespace App\Services\Integration;

use App\Services\FhirService;

class InsuranceEligibilityService extends IntegrationService
{
    private FhirService $fhirService;
    
    public function verifyEligibility(string $patientId, string $procedureCode): array
    {
        try {
            // Get patient insurance information
            $coverage = $this->getPatientCoverage($patientId);
            
            if (!$coverage) {
                return [
                    'eligible' => false,
                    'reason' => 'No active insurance coverage found',
                ];
            }
            
            // Build eligibility request
            $eligibilityRequest = $this->buildEligibilityRequest(
                $patientId,
                $coverage,
                $procedureCode
            );
            
            // Submit to insurance API
            $response = $this->submitEligibilityRequest($eligibilityRequest);
            
            // Create FHIR CoverageEligibilityResponse
            $eligibilityResponse = $this->createEligibilityResponse(
                $patientId,
                $procedureCode,
                $response
            );
            
            // Store in FHIR
            $this->fhirService->createResource(
                'CoverageEligibilityResponse',
                $eligibilityResponse
            );
            
            return $this->parseEligibilityResponse($response);
            
        } catch (\Exception $e) {
            $this->logger->error('Eligibility verification failed', [
                'patient_id' => $patientId,
                'procedure_code' => $procedureCode,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    private function getPatientCoverage(string $patientId): ?array
    {
        $results = $this->fhirService->searchResources('Coverage', [
            'patient' => $patientId,
            'status' => 'active',
        ]);
        
        return $results['resources'][0] ?? null;
    }
    
    private function buildEligibilityRequest(
        string $patientId,
        array $coverage,
        string $procedureCode
    ): array {
        return [
            'resourceType' => 'CoverageEligibilityRequest',
            'status' => 'active',
            'purpose' => ['validation', 'benefits'],
            'patient' => [
                'reference' => "Patient/{$patientId}",
            ],
            'created' => now()->toIso8601String(),
            'insurer' => [
                'reference' => $coverage['payor'][0]['reference'],
            ],
            'insurance' => [
                [
                    'coverage' => [
                        'reference' => "Coverage/{$coverage['id']}",
                    ],
                ],
            ],
            'item' => [
                [
                    'category' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/ex-benefitcategory',
                                'code' => 'medical',
                            ],
                        ],
                    ],
                    'productOrService' => [
                        'coding' => [
                            [
                                'system' => 'http://www.ama-assn.org/go/cpt',
                                'code' => $procedureCode,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
```

### Message Queue Integration

#### 1. **Event-Driven Architecture**

```php
// app/Services/Integration/MessageQueueService.php
namespace App\Services\Integration;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MessageQueueService
{
    private $connection;
    private $channel;
    
    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password')
        );
        
        $this->channel = $this->connection->channel();
    }
    
    public function publishFhirEvent(string $eventType, array $data): void
    {
        $message = new AMQPMessage(json_encode([
            'event_type' => $eventType,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ]), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);
        
        $this->channel->basic_publish(
            $message,
            'fhir_events',
            $eventType
        );
        
        Log::info('FHIR event published', [
            'event_type' => $eventType,
            'data_size' => strlen(json_encode($data)),
        ]);
    }
    
    public function consumeFhirEvents(array $eventTypes, callable $callback): void
    {
        $this->channel->exchange_declare(
            'fhir_events',
            'topic',
            false,
            true,
            false
        );
        
        $queueName = $this->channel->queue_declare(
            '',
            false,
            false,
            true,
            false
        )[0];
        
        foreach ($eventTypes as $eventType) {
            $this->channel->queue_bind(
                $queueName,
                'fhir_events',
                $eventType
            );
        }
        
        $this->channel->basic_consume(
            $queueName,
            '',
            false,
            false,
            false,
            false,
            function ($message) use ($callback) {
                $data = json_decode($message->body, true);
                
                try {
                    $callback($data);
                    $message->ack();
                } catch (\Exception $e) {
                    Log::error('Event processing failed', [
                        'event' => $data,
                        'error' => $e->getMessage(),
                    ]);
                    $message->nack(true);
                }
            }
        );
        
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }
}
```

### Chapter Summary

Integration patterns for FHIR services require:

1. **Abstraction Layer**: Base integration service for common functionality
2. **EHR Integration**: Bidirectional sync with Epic, Cerner, etc.
3. **Document Management**: Integration with DocuSeal for forms and documents
4. **Insurance Verification**: Real-time eligibility checking
5. **Message Queuing**: Event-driven architecture for asynchronous processing
6. **Security**: Encryption and audit logging for all integrations
7. **Error Handling**: Robust retry and fallback mechanisms

---

# Part III: The Success Story - Episode-Based Workflows in Production

## Chapter 8: The Database Foundation Crisis {#chapter-8}

### The Critical Discovery

During the implementation of the MSC Wound Care Distribution Platform, a critical issue emerged that threatened the entire project: the core database foundation was missing. This chapter details how the crisis was discovered, diagnosed, and resolved—providing valuable lessons for similar implementations.

### The Initial Symptoms

The problems began manifesting as a cascade of migration failures:

```bash
# Initial migration attempt output
php artisan migrate

Migration table created successfully.
Migrating: 2024_03_28_000000_create_all_tables
   ERROR  Table 'product_requests' already exists

Migrating: 2024_07_01_000000_create_episode_table
   ERROR  Table 'episodes' doesn't exist

Migrating: 2024_07_01_000001_add_ivr_episode_id_to_orders
   ERROR  Column 'ivr_episode_id' cannot be added: table 'orders' doesn't exist
```

### Root Cause Analysis

#### 1. **Investigation Process**

```php
// Diagnostic script to check database state
class DatabaseDiagnostic
{
    public function checkDatabaseState(): array
    {
        $issues = [];
        
        // Check for core tables
        $coreTables = [
            'product_requests',
            'orders',
            'users',
            'manufacturers',
            'patients',
        ];
        
        foreach ($coreTables as $table) {
            if (!Schema::hasTable($table)) {
                $issues[] = "Missing core table: {$table}";
            }
        }
        
        // Check migration history
        $migrations = DB::table('migrations')->pluck('migration');
        
        if (!$migrations->contains('2024_03_28_000000_create_all_tables')) {
            $issues[] = "Foundation migration never executed";
        }
        
        // Check for duplicate migrations
        $migrationFiles = array_map(function ($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, glob(database_path('migrations/*.php')));
        
        $duplicates = array_diff_assoc(
            $migrationFiles,
            array_unique($migrationFiles)
        );
        
        if (!empty($duplicates)) {
            $issues[] = "Duplicate migrations found: " . implode(', ', $duplicates);
        }
        
        return $issues;
    }
}
```

#### 2. **Discovery of Missing Foundation**

The investigation revealed that the foundational migration `2024_03_28_000000_create_all_tables.php` had never been executed. This migration contained the core table definitions that all subsequent migrations depended on.

### The Resolution Strategy

#### 1. **Migration Dependency Mapping**

```php
// Created dependency graph for migrations
class MigrationDependencyAnalyzer
{
    private array $dependencies = [];
    
    public function analyze(): array
    {
        $migrations = $this->getMigrationFiles();
        
        foreach ($migrations as $migration) {
            $content = file_get_contents($migration);
            $this->dependencies[$migration] = $this->extractDependencies($content);
        }
        
        return $this->topologicalSort($this->dependencies);
    }
    
    private function extractDependencies(string $content): array
    {
        $dependencies = [];
        
        // Extract table references
        preg_match_all('/Schema::table\([\'"](\w+)[\'"]/', $content, $matches);
        $dependencies = array_merge($dependencies, $matches[1]);
        
        // Extract foreign key references
        preg_match_all('/->references\([\'"](\w+)[\'"]/', $content, $matches);
        $dependencies = array_merge($dependencies, $matches[1]);
        
        return array_unique($dependencies);
    }
    
    private function topologicalSort(array $dependencies): array
    {
        $sorted = [];
        $visited = [];
        
        foreach (array_keys($dependencies) as $node) {
            if (!isset($visited[$node])) {
                $this->visit($node, $dependencies, $visited, $sorted);
            }
        }
        
        return array_reverse($sorted);
    }
}
```

#### 2. **Safe Migration Execution**

```php
// Safe migration runner with rollback capability
class SafeMigrationRunner
{
    private array $executedMigrations = [];
    
    public function runMigrations(array $migrations): void
    {
        DB::beginTransaction();
        
        try {
            foreach ($migrations as $migration) {
                $this->executeMigration($migration);
                $this->executedMigrations[] = $migration;
            }
            
            DB::commit();
            Log::info('All migrations completed successfully', [
                'count' => count($this->executedMigrations),
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            
            // Rollback executed migrations
            foreach (array_reverse($this->executedMigrations) as $migration) {
                $this->rollbackMigration($migration);
            }
            
            Log::error('Migration failed, rolled back', [
                'error' => $e->getMessage(),
                'failed_at' => $migration,
            ]);
            
            throw $e;
        }
    }
    
    private function executeMigration(string $migrationFile): void
    {
        require_once $migrationFile;
        
        $className = $this->getMigrationClassName($migrationFile);
        $migration = new $className();
        
        // Add safety checks
        if (method_exists($migration, 'up')) {
            $migration->up();
        }
        
        // Record in migrations table
        DB::table('migrations')->insert([
            'migration' => basename($migrationFile, '.php'),
            'batch' => $this->getBatchNumber(),
        ]);
    }
}
```

### Fixing the Migrations

#### 1. **Adding Existence Checks**

```php
// Updated migration with existence checks
class AddIvrEpisodeIdToOrdersTable extends Migration
{
    public function up(): void
    {
        // Check if table exists
        if (!Schema::hasTable('orders')) {
            Log::warning('Orders table does not exist, skipping migration');
            return;
        }
        
        // Check if column already exists
        if (Schema::hasColumn('orders', 'ivr_episode_id')) {
            Log::info('Column ivr_episode_id already exists');
            return;
        }
        
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('ivr_episode_id')->nullable()->after('id');
            
            // Check if foreign key table exists
            if (Schema::hasTable('patient_manufacturer_ivr_episodes')) {
                $table->foreign('ivr_episode_id')
                    ->references('id')
                    ->on('patient_manufacturer_ivr_episodes')
                    ->onDelete('set null');
            }
        });
    }
    
    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }
        
        Schema::table('orders', function (Blueprint $table) {
            // Drop foreign key if it exists
            $foreignKeys = $this->listTableForeignKeys('orders');
            if (in_array('orders_ivr_episode_id_foreign', $foreignKeys)) {
                $table->dropForeign(['ivr_episode_id']);
            }
            
            // Drop column if it exists
            if (Schema::hasColumn('orders', 'ivr_episode_id')) {
                $table->dropColumn('ivr_episode_id');
            }
        });
    }
    
    private function listTableForeignKeys(string $table): array
    {
        $keys = DB::select(
            "SELECT CONSTRAINT_NAME 
             FROM information_schema.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = ? 
             AND TABLE_NAME = ? 
             AND REFERENCED_TABLE_NAME IS NOT NULL",
            [DB::getDatabaseName(), $table]
        );
        
        return array_column($keys, 'CONSTRAINT_NAME');
    }
}
```

#### 2. **Handling MySQL Index Name Limits**

```php
// Fixing index name length issues
class FixLongIndexNames extends Migration
{
    public function up(): void
    {
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            // Original index name was too long for MySQL
            // 'patient_manufacturer_ivr_episodes_patient_id_manufacturer_id_unique'
            
            // Use shorter custom name
            $table->unique(
                ['patient_id', 'manufacturer_id'],
                'pmie_patient_manufacturer_unique'
            );
            
            // Index for performance
            $table->index('patient_id', 'pmie_patient_idx');
            $table->index('manufacturer_id', 'pmie_manufacturer_idx');
            $table->index('created_at', 'pmie_created_idx');
        });
    }
}
```

### Lessons Learned

#### 1. **Always Verify Foundation**

```php
// Pre-deployment checklist
class DeploymentReadinessChecker
{
    public function checkDatabaseFoundation(): array
    {
        $checks = [];
        
        // Verify core tables exist
        $checks['core_tables'] = $this->verifyCoreTablesExist();
        
        // Verify all migrations are compatible
        $checks['migration_compatibility'] = $this->checkMigrationCompatibility();
        
        // Verify foreign key constraints
        $checks['foreign_keys'] = $this->verifyForeignKeyIntegrity();
        
        // Verify indexes are optimized
        $checks['indexes'] = $this->checkIndexOptimization();
        
        return $checks;
    }
}
```

#### 2. **Migration Best Practices**

```php
// Template for safe migrations
abstract class SafeMigration extends Migration
{
    protected function safeUp(): void
    {
        // Override in child classes
    }
    
    protected function safeDown(): void
    {
        // Override in child classes
    }
    
    public function up(): void
    {
        try {
            $this->logMigrationStart();
            $this->safeUp();
            $this->logMigrationComplete();
        } catch (\Exception $e) {
            $this->logMigrationFailure($e);
            throw $e;
        }
    }
    
    public function down(): void
    {
        try {
            $this->logRollbackStart();
            $this->safeDown();
            $this->logRollbackComplete();
        } catch (\Exception $e) {
            $this->logRollbackFailure($e);
            throw $e;
        }
    }
    
    protected function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }
    
    protected function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
    
    protected function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select(
            "SHOW INDEX FROM {$table} WHERE Key_name = ?",
            [$index]
        );
        
        return !empty($indexes);
    }
}
```

### Post-Crisis Improvements

#### 1. **Automated Database Health Checks**

```php
// Scheduled health check command
class DatabaseHealthCheck extends Command
{
    protected $signature = 'db:health-check';
    protected $description = 'Check database health and integrity';
    
    public function handle(): int
    {
        $this->info('Running database health check...');
        
        $checks = [
            'Table Integrity' => $this->checkTableIntegrity(),
            'Foreign Keys' => $this->checkForeignKeys(),
            'Indexes' => $this->checkIndexes(),
            'Migration Status' => $this->checkMigrationStatus(),
        ];
        
        $failed = false;
        
        foreach ($checks as $name => $result) {
            if ($result['status'] === 'pass') {
                $this->info("✓ {$name}: {$result['message']}");
            } else {
                $this->error("✗ {$name}: {$result['message']}");
                $failed = true;
            }
        }
        
        if ($failed) {
            $this->error('Database health check failed!');
            
            // Send alert to team
            Mail::to(config('database.health_alert_email'))
                ->send(new DatabaseHealthAlert($checks));
                
            return 1;
        }
        
        $this->info('All database health checks passed!');
        return 0;
    }
}
```

### Chapter Summary

The database foundation crisis taught valuable lessons:

1. **Never Assume**: Always verify that foundational components are in place
2. **Defensive Migrations**: Use existence checks and safe practices
3. **Dependency Management**: Understand and document migration dependencies
4. **Automated Monitoring**: Implement health checks to catch issues early
5. **Rollback Strategy**: Always have a plan to reverse changes safely
6. **Documentation**: Keep detailed records of database schema evolution

The resolution of this crisis laid the groundwork for the successful implementation of the episode-based workflow system, which is covered in the next chapter.

---

## Chapter 9: Episode-Based Architecture {#chapter-9}

### Transforming Clinical Workflows

The episode-based architecture represents a fundamental shift in how wound care orders are managed. Instead of treating each order as an isolated transaction, the system groups related orders by patient and manufacturer into clinical episodes, providing better continuity of care and operational efficiency.

### The Episode Concept

#### 1. **Episode Definition**

```php
// app/Models/PatientManufacturerIVREpisode.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientManufacturerIVREpisode extends Model
{
    protected $table = 'patient_manufacturer_ivr_episodes';
    
    protected $fillable = [
        'patient_id',
        'manufacturer_id',
        'episode_status',
        'ivr_status',
        'docuseal_submission_id',
        'ivr_generated_at',
        'sent_to_manufacturer_at',
        'manufacturer_response_at',
        'completed_at',
        'metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'ivr_generated_at' => 'datetime',
        'sent_to_manufacturer_at' => 'datetime',
        'manufacturer_response_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
    
    // Episode groups multiple orders
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'ivr_episode_id');
    }
    
    // Episode belongs to a patient
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
    
    // Episode belongs to a manufacturer
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }
    
    // Business logic for episode management
    public function canGenerateIVR(): bool
    {
        return $this->episode_status === 'pending' 
            && $this->orders()->where('status', 'approved')->exists();
    }
    
    public function canSendToManufacturer(): bool
    {
        return $this->episode_status === 'ivr_generated'
            && $this->ivr_status === 'completed'
            && !is_null($this->docuseal_submission_id);
    }
    
    public function isReadyForCompletion(): bool
    {
        return $this->episode_status === 'sent_to_manufacturer'
            && $this->orders()->where('status', '!=', 'completed')->doesntExist();
    }
}
```

### Episode State Management

#### 1. **State Machine Implementation**

```php
// app/Services/EpisodeStateMachine.php
namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Support\Facades\DB;

class EpisodeStateMachine
{
    private array $validTransitions = [
        'pending' => ['ivr_generated'],
        'ivr_generated' => ['sent_to_manufacturer', 'pending'],
        'sent_to_manufacturer' => ['completed', 'ivr_generated'],
        'completed' => [], // Terminal state
    ];
    
    public function transition(
        PatientManufacturerIVREpisode $episode,
        string $newStatus,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($episode, $newStatus, $metadata) {
            $currentStatus = $episode->episode_status;
            
            // Validate transition
            if (!$this->canTransition($currentStatus, $newStatus)) {
                throw new InvalidStateTransitionException(
                    "Cannot transition from {$currentStatus} to {$newStatus}"
                );
            }
            
            // Update episode status
            $episode->episode_status = $newStatus;
            
            // Update timestamps based on new status
            switch ($newStatus) {
                case 'ivr_generated':
                    $episode->ivr_generated_at = now();
                    break;
                case 'sent_to_manufacturer':
                    $episode->sent_to_manufacturer_at = now();
                    break;
                case 'completed':
                    $episode->completed_at = now();
                    break;
            }
            
            // Merge metadata
            $episode->metadata = array_merge(
                $episode->metadata ?? [],
                $metadata,
                ['last_transition' => [
                    'from' => $currentStatus,
                    'to' => $newStatus,
                    'at' => now()->toIso8601String(),
                    'by' => auth()->id(),
                ]]
            );
            
            $episode->save();
            
            // Fire state transition event
            event(new EpisodeStateChanged($episode, $currentStatus, $newStatus));
            
            // Update related orders
            $this->updateRelatedOrders($episode, $newStatus);
        });
    }
    
    private function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->validTransitions[$from] ?? []);
    }
    
    private function updateRelatedOrders(
        PatientManufacturerIVREpisode $episode,
        string $newStatus
    ): void {
        $orderStatus = $this->mapEpisodeStatusToOrderStatus($newStatus);
        
        if ($orderStatus) {
            $episode->orders()->update([
                'status' => $orderStatus,
                'updated_at' => now(),
            ]);
        }
    }
}
```

### Episode-Based Controllers

#### 1. **Episode Management Controller**

```php
// app/Http/Controllers/Admin/EpisodeController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\EpisodeService;
use App\Services\IVRGenerationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EpisodeController extends Controller
{
    private EpisodeService $episodeService;
    private IVRGenerationService $ivrService;
    
    public function __construct(
        EpisodeService $episodeService,
        IVRGenerationService $ivrService
    ) {
        $this->episodeService = $episodeService;
        $this->ivrService = $ivrService;
    }
    
    public function index(Request $request)
    {
        $episodes = PatientManufacturerIVREpisode::with([
            'patient:id,first_name,last_name,display_id',
            'manufacturer:id,name',
            'orders:id,order_number,status,created_at',
        ])
        ->when($request->search, function ($query, $search) {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('display_id', 'like', "%{$search}%");
            });
        })
        ->when($request->status, function ($query, $status) {
            $query->where('episode_status', $status);
        })
        ->when($request->manufacturer_id, function ($query, $manufacturerId) {
            $query->where('manufacturer_id', $manufacturerId);
        })
        ->latest()
        ->paginate(20);
        
        return Inertia::render('Admin/Episodes/Index', [
            'episodes' => $episodes,
            'filters' => $request->only(['search', 'status', 'manufacturer_id']),
            'statuses' => [
                'pending' => 'Pending',
                'ivr_generated' => 'IVR Generated',
                'sent_to_manufacturer' => 'Sent to Manufacturer',
                'completed' => 'Completed',
            ],
        ]);
    }
    
    public function show(PatientManufacturerIVREpisode $episode)
    {
        $episode->load([
            'patient' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'display_id', 'fhir_id');
            },
            'manufacturer:id,name,contact_email,contact_phone',
            'orders' => function ($query) {
                $query->with(['products:id,name,sku'])
                    ->latest();
            },
        ]);
        
        // Get clinical data from FHIR
        $clinicalData = $this->episodeService->getEpisodeClinicalData($episode);
        
        // Get IVR history
        $ivrHistory = $this->ivrService->getIVRHistory($episode);
        
        return Inertia::render('Admin/Episodes/Show', [
            'episode' => $episode,
            'clinicalData' => $clinicalData,
            'ivrHistory' => $ivrHistory,
            'permissions' => [
                'canGenerateIVR' => $episode->canGenerateIVR(),
                'canSendToManufacturer' => $episode->canSendToManufacturer(),
                'canComplete' => $episode->isReadyForCompletion(),
            ],
        ]);
    }
    
    public function generateIVR(PatientManufacturerIVREpisode $episode)
    {
        $this->authorize('generateIVR', $episode);
        
        try {
            $result = $this->ivrService->generateEpisodeIVR($episode);
            
            return redirect()
                ->route('admin.episodes.show', $episode)
                ->with('success', 'IVR generated successfully');
                
        } catch (\Exception $e) {
            Log::error('IVR generation failed', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()
                ->route('admin.episodes.show', $episode)
                ->with('error', 'Failed to generate IVR: ' . $e->getMessage());
        }
    }
}
```

### Episode Service Layer

#### 1. **Episode Service Implementation**

```php
// app/Services/EpisodeService.php
namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class EpisodeService
{
    private FhirService $fhirService;
    private EpisodeStateMachine $stateMachine;
    
    public function __construct(
        FhirService $fhirService,
        EpisodeStateMachine $stateMachine
    ) {
        $this->fhirService = $fhirService;
        $this->stateMachine = $stateMachine;
    }
    
    public function createOrUpdateEpisode(Order $order): PatientManufacturerIVREpisode
    {
        return DB::transaction(function () use ($order) {
            // Find existing episode or create new one
            $episode = PatientManufacturerIVREpisode::firstOrCreate([
                'patient_id' => $order->patient_id,
                'manufacturer_id' => $order->manufacturer_id,
                'episode_status' => 'pending',
            ], [
                'metadata' => [
                    'created_from_order' => $order->id,
                    'created_at' => now()->toIso8601String(),
                ],
            ]);
            
            // Associate order with episode
            $order->update(['ivr_episode_id' => $episode->id]);
            
            // Log episode association
            activity()
                ->performedOn($episode)
                ->causedBy(auth()->user())
                ->withProperties([
                    'order_id' => $order->id,
                    'action' => 'order_associated',
                ])
                ->log('Order associated with episode');
            
            return $episode;
        });
    }
    
    public function getEpisodeClinicalData(PatientManufacturerIVREpisode $episode): array
    {
        try {
            // Get patient data from FHIR
            $patient = $this->fhirService->getResource('Patient', $episode->patient->fhir_id);
            
            // Get recent observations
            $observations = $this->fhirService->searchResources('Observation', [
                'patient' => $episode->patient->fhir_id,
                '_sort' => '-date',
                '_count' => 20,
            ]);
            
            // Get active conditions
            $conditions = $this->fhirService->searchResources('Condition', [
                'patient' => $episode->patient->fhir_id,
                'clinical-status' => 'active',
            ]);
            
            // Get wound-specific data
            $woundData = $this->extractWoundData($observations['resources']);
            
            // Get lab results
            $labResults = $this->extractLabResults($observations['resources']);
            
            return [
                'patient' => $this->formatPatientData($patient),
                'wounds' => $woundData,
                'lab_results' => $labResults,
                'conditions' => $this->formatConditions($conditions['resources']),
                'summary' => $this->generateClinicalSummary($woundData, $labResults),
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get episode clinical data', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'error' => 'Unable to retrieve clinical data',
                'patient' => null,
                'wounds' => [],
                'lab_results' => [],
                'conditions' => [],
            ];
        }
    }
    
    private function extractWoundData(array $observations): array
    {
        $wounds = [];
        
        foreach ($observations as $obs) {
            if ($this->isWoundObservation($obs)) {
                $wounds[] = [
                    'id' => $obs['id'],
                    'date' => $obs['effectiveDateTime'] ?? null,
                    'type' => $obs['code']['text'] ?? 'Unknown',
                    'measurements' => $this->extractWoundMeasurements($obs),
                    'location' => $this->extractBodySite($obs),
                    'assessment' => $obs['note'][0]['text'] ?? null,
                ];
            }
        }
        
        return $wounds;
    }
    
    private function generateClinicalSummary(array $wounds, array $labs): array
    {
        return [
            'total_wounds' => count($wounds),
            'latest_wound_date' => $wounds[0]['date'] ?? null,
            'hemoglobin_status' => $this->assessHemoglobin($labs['hemoglobin'] ?? null),
            'albumin_status' => $this->assessAlbumin($labs['albumin'] ?? null),
            'healing_potential' => $this->calculateHealingPotential($wounds, $labs),
        ];
    }
}
```

### IVR Generation Service

#### 1. **Episode-Based IVR Generation**

```php
// app/Services/IVRGenerationService.php
namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use App\Services\Integration\DocuSealIntegrationService;

class IVRGenerationService
{
    private DocuSealIntegrationService $docuSeal;
    private FhirService $fhirService;
    private EpisodeService $episodeService;
    
    public function generateEpisodeIVR(PatientManufacturerIVREpisode $episode): array
    {
        DB::beginTransaction();
        
        try {
            // Get clinical data
            $clinicalData = $this->episodeService->getEpisodeClinicalData($episode);
            
            // Get manufacturer template
            $template = $this->getManufacturerTemplate($episode->manufacturer_id);
            
            // Prepare IVR data
            $ivrData = $this->prepareIVRData($episode, $clinicalData);
            
            // Generate DocuSeal submission
            $submission = $this->docuSeal->createSubmission(
                $template['docuseal_template_id'],
                $ivrData
            );
            
            // Update episode with DocuSeal info
            $episode->update([
                'docuseal_submission_id' => $submission['id'],
                'ivr_status' => 'pending',
                'metadata' => array_merge($episode->metadata ?? [], [
                    'ivr_generation' => [
                        'generated_at' => now()->toIso8601String(),
                        'template_used' => $template['id'],
                        'submission_id' => $submission['id'],
                        'clinical_data_included' => array_keys($clinicalData),
                    ],
                ]),
            ]);
            
            // Transition episode state
            $this->stateMachine->transition($episode, 'ivr_generated');
            
            DB::commit();
            
            return [
                'success' => true,
                'submission_id' => $submission['id'],
                'submission_url' => $submission['url'],
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('IVR generation failed', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    private function prepareIVRData(
        PatientManufacturerIVREpisode $episode,
        array $clinicalData
    ): array {
        $patient = $clinicalData['patient'];
        $wounds = $clinicalData['wounds'];
        $labs = $clinicalData['lab_results'];
        
        // Get all products from episode orders
        $products = $episode->orders()
            ->with('products')
            ->get()
            ->pluck('products')
            ->flatten()
            ->unique('id');
        
        return [
            // Patient Information
            'patient_name' => $patient['name'],
            'patient_dob' => $patient['birth_date'],
            'patient_mrn' => $patient['mrn'],
            'patient_phone' => $patient['phone'],
            
            // Wound Information
            'primary_wound_type' => $wounds[0]['type'] ?? '',
            'wound_location' => $wounds[0]['location'] ?? '',
            'wound_dimensions' => $this->formatWoundDimensions($wounds[0] ?? []),
            'wound_duration' => $this->calculateWoundDuration($wounds[0] ?? []),
            
            // Clinical Data
            'hemoglobin' => $labs['hemoglobin']['value'] ?? '',
            'hba1c' => $labs['hba1c']['value'] ?? '',
            'albumin' => $labs['albumin']['value'] ?? '',
            
            // Products Requested
            'products_requested' => $products->map(function ($product) {
                return [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $product->pivot->quantity,
                ];
            })->toArray(),
            
            // Episode Information
            'episode_id' => $episode->id,
            'order_numbers' => $episode->orders->pluck('order_number')->join(', '),
            'total_orders' => $episode->orders->count(),
        ];
    }
}
```

### Episode Analytics

#### 1. **Episode Metrics Service**

```php
// app/Services/EpisodeAnalyticsService.php
namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Support\Facades\DB;

class EpisodeAnalyticsService
{
    public function getEpisodeMetrics(array $filters = []): array
    {
        $query = PatientManufacturerIVREpisode::query();
        
        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['manufacturer_id'])) {
            $query->where('manufacturer_id', $filters['manufacturer_id']);
        }
        
        return [
            'total_episodes' => $query->count(),
            'episodes_by_status' => $this->getEpisodesByStatus($query),
            'average_orders_per_episode' => $this->getAverageOrdersPerEpisode($query),
            'episode_duration_metrics' => $this->getEpisodeDurationMetrics($query),
            'manufacturer_distribution' => $this->getManufacturerDistribution($query),
            'completion_rate' => $this->getCompletionRate($query),
        ];
    }
    
    private function getEpisodesByStatus($baseQuery): array
    {
        return (clone $baseQuery)
            ->select('episode_status', DB::raw('COUNT(*) as count'))
            ->groupBy('episode_status')
            ->pluck('count', 'episode_status')
            ->toArray();
    }
    
    private function getEpisodeDurationMetrics($baseQuery): array
    {
        $durations = (clone $baseQuery)
            ->whereNotNull('completed_at')
            ->selectRaw('TIMESTAMPDIFF(HOUR, created_at, completed_at) as duration_hours')
            ->pluck('duration_hours')
            ->sort()
            ->values();
        
        if ($durations->isEmpty()) {
            return [
                'min' => 0,
                'max' => 0,
                'average' => 0,
                'median' => 0,
            ];
        }
        
        return [
            'min' => $durations->min(),
            'max' => $durations->max(),
            'average' => round($durations->average(), 2),
            'median' => $durations->median(),
        ];
    }
}
```

### Chapter Summary

The episode-based architecture provides:

1. **Clinical Grouping**: Orders grouped by patient + manufacturer
2. **State Management**: Clear workflow states with valid transitions
3. **Unified IVR Generation**: Single IVR per episode instead of per order
4. **Better Tracking**: Episode-level status and history
5. **Improved Efficiency**: Reduced paperwork and processing time
6. **Analytics**: Comprehensive metrics for workflow optimization
7. **Audit Trail**: Complete history of all episode actions

---

## Chapter 10: Frontend Implementation {#chapter-10}

### React and TypeScript Architecture

The frontend implementation leverages React with TypeScript to create a type-safe, maintainable user interface for the episode-based workflow system. This chapter details the component architecture, state management, and user experience design.

### TypeScript Type Definitions

#### 1. **Core Episode Types**

```typescript
// types/episode.ts
export interface Episode {
  id: string;
  patient_id: string;
  manufacturer_id: string;
  episode_status: EpisodeStatus;
  ivr_status: IVRStatus | null;
  docuseal_submission_id: string | null;
  ivr_generated_at: string | null;
  sent_to_manufacturer_at: string | null;
  manufacturer_response_at: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
  metadata: Record<string, any>;
  
  // Relationships
  patient?: Patient;
  manufacturer?: Manufacturer;
  orders?: Order[];
}

export enum EpisodeStatus {
  PENDING = 'pending',
  IVR_GENERATED = 'ivr_generated',
  SENT_TO_MANUFACTURER = 'sent_to_manufacturer',
  COMPLETED = 'completed',
}

export enum IVRStatus {
  PENDING = 'pending',
  IN_PROGRESS = 'in_progress',
  COMPLETED = 'completed',
  FAILED = 'failed',
}

export interface EpisodeFilters {
  search?: string;
  status?: EpisodeStatus;
  manufacturer_id?: string;
  date_from?: string;
  date_to?: string;
}

export interface EpisodeActions {
  canGenerateIVR: boolean;
  canSendToManufacturer: boolean;
  canComplete: boolean;
}
```

#### 2. **API Response Types**

```typescript
// types/api.ts
export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
  code?: string;
}

export interface EpisodeDetailResponse {
  episode: Episode;
  clinicalData: ClinicalData;
  ivrHistory: IVRHistoryItem[];
  permissions: EpisodeActions;
}
```

### Episode List Component

#### 1. **Episode Index Component**

```tsx
// resources/js/Pages/Admin/Episodes/Index.tsx
import React, { useState, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import { PaginatedResponse, Episode, EpisodeFilters } from '@/types/episode';
import EpisodeTable from '@/Components/Episodes/EpisodeTable';
import EpisodeFilters from '@/Components/Episodes/EpisodeFilters';
import Pagination from '@/Components/Common/Pagination';
import { useDebounce } from '@/hooks/useDebounce';

interface Props {
  episodes: PaginatedResponse<Episode>;
  filters: EpisodeFilters;
  statuses: Record<string, string>;
  manufacturers: Array<{ id: string; name: string }>;
}

export default function EpisodeIndex({ episodes, filters, statuses, manufacturers }: Props) {
  const [localFilters, setLocalFilters] = useState<EpisodeFilters>(filters);
  const debouncedSearch = useDebounce(localFilters.search, 300);
  
  // Apply filters
  useEffect(() => {
    router.get(
      route('admin.episodes.index'),
      { ...localFilters, search: debouncedSearch },
      { preserveState: true, preserveScroll: true }
    );
  }, [debouncedSearch, localFilters.status, localFilters.manufacturer_id]);
  
  const handleFilterChange = useCallback((key: keyof EpisodeFilters, value: any) => {
    setLocalFilters(prev => ({ ...prev, [key]: value }));
  }, []);
  
  const handleGenerateIVR = useCallback((episode: Episode) => {
    if (confirm('Generate IVR for this episode?')) {
      router.post(route('admin.episodes.generate-ivr', episode.id));
    }
  }, []);
  
  return (
    <>
      <Head title="Episode Management" />
      
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6">
              <h1 className="text-2xl font-semibold mb-6">Episode Management</h1>
              
              <EpisodeFilters
                filters={localFilters}
                statuses={statuses}
                manufacturers={manufacturers}
                onChange={handleFilterChange}
              />
              
              <EpisodeTable
                episodes={episodes.data}
                onGenerateIVR={handleGenerateIVR}
              />
              
              <Pagination
                links={episodes.links}
                from={episodes.from}
                to={episodes.to}
                total={episodes.total}
              />
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
```

#### 2. **Episode Table Component**

```tsx
// resources/js/Components/Episodes/EpisodeTable.tsx
import React from 'react';
import { Link } from '@inertiajs/react';
import { Episode } from '@/types/episode';
import StatusBadge from '@/Components/Common/StatusBadge';
import ActionButton from '@/Components/Common/ActionButton';
import { formatDate } from '@/utils/dates';

interface Props {
  episodes: Episode[];
  onGenerateIVR: (episode: Episode) => void;
}

export default function EpisodeTable({ episodes, onGenerateIVR }: Props) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Episode ID
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Patient
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Manufacturer
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Orders
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Status
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              IVR Status
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Created
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody className="bg-white divide-y divide-gray-200">
          {episodes.map((episode) => (
            <tr key={episode.id} className="hover:bg-gray-50">
              <td className="px-6 py-4 whitespace-nowrap">
                <Link
                  href={route('admin.episodes.show', episode.id)}
                  className="text-indigo-600 hover:text-indigo-900"
                >
                  {episode.id.substring(0, 8)}...
                </Link>
              </td>
              <td className="px-6 py-4 whitespace-nowrap">
                <div>
                  <div className="text-sm font-medium text-gray-900">
                    {episode.patient?.display_id}
                  </div>
                  <div className="text-sm text-gray-500">
                    {episode.patient?.first_name} {episode.patient?.last_name}
                  </div>
                </div>
              </td>
              <td className="px-6 py-4 whitespace-nowrap">
                <div className="text-sm text-gray-900">
                  {episode.manufacturer?.name}
                </div>
              </td>
              <td className="px-6 py-4 whitespace-nowrap">
                <div className="text-sm text-gray-900">
                  {episode.orders?.length || 0} orders
                </div>
              </td>
              <td className="px-6 py-4 whitespace-nowrap">
                <StatusBadge status={episode.episode_status} />
              </td>
              <td className="px-6 py-4 whitespace-nowrap">
                {episode.ivr_status && (
                  <StatusBadge 
                    status={episode.ivr_status} 
                    variant="secondary"
                  />
                )}
              </td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {formatDate(episode.created_at)}
              </td>
              <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div className="flex space-x-2">
                  <Link
                    href={route('admin.episodes.show', episode.id)}
                    className="text-indigo-600 hover:text-indigo-900"
                  >
                    View
                  </Link>
                  {episode.episode_status === 'pending' && (
                    <ActionButton
                      onClick={() => onGenerateIVR(episode)}
                      className="text-green-600 hover:text-green-900"
                    >
                      Generate IVR
                    </ActionButton>
                  )}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
```

### Episode Detail Component

#### 1. **Episode Show Component**

```tsx
// resources/js/Pages/Admin/Episodes/Show.tsx
import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Episode, EpisodeActions } from '@/types/episode';
import { ClinicalData, IVRHistoryItem } from '@/types/clinical';
import EpisodeHeader from '@/Components/Episodes/EpisodeHeader';
import EpisodeTimeline from '@/Components/Episodes/EpisodeTimeline';
import ClinicalDataPanel from '@/Components/Episodes/ClinicalDataPanel';
import OrdersPanel from '@/Components/Episodes/OrdersPanel';
import IVRHistoryPanel from '@/Components/Episodes/IVRHistoryPanel';
import ActionPanel from '@/Components/Episodes/ActionPanel';

interface Props {
  episode: Episode;
  clinicalData: ClinicalData;
  ivrHistory: IVRHistoryItem[];
  permissions: EpisodeActions;
}

export default function EpisodeShow({ 
  episode, 
  clinicalData, 
  ivrHistory, 
  permissions 
}: Props) {
  const [activeTab, setActiveTab] = useState<'clinical' | 'orders' | 'history'>('clinical');
  const [isProcessing, setIsProcessing] = useState(false);
  
  const handleAction = async (action: string) => {
    if (!confirm(`Are you sure you want to ${action}?`)) return;
    
    setIsProcessing(true);
    
    try {
      await router.post(
        route(`admin.episodes.${action}`, episode.id),
        {},
        {
          onSuccess: () => {
            // Page will reload with updated data
          },
          onError: (errors) => {
            console.error('Action failed:', errors);
            alert('Action failed. Please try again.');
          },
          onFinish: () => setIsProcessing(false),
        }
      );
    } catch (error) {
      console.error('Action error:', error);
      setIsProcessing(false);
    }
  };
  
  return (
    <>
      <Head title={`Episode ${episode.id.substring(0, 8)}`} />
      
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <EpisodeHeader episode={episode} />
            
            <div className="border-t border-gray-200">
              <EpisodeTimeline episode={episode} />
            </div>
            
            <div className="p-6">
              <div className="mb-6">
                <nav className="flex space-x-8" aria-label="Tabs">
                  {[
                    { id: 'clinical', name: 'Clinical Data' },
                    { id: 'orders', name: `Orders (${episode.orders?.length || 0})` },
                    { id: 'history', name: 'IVR History' },
                  ].map((tab) => (
                    <button
                      key={tab.id}
                      onClick={() => setActiveTab(tab.id as any)}
                      className={`${
                        activeTab === tab.id
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm`}
                    >
                      {tab.name}
                    </button>
                  ))}
                </nav>
              </div>
              
              <div className="mt-6">
                {activeTab === 'clinical' && (
                  <ClinicalDataPanel clinicalData={clinicalData} />
                )}
                {activeTab === 'orders' && (
                  <OrdersPanel orders={episode.orders || []} />
                )}
                {activeTab === 'history' && (
                  <IVRHistoryPanel history={ivrHistory} />
                )}
              </div>
              
              <ActionPanel
                episode={episode}
                permissions={permissions}
                onAction={handleAction}
                isProcessing={isProcessing}
              />
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
```

### State Management

#### 1. **Episode Store with Zustand**

```typescript
// stores/episodeStore.ts
import { create } from 'zustand';
import { devtools } from 'zustand/middleware';
import { Episode, EpisodeFilters } from '@/types/episode';
import api from '@/services/api';

interface EpisodeState {
  episodes: Episode[];
  currentEpisode: Episode | null;
  filters: EpisodeFilters;
  isLoading: boolean;
  error: string | null;
  
  // Actions
  setEpisodes: (episodes: Episode[]) => void;
  setCurrentEpisode: (episode: Episode | null) => void;
  setFilters: (filters: Partial<EpisodeFilters>) => void;
  fetchEpisodes: () => Promise<void>;
  fetchEpisode: (id: string) => Promise<void>;
  updateEpisodeStatus: (id: string, status: string) => Promise<void>;
}

export const useEpisodeStore = create<EpisodeState>()(
  devtools(
    (set, get) => ({
      episodes: [],
      currentEpisode: null,
      filters: {},
      isLoading: false,
      error: null,
      
      setEpisodes: (episodes) => set({ episodes }),
      
      setCurrentEpisode: (episode) => set({ currentEpisode: episode }),
      
      setFilters: (filters) => set((state) => ({
        filters: { ...state.filters, ...filters }
      })),
      
      fetchEpisodes: async () => {
        set({ isLoading: true, error: null });
        
        try {
          const
