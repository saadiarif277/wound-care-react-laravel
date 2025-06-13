<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Users\Organization\Organization;
use App\Models\Fhir\Facility;
use App\Models\Users\Provider\ProviderProfile;
use App\Models\Users\Provider\ProviderInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProviderInvitationController extends Controller
{
    public function show($token)
    {
        $invitation = ProviderInvitation::where('token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => 'expired']);
            throw ValidationException::withMessages([
                'token' => 'This invitation has expired.'
            ]);
        }

        $states = collect([
            ['code' => 'AL', 'name' => 'Alabama'],
            ['code' => 'AK', 'name' => 'Alaska'],
            ['code' => 'AZ', 'name' => 'Arizona'],
            ['code' => 'AR', 'name' => 'Arkansas'],
            ['code' => 'CA', 'name' => 'California'],
            ['code' => 'CO', 'name' => 'Colorado'],
            ['code' => 'CT', 'name' => 'Connecticut'],
            ['code' => 'DE', 'name' => 'Delaware'],
            ['code' => 'FL', 'name' => 'Florida'],
            ['code' => 'GA', 'name' => 'Georgia'],
            ['code' => 'HI', 'name' => 'Hawaii'],
            ['code' => 'ID', 'name' => 'Idaho'],
            ['code' => 'IL', 'name' => 'Illinois'],
            ['code' => 'IN', 'name' => 'Indiana'],
            ['code' => 'IA', 'name' => 'Iowa'],
            ['code' => 'KS', 'name' => 'Kansas'],
            ['code' => 'KY', 'name' => 'Kentucky'],
            ['code' => 'LA', 'name' => 'Louisiana'],
            ['code' => 'ME', 'name' => 'Maine'],
            ['code' => 'MD', 'name' => 'Maryland'],
            ['code' => 'MA', 'name' => 'Massachusetts'],
            ['code' => 'MI', 'name' => 'Michigan'],
            ['code' => 'MN', 'name' => 'Minnesota'],
            ['code' => 'MS', 'name' => 'Mississippi'],
            ['code' => 'MO', 'name' => 'Missouri'],
            ['code' => 'MT', 'name' => 'Montana'],
            ['code' => 'NE', 'name' => 'Nebraska'],
            ['code' => 'NV', 'name' => 'Nevada'],
            ['code' => 'NH', 'name' => 'New Hampshire'],
            ['code' => 'NJ', 'name' => 'New Jersey'],
            ['code' => 'NM', 'name' => 'New Mexico'],
            ['code' => 'NY', 'name' => 'New York'],
            ['code' => 'NC', 'name' => 'North Carolina'],
            ['code' => 'ND', 'name' => 'North Dakota'],
            ['code' => 'OH', 'name' => 'Ohio'],
            ['code' => 'OK', 'name' => 'Oklahoma'],
            ['code' => 'OR', 'name' => 'Oregon'],
            ['code' => 'PA', 'name' => 'Pennsylvania'],
            ['code' => 'RI', 'name' => 'Rhode Island'],
            ['code' => 'SC', 'name' => 'South Carolina'],
            ['code' => 'SD', 'name' => 'South Dakota'],
            ['code' => 'TN', 'name' => 'Tennessee'],
            ['code' => 'TX', 'name' => 'Texas'],
            ['code' => 'UT', 'name' => 'Utah'],
            ['code' => 'VT', 'name' => 'Vermont'],
            ['code' => 'VA', 'name' => 'Virginia'],
            ['code' => 'WA', 'name' => 'Washington'],
            ['code' => 'WV', 'name' => 'West Virginia'],
            ['code' => 'WI', 'name' => 'Wisconsin'],
            ['code' => 'WY', 'name' => 'Wyoming'],
        ]);

        return Inertia::render('Auth/ProviderInvitation', [
            'invitation' => [
                'id' => $invitation->id,
                'organization_name' => $invitation->organization_name ?? 'MSC Wound Portal',
                'organization_type' => $invitation->organization_type ?? 'Healthcare Provider',
                'invited_email' => $invitation->email,
                'invited_role' => $invitation->role,
                'expires_at' => $invitation->expires_at->toISOString(),
                'status' => $invitation->status,
                'metadata' => [
                    'organization_id' => $invitation->organization_id,
                    'invited_by' => $invitation->invited_by,
                    'invited_by_name' => $invitation->invitedBy->name ?? 'MSC Team',
                ]
            ],
            'token' => $token,
            'states' => $states
        ]);
    }

    public function accept(Request $request, $token)
    {
        $invitation = ProviderInvitation::where('token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation has expired.'
            ]);
        }

        // Comprehensive validation for all the manufacturer form fields
        $validated = $request->validate([
            // Personal Information
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',

                        // Professional Credentials
            'individual_npi' => 'nullable|digits:10',
            'specialty' => 'nullable|string|max:255',
            'license_number' => 'nullable|string|max:255',
            'license_state' => 'nullable|string|max:2',
            'ptan' => 'nullable|string|max:255',

            // Organization Information
            'organization_name' => 'required|string|max:255',
            'organization_tax_id' => 'nullable|string|max:255',
            'organization_type' => 'required|string|max:255',

            // Facility Information
            'facility_name' => 'required|string|max:255',
            'facility_type' => 'required|string|max:255',
            'group_npi' => 'nullable|digits:10',
            'facility_tax_id' => 'nullable|string|max:255',
            'facility_ptan' => 'nullable|string|max:255',

            // Ship-To Address (Facility Address)
            'facility_address' => 'required|string|max:255',
            'facility_city' => 'required|string|max:255',
            'facility_state' => 'required|string|max:2',
            'facility_zip' => 'required|string|max:10',
            'facility_phone' => 'nullable|string|max:255',
            'facility_email' => 'nullable|email|max:255',

            // Bill-To Address (Organization Address)
            'billing_address' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:255',
            'billing_state' => 'nullable|string|max:2',
            'billing_zip' => 'nullable|string|max:10',

            // Accounts Payable Contact
            'ap_contact_name' => 'nullable|string|max:255',
            'ap_contact_phone' => 'nullable|string|max:255',
            'ap_contact_email' => 'nullable|email|max:255',

            // Business Operations
            'business_hours' => 'nullable|string',
            'default_place_of_service' => 'required|in:11,12,31,32',

            // Practice Type
            'practice_type' => 'required|in:solo_practitioner,group_practice,hospital_system,existing_organization',

            // Terms
            'accept_terms' => 'required|accepted',
        ]);

        DB::beginTransaction();

        try {
            // 1. Create the User Account
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'],
                'role' => $invitation->role,
                'is_verified' => false, // Will be verified after credential review
            ]);

            // 2. Create or Find Organization
            $organization = null;
            if ($validated['practice_type'] === 'existing_organization' && $invitation->organization_id) {
                $organization = Organization::find($invitation->organization_id);
            } else {
                // Create new organization
                $organization = Organization::create([
                    'name' => $validated['organization_name'],
                    'type' => $validated['organization_type'],
                    'tax_id' => $validated['organization_tax_id'],
                    'status' => 'pending', // Will be activated after verification
                    'email' => $validated['facility_email'] ?? $validated['email'],
                    'phone' => $validated['facility_phone'] ?? $validated['phone'],
                    // Use billing address for organization if provided, otherwise facility address
                    'address' => $validated['billing_address'] ?? $validated['facility_address'],
                    'city' => $validated['billing_city'] ?? $validated['facility_city'],
                    'region' => $validated['billing_state'] ?? $validated['facility_state'],
                    'postal_code' => $validated['billing_zip'] ?? $validated['facility_zip'],
                    'country' => 'US',
                ]);
            }

            // 3. Create Facility (Ship-To Location)
            $facility = Facility::create([
                'organization_id' => $organization->id,
                'name' => $validated['facility_name'],
                'facility_type' => $validated['facility_type'],
                'address' => $validated['facility_address'],
                'city' => $validated['facility_city'],
                'state' => $validated['facility_state'],
                'zip_code' => $validated['facility_zip'],
                'phone' => $validated['facility_phone'],
                'email' => $validated['facility_email'],
                'group_npi' => $validated['group_npi'],
                'tax_id' => $validated['facility_tax_id'] ?? $validated['organization_tax_id'],
                'ptan' => $validated['facility_ptan'] ?? $validated['ptan'],
                'default_place_of_service' => $validated['default_place_of_service'],
                'business_hours' => json_encode([
                    'schedule' => $validated['business_hours']
                ]),
                'active' => true,
                'status' => 'pending', // Will be activated after verification
            ]);

            // 4. Create Provider Profile
            $providerProfile = ProviderProfile::create([
                'provider_id' => $user->id,
                'npi' => $validated['individual_npi'],
                'tax_id' => $validated['organization_tax_id'], // Provider tax ID usually same as organization
                'ptan' => $validated['ptan'],
                'specialty' => $validated['specialty'],
                'verification_status' => 'pending',
                'profile_completion_percentage' => $this->calculateProfileCompletion($validated),
                'specializations' => json_encode([$validated['specialty']]),
                'languages_spoken' => json_encode(['English']), // Default, can be updated later
            ]);

            // 5. Link User to Organization and Facility
            $user->update([
                'current_organization_id' => $organization->id,
            ]);

            // Create facility-user relationship
            DB::table('facility_user')->insert([
                'facility_id' => $facility->id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 6. Store Accounts Payable Contact Info (can be stored in organization metadata)
            if ($validated['ap_contact_name'] || $validated['ap_contact_phone'] || $validated['ap_contact_email']) {
                $organization->update([
                    'metadata' => json_encode([
                        'accounts_payable' => [
                            'contact_name' => $validated['ap_contact_name'],
                            'contact_phone' => $validated['ap_contact_phone'],
                            'contact_email' => $validated['ap_contact_email'],
                        ],
                        'billing_address' => [
                            'address' => $validated['billing_address'],
                            'city' => $validated['billing_city'],
                            'state' => $validated['billing_state'],
                            'zip' => $validated['billing_zip'],
                        ]
                    ])
                ]);
            }

            // 7. Mark invitation as accepted
            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'user_id' => $user->id,
            ]);

            DB::commit();

            // TODO: Send welcome email with login instructions
            // TODO: Notify admin team for credential verification
            // TODO: Create workflow for verifying NPI, license, etc.

            return response()->json([
                'message' => 'Registration completed successfully',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ],
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ],
                'facility' => [
                    'id' => $facility->id,
                    'name' => $facility->name,
                ],
                'next_steps' => [
                    'credential_verification_required' => true,
                    'estimated_verification_time' => '1-2 business days',
                    'login_available_after_verification' => true,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Provider registration failed', [
                'error' => $e->getMessage(),
                'invitation_id' => $invitation->id,
                'user_data' => $validated
            ]);

            throw ValidationException::withMessages([
                'registration' => 'Registration failed. Please try again or contact support.'
            ]);
        }
    }

    private function calculateProfileCompletion(array $data): int
    {
        $requiredFields = [
            'first_name', 'last_name', 'email', 'phone',
            'individual_npi', 'specialty', 'license_number', 'license_state',
            'organization_name', 'facility_name', 'facility_address'
        ];

        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($data[$field])) {
                $completedFields++;
            }
        }

        return (int) round(($completedFields / count($requiredFields)) * 100);
    }
}
