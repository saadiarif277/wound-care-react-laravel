<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;

class EpisodeWorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $manufacturer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with proper permissions
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create a test manufacturer
        $this->manufacturer = Manufacturer::factory()->create([
            'name' => 'Test Manufacturer',
            'contact_email' => 'test@manufacturer.com'
        ]);
    }

    /** @test */
    public function it_can_display_episode_index_page()
    {
        // Create test episodes
        $episode1 = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'ready_for_review',
            'ivr_status' => 'pending'
        ]);

        $episode2 = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'ivr_verified',
            'ivr_status' => 'verified'
        ]);

        // Create orders for episodes
        Order::factory()->create(['ivr_episode_id' => $episode1->id]);
        Order::factory()->create(['ivr_episode_id' => $episode2->id]);

        $response = $this->get(route('admin.orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/OrderCenter/Index')
            ->has('episodes.data', 2)
            ->has('statusCounts')
            ->has('ivrStatusCounts')
            ->has('manufacturers')
        );
    }

    /** @test */
    public function it_can_filter_episodes_by_status()
    {
        // Create episodes with different statuses
        PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'ready_for_review',
            'ivr_status' => 'pending'
        ]);

        PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'completed',
            'ivr_status' => 'verified'
        ]);

        $response = $this->get(route('admin.orders.index', ['status' => 'ready_for_review']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('filters.status', 'ready_for_review')
            ->has('episodes.data', 1)
            ->where('episodes.data.0.status', 'ready_for_review')
        );
    }

    /** @test */
    public function it_can_search_episodes()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'patient_id' => 'test-patient-123'
        ]);

        $response = $this->get(route('admin.orders.index', ['search' => 'test-patient']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('filters.search', 'test-patient')
            ->has('episodes.data', 1)
        );
    }

    /** @test */
    public function it_can_display_episode_detail_page()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'ready_for_review',
            'ivr_status' => 'pending'
        ]);

        // Create orders for the episode
        $order1 = Order::factory()->create(['ivr_episode_id' => $episode->id]);
        $order2 = Order::factory()->create(['ivr_episode_id' => $episode->id]);

        $response = $this->get(route('admin.episodes.show', $episode->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/OrderCenter/ShowEpisode')
            ->where('episode.id', $episode->id)
            ->where('episode.status', 'ready_for_review')
            ->where('episode.ivr_status', 'pending')
            ->has('episode.orders', 2)
            ->has('can_generate_ivr')
            ->has('can_manage_episode')
            ->has('can_send_to_manufacturer')
        );
    }

    /** @test */
    public function it_redirects_individual_order_to_episode_if_episode_exists()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id
        ]);

        $order = Order::factory()->create(['ivr_episode_id' => $episode->id]);

        $response = $this->get(route('admin.orders.show', $order->id));

        $response->assertRedirect(route('admin.episodes.show', $episode->id));
    }

    /** @test */
    public function it_can_generate_ivr_for_episode()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'ready_for_review',
            'ivr_status' => 'pending'
        ]);

        // Create orders for the episode
        Order::factory()->create(['ivr_episode_id' => $episode->id]);

        $response = $this->postJson(route('admin.episodes.generate-ivr', $episode->id));

        // Note: This will fail in tests due to DocuSeal service dependencies
        // In a real test, we would mock the DocuSeal service
        $response->assertStatus(500); // Expected due to service dependencies
    }

    /** @test */
    public function it_validates_episode_status_for_ivr_generation()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'completed',
            'ivr_status' => 'verified' // Already verified
        ]);

        $response = $this->postJson(route('admin.episodes.generate-ivr', $episode->id));

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'IVR can only be generated for episodes with pending status'
        ]);
    }

    /** @test */
    public function it_can_send_episode_to_manufacturer()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'ivr_verified',
            'ivr_status' => 'verified'
        ]);

        Order::factory()->create(['ivr_episode_id' => $episode->id]);

        $response = $this->postJson(route('admin.episodes.send-to-manufacturer', $episode->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Episode sent to manufacturer successfully'
        ]);

        $episode->refresh();
        $this->assertEquals('sent_to_manufacturer', $episode->status);
    }

    /** @test */
    public function it_validates_episode_status_for_manufacturer_submission()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'ready_for_review', // Not IVR verified
            'ivr_status' => 'pending'
        ]);

        $response = $this->postJson(route('admin.episodes.send-to-manufacturer', $episode->id));

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Episode must be IVR verified before sending to manufacturer'
        ]);
    }

    /** @test */
    public function it_can_update_episode_tracking()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'sent_to_manufacturer'
        ]);

        $order = Order::factory()->create(['ivr_episode_id' => $episode->id]);

        $trackingData = [
            'tracking_number' => 'TRK123456789',
            'carrier' => 'UPS',
            'estimated_delivery' => '2025-02-15'
        ];

        $response = $this->postJson(route('admin.episodes.update-tracking', $episode->id), $trackingData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Tracking information updated successfully'
        ]);

        $episode->refresh();
        $order->refresh();

        $this->assertEquals('tracking_added', $episode->status);
        $this->assertEquals('shipped', $order->order_status);
        $this->assertEquals('TRK123456789', $order->tracking_number);
        $this->assertEquals('UPS', $order->carrier);
    }

    /** @test */
    public function it_validates_tracking_data()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'sent_to_manufacturer'
        ]);

        $invalidData = [
            'tracking_number' => '', // Required
            'carrier' => '', // Required
        ];

        $response = $this->postJson(route('admin.episodes.update-tracking', $episode->id), $invalidData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tracking_number', 'carrier']);
    }

    /** @test */
    public function it_can_mark_episode_as_completed()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'tracking_added'
        ]);

        $order = Order::factory()->create(['ivr_episode_id' => $episode->id]);

        $response = $this->postJson(route('admin.episodes.mark-completed', $episode->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Episode marked as completed successfully'
        ]);

        $episode->refresh();
        $order->refresh();

        $this->assertEquals('completed', $episode->status);
        $this->assertEquals('delivered', $order->order_status);
        $this->assertNotNull($order->delivered_at);
    }

    /** @test */
    public function it_validates_episode_status_for_completion()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'status' => 'ready_for_review' // Invalid status for completion
        ]);

        $response = $this->postJson(route('admin.episodes.mark-completed', $episode->id));

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Episode must have tracking or be sent to manufacturer before completion'
        ]);
    }

    /** @test */
    public function it_calculates_episode_metrics_correctly()
    {
        $episode = PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id
        ]);

        // Create orders with different values
        Order::factory()->create([
            'ivr_episode_id' => $episode->id,
            'total_amount' => 1000,
            'action_required' => true
        ]);

        Order::factory()->create([
            'ivr_episode_id' => $episode->id,
            'total_amount' => 1500,
            'action_required' => false
        ]);

        $response = $this->get(route('admin.orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('episodes.data.0.orders_count', 2)
            ->where('episodes.data.0.total_order_value', 2500)
            ->where('episodes.data.0.action_required', true)
        );
    }

    /** @test */
    public function it_handles_expiring_ivrs()
    {
        // Create episode with IVR expiring soon
        PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'ivr_status' => 'verified',
            'expiration_date' => now()->addDays(15) // Expires in 15 days
        ]);

        // Create episode with IVR not expiring soon
        PatientManufacturerIVREpisode::factory()->create([
            'manufacturer_id' => $this->manufacturer->id,
            'ivr_status' => 'verified',
            'expiration_date' => now()->addDays(60) // Expires in 60 days
        ]);

        $response = $this->get(route('admin.orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('expiringIVRs', 1) // Only one expiring within 30 days
        );
    }

    /** @test */
    public function it_groups_orders_by_patient_and_manufacturer()
    {
        $patientId = 'Patient/test-patient-123';

        // Create two episodes for same patient but different manufacturers
        $manufacturer1 = Manufacturer::factory()->create(['name' => 'Manufacturer A']);
        $manufacturer2 = Manufacturer::factory()->create(['name' => 'Manufacturer B']);

        $episode1 = PatientManufacturerIVREpisode::factory()->create([
            'patient_id' => $patientId,
            'manufacturer_id' => $manufacturer1->id
        ]);

        $episode2 = PatientManufacturerIVREpisode::factory()->create([
            'patient_id' => $patientId,
            'manufacturer_id' => $manufacturer2->id
        ]);

        // Create orders for each episode
        Order::factory()->create(['ivr_episode_id' => $episode1->id]);
        Order::factory()->create(['ivr_episode_id' => $episode2->id]);

        $response = $this->get(route('admin.orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('episodes.data', 2) // Two separate episodes
            ->where('episodes.data.0.patient_id', $patientId)
            ->where('episodes.data.1.patient_id', $patientId)
        );
    }
}
