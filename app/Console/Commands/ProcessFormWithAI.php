<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;
use App\Models\Episode;

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
    protected $description = 'Process form data using Python AI script for intelligent field mapping';

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

        // Verify episode exists
        $episode = Episode::find($episodeId);
        if (!$episode) {
            $this->error("Episode {$episodeId} not found");
            return 1;
        }

        // Get Python executable and venv path from config
        $pythonExec = config('services.python.executable', env('PYTHON_EXECUTABLE', 'python3'));
        $venvPath = config('services.python.venv_path', env('PYTHON_VENV_PATH', './Scripts/.venv'));
        
        // Use venv Python if available
        $venvPython = base_path("{$venvPath}/bin/python");
        if (file_exists($venvPython)) {
            $pythonExec = $venvPython;
            $this->info("Using virtual environment Python: {$venvPython}");
        }

        // Build command
        $scriptPath = base_path('scripts/form_map_gpt.py');
        $command = [
            $pythonExec,
            $scriptPath,
            '--fhir-episode', $episodeId,
            '--manufacturer', $manufacturer,
            '--form-type', $formType,
            '--output-format', 'json'
        ];

        if ($debug) {
            $command[] = '--debug';
        }

        // Execute Python script
        $process = new Process($command);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(60); // 60 seconds timeout

        try {
            $this->info("Executing: " . implode(' ', $command));
            $process->mustRun();

            $output = $process->getOutput();
            
            if ($debug) {
                $this->info("Raw output:");
                $this->line($output);
            }

            // Parse JSON output
            $result = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Failed to parse JSON output: " . json_last_error_msg());
            }

            // Display results
            $this->info("✅ AI Processing Complete!");
            
            if (isset($result['mapped_fields'])) {
                $this->info("Mapped Fields: " . count($result['mapped_fields']));
                
                if ($debug) {
                    $this->table(
                        ['Field', 'Value', 'Confidence'],
                        collect($result['mapped_fields'])->map(function ($value, $field) use ($result) {
                            return [
                                $field,
                                is_array($value) ? json_encode($value) : $value,
                                $result['confidence_scores'][$field] ?? 'N/A'
                            ];
                        })->toArray()
                    );
                }
            }

            if (isset($result['fhir_resources'])) {
                $this->info("FHIR Resources Created: " . count($result['fhir_resources']));
            }

            if (isset($result['docuseal_submission'])) {
                $this->info("Docuseal Submission ID: " . $result['docuseal_submission']['id']);
            }

            // Log to Laravel logs
            Log::info('AI form processing completed', [
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturer,
                'result' => $result
            ]);

            return 0;

        } catch (ProcessFailedException $e) {
            $this->error("Python script failed: " . $e->getMessage());
            $this->error("Error output: " . $process->getErrorOutput());
            
            Log::error('AI form processing failed', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage(),
                'stderr' => $process->getErrorOutput()
            ]);
            
            return 1;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            
            Log::error('AI form processing error', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage()
            ]);
            
            return 1;
        }
    }
}