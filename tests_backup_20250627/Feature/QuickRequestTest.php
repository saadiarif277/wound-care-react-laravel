<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\Episode;
use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use App\Mail\ManufacturerOrderEmail;
use App\Services\DocuSealService;

class QuickRequestTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    public function test_start_episode_endpoint()
    {
        // Mock DocuSealService to prevent external API calls
        $docuSealService = \Mockery::mock(DocuSealService::class);
        $docuSealService->shouldReceive('createIVRSubmission')
            ->once()
            ->andReturn(['embed_url' => 'http://example.com/ivrs', 'submission_id' => 'sub1']);
        $this->app->instance(DocuSealService::class, $docuSealService);

        $payload = [
            'patient_id'           => 'p1',
            'patient_fhir_id'      => 'pfhir1',
            'patient_display_id'   => 'disp1',
            'manufacturer_id'      => Str::uuid()->toString(),
            'order_details'        => ['product' => 'x', 'quantity' => 1],
        ];

        $response = $this->postJson('/api/v1/quick-request/episodes', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure(['success', 'episode_id', 'order_id']);

        $this->assertDatabaseHas('patient_manufacturer_ivr_episodes', [
            'id' => $response->json('episode_id'),
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $response->json('order_id'),
        ]);
    }

    public function test_add_follow_up_endpoint()
    {
        $episode = Episode::create([
            'patient_id'           => 'p2',
            'patient_fhir_id'      => 'pfhir2',
            'patient_display_id'   => 'disp2',
            'manufacturer_id'      => Str::uuid()->toString(),
            'status'               => 'draft',
        ]);

        $initialOrder = Order::create([
            'episode_id' => $episode->id,
            'type'       => 'initial',
            'details'    => [],
        ]);

        $payload = [
            'parent_order_id' => $initialOrder->id,
            'order_details'   => ['item' => 'y', 'quantity' => 2],
        ];

        $response = $this->postJson("/api/v1/quick-request/episodes/{$episode->id}/follow-up", $payload);

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['order_id']);

        $this->assertDatabaseHas('orders', [
            'parent_order_id' => $initialOrder->id,
            'type'            => 'follow_up',
        ]);
    }

    public function test_approve_endpoint()
    {
        Mail::fake();

        $episode = Episode::create([
            'patient_id'           => 'p3',
            'patient_fhir_id'      => 'pfhir3',
            'patient_display_id'   => 'disp3',
            'manufacturer_id'      => Str::uuid()->toString(),
            'status'               => 'pending_review',
        ]);

        $manufacturer = Manufacturer::create([
            'name'          => 'Test',
            'slug'          => 'test',
            'contact_email' => 'mfg@example.com',
            'contact_phone' => '123',
            'address'       => [],
            'website'       => '',
            'is_active'     => true,
        ]);

        $episode->manufacturer()->associate($manufacturer)->save();

        $response = $this->postJson("/api/v1/quick-request/episodes/{$episode->id}/approve");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('patient_manufacturer_ivr_episodes', [
            'id'     => $episode->id,
            'status' => 'manufacturer_review',
        ]);

        Mail::assertSent(ManufacturerOrderEmail::class);
    }
}