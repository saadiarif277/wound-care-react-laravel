<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\PatientIVRStatus;
use App\Models\Order\Product;
use App\Models\User;
use App\Models\Fhir\Facility;

class EpisodeSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating episode seeder data...');

                // Get actual manufacturers from the database
        $manufacturers = \App\Models\Order\Manufacturer::take(3)->get();

        if ($manufacturers->isEmpty()) {
            $this->command->warn('No manufacturers found in database. Please run CategoriesAndManufacturersSeeder first.');
            return;
        }

        $this->command->info('Creating episodes with real manufacturer data...');

        // Define test patients with realistic data
        $patients = [
            [
                'fhir_id' => 'Patient/episode-patient-001',
                'display_id' => 'JODO001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'dob' => '1965-03-15',
                'wound_type' => 'DFU',
                'payer' => 'Medicare Part B'
            ],
            [
                'fhir_id' => 'Patient/episode-patient-002',
                'display_id' => 'MASM002',
                'first_name' => 'Maria',
                'last_name' => 'Smith',
                'dob' => '1972-08-22',
                'wound_type' => 'VLU',
                'payer' => 'Blue Cross Blue Shield'
            ],
            [
                'fhir_id' => 'Patient/episode-patient-003',
                'display_id' => 'ROJO003',
                'first_name' => 'Robert',
                'last_name' => 'Johnson',
                'dob' => '1958-11-30',
                'wound_type' => 'PU',
                'payer' => 'Aetna'
            ],
            [
                'fhir_id' => 'Patient/episode-patient-004',
                'display_id' => 'LIWI004',
                'first_name' => 'Linda',
                'last_name' => 'Wilson',
                'dob' => '1980-05-18',
                'wound_type' => 'DFU',
                'payer' => 'Medicare Part B'
            ]
        ];

                // Use the manufacturer objects we fetched above

        DB::beginTransaction();

        try {
            $episodeCount = 0;

            // Create episodes for each patient-manufacturer combination
            foreach ($patients as $patientData) {
                foreach ($manufacturers as $manufacturer) {
                    $episodeCount++;

                    // Determine episode status based on creation order
                    $episodeStatus = match($episodeCount % 6) {
                        1 => 'ready_for_review',
                        2 => 'ivr_sent',
                        3 => 'ivr_verified',
                        4 => 'sent_to_manufacturer',
                        5 => 'tracking_added',
                        0 => 'completed'
                    };

                    // Determine IVR status
                    $ivrStatus = match($episodeStatus) {
                        'ready_for_review' => 'pending',
                        'ivr_sent' => 'pending',
                        'ivr_verified' => 'verified',
                        'sent_to_manufacturer' => 'verified',
                        'tracking_added' => 'verified',
                        'completed' => 'verified'
                    };

                                        // Create the episode using the correct table structure
                    $episode = PatientIVRStatus::create([
                        'patient_id' => \Illuminate\Support\Str::uuid(), // Mock patient UUID
                        'manufacturer_id' => $manufacturer->id, // Use real manufacturer ID
                        'status' => $episodeStatus,
                        'ivr_status' => $ivrStatus,
                        'verification_date' => in_array($episodeStatus, ['ivr_verified', 'sent_to_manufacturer', 'tracking_added', 'completed']) ? now()->subDays(rand(1, 15)) : null,
                        'expiration_date' => in_array($episodeStatus, ['ivr_verified', 'sent_to_manufacturer', 'tracking_added', 'completed']) ? now()->addDays(90) : null,
                        'frequency_days' => 90,
                        'created_at' => now()->subDays(rand(1, 30)),
                        'updated_at' => now()->subDays(rand(0, 5)),
                        'completed_at' => $episodeStatus === 'completed' ? now()->subDays(rand(1, 5)) : null,
                    ]);

                                        $this->command->info("Created episode {$episode->id}: {$patientData['display_id']} + {$manufacturer->name} ({$episodeStatus})");


                }
            }

            DB::commit();
            $this->command->info("Successfully created {$episodeCount} episodes!");

        } catch (\Exception $e) {
            DB::rollback();
            $this->command->error('Episode seeder failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
