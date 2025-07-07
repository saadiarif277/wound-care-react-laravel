<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\AzureFoundryService;
use App\Services\AI\AzureRealtimeService;
use App\Services\AI\AzureSpeechService;

class TestAzureAIServices extends Command
{
    protected $signature = 'ai:test-services';
    protected $description = 'Test all Azure AI services configuration';

    public function handle(
        AzureFoundryService $foundryService,
        AzureRealtimeService $realtimeService,
        AzureSpeechService $speechService
    ): int
    {
        $this->info('Testing Azure AI Services Configuration...');
        $this->newLine();

        // Test Azure OpenAI Text Endpoint
        $this->info('1. Testing Azure OpenAI Text Endpoint (GPT-4o)...');
        try {
            $response = $foundryService->generateChatResponse(
                'Say "Hello, Azure!"',
                [],
                '',
                'You are a test assistant. Respond with exactly: "Hello, Azure!"'
            );
            
            if (str_contains($response, 'Hello, Azure!')) {
                $this->line('   ✓ Azure OpenAI Text API: <fg=green>Connected</>');
                $this->line('   - Endpoint: ' . config('azure.ai_foundry.endpoint'));
                $this->line('   - Deployment: ' . config('azure.ai_foundry.deployment_name'));
            } else {
                $this->error('   ✗ Azure OpenAI Text API: Unexpected response');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Azure OpenAI Text API: ' . $e->getMessage());
        }

        $this->newLine();

        // Test Azure Realtime API
        $this->info('2. Testing Azure OpenAI Realtime Endpoint (Voice)...');
        try {
            $session = $realtimeService->createSession(['voice' => 'alloy']);
            
            if ($session['success']) {
                $this->line('   ✓ Azure Realtime API: <fg=green>Available</>');
                $this->line('   - WebSocket URL: ' . $session['websocket_url']);
                $this->line('   - Deployment: ' . $session['deployment']);
                $this->line('   - Model: ' . $session['model']);
                
                if (isset($session['warning'])) {
                    $this->warn('   ⚠ ' . $session['warning']);
                }
            } else {
                $this->error('   ✗ Azure Realtime API: ' . ($session['error'] ?? 'Failed to create session'));
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Azure Realtime API: ' . $e->getMessage());
        }

        $this->newLine();

        // Test Azure Speech Services
        $this->info('3. Testing Azure Speech Services (TTS)...');
        try {
            $result = $speechService->textToSpeech('Test', ['voice' => 'en-US-JennyNeural']);
            
            if ($result['success']) {
                $this->line('   ✓ Azure Speech Services: <fg=green>Connected</>');
                $this->line('   - Region: ' . config('azure.speech.region'));
                $this->line('   - Available voices: ' . count($speechService->getVoices()));
            } else {
                $this->error('   ✗ Azure Speech Services: ' . ($result['error'] ?? 'Failed'));
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Azure Speech Services: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('Configuration test complete!');
        
        return Command::SUCCESS;
    }
} 