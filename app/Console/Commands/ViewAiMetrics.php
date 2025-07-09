<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\FieldMappingMetricsService;

class ViewAiMetrics extends Command
{
    protected $signature = 'ai:metrics 
                           {--reset : Reset all metrics}
                           {--json : Output as JSON}';

    protected $description = 'View AI field mapping metrics and service health';

    public function handle(FieldMappingMetricsService $metricsService): int
    {
        if ($this->option('reset')) {
            if ($this->confirm('Are you sure you want to reset all AI metrics?')) {
                $metricsService->resetMetrics();
                $this->info('✓ AI metrics have been reset');
            }
            return Command::SUCCESS;
        }

        $metrics = $metricsService->getMetricsSummary();

        if ($this->option('json')) {
            $this->line(json_encode($metrics, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        // Display formatted metrics
        $this->newLine();
        $this->info('🤖 AI Field Mapping Metrics');
        $this->line(str_repeat('─', 50));

        // Overall Statistics
        $this->line('<fg=cyan>Overall Statistics:</>');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', $metrics['total_requests']],
                ['Successful Mappings', $metrics['successful_mappings']],
                ['Failed Mappings', $metrics['failed_mappings']],
                ['Fallback Used', $metrics['fallback_used']],
                ['Success Rate', $metrics['success_rate'] . '%'],
            ]
        );

        // Performance Metrics
        $this->newLine();
        $this->line('<fg=cyan>Performance Metrics:</>');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Average Response Time', $metrics['average_response_time'] . ' ms'],
                ['Average Confidence', $metrics['average_confidence']],
                ['Average Field Completeness', $metrics['average_field_completeness'] . '%'],
            ]
        );

        // Service Health
        $this->newLine();
        $this->line('<fg=cyan>Service Health:</>');
        $health = $metrics['service_health'];
        $statusColor = match($health['status']) {
            'healthy' => 'green',
            'warning' => 'yellow',
            'degraded' => 'red',
            default => 'gray'
        };
        
        $this->line("Status: <fg={$statusColor}>" . strtoupper($health['status']) . "</>");
        $this->line("Success Rate: {$health['success_rate']}%");
        $this->line("Avg Response Time: {$health['avg_response_time_ms']} ms");
        $this->line("Last Check: {$health['last_check']}");

        // 24-Hour Statistics
        $this->newLine();
        $this->line('<fg=cyan>Last 24 Hours:</>');
        $last24h = $metrics['last_24h_stats'];
        $this->line("Requests/Hour: {$last24h['requests_per_hour']['current_hour']}");
        $this->line("Peak Hour: {$last24h['peak_hour']}");
        
        if ($last24h['error_spike_detected']) {
            $this->warn('⚠️  Error spike detected! Failure rate > 20%');
        }

        // Recommendations
        if ($metrics['success_rate'] < 80) {
            $this->newLine();
            $this->warn('⚠️  Recommendations:');
            $this->line('  • Check AI service health: php artisan ai:health');
            $this->line('  • Review recent error logs: tail -f storage/logs/ai-metrics.log');
            $this->line('  • Verify Azure OpenAI credentials are valid');
        }

        $this->newLine();
        return Command::SUCCESS;
    }
}