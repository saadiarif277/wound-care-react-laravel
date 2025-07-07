<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\AzureFoundryService;

class TestAzureOpenAI extends Command
{
    protected $signature = 'azure:test-openai';
    protected $description = 'Test Azure OpenAI connection and configuration';

    public function handle()
    {
        $this->info('Testing Azure OpenAI configuration...');
        
        // Show current configuration (without exposing keys)
        $this->info('Configuration:');
        $this->line('Endpoint: ' . config('azure.ai_foundry.endpoint'));
        $this->line('Deployment: ' . config('azure.ai_foundry.deployment_name'));
        $this->line('API Version: ' . config('azure.ai_foundry.api_version'));
        $this->line('API Key: ' . (config('azure.ai_foundry.api_key') ? '***configured***' : 'NOT SET'));
        
        try {
            $service = new AzureFoundryService();
            
            // Test 1: Connection test
            $this->info("\nTest 1: Testing connection...");
            $connectionResult = $service->testConnection();
            
            if ($connectionResult['success']) {
                $this->info('✓ Connection successful!');
                $this->line('Response: ' . json_encode($connectionResult, JSON_PRETTY_PRINT));
            } else {
                $this->error('✗ Connection failed: ' . $connectionResult['error']);
                return 1;
            }
            
            // Test 2: Simple chat
            $this->info("\nTest 2: Testing chat response...");
            $chatResponse = $service->generateChatResponse(
                'Hello, please respond with a simple greeting. Do not say this is a demo.',
                [],
                '',
                'You are a helpful AI assistant. Respond naturally to greetings.'
            );
            
            $this->info('✓ Chat response received:');
            $this->line($chatResponse);
            
            // Check if response contains "demo"
            if (stripos($chatResponse, 'demo') !== false) {
                $this->warn("\n⚠️  WARNING: Response contains 'demo' - your Azure OpenAI deployment might be in demo mode!");
                $this->warn("Please check your Azure OpenAI deployment in the Azure portal.");
            }
            
            // Test 3: System prompt test
            $this->info("\nTest 3: Testing with medical context...");
            $medicalResponse = $service->generateChatResponse(
                'What type of assistant are you?',
                [],
                '',
                'You are a helpful AI assistant for MSC Wound Care Portal. You help healthcare providers with wound care product requests.'
            );
            
            $this->info('✓ Medical context response:');
            $this->line($medicalResponse);
            
            $this->info("\n✅ All tests completed!");
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error('Stack trace:');
            $this->line($e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
} 