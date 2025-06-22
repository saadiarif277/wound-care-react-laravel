<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\User;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Str;

class PatientManufacturerIVREpisodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $patients = User::whereHas('roles', function ($query) {
            $query->where('slug', 'patient');
        })->take(5)->get();

        $manufacturers = Manufacturer::take(5)->get();

        if ($patients->isEmpty() || $manufacturers->isEmpty()) {
            $this->command->info('Could not find patients or manufacturers to seed episodes.');
            return;
        }

        foreach ($patients as $patient) {
            foreach ($manufacturers as $manufacturer) {
                PatientManufacturerIVREpisode::create([
                    'id' => Str::uuid(),
                    'patient_id' => $patient->id,
                    'patient_fhir_id' => 'FHIR-' . $patient->id,
                    'patient_display_id' => 'PAT' . substr(str_shuffle('0123456789'), 1, 4),
                    'manufacturer_id' => $manufacturer->id,
                    'status' => 'pending_ivr',
                    'ivr_status' => 'not_started',
                    'verification_date' => now(),
                    'expiration_date' => now()->addMonths(3),
                    'metadata' => [
                        'created_by' => 'seeder',
                    ],
                ]);
            }
        }
    }
}
