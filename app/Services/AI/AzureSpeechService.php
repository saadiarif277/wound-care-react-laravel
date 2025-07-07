<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AzureSpeechService
{
    private string $speechKey;
    private string $speechRegion;
    private string $speechEndpoint;

    public function __construct()
    {
        $this->speechKey = config('azure.speech.key', '');
        $this->speechRegion = config('azure.speech.region', 'eastus');
        $this->speechEndpoint = config('azure.speech.endpoint') ?: 
            "https://{$this->speechRegion}.tts.speech.microsoft.com/cognitiveservices/v1";
    }

    /**
     * Convert text to speech using Azure Neural voices
     */
    public function textToSpeech(string $text, array $options = []): array
    {
        $voice = $options['voice'] ?? 'en-US-JennyNeural'; // Natural female voice
        $rate = $options['rate'] ?? '0%'; // Normal speed
        $pitch = $options['pitch'] ?? '0%'; // Normal pitch

        $ssml = $this->buildSSML($text, $voice, $rate, $pitch);

        try {
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->speechKey,
                'Content-Type' => 'application/ssml+xml',
                'X-Microsoft-OutputFormat' => 'audio-24khz-48kbitrate-mono-mp3',
                'User-Agent' => 'MSCWoundCare'
            ])
            ->withBody($ssml, 'application/ssml+xml')
            ->post($this->speechEndpoint);

            if (!$response->successful()) {
                throw new Exception('Azure Speech synthesis failed: ' . $response->body());
            }

            // Return audio data as base64
            return [
                'success' => true,
                'audio' => base64_encode($response->body()),
                'format' => 'mp3'
            ];

        } catch (Exception $e) {
            Log::error('Azure Speech Service error', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available neural voices
     */
    public function getVoices(): array
    {
        return [
            'en-US-JennyNeural' => 'Jenny (Female, Natural)',
            'en-US-GuyNeural' => 'Guy (Male, Natural)',
            'en-US-AriaNeural' => 'Aria (Female, Conversational)',
            'en-US-DavisNeural' => 'Davis (Male, Professional)',
            'en-US-JaneNeural' => 'Jane (Female, Friendly)',
            'en-US-JasonNeural' => 'Jason (Male, Casual)',
            'en-US-SaraNeural' => 'Sara (Female, Patient)',
            'en-US-TonyNeural' => 'Tony (Male, Enthusiastic)'
        ];
    }

    /**
     * Build SSML for speech synthesis
     */
    private function buildSSML(string $text, string $voice, string $rate, string $pitch): string
    {
        // Escape XML characters
        $text = htmlspecialchars($text, ENT_XML1, 'UTF-8');

        return <<<SSML
<speak version='1.0' xml:lang='en-US'>
    <voice xml:lang='en-US' name='{$voice}'>
        <prosody rate='{$rate}' pitch='{$pitch}'>
            {$text}
        </prosody>
    </voice>
</speak>
SSML;
    }

    /**
     * Stream text to speech for real-time playback
     */
    public function streamTextToSpeech(string $text, callable $onChunk, array $options = []): void
    {
        // Split text into sentences for streaming
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        
        foreach ($sentences as $sentence) {
            if (trim($sentence)) {
                $result = $this->textToSpeech($sentence, $options);
                if ($result['success']) {
                    $onChunk($result['audio']);
                }
            }
        }
    }
} 