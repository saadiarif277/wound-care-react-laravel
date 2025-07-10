<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\InsuranceAIAssistantService;
use App\Services\AI\AzureAIAgentService;
use App\Services\Learning\ContinuousLearningService;
use App\Services\Learning\BehavioralTrackingService;
use App\Services\Learning\MLDataPipelineService;
use Exception;

class TestInsuranceAIAssistant extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:insurance-ai-assistant 
                          {--manufacturer=biowound-solutions : Manufacturer to test with}
                          {--template-id=test-template : Template ID to test with}
                          {--user-id=1 : User ID to test with}
                          {--message=How can you help me with insurance forms? : Test message}';

    /**
     * The console command description.
     */
    protected $description = 'Test the Insurance AI Assistant integration with Microsoft AI agent and ML ensemble';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Insurance AI Assistant Integration');
        $this->newLine();

        // First check configuration
        if (!$this->checkConfiguration()) {
            $this->error('❌ Configuration check failed. Please fix the issues above before proceeding.');
            return Command::FAILURE;
        }

        // Test individual components
        $this->info('📋 Testing Configuration...');
        $this->testConfiguration();
        $this->newLine();

        $this->info('🧠 Testing ML Integration...');
        $this->testMLIntegration();
        $this->newLine();

        $this->info('💬 Testing Conversation Flow...');
        $this->testConversationFlow();
        $this->newLine();

        $this->info('📝 Testing Form Assistance...');
        $this->testFormAssistance();
        $this->newLine();

        $this->info('🎯 Testing Personalized Recommendations...');
        $this->testPersonalizedRecommendations();
        $this->newLine();

        $this->info('🎤 Testing Voice Mode...');
        $this->testVoiceMode();
        $this->newLine();

        $this->info('📊 Testing Status Monitoring...');
        $this->testStatusMonitoring();
        $this->newLine();

        $this->info('✅ Insurance AI Assistant testing complete!');
        return Command::SUCCESS;
    }

    private function checkConfiguration(): bool
    {
        $configOk = true;
        
        $this->info('🔧 Checking Configuration...');
        
        // Check required Azure AI settings
        $assistantId = config('azure.insurance_assistant.assistant_id');
        if (empty($assistantId)) {
            $this->error('❌ AZURE_INSURANCE_ASSISTANT_ID not set in .env');
            $this->comment('   Add your Microsoft AI agent ID to .env file');
            $configOk = false;
        } else {
            $this->info('✅ Azure Insurance Assistant ID configured');
        }

        // Check Azure OpenAI settings
        $azureEndpoint = config('azure.openai.endpoint');
        $azureKey = config('azure.openai.key');
        if (empty($azureEndpoint) || empty($azureKey)) {
            $this->warn('⚠️  Azure OpenAI not fully configured');
            $this->comment('   Set AZURE_OPENAI_ENDPOINT and AZURE_OPENAI_KEY for full functionality');
        } else {
            $this->info('✅ Azure OpenAI configured');
        }

        // Check DocuSeal settings
        $docusealKey = config('services.docuseal.api_key');
        if (empty($docusealKey)) {
            $this->warn('⚠️  DocuSeal API key not configured');
            $this->comment('   Set DOCUSEAL_API_KEY for form assistance testing');
        } else {
            $this->info('✅ DocuSeal API configured');
        }

        $this->newLine();
        
        if (!$configOk) {
            $this->comment('Configuration help:');
            $this->comment('1. Copy .env.example to .env if not done already');
            $this->comment('2. Add your Microsoft AI agent ID: AZURE_INSURANCE_ASSISTANT_ID=your-agent-id');
            $this->comment('3. Add Azure OpenAI credentials: AZURE_OPENAI_ENDPOINT and AZURE_OPENAI_KEY');
            $this->comment('4. Add DocuSeal API key: DOCUSEAL_API_KEY=your-docuseal-key');
        }
        
        return $configOk;
    }

    private function testConfiguration()
    {
        $assistantId = config('azure.insurance_assistant.assistant_id');
        $voiceEnabled = config('azure.insurance_assistant.voice_enabled');
        $mlEnhancement = config('azure.insurance_assistant.enable_ml_enhancement');

        if (empty($assistantId)) {
            $this->error('❌ AZURE_INSURANCE_ASSISTANT_ID not configured');
            $this->comment('   Please set AZURE_INSURANCE_ASSISTANT_ID in your .env file');
            return;
        }

        $this->info("✅ Assistant ID: {$assistantId}");
        $this->info("✅ Voice Enabled: " . ($voiceEnabled ? 'Yes' : 'No'));
        $this->info("✅ ML Enhancement: " . ($mlEnhancement ? 'Yes' : 'No'));
    }



    private function testMLIntegration()
    {
        try {
            $continuousLearning = app(ContinuousLearningService::class);
            $this->info('✅ ContinuousLearningService available');
        } catch (Exception $e) {
            $this->error('❌ ContinuousLearningService not available: ' . $e->getMessage());
        }

        try {
            $behavioralTracking = app(BehavioralTrackingService::class);
            $this->info('✅ BehavioralTrackingService available');
        } catch (Exception $e) {
            $this->error('❌ BehavioralTrackingService not available: ' . $e->getMessage());
        }

        try {
            $mlPipeline = app(MLDataPipelineService::class);
            $this->info('✅ MLDataPipelineService available');
        } catch (Exception $e) {
            $this->error('❌ MLDataPipelineService not available: ' . $e->getMessage());
        }
    }

    private function testConversationFlow()
    {
        try {
            $service = app(InsuranceAIAssistantService::class);
            
            // Start conversation
            $context = [
                'manufacturer' => $this->option('manufacturer'),
                'template_id' => $this->option('template-id'),
                'user_id' => $this->option('user-id')
            ];

            $this->info('🚀 Starting conversation...');
            
            // Check if Azure AI is configured first
            $assistantId = config('azure.insurance_assistant.assistant_id');
            if (empty($assistantId)) {
                $this->warn('⚠️  Azure Insurance Assistant ID not configured - using mock mode');
                $this->info('   Please set AZURE_INSURANCE_ASSISTANT_ID in .env for full testing');
                return;
            }

            $result = $service->startConversation($context);

            if ($result['success']) {
                $this->info('✅ Conversation started successfully');
                $this->info('   Thread ID: ' . $result['thread_id']);
                $this->info('   Assistant ID: ' . $result['assistant_id']);
                $this->info('   Voice Enabled: ' . ($result['voice_enabled'] ? 'Yes' : 'No'));
                
                // Test sending a message
                $this->info('💬 Sending test message...');
                $messageResult = $service->sendMessage($this->option('message'), $context);
                
                if ($messageResult['success']) {
                    $this->info('✅ Message sent successfully');
                    $this->info('   ML Enhanced: ' . ($messageResult['ml_enhanced'] ? 'Yes' : 'No'));
                    $this->info('   Insurance Data Used: ' . ($messageResult['insurance_data_used'] ? 'Yes' : 'No'));
                } else {
                    $this->error('❌ Failed to send message: ' . $messageResult['error']);
                    $this->comment('   This might be due to Azure AI configuration or network issues');
                }
            } else {
                $this->error('❌ Failed to start conversation: ' . $result['error']);
                $this->comment('   Common causes:');
                $this->comment('   - Azure AI endpoint not reachable');
                $this->comment('   - Invalid API key or assistant ID');
                $this->comment('   - Network connectivity issues');
            }
        } catch (Exception $e) {
            $this->error('❌ Conversation flow test failed: ' . $e->getMessage());
            $this->comment('   This might indicate missing dependencies or configuration issues');
        }
    }

    private function testFormAssistance()
    {
        try {
            // Check if DocuSeal is configured
            $docusealApiKey = config('services.docuseal.api_key');
            if (empty($docusealApiKey)) {
                $this->warn('⚠️  DocuSeal API key not configured - skipping form assistance test');
                $this->info('   Please set DOCUSEAL_API_KEY in .env for full testing');
                return;
            }

            $service = app(InsuranceAIAssistantService::class);
            
            $result = $service->getFormAssistance(
                $this->option('template-id'),
                $this->option('manufacturer')
            );

            if ($result['success']) {
                $this->info('✅ Form assistance working');
                $this->info('   Template Fields: ' . count($result['template_fields'] ?? []));
                $this->info('   Manufacturer Patterns: ' . count($result['manufacturer_patterns'] ?? []));
            } else {
                $this->error('❌ Form assistance failed: ' . $result['error']);
                if (strpos($result['error'], '404') !== false) {
                    $this->comment('   This is likely because the test template ID doesn\'t exist');
                    $this->comment('   Try using a real template ID from your DocuSeal account');
                }
            }
        } catch (Exception $e) {
            $this->error('❌ Form assistance test failed: ' . $e->getMessage());
            $this->comment('   This might be due to DocuSeal API configuration or template issues');
        }
    }

    private function testPersonalizedRecommendations()
    {
        try {
            $service = app(InsuranceAIAssistantService::class);
            
            $result = $service->getPersonalizedRecommendations(
                $this->option('user-id'),
                [
                    'manufacturer' => $this->option('manufacturer'),
                    'template_id' => $this->option('template-id')
                ]
            );

            if ($result['success']) {
                $this->info('✅ Personalized recommendations working');
                $this->info('   ML Enhanced: ' . ($result['ml_enhanced'] ? 'Yes' : 'No'));
                $this->info('   Personalized: ' . ($result['personalized'] ? 'Yes' : 'No'));
            } else {
                $this->error('❌ Personalized recommendations failed: ' . $result['error']);
            }
        } catch (Exception $e) {
            $this->error('❌ Personalized recommendations test failed: ' . $e->getMessage());
        }
    }

    private function testVoiceMode()
    {
        try {
            $voiceEnabled = config('azure.insurance_assistant.voice_enabled', false);
            
            if (!$voiceEnabled) {
                $this->warn('⚠️  Voice mode disabled in configuration');
                $this->comment('   Set AZURE_INSURANCE_ASSISTANT_VOICE_ENABLED=true to enable');
                return;
            }

            $service = app(InsuranceAIAssistantService::class);
            
            $result = $service->enableVoiceMode([
                'language' => 'en-US',
                'voice' => 'neural',
                'output_format' => 'audio-24khz-48kbitrate-mono-mp3'
            ]);

            if ($result['success']) {
                $this->info('✅ Voice mode enabled successfully');
                $this->info('   Speech Recognition: ' . ($result['speech_recognition'] ? 'Yes' : 'No'));
                $this->info('   Text-to-Speech: ' . ($result['text_to_speech'] ? 'Yes' : 'No'));
                $this->info('   Language: ' . ($result['language'] ?? 'Not set'));
            } else {
                $this->error('❌ Voice mode failed: ' . $result['error']);
                $this->comment('   This might be due to Azure Speech Services configuration');
            }
        } catch (Exception $e) {
            $this->error('❌ Voice mode test failed: ' . $e->getMessage());
            $this->comment('   Azure Speech Services might not be configured');
        }
    }

    private function testStatusMonitoring()
    {
        try {
            $this->info('🔍 Checking service health status...');
            
            // Check individual services
            $services = [
                'AzureAIAgentService',
                'ContinuousLearningService',
                'BehavioralTrackingService',
                'MLDataPipelineService',
            ];

            $healthyServices = 0;
            $totalServices = count($services);

            foreach ($services as $serviceName) {
                try {
                    app($serviceName);
                    $this->info("✅ {$serviceName} - Healthy");
                    $healthyServices++;
                } catch (Exception $e) {
                    $this->warn("⚠️  {$serviceName} - Unavailable");
                    $this->comment("   Error: " . $e->getMessage());
                }
            }

            // Overall health score
            $healthScore = ($healthyServices / $totalServices) * 100;
            
            if ($healthScore >= 75) {
                $this->info("✅ System Health: {$healthScore}% ({$healthyServices}/{$totalServices} services healthy)");
            } elseif ($healthScore >= 50) {
                $this->warn("⚠️  System Health: {$healthScore}% ({$healthyServices}/{$totalServices} services healthy)");
            } else {
                $this->error("❌ System Health: {$healthScore}% ({$healthyServices}/{$totalServices} services healthy)");
            }
            
        } catch (Exception $e) {
            $this->error('❌ Status monitoring failed: ' . $e->getMessage());
        }
    }
} 