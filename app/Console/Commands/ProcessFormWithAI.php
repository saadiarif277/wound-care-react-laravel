<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiFormFillerService;
use App\Models\Episode;
use Illuminate\Support\Facades\Log;

class ProcessFormWithAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'form:process-ai {episode_id} {manufacturer} {--type=ivr : Form type (ivr, order)} {--debug : Show debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process form data using the AiFormFillerService for intelligent field mapping';

    protected $aiFormFiller;

    public function __construct(AiFormFillerService $aiFormFiller)
    {
        parent::__construct();
        $this->aiFormFiller = $aiFormFiller;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $episodeId = $this->argument('episode_id');
        $manufacturer = $this->argument('manufacturer');
        $formType = $this->option('type');
        $debug = $this->option('debug');

        $this->info("🤖 Processing form with AI for Episode: {$episodeId}, Manufacturer: {$manufacturer}");

        $episode = Episode::find($episodeId);
        if (!$episode) {
            $this->error("Episode {$episodeId} not found");
            return 1;
        }

        // Prepare data for the service
        // This is a simplified example. In a real scenario, you'd gather more comprehensive data.
        $formData = [
            'patient_first_name' => $episode->patient->first_name ?? 'Test',
            'patient_last_name' => $episode->patient->last_name ?? 'Patient',
            'patient_dob' => $episode->patient->date_of_birth ?? '1970-01-01',
            // Add other relevant data from the episode
        ];
        
        $this->info("Calling AiFormFillerService...");

        try {
            $result = $this->aiFormFiller->fillFormFields($formData, $formType, []);
            
            if ($debug) {
                $this->info("Raw output from service:");
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
            }

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'AI service returned an error');
            }
            
            $this->info("✅ AI Processing Complete!");

            if (isset($result['filled_fields'])) {
                $this->info("Mapped Fields: " . count($result['filled_fields']));
                
                if ($debug) {
                    $this->table(
                        ['Field', 'Value', 'Confidence'],
                        collect($result['filled_fields'])->map(function ($value, $field) use ($result) {
                            return [
                                $field,
                                is_array($value) ? json_encode($value) : $value,
                                $result['confidence_scores'][$field] ?? 'N/A'
                            ];
                        })->toArray()
                    );
                }
            }

            // Here you would typically update the episode or related models with the results
            // For example: $episode->update(['metadata' -> $result['filled_fields']]);

            Log::info('AI form processing completed via command', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturer,
                'result' => $result
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            
            Log::error('AI form processing command failed', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);
            
            return 1;
        }
    }
}