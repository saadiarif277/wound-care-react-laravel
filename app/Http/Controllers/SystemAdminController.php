<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SystemAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:msc-admin']);
    }

    public function config(Request $request)
    {
        $this->authorize('manage-system-config');

        // Mock system configuration data
        $systemConfig = [
            'application' => [
                'name' => config('app.name', 'MSC Wound Care Portal'),
                'version' => '2.1.0',
                'environment' => config('app.env'),
                'debug_mode' => config('app.debug'),
                'maintenance_mode' => app()->isDownForMaintenance(),
                'timezone' => config('app.timezone'),
            ],
            'database' => [
                'connection' => config('database.default'),
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'connection_status' => $this->getDatabaseStatus(),
                'last_backup' => '2024-03-25 02:00:00',
            ],
            'integrations' => [
                'azure_health_services' => [
                    'status' => 'connected',
                    'last_sync' => '2024-03-25 14:30:00',
                    'endpoint' => config('azure.fhir_endpoint'),
                ],
                'cms_api' => [
                    'status' => 'connected',
                    'last_validation' => '2024-03-25 13:45:00',
                    'rate_limit_remaining' => 4750,
                ],
                'ecw_integration' => [
                    'status' => 'connected',
                    'last_sync' => '2024-03-25 15:00:00',
                    'active_connections' => 12,
                ],
            ],
            'performance' => [
                'cache_status' => Cache::getStore() ? 'active' : 'inactive',
                'queue_status' => 'running',
                'storage_usage' => '45.2 GB / 100 GB',
                'avg_response_time' => '245ms',
            ],
        ];

        return Inertia::render('SystemAdmin/Config', [
            'systemConfig' => $systemConfig,
            'recentActivity' => $this->getRecentSystemActivity(),
        ]);
    }

    public function integrations(Request $request)
    {
        $this->authorize('manage-system-config');

        $integrations = [
            [
                'id' => 1,
                'name' => 'Azure Health Data Services (FHIR)',
                'type' => 'healthcare_data',
                'status' => 'active',
                'description' => 'FHIR R4 compliant patient data storage',
                'endpoint' => 'https://msc-fhir.azurehealthcareapis.com',
                'last_sync' => '2024-03-25 14:30:00',
                'sync_frequency' => 'real-time',
                'configuration' => [
                    'tenant_id' => 'configured',
                    'client_id' => 'configured',
                    'resource_audience' => 'configured',
                ],
                'health_status' => 'healthy',
                'error_rate' => 0.02,
            ],
            [
                'id' => 2,
                'name' => 'CMS Medicare Coverage Database',
                'type' => 'coverage_validation',
                'status' => 'active',
                'description' => 'Medicare MAC validation and coverage determination',
                'endpoint' => 'https://www.cms.gov/medicare-coverage-database',
                'last_sync' => '2024-03-25 13:45:00',
                'sync_frequency' => 'daily',
                'configuration' => [
                    'api_key' => 'configured',
                    'contractor_id' => 'configured',
                    'jurisdiction' => 'all_macs',
                ],
                'health_status' => 'healthy',
                'error_rate' => 0.01,
            ],
            [
                'id' => 3,
                'name' => 'eClinicalWorks (ECW)',
                'type' => 'ehr_integration',
                'status' => 'active',
                'description' => 'Electronic Health Record integration',
                'endpoint' => 'https://api.ecwcloud.com',
                'last_sync' => '2024-03-25 15:00:00',
                'sync_frequency' => 'real-time',
                'configuration' => [
                    'client_credentials' => 'configured',
                    'webhook_url' => 'configured',
                    'scope' => 'patient.read order.write',
                ],
                'health_status' => 'healthy',
                'error_rate' => 0.05,
            ],
            [
                'id' => 4,
                'name' => 'Supabase (Operational Data)',
                'type' => 'operational_database',
                'status' => 'active',
                'description' => 'Non-PHI operational data storage',
                'endpoint' => config('supabase.url'),
                'last_sync' => '2024-03-25 15:15:00',
                'sync_frequency' => 'real-time',
                'configuration' => [
                    'project_url' => 'configured',
                    'anon_key' => 'configured',
                    'service_role_key' => 'configured',
                ],
                'health_status' => 'healthy',
                'error_rate' => 0.01,
            ],
        ];

        return Inertia::render('SystemAdmin/Integrations', [
            'integrations' => $integrations,
            'integrationStats' => [
                'total_integrations' => count($integrations),
                'active_integrations' => count(array_filter($integrations, fn($i) => $i['status'] === 'active')),
                'average_error_rate' => round(array_sum(array_column($integrations, 'error_rate')) / count($integrations), 3),
                'total_api_calls_today' => 15247,
            ],
        ]);
    }

    public function api(Request $request)
    {
        $this->authorize('manage-system-config');

        // Mock API management data
        $apiEndpoints = [
            [
                'id' => 1,
                'path' => '/api/product-requests',
                'method' => 'GET',
                'description' => 'List product requests',
                'rate_limit' => '1000/hour',
                'authentication' => 'bearer_token',
                'permissions_required' => ['view-product-requests'],
                'usage_24h' => 245,
                'avg_response_time' => '125ms',
                'error_rate' => 0.02,
                'last_called' => '2024-03-25 15:20:00',
            ],
            [
                'id' => 2,
                'path' => '/api/mac-validation/quick-check',
                'method' => 'POST',
                'description' => 'Quick MAC validation check',
                'rate_limit' => '500/hour',
                'authentication' => 'bearer_token',
                'permissions_required' => ['manage-mac-validation'],
                'usage_24h' => 89,
                'avg_response_time' => '350ms',
                'error_rate' => 0.05,
                'last_called' => '2024-03-25 15:18:00',
            ],
            [
                'id' => 3,
                'path' => '/api/products/recommendations',
                'method' => 'GET',
                'description' => 'Get product recommendations',
                'rate_limit' => '2000/hour',
                'authentication' => 'bearer_token',
                'permissions_required' => ['view-products'],
                'usage_24h' => 456,
                'avg_response_time' => '89ms',
                'error_rate' => 0.01,
                'last_called' => '2024-03-25 15:22:00',
            ],
        ];

        $apiStats = [
            'total_endpoints' => count($apiEndpoints),
            'total_calls_24h' => array_sum(array_column($apiEndpoints, 'usage_24h')),
            'average_response_time' => round(array_sum(array_column($apiEndpoints, 'avg_response_time')) / count($apiEndpoints)),
            'average_error_rate' => round(array_sum(array_column($apiEndpoints, 'error_rate')) / count($apiEndpoints), 3),
        ];

        return Inertia::render('SystemAdmin/Api', [
            'apiEndpoints' => $apiEndpoints,
            'apiStats' => $apiStats,
            'recentApiCalls' => $this->getRecentApiCalls(),
        ]);
    }

    public function audit(Request $request)
    {
        $this->authorize('view-audit-logs');

        // Mock audit log data
        $auditLogs = [
            [
                'id' => 1,
                'event_type' => 'user_login',
                'user_email' => 'provider@example.com',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'timestamp' => '2024-03-25 15:22:15',
                'details' => 'Successful login from new device',
                'risk_level' => 'low',
            ],
            [
                'id' => 2,
                'event_type' => 'product_request_approved',
                'user_email' => 'admin@mscwoundcare.com',
                'ip_address' => '10.0.0.50',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'timestamp' => '2024-03-25 15:20:30',
                'details' => 'Product request PR-2024-0325-001 approved',
                'risk_level' => 'low',
            ],
            [
                'id' => 3,
                'event_type' => 'permission_changed',
                'user_email' => 'admin@mscwoundcare.com',
                'ip_address' => '10.0.0.50',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'timestamp' => '2024-03-25 14:45:00',
                'details' => 'Added manage-products permission to user jane.doe@clinic.com',
                'risk_level' => 'medium',
            ],
        ];

        // Apply filters
        if ($request->filled('event_type')) {
            $auditLogs = array_filter($auditLogs, fn($log) => $log['event_type'] === $request->input('event_type'));
        }

        if ($request->filled('risk_level')) {
            $auditLogs = array_filter($auditLogs, fn($log) => $log['risk_level'] === $request->input('risk_level'));
        }

        return Inertia::render('SystemAdmin/Audit', [
            'auditLogs' => array_values($auditLogs),
            'filters' => $request->only(['event_type', 'risk_level', 'date_from', 'date_to']),
            'eventTypes' => ['user_login', 'user_logout', 'product_request_approved', 'permission_changed', 'system_config_changed'],
            'riskLevels' => ['low', 'medium', 'high', 'critical'],
            'auditStats' => [
                'total_events_24h' => count($auditLogs),
                'high_risk_events' => count(array_filter($auditLogs, fn($log) => in_array($log['risk_level'], ['high', 'critical']))),
                'unique_users' => count(array_unique(array_column($auditLogs, 'user_email'))),
                'login_events' => count(array_filter($auditLogs, fn($log) => $log['event_type'] === 'user_login')),
            ],
        ]);
    }

    // Private helper methods

    private function getDatabaseStatus(): string
    {
        try {
            DB::connection()->getPdo();
            return 'connected';
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }

    private function getRecentSystemActivity(): array
    {
        return [
            [
                'timestamp' => '2024-03-25 15:20:00',
                'event' => 'Database backup completed',
                'status' => 'success',
            ],
            [
                'timestamp' => '2024-03-25 14:30:00',
                'event' => 'Azure FHIR sync completed',
                'status' => 'success',
            ],
            [
                'timestamp' => '2024-03-25 13:45:00',
                'event' => 'CMS API rate limit warning',
                'status' => 'warning',
            ],
        ];
    }

    private function getRecentApiCalls(): array
    {
        return [
            [
                'timestamp' => '2024-03-25 15:22:00',
                'endpoint' => '/api/products/recommendations',
                'method' => 'GET',
                'status_code' => 200,
                'response_time' => '95ms',
                'user' => 'provider@example.com',
            ],
            [
                'timestamp' => '2024-03-25 15:21:45',
                'endpoint' => '/api/product-requests',
                'method' => 'GET',
                'status_code' => 200,
                'response_time' => '134ms',
                'user' => 'admin@mscwoundcare.com',
            ],
            [
                'timestamp' => '2024-03-25 15:21:30',
                'endpoint' => '/api/mac-validation/quick-check',
                'method' => 'POST',
                'status_code' => 200,
                'response_time' => '287ms',
                'user' => 'office.manager@clinic.com',
            ],
        ];
    }
} 