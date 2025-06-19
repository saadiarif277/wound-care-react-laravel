<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PatientIVRStatus;
use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class PatientIVRStatusTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_an_episode()
    {
        $manufacturer = Manufacturer::factory()->create();

        $episode = PatientIVRStatus::create([
            'id' => 'test-episode-id',
            'patient_id' => 'Patient/test-patient-123',
            'manufacturer_id' => $manufacturer->id,
            'status' => 'ready_for_review',
            'ivr_status' => 'pending',
        ]);

        $this->assertDatabaseHas('patient_manufacturer_ivr_episodes', [
            'id' => 'test-episode-id',
            'patient_id' => 'Patient/test-patient-123',
            'manufacturer_id' => $manufacturer->id,
            'status' => 'ready_for_review',
            'ivr_status' => 'pending',
        ]);
    }

    /** @test */
    public function it_has_manufacturer_relationship()
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Test Manufacturer']);

        $episode = PatientIVRStatus::factory()->create([
            'manufacturer_id' => $manufacturer->id
        ]);

        $this->assertEquals('Test Manufacturer', $episode->manufacturer->name);
    }

    /** @test */
    public function it_has_orders_relationship()
    {
        $episode = PatientIVRStatus::factory()->create();

        $order1 = Order::factory()->create(['ivr_episode_id' => $episode->id]);
        $order2 = Order::factory()->create(['ivr_episode_id' => $episode->id]);

        $this->assertCount(2, $episode->orders);
        $this->assertTrue($episode->orders->contains($order1));
        $this->assertTrue($episode->orders->contains($order2));
    }

    /** @test */
    public function it_can_check_if_ivr_is_expired()
    {
        // Episode with expired IVR
        $expiredEpisode = PatientIVRStatus::factory()->create([
            'expiration_date' => Carbon::yesterday()
        ]);

        // Episode with active IVR
        $activeEpisode = PatientIVRStatus::factory()->create([
            'expiration_date' => Carbon::tomorrow()
        ]);

        // Episode with no expiration date
        $noExpirationEpisode = PatientIVRStatus::factory()->create([
            'expiration_date' => null
        ]);

        $this->assertTrue($expiredEpisode->isExpired());
        $this->assertFalse($activeEpisode->isExpired());
        $this->assertTrue($noExpirationEpisode->isExpired());
    }

    /** @test */
    public function it_can_check_if_ivr_is_expiring_soon()
    {
        // Episode expiring in 15 days (within 30 days)
        $expiringSoonEpisode = PatientIVRStatus::factory()->create([
            'expiration_date' => Carbon::now()->addDays(15)
        ]);

        // Episode expiring in 45 days (not within 30 days)
        $notExpiringSoonEpisode = PatientIVRStatus::factory()->create([
            'expiration_date' => Carbon::now()->addDays(45)
        ]);

        // Episode with no expiration date
        $noExpirationEpisode = PatientIVRStatus::factory()->create([
            'expiration_date' => null
        ]);

        $this->assertTrue($expiringSoonEpisode->isExpiringSoon());
        $this->assertFalse($notExpiringSoonEpisode->isExpiringSoon());
        $this->assertFalse($noExpirationEpisode->isExpiringSoon());
    }

    /** @test */
    public function it_can_calculate_next_expiration_date()
    {
        $episode = PatientIVRStatus::factory()->create(['frequency' => 'monthly']);
        $baseDate = Carbon::parse('2025-01-01');

        $nextExpiration = $episode->calculateNextExpirationDate($baseDate);

        $this->assertEquals('2025-02-01', $nextExpiration->format('Y-m-d'));
    }

    /** @test */
    public function it_calculates_expiration_date_for_different_frequencies()
    {
        $baseDate = Carbon::parse('2025-01-01');

        // Weekly frequency
        $weeklyEpisode = PatientIVRStatus::factory()->create(['frequency' => 'weekly']);
        $weeklyExpiration = $weeklyEpisode->calculateNextExpirationDate($baseDate);
        $this->assertEquals('2025-01-08', $weeklyExpiration->format('Y-m-d'));

        // Monthly frequency
        $monthlyEpisode = PatientIVRStatus::factory()->create(['frequency' => 'monthly']);
        $monthlyExpiration = $monthlyEpisode->calculateNextExpirationDate($baseDate);
        $this->assertEquals('2025-02-01', $monthlyExpiration->format('Y-m-d'));

        // Quarterly frequency
        $quarterlyEpisode = PatientIVRStatus::factory()->create(['frequency' => 'quarterly']);
        $quarterlyExpiration = $quarterlyEpisode->calculateNextExpirationDate($baseDate);
        $this->assertEquals('2025-04-01', $quarterlyExpiration->format('Y-m-d'));

        // Yearly frequency
        $yearlyEpisode = PatientIVRStatus::factory()->create(['frequency' => 'yearly']);
        $yearlyExpiration = $yearlyEpisode->calculateNextExpirationDate($baseDate);
        $this->assertEquals('2026-01-01', $yearlyExpiration->format('Y-m-d'));

        // Default frequency (quarterly)
        $defaultEpisode = PatientIVRStatus::factory()->create(['frequency' => 'unknown']);
        $defaultExpiration = $defaultEpisode->calculateNextExpirationDate($baseDate);
        $this->assertEquals('2025-04-01', $defaultExpiration->format('Y-m-d'));
    }

    /** @test */
    public function it_can_mark_as_verified()
    {
        $episode = PatientIVRStatus::factory()->create([
            'status' => 'pending',
            'last_verified_date' => null,
            'expiration_date' => null,
            'frequency' => 'quarterly'
        ]);

        $docusealSubmissionId = 'test-submission-123';
        $episode->markAsVerified($docusealSubmissionId);

        $episode->refresh();

        $this->assertNotNull($episode->last_verified_date);
        $this->assertNotNull($episode->expiration_date);
        $this->assertEquals('active', $episode->status);
        $this->assertEquals($docusealSubmissionId, $episode->latest_docuseal_submission_id);
    }

    /** @test */
    public function it_can_get_patient_status_across_manufacturers()
    {
        $patientId = 'Patient/test-patient-123';
        $manufacturer1 = Manufacturer::factory()->create();
        $manufacturer2 = Manufacturer::factory()->create();

        // Create episodes for the same patient with different manufacturers
        PatientIVRStatus::factory()->create([
            'patient_id' => $patientId,
            'manufacturer_id' => $manufacturer1->id,
            'expiration_date' => Carbon::now()->addDays(30)
        ]);

        PatientIVRStatus::factory()->create([
            'patient_id' => $patientId,
            'manufacturer_id' => $manufacturer2->id,
            'expiration_date' => Carbon::now()->addDays(60)
        ]);

        // Create episode for different patient
        PatientIVRStatus::factory()->create([
            'patient_id' => 'Patient/different-patient',
            'manufacturer_id' => $manufacturer1->id
        ]);

        $patientStatuses = PatientIVRStatus::getPatientStatus($patientId);

        $this->assertCount(2, $patientStatuses);
        $this->assertTrue($patientStatuses->every(fn($status) => $status->patient_id === $patientId));
    }

    /** @test */
    public function it_can_get_expiring_ivrs()
    {
        // Create episodes with different expiration dates
        PatientIVRStatus::factory()->create([
            'status' => 'active',
            'expiration_date' => Carbon::now()->addDays(15) // Expiring within 30 days
        ]);

        PatientIVRStatus::factory()->create([
            'status' => 'active',
            'expiration_date' => Carbon::now()->addDays(45) // Not expiring within 30 days
        ]);

        PatientIVRStatus::factory()->create([
            'status' => 'inactive',
            'expiration_date' => Carbon::now()->addDays(15) // Expiring but inactive
        ]);

        $expiringIVRs = PatientIVRStatus::getExpiringIVRs(30);

        $this->assertCount(1, $expiringIVRs);
        $this->assertEquals('active', $expiringIVRs->first()->status);
    }

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $episode = PatientIVRStatus::factory()->create();

        $this->assertIsString($episode->id);
        $this->assertEquals(36, strlen($episode->id)); // UUID length
        $this->assertFalse($episode->incrementing);
        $this->assertEquals('string', $episode->getKeyType());
    }

    /** @test */
    public function it_uses_correct_table_name()
    {
        $episode = new PatientIVRStatus();

        $this->assertEquals('patient_manufacturer_ivr_episodes', $episode->getTable());
    }

    /** @test */
    public function it_has_correct_fillable_fields()
    {
        $episode = new PatientIVRStatus();

        $expectedFillable = [
            'docuseal_submission_id',
            'docuseal_status',
            'docuseal_audit_log_url',
            'docuseal_signed_document_url',
            'docuseal_template_id',
            'docuseal_last_synced_at',
        ];

        $this->assertEquals($expectedFillable, $episode->getFillable());
    }

    /** @test */
    public function it_can_find_or_create_episode_for_patient_manufacturer()
    {
        $patientId = 'Patient/test-patient-123';
        $manufacturer = Manufacturer::factory()->create();

        // First call should create new episode
        $episode1 = PatientIVRStatus::where('patient_id', $patientId)
            ->where('manufacturer_id', $manufacturer->id)
            ->where(function($q) {
                $q->whereNull('expiration_date')->orWhere('expiration_date', '>', now());
            })
            ->first();

        $this->assertNull($episode1);

        // Create episode
        $newEpisode = PatientIVRStatus::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'patient_id' => $patientId,
            'manufacturer_id' => $manufacturer->id,
            'status' => 'ready_for_review',
            'ivr_status' => 'pending',
        ]);

        // Second call should return existing episode
        $episode2 = PatientIVRStatus::where('patient_id', $patientId)
            ->where('manufacturer_id', $manufacturer->id)
            ->where(function($q) {
                $q->whereNull('expiration_date')->orWhere('expiration_date', '>', now());
            })
            ->first();

        $this->assertNotNull($episode2);
        $this->assertEquals($newEpisode->id, $episode2->id);
    }
}
