<?php

namespace Database\Seeders;

use App\Models\VerifiedSender;
use App\Models\SenderMapping;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VerifiedSendersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default MSC Platform sender
        $mscSender = VerifiedSender::firstOrCreate(
            ['email_address' => 'noreply@mscwoundcare.com'],
            [
                'display_name' => 'MSC Wound Care Platform',
                'organization' => 'MSC Platform',
                'is_verified' => true,
                'verification_method' => 'azure_domain',
                'is_active' => true,
                'verified_at' => now(),
                'metadata' => [
                    'purpose' => 'Default platform sender',
                    'created_by_seeder' => true,
                ],
            ]
        );

        // Create orders notification sender
        $ordersSender = VerifiedSender::firstOrCreate(
            ['email_address' => 'orders@mscwoundcare.com'],
            [
                'display_name' => 'MSC Orders Team',
                'organization' => 'MSC Platform',
                'is_verified' => true,
                'verification_method' => 'azure_domain',
                'is_active' => true,
                'verified_at' => now(),
                'metadata' => [
                    'purpose' => 'Order notifications and manufacturer communications',
                    'created_by_seeder' => true,
                ],
            ]
        );

        // Create IVR notification sender
        $ivrSender = VerifiedSender::firstOrCreate(
            ['email_address' => 'ivr@mscwoundcare.com'],
            [
                'display_name' => 'MSC IVR Team',
                'organization' => 'MSC Platform',
                'is_verified' => true,
                'verification_method' => 'azure_domain',
                'is_active' => true,
                'verified_at' => now(),
                'metadata' => [
                    'purpose' => 'Insurance verification requests',
                    'created_by_seeder' => true,
                ],
            ]
        );

        // Create support sender
        $supportSender = VerifiedSender::firstOrCreate(
            ['email_address' => 'support@mscwoundcare.com'],
            [
                'display_name' => 'MSC Support Team',
                'organization' => 'MSC Platform',
                'is_verified' => true,
                'verification_method' => 'azure_domain',
                'is_active' => true,
                'verified_at' => now(),
                'metadata' => [
                    'purpose' => 'Customer support and notifications',
                    'created_by_seeder' => true,
                ],
            ]
        );

        // Example partner organization sender (on behalf)
        $partnerSender = VerifiedSender::firstOrCreate(
            ['email_address' => 'orders@partnerclinic.com'],
            [
                'display_name' => 'Partner Clinic Orders',
                'organization' => 'Partner Clinic',
                'is_verified' => true,
                'verification_method' => 'on_behalf',
                'is_active' => true,
                'verified_at' => now(),
                'metadata' => [
                    'purpose' => 'Example partner organization sender',
                    'created_by_seeder' => true,
                    'requires_spf' => false, // Using on_behalf method
                ],
            ]
        );

        $this->command->info('Created verified senders');

        // Create sender mappings

        // Map IVR documents to IVR sender
        SenderMapping::firstOrCreate(
            [
                'document_type' => 'ivr',
                'sender_id' => $ivrSender->id,
            ],
            [
                'priority' => 100,
                'is_active' => true,
                'conditions' => null,
            ]
        );

        // Map order documents to orders sender
        SenderMapping::firstOrCreate(
            [
                'document_type' => 'order',
                'sender_id' => $ordersSender->id,
            ],
            [
                'priority' => 100,
                'is_active' => true,
                'conditions' => null,
            ]
        );

        // Map general notifications to support sender
        SenderMapping::firstOrCreate(
            [
                'document_type' => 'notification',
                'sender_id' => $supportSender->id,
            ],
            [
                'priority' => 100,
                'is_active' => true,
                'conditions' => null,
            ]
        );

        // Map partner clinic organization to their sender
        SenderMapping::firstOrCreate(
            [
                'organization' => 'Partner Clinic',
                'sender_id' => $partnerSender->id,
            ],
            [
                'priority' => 200, // Higher priority than document type mappings
                'is_active' => true,
                'conditions' => null,
            ]
        );

        // Example manufacturer-specific mappings
        $manufacturers = ['ACZ', 'Integra', 'Kerecis', 'MiMedx', 'Organogenesis'];
        
        foreach ($manufacturers as $manufacturer) {
            // Map IVR documents for this manufacturer to IVR sender
            SenderMapping::firstOrCreate(
                [
                    'manufacturer_id' => $manufacturer,
                    'document_type' => 'ivr',
                    'sender_id' => $ivrSender->id,
                ],
                [
                    'priority' => 150, // Higher than general document type mapping
                    'is_active' => true,
                    'conditions' => [
                        'urgent' => false, // Example condition
                    ],
                ]
            );

            // Map order documents for this manufacturer to orders sender
            SenderMapping::firstOrCreate(
                [
                    'manufacturer_id' => $manufacturer,
                    'document_type' => 'order',
                    'sender_id' => $ordersSender->id,
                ],
                [
                    'priority' => 150,
                    'is_active' => true,
                    'conditions' => null,
                ]
            );
        }

        $this->command->info('Created sender mappings');
        $this->command->info('✅ Verified senders and mappings seeded successfully');
        
        // Output summary
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Total Verified Senders', VerifiedSender::count()],
                ['Active Senders', VerifiedSender::active()->count()],
                ['Azure Domain Senders', VerifiedSender::byVerificationMethod('azure_domain')->count()],
                ['On Behalf Senders', VerifiedSender::byVerificationMethod('on_behalf')->count()],
                ['Total Mappings', SenderMapping::count()],
                ['Active Mappings', SenderMapping::active()->count()],
            ]
        );
    }
}
