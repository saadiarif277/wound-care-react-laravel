<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Mail;
// AccessRequestController removed - feature deprecated

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImagesController;
use App\Http\Controllers\OrganizationsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Admin\UsersController as AdminUsersController;
use App\Http\Controllers\Admin\PatientIVRController;
use App\Http\Controllers\Provider\DashboardController as ProviderDashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\EligibilityController;
use App\Http\Controllers\MACValidationController;
use App\Http\Controllers\Commission\CommissionController;
use App\Http\Controllers\Commission\CommissionRuleController;
use App\Http\Controllers\Commission\CommissionRecordController;
use App\Http\Controllers\Commission\CommissionPayoutController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RBACController;
// AccessControlController removed - feature deprecated
use App\Http\Controllers\Api\MedicareMacValidationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PreAuthorizationController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\EngineController;
use App\Http\Controllers\SystemAdminController;
use App\Http\Controllers\RoleManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\DocusealWebhookController;
use App\Http\Controllers\FhirController;
use App\Http\Controllers\Api\AuthTokenController;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use App\Services\DocusealService;
use App\Services\FhirService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\OrderReviewPageController;

Route::get('/test-fhir-docuseal/{episodeId}', function($episodeId) {
    $service = app(DocusealService::class);

    // Test the FHIR data fetch
    $episode = DB::table('patient_manufacturer_ivr_episodes')->find($episodeId);

    if ($episode->azure_order_checklist_fhir_id) {
        $fhirService = app(FhirService::class);
        // Use search method to find the DocumentReference by ID
        $bundle = $fhirService->search('DocumentReference', ['_id' => $episode->azure_order_checklist_fhir_id]);

        // Extract the first entry from the bundle
        if (!isset($bundle['entry'][0]['resource'])) {
            return 'DocumentReference not found';
        }

        $doc = $bundle['entry'][0]['resource'];
        $checklistData = json_decode(base64_decode($doc['content'][0]['attachment']['data']), true);

        dd([
            'episode' => $episode,
            'fhir_data' => $checklistData,
            'would_map_to' => $service->mapFHIRToDocuseal($checklistData)
        ]);
    }

    return 'No FHIR data found';
});

// Removed debug CSRF routes - security vulnerability fixed

// AI Support Escalation Route
Route::get('/auth/token', AuthTokenController::class)->middleware('auth')->name('auth.token');

// CSRF Token Refresh Route
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.refresh');

Route::post('/api/support/escalate', function (Request $request) {
    // Validate the request
    $validated = $request->validate([
        'message' => 'required|string|max:1000',
        'metadata' => 'sometimes|array',
        'metadata.screen' => 'sometimes|string',
        'metadata.triggered_by' => 'sometimes|string',
        'metadata.timestamp' => 'sometimes|string',
    ]);

        // Log the escalation request
    Log::info('AI Support Escalation', [
        'user_id' => Auth::id(),
        'message' => $validated['message'],
        'metadata' => $validated['metadata'] ?? [],
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);

    // Here you would integrate with your actual support system
    // For now, we'll just return a success response

    return response()->json([
        'success' => true,
        'message' => 'Your message has been sent to our support team.',
        'ticket_id' => 'AI-' . time() . '-' . (Auth::id() ?? 'guest'),
        'estimated_response_time' => '2-4 hours during business hours',
    ]);
})->name('api.support.escalate')->middleware('auth');

// Auth

Route::get('login', [LoginController::class, 'create'])
    ->name('login')
    ->middleware('guest');

Route::post('login', [LoginController::class, 'store'])
    ->name('login.store')
    ->middleware('guest');

Route::delete('logout', [LoginController::class, 'destroy'])
    ->name('logout')
    ->middleware('auth');

// Access Request routes removed - feature deprecated

// Provider Invitation Routes
Route::get('auth/provider-invitation/{token}', function ($token) {
    // Get invitation data and render the invitation page
    $invitation = App\Models\Users\Provider\ProviderInvitation::where('invitation_token', $token)
        ->where('status', 'sent')
        ->where('expires_at', '>', now())
        ->first();

    if (!$invitation) {
        abort(404, 'Invitation not found or expired');
    }

    // Load organization and its facilities
    $invitation->load('organization.facilities');

    return Inertia::render('Auth/ProviderInvitation', [
        'invitation' => [
            'id' => $invitation->id,
            'organization_name' => $invitation->organization->name,
            'organization_type' => $invitation->organization->type ?? 'Healthcare Organization',
            'invited_email' => $invitation->email,
            'invited_role' => 'Provider', // This can be dynamic from invitation record later
            'expires_at' => $invitation->expires_at ?? now()->addDays(7)->toIso8601String(),
            'status' => $invitation->status,
            'metadata' => [
                'organization_id' => $invitation->organization_id,
                'invited_by' => $invitation->invited_by_user_id,
                'invited_by_name' => $invitation->invitedBy->first_name . ' ' . $invitation->invitedBy->last_name,
            ]
        ],
        'token' => $token,
        'facilities' => $invitation->organization->facilities->map(function ($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'full_address' => $facility->full_address,
            ];
        }),
        'states' => [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
            'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
            'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
            'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
            'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
            'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
            'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
            'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
            'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
            'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming'
        ]
    ]);
})->name('auth.provider-invitation.show')->middleware('guest');

Route::post('auth/provider-invitation/{token}/accept', function ($token, Request $request) {
    $onboardingService = app(\App\Services\OnboardingService::class);

    $result = $onboardingService->acceptProviderInvitation($token, $request->all());

    if ($result['success']) {
        return redirect()->route('login')->with('success', 'Account created successfully. Please log in.');
    } else {
        return back()->withErrors(['error' => $result['message']]);
    }
})->name('auth.provider-invitation.accept')->middleware('guest');

// Dashboard

Route::get('/', [DashboardController::class, 'index'])
    ->name('dashboard')
    ->middleware(['auth', 'role.redirect']);

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard.alias')
    ->middleware(['auth', 'role.redirect']);

// ================================================================
// CONSOLIDATED ORDER MANAGEMENT
// ================================================================

// Consolidated Order Center - Request Reviews + Order Management
Route::middleware(['permission:manage-orders'])->group(function () {
    Route::get('/orders/center', [OrderController::class, 'center'])->name('orders.center');
    Route::get('/orders/{id}/tracking', [OrderController::class, 'tracking'])->name('orders.tracking');
});

// Order Review Page
Route::middleware(['auth'])->group(function () {
    Route::get('/orders/{orderId}/review', [OrderReviewPageController::class, 'show'])->name('orders.review');
});

// Admin Order Center Routes (Episode-based workflow)
Route::middleware(['permission:manage-orders'])->prefix('admin')->group(function () {
    // Main episode management routes
    Route::get('/orders', [App\Http\Controllers\Admin\OrderCenterController::class, 'index'])->name('admin.orders.index');
    Route::get('/episodes/{episode}', [App\Http\Controllers\Admin\OrderCenterController::class, 'showEpisode'])->name('admin.episodes.show');

    // Legacy order routes (redirect to episodes if order has episode_id)
    Route::get('/orders/{id}', [App\Http\Controllers\Admin\OrderCenterController::class, 'show'])->name('admin.orders.show');

    // Episode-level actions (provider-centered workflow)
    Route::post('/episodes/{episode}/review', [App\Http\Controllers\Admin\OrderCenterController::class, 'reviewEpisode'])->name('admin.episodes.review');
    Route::post('/episodes/{episode}/send-to-manufacturer', [App\Http\Controllers\Admin\OrderCenterController::class, 'sendEpisodeToManufacturer'])->name('admin.episodes.send-to-manufacturer');
    Route::post('/episodes/{episode}/update-tracking', [App\Http\Controllers\Admin\OrderCenterController::class, 'updateEpisodeTracking'])->name('admin.episodes.update-tracking');
    Route::post('/episodes/{episode}/mark-completed', [App\Http\Controllers\Admin\OrderCenterController::class, 'markEpisodeCompleted'])->name('admin.episodes.mark-completed');

    // Episode document management
    Route::post('/episodes/{episode}/documents', [App\Http\Controllers\Admin\OrderCenterController::class, 'uploadEpisodeDocuments'])->name('admin.episodes.documents.upload');
    Route::get('/episodes/{episode}/documents/{document}', [App\Http\Controllers\Admin\OrderCenterController::class, 'downloadEpisodeDocument'])->name('admin.episodes.documents.download');
    Route::delete('/episodes/{episode}/documents/{document}', [App\Http\Controllers\Admin\OrderCenterController::class, 'deleteEpisodeDocument'])->name('admin.episodes.documents.delete');

    // Enhanced Dashboard Routes - Now handled by OrderCenterController
    // Redirect old enhanced dashboard route to consolidated order center
    Route::get('/dashboard/enhanced', function () {
        return redirect()->route('admin.orders.index');
    })->name('admin.dashboard.enhanced');

    // Patient IVR Management Routes - Requires IVR management permissions
    Route::get('/ivr/management', [PatientIVRController::class, 'management'])
        ->name('admin.ivr.management')
        ->middleware('permission:view-ivr-management');
    Route::post('/ivr/bulk-remind', [PatientIVRController::class, 'bulkRemind'])
        ->name('admin.ivr.bulk-remind')
        ->middleware('permission:manage-ivr');
    Route::post('/ivr/export', [PatientIVRController::class, 'export'])
        ->name('admin.ivr.export')
        ->middleware('permission:export-ivr-data');
    Route::get('/ivr/settings', [PatientIVRController::class, 'settings'])
        ->name('admin.ivr.settings')
        ->middleware('permission:manage-ivr');
    Route::get('/ivr/{ivr}', [PatientIVRController::class, 'show'])
        ->name('admin.ivr.show')
        ->middleware('permission:view-ivr-management');
    Route::post('/ivr/{ivr}/remind', [PatientIVRController::class, 'remind'])
        ->name('admin.ivr.remind')
        ->middleware('permission:manage-ivr');
    Route::get('/ivr/{ivr}/contact', [PatientIVRController::class, 'contact'])
        ->name('admin.ivr.contact')
        ->middleware('permission:manage-ivr');

    // Legacy individual order actions removed - Now using episode-based workflow
    // Individual orders with ivr_episode_id automatically redirect to episode view

    // Patient IVR Status
    Route::get('/patients/ivr-status', [PatientIVRController::class, 'index'])->name('admin.patients.ivr-status');
    Route::post('/patients/{patientFhirId}/ivr/{manufacturerId}', [PatientIVRController::class, 'updateStatus'])->name('admin.patients.ivr.update');
});

// Legacy order routes (redirect to consolidated order center)
Route::get('/orders', function () {
    return redirect()->route('orders.center');
})->name('orders')->middleware('auth');

Route::get('/orders/management', function () {
    return redirect()->route('orders.center');
})->name('orders.management')->middleware('auth');

Route::get('/orders/approvals', function () {
    return redirect()->route('orders.center');
})->name('orders.approval')->middleware('auth');

Route::get('/orders/manage', function () {
    return redirect()->route('orders.center');
})->name('orders.manage')->middleware('auth');

Route::get('/orders/create', function () {
    return redirect()->route('orders.center');
})->name('orders.create')->middleware('auth');

// ================================================================
// PROVIDER DASHBOARD ROUTES
// ================================================================

// Provider Dashboard Routes - for authenticated providers
Route::middleware(['auth', 'permission:view-orders'])->prefix('provider')->group(function () {
    Route::get('/dashboard', [ProviderDashboardController::class, 'index'])
        ->name('provider.dashboard')
        ->middleware('permission:view-dashboard');
    Route::get('/episodes', [ProviderDashboardController::class, 'episodes'])
        ->name('provider.episodes')
        ->middleware('permission:view-orders');
    Route::get('/episodes/{episode}', [ProviderDashboardController::class, 'showEpisode'])
        ->name('provider.episodes.show')
        ->middleware('permission:view-orders');
});

// Legacy routes (redirect to consolidated page)
Route::get('/organizations', function () {
    return redirect()->route('admin.organizations.index');
})->name('organizations')->middleware('auth');

Route::get('/admin/customer-management', function () {
    return redirect()->route('admin.organizations.index');
})->name('admin.customer-management')->middleware('auth');

Route::get('/admin/onboarding', function () {
    return redirect()->route('admin.organizations.index');
})->name('admin.onboarding')->middleware('auth');

Route::get('/customers', function () {
    return redirect()->route('admin.organizations.index');
})->name('customers.index')->middleware('auth');

// ================================================================
// CONSOLIDATED SALES MANAGEMENT
// ================================================================

// Sales Rep Dashboard - New Enhanced Commission Dashboard
Route::middleware(['auth', 'role:msc-rep,msc-subrep'])->group(function () {
    Route::get('/sales-rep/dashboard', function () {
        return Inertia::render('SalesRep/Dashboard');
    })->name('sales-rep.dashboard');
});

// Consolidated Sales Management - Commission Tracking, Payouts, Sales Rep Management, Sub-Rep Approvals
Route::middleware(['financial.access'])->group(function () {
    Route::get('/commission/management', function () {
        return Inertia::render('Commission/Index');
    })->name('commission.management');
});

// Legacy commission routes (redirect to consolidated page)
Route::get('/commission', function () {
    return redirect()->route('commission.management');
})->name('commission.index')->middleware('auth');

Route::get('/commission/rules', function () {
    return redirect()->route('commission.management');
})->name('commission-rules.index')->middleware('auth');

Route::get('/commission/records', function () {
    return redirect()->route('commission.management');
})->name('commission-records.index')->middleware('auth');

Route::get('/commission/payouts', function () {
    return redirect()->route('commission.management');
})->name('commission-payouts.index')->middleware('auth');

Route::get('/subrep-approvals', function () {
    return redirect()->route('commission.management');
})->name('subrep-approvals.index')->middleware('auth');

// ================================================================
// STANDARD ROUTES (UNCHANGED)
// ================================================================

// Products - with proper permission middleware

Route::middleware(['permission:view-products', 'financial.access'])->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    // Product API endpoints accessible to all roles with view-products permission
    Route::get('api/products/search', [ProductController::class, 'search'])->name('api.products.search');
    Route::get('api/products/recommendations', [ProductController::class, 'recommendations'])->name('api.products.recommendations');
});

// Product management - restricted to admin roles only
Route::middleware(['permission:manage-products'])->group(function () {
    // Specific routes first (BEFORE parameterized routes)
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::get('products/manage', [ProductController::class, 'manage'])->name('products.manage');

    // CMS pricing sync routes
    Route::post('api/products/cms/sync', [ProductController::class, 'syncCmsPricing'])->name('api.products.cms.sync');
    Route::get('api/products/cms/status', [ProductController::class, 'getCmsSyncStatus'])->name('api.products.cms.status');

    // Then parameterized routes
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::put('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');

    // MUE validation route (for order validation)
    Route::post('products/{product}/validate-quantity', [ProductController::class, 'validateQuantity'])->name('products.validate-quantity');
    
    // Pricing history route
    Route::get('products/{product}/pricing-history', [ProductController::class, 'getPricingHistory'])->name('products.pricing-history');
});

// Product view routes (AFTER specific management routes)
Route::middleware(['permission:view-products'])->group(function () {
    // Specific routes must come before parameterized routes
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('api/products/{product}', [ProductController::class, 'apiShow'])->name('api.products.show');
});



// Reports

Route::get('reports', [ReportsController::class, 'index'])
    ->name('reports')
    ->middleware('auth');

// Images

Route::get('/img/{path}', [ImagesController::class, 'show'])
    ->where('path', '.*')
    ->name('image');

Route::middleware(['web', 'auth'])->group(function () {
    // Eligibility Verification Routes
    Route::get('eligibility', [\App\Http\Controllers\EligibilityPageController::class, 'index'])
    ->middleware(['auth', 'permission:view-eligibility'])
    ->name('eligibility.index');

    // MAC Validation Routes - accessible to office managers and admins
    Route::middleware(['permission:view-mac-validation'])->prefix('mac-validation')->group(function () {
        Route::get('/', [MedicareMacValidationController::class, 'index'])->name('mac-validation.index');
        Route::post('/validate', [MedicareMacValidationController::class, 'validateMAC'])->name('mac-validation.validate');
    });

    // eClinicalWorks Integration Routes
    // Note: ECW Integration route removed - EcwController has been deprecated

    // Product Request Routes
    Route::prefix('product-requests')->group(function () {
        Route::get('/', [ProductRequestController::class, 'index'])
            ->middleware('permission:view-product-requests')
            ->name('product-requests.index');
        Route::get('/create', [ProductRequestController::class, 'create'])
            ->middleware('permission:create-product-requests')
            ->name('product-requests.create');
        Route::post('/', [ProductRequestController::class, 'store'])
            ->middleware('permission:create-product-requests')
            ->name('product-requests.store');
        Route::get('/{productRequest}', [ProductRequestController::class, 'show'])
            ->middleware('permission:view-product-requests')
            ->name('product-requests.show');
        Route::post('/{productRequest}/update-step', [ProductRequestController::class, 'updateStep'])
            ->middleware('permission:create-product-requests')
            ->name('product-requests.update-step');
        Route::post('/mac-validation', [ProductRequestController::class, 'runMacValidation'])
            ->middleware('permission:manage-mac-validation')
            ->name('product-requests.mac-validation');
        Route::post('/{productRequest}/eligibility-check', [ProductRequestController::class, 'runEligibilityCheck'])
            ->middleware('permission:manage-eligibility')
            ->name('product-requests.eligibility-check');
        Route::post('/{productRequest}/submit-prior-auth', [ProductRequestController::class, 'submitPriorAuth'])
            ->middleware('permission:manage-pre-authorization')
            ->name('product-requests.submit-prior-auth');
        Route::post('/{productRequest}/check-prior-auth-status', [ProductRequestController::class, 'checkPriorAuthStatus'])
            ->middleware('permission:manage-pre-authorization')
            ->name('product-requests.check-prior-auth-status');
        Route::post('/{productRequest}/submit', [ProductRequestController::class, 'submit'])
            ->middleware('permission:create-product-requests')
            ->name('product-requests.submit');
    });

    // Quick Request Routes
    Route::prefix('quick-requests')->middleware('auth')->group(function () {
        Route::get('/create', [\App\Http\Controllers\QuickRequestController::class, 'create'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.create');
        Route::get('/create-new', [\App\Http\Controllers\QuickRequestController::class, 'create'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.create-new');

        // Order Review and Submission Routes
        Route::get('/review', [\App\Http\Controllers\QuickRequestController::class, 'reviewOrder'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.review');
        Route::post('/submit-order', [\App\Http\Controllers\QuickRequestController::class, 'submitOrder'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.submit-order');

        // Main store route
        Route::post('/', [\App\Http\Controllers\QuickRequestController::class, 'store'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.store');

        // Docuseal IVR Integration - moved to DocusealController
        Route::post('/prepare-docuseal-ivr', [\App\Http\Controllers\QuickRequest\DocusealController::class, 'prepareDocusealIVR'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.prepare-docuseal-ivr');

        // Docuseal routes - moved to DocusealController
        Route::post('/docuseal/generate-form-token', [\App\Http\Controllers\QuickRequest\DocusealController::class, 'generateFormToken'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.docuseal.generate-form-token');

        Route::post('/docuseal/generate-submission-slug', [\App\Http\Controllers\QuickRequest\DocusealController::class, 'generateSubmissionSlug'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.docuseal.generate-submission-slug');

        // Debug endpoint for Docuseal integration troubleshooting
        Route::get('/docuseal/debug', [\App\Http\Controllers\QuickRequest\DocusealController::class, 'debugDocusealIntegration'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.docuseal.debug');

        Route::get('/docuseal/test-count', [\App\Http\Controllers\QuickRequest\DocusealController::class, 'testTemplateCount'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.docuseal.test-count');

        // Docuseal Builder Token Generation (web auth)
        Route::post('/docuseal/generate-builder-token', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'generateBuilderToken'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.docuseal.generate-builder-token');

        // Episode-centric workflow with document processing
        Route::post('/create-episode-with-documents', [\App\Http\Controllers\Api\V1\QuickRequestController::class, 'createEpisodeWithDocuments'])
            ->middleware('permission:create-product-requests')
            ->name('quick-requests.create-episode-with-documents');

        // Remove routes for methods that don't exist anymore
        // Order Summary Route - temporarily commented out until we implement it
        // Route::get('/order-summary/{order_id}', [\App\Http\Controllers\QuickRequestController::class, 'showOrderSummary'])
        //     ->middleware('permission:create-product-requests')
        //     ->name('quick-requests.order-summary');

        // My Orders Page - temporarily commented out until we implement it
        // Route::get('/my-orders', [\App\Http\Controllers\QuickRequestController::class, 'myOrders'])
        //     ->middleware('permission:view-product-requests')
        //     ->name('quick-requests.my-orders');

        // Debug routes - temporarily commented out
        // Route::get('/debug-facilities', [\App\Http\Controllers\QuickRequestController::class, 'debugFacilities'])
        //     ->middleware('permission:create-product-requests')
        //     ->name('quick-requests.debug-facilities');
    });

    // Insurance Card Analysis API
    Route::prefix('api/insurance-card')->group(function () {
        Route::post('/analyze', [\App\Http\Controllers\Api\InsuranceCardController::class, 'analyze'])
            ->middleware('permission:create-product-requests')
            ->name('api.insurance-card.analyze');
        Route::get('/status', [\App\Http\Controllers\Api\InsuranceCardController::class, 'status'])
            ->name('api.insurance-card.status');
    });

    // Session storage for form data
    Route::prefix('api/session')->group(function () {
        Route::post('/store-form-data', function (Request $request) {
            $validated = $request->validate([
                'quick_request_form_data' => 'required|array',
                'validated_episode_data' => 'sometimes|array'
            ]);

            // Debug logging
            Log::info('Session storage - Storing form data', [
                'form_data_keys' => array_keys($validated['quick_request_form_data']),
                'form_data_count' => count($validated['quick_request_form_data']),
                'episode_data_keys' => isset($validated['validated_episode_data']) ? array_keys($validated['validated_episode_data']) : [],
                'episode_data_count' => isset($validated['validated_episode_data']) ? count($validated['validated_episode_data']) : 0,
                'session_id' => $request->session()->getId(),
                'user_id' => Auth::id(),
            ]);

            $request->session()->put('quick_request_form_data', $validated['quick_request_form_data']);
            if (isset($validated['validated_episode_data'])) {
                $request->session()->put('validated_episode_data', $validated['validated_episode_data']);
            }

            // Verify data was stored
            $storedFormData = $request->session()->get('quick_request_form_data', []);
            Log::info('Session storage - Data stored successfully', [
                'stored_form_data_keys' => array_keys($storedFormData),
                'stored_form_data_count' => count($storedFormData),
                'session_id' => $request->session()->getId(),
            ]);

            return response()->json(['success' => true]);
        })->middleware('auth')->name('api.session.store-form-data');
    });

    // Product Request API Routes
    Route::prefix('api/product-requests')->group(function () {
        Route::post('/search-patients', [ProductRequestController::class, 'searchPatients'])->name('api.product-requests.search-patients');
        Route::get('/{productRequest}/recommendations', [ProductRequestController::class, 'getRecommendations'])->name('api.product-requests.recommendations');
        Route::post('/{productRequest}/eligibility-check', [ProductRequestController::class, 'runEligibilityCheck'])->name('api.product-requests.eligibility-check');
    });

    // Product Request Review Routes (Admin)
    Route::middleware(['permission:manage-product-requests'])->prefix('product-requests')->group(function () {
        Route::get('/review', [\App\Http\Controllers\Admin\ProductRequestReviewController::class, 'index'])
            ->name('product-requests.review');
        Route::get('/review/{productRequest}', [\App\Http\Controllers\Admin\ProductRequestReviewController::class, 'show'])
            ->name('product-requests.review.show');
        Route::post('/review/{productRequest}/approve', [\App\Http\Controllers\Admin\ProductRequestReviewController::class, 'approve'])
            ->name('product-requests.review.approve');
        Route::post('/review/{productRequest}/reject', [\App\Http\Controllers\Admin\ProductRequestReviewController::class, 'reject'])
            ->name('product-requests.review.reject');
        Route::post('/review/{productRequest}/request-info', [\App\Http\Controllers\Admin\ProductRequestReviewController::class, 'requestInformation'])
            ->name('product-requests.review.request-info');
        Route::post('/review/bulk-action', [\App\Http\Controllers\Admin\ProductRequestReviewController::class, 'bulkAction'])
            ->name('product-requests.review.bulk-action');
    });

    // Product API Routes
    Route::prefix('api/products')->group(function () {
        Route::get('/search', [ProductController::class, 'getAll'])->name('api.products.search');
        Route::get('/{product}', [ProductController::class, 'apiShow'])->name('api.products.show');
    });

    // Office Manager specific routes - Add proper authorization
    Route::middleware(['permission:view-product-requests'])->group(function () {
        Route::get('/product-requests/facility', [ProductRequestController::class, 'facilityRequests'])
            ->name('product-requests.facility');
        Route::get('/product-requests/providers', [ProductRequestController::class, 'providerRequests'])
            ->name('product-requests.providers');
        Route::get('/product-requests/status', [ProductRequestController::class, 'status'])
            ->name('product-requests.status');
    });

    Route::middleware(['permission:view-providers'])->group(function () {
        Route::get('/providers', [ProviderController::class, 'index'])
            ->name('providers.index');
        Route::get('/providers/{provider}', [ProviderController::class, 'show'])
            ->name('providers.show');
    });

    // Provider Credential Management
    Route::middleware(['permission:manage-providers'])->group(function () {
        Route::get('/providers/credentials', [ProviderController::class, 'credentials'])
            ->name('providers.credentials');
        Route::post('/api/v1/provider/credentials', [ProviderController::class, 'storeCredential'])
            ->name('api.provider.credentials.store');
        Route::put('/api/v1/provider/credentials/{credential}', [ProviderController::class, 'updateCredential'])
            ->name('api.provider.credentials.update');
    });

    Route::middleware(['permission:view-pre-authorization'])->group(function () {
        Route::get('/pre-authorization', [PreAuthorizationController::class, 'index'])
            ->name('pre-authorization.index');
        Route::post('/pre-authorization/submit', [PreAuthorizationController::class, 'submit'])
            ->middleware('permission:manage-pre-authorization')
            ->name('pre-authorization.submit');
        Route::get('/pre-authorization/status', [PreAuthorizationController::class, 'status'])
            ->name('pre-authorization.status');
    });

    Route::middleware(['permission:view-analytics'])->group(function () {
        Route::get('/orders/analytics', [OrderController::class, 'analytics'])->name('orders.analytics');
    });

    Route::middleware(['permission:view-settings'])->group(function () {
        Route::get('/settings', function () {
            return Inertia::render('Settings/Index');
        })->name('settings.index');
    });

    // Role Management Routes
    Route::middleware(['auth', 'role:msc-admin'])->group(function () {
        Route::get('/roles', [RoleManagementController::class, 'index'])->name('web.roles.index');
        Route::post('/roles', [RoleManagementController::class, 'store'])->name('web.roles.store');
        Route::put('/roles/{role}', [RoleManagementController::class, 'update'])->name('web.roles.update');
        Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy'])->name('web.roles.destroy');
    });

    // RBAC Management Routes
    Route::middleware(['auth', 'permission:manage-rbac', 'role:msc-admin'])->group(function () {
        Route::get('/rbac', [RBACController::class, 'index'])->name('web.rbac.index');
        Route::get('/rbac/role/{role}/config', [RBACController::class, 'getRoleConfig'])->name('web.rbac.role-config');
        Route::post('/rbac/role/{role}/toggle-status', [RBACController::class, 'toggleRoleStatus'])->name('web.rbac.toggle-status');
        Route::get('/rbac/role/{role}/permissions', [RBACController::class, 'getRolePermissions'])->name('web.rbac.role-permissions');
        Route::put('/rbac/role/{role}/permissions', [RBACController::class, 'updateRolePermissions'])->name('web.rbac.update-permissions');
        Route::get('/rbac/security-audit', [RBACController::class, 'getSecurityAudit'])->name('web.rbac.security-audit');
    });

    // Access Control routes removed - feature deprecated

    Route::middleware(['permission:view-commission'])->group(function () {
        Route::get('/commission/overview', function () {
            return Inertia::render('Commission/Overview');
        })->name('commission.overview');
    });

    // System Admin routes - Add proper authorization
    Route::middleware(['permission:manage-system-config'])->group(function () {
        Route::prefix('system-admin')->group(function () {
            Route::get('/config', [SystemAdminController::class, 'config'])
                ->name('system-admin.config');
            Route::get('/integrations', [SystemAdminController::class, 'integrations'])
                ->name('system-admin.integrations');
            Route::get('/api', [SystemAdminController::class, 'api'])
                ->name('system-admin.api');
        });
    });

    Route::middleware(['permission:view-audit-logs'])->group(function () {
        Route::get('/system-admin/audit', [SystemAdminController::class, 'audit'])
            ->name('system-admin.audit');
    });

    // Admin User Management Routes (Consolidated)
    Route::middleware(['permission:manage-users'])->prefix('admin/users')->group(function () {
        Route::get('/', [AdminUsersController::class, 'index'])
            ->name('admin.users.index');
        Route::get('/create', [AdminUsersController::class, 'create'])
            ->name('admin.users.create');
        Route::post('/', [AdminUsersController::class, 'store'])
            ->name('admin.users.store');
        Route::get('/{user}/edit', [AdminUsersController::class, 'edit'])
            ->name('admin.users.edit');
        Route::put('/{user}', [AdminUsersController::class, 'update'])
            ->name('admin.users.update');
        Route::patch('/{user}/deactivate', [AdminUsersController::class, 'deactivate'])
            ->name('admin.users.deactivate');
        Route::patch('/{user}/activate', [AdminUsersController::class, 'activate'])
            ->name('admin.users.activate');
    });

    // User Invitations Management Routes (General for all roles)
    Route::middleware(['permission:manage-users'])->prefix('admin/invitations')->group(function () {
        Route::get('/', function () {
            $invitations = App\Models\Users\Provider\ProviderInvitation::with(['organization', 'invitedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return Inertia::render('Admin/Invitations/Index', [
                'invitations' => $invitations
            ]);
        })->name('admin.invitations.index');

        Route::post('/', function (Request $request) {
            $request->validate([
                'email' => 'required|email|unique:users,email|unique:provider_invitations,invited_email',
                'role' => 'required|in:provider,office-manager,msc-rep,msc-subrep,msc-admin',
                'organization_id' => 'nullable|uuid|exists:organizations,id',
                'message' => 'nullable|string|max:500'
            ]);

            // Create the invitation
            $invitation = App\Models\Users\Provider\ProviderInvitation::create([
                'id' => Str::uuid(),
                'invited_email' => $request->email,
                'invited_role' => $request->role,
                'organization_id' => $request->organization_id,
                'invited_by' => Auth::id(),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
                'metadata' => [
                    'custom_message' => $request->message,
                    'invited_by_name' => Auth::user()->first_name . ' ' . Auth::user()->last_name
                ]
            ]);

            // Send email notification
            Mail::to($invitation->email)->send(new \App\Mail\ProviderInvitationEmail($invitation));

            return back()->with('success', 'Invitation sent successfully');
        })->name('admin.invitations.store');

        Route::post('/{invitation}/resend', function (App\Models\Users\Provider\ProviderInvitation $invitation) {
            // Resend invitation logic
            $onboardingService = app(\App\Services\OnboardingService::class);
            // Implementation would go here
            return back()->with('success', 'Invitation resent successfully');
        })->name('admin.invitations.resend');

        Route::delete('/{invitation}', function (App\Models\Users\Provider\ProviderInvitation $invitation) {
            $invitation->update(['status' => 'cancelled']);
            return back()->with('success', 'Invitation cancelled successfully');
        })->name('admin.invitations.cancel');
    });

    // Engine routes - Add proper authorization
    Route::middleware(['permission:manage-clinical-rules'])->group(function () {
        Route::get('/engines/clinical-rules', [EngineController::class, 'clinicalRules'])
            ->name('engines.clinical-rules');
    });

    Route::middleware(['permission:manage-recommendation-rules'])->group(function () {
        Route::get('/engines/recommendation-rules', [EngineController::class, 'recommendationRules'])
            ->name('engines.recommendation-rules');
    });

    Route::middleware(['permission:manage-commission-engine'])->group(function () {
        Route::get('/engines/commission', [EngineController::class, 'commission'])
            ->name('engines.commission');
    });

   // Route::middleware(['permission:view-team'])->group(function () {
        // Route::get('/team', [TeamController::class, 'index'])
        //     ->name('team.index');
        // Route::get('/team/{member}', [TeamController::class, 'show'])
        //     ->name('team.show');
    });

    // Test route for role restrictions
    Route::get('/test-role-restrictions', function () {
        $user = Auth::user()->load('roles');
        $primaryRole = $user->getPrimaryRole();

        return response()->json([
            'user_email' => $user->email,
            'role_name' => $primaryRole?->slug,
            'role_display_name' => $primaryRole?->name,
            'financial_restrictions' => [
                'can_access_financials' => $user->hasAnyPermission(['view-financials', 'manage-financials']),
                'can_see_discounts' => $user->hasPermission('view-discounts'),
                'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
                'can_see_order_totals' => $user->hasPermission('view-order-totals'),
                'pricing_access_level' => $user->hasPermission('view-msc-pricing') && $user->hasPermission('view-discounts') ? 'full' : ($user->hasPermission('view-financials') ? 'limited' : 'national_asp_only'),
            ],
        ]);
    })->name('test.role-restrictions');

    // Test RBAC functionality
    Route::get('/test-rbac', function () {
        $roles = \App\Models\Role::with(['permissions', 'users'])->get();
        $permissions = \App\Models\Permission::all();

        return response()->json([
            'roles_count' => $roles->count(),
            'permissions_count' => $permissions->count(),
            'roles' => $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'is_active' => $role->is_active ?? true,
                    'users_count' => $role->users->count(),
                    'permissions_count' => $role->permissions->count(),
                    'permissions' => $role->permissions->pluck('slug')->toArray()
                ];
            }),
            'rbac_audit_logs' => \App\Models\RbacAuditLog::latest()->take(5)->get()
        ]);
    })->name('test.rbac')->middleware('auth');

    // Test route for Office Manager permissions
    Route::get('/test-office-manager-permissions', function () {
        $user = Auth::user();

        return response()->json([
            'user_email' => $user->email,
            'user_role' => $user->getPrimaryRole()?->slug,
            'permissions' => [
                'view-products' => $user->hasPermission('view-products'),
                'view-providers' => $user->hasPermission('view-providers'),
                'view-product-requests' => $user->hasPermission('view-product-requests'),
                'manage-mac-validation' => $user->hasPermission('manage-mac-validation'),
                'manage-pre-authorization' => $user->hasPermission('manage-pre-authorization'),
            ],
            'all_permissions' => $user->getAllPermissions()->pluck('slug')->toArray(),
        ]);
    })->name('test.office-manager-permissions');

    // Docuseal Document Management Routes
    Route::middleware(['permission:manage-orders'])->prefix('admin/docuseal')->group(function () {
        // Template management
        Route::get('/templates', function () {
            return Inertia::render('Admin/Docuseal/Templates');
        })->name('admin.docuseal.templates');

        // Analytics dashboard
        Route::get('/analytics', function () {
            return Inertia::render('Admin/Docuseal/Analytics');
        })->name('admin.docuseal.analytics');
    });

    // Docuseal API endpoints (within web middleware for session auth)
    // NOTE: DocusealTemplateController needs to be created
    /*
    Route::prefix('api/v1/docuseal/templates')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\DocusealTemplateController::class, 'index']);
        Route::post('/sync', [\App\Http\Controllers\Api\V1\DocusealTemplateController::class, 'sync']);
        Route::post('/extract-fields', [\App\Http\Controllers\Api\V1\DocusealTemplateController::class, 'extractFields']);
        Route::post('/upload-embedded', [\App\Http\Controllers\Api\V1\DocusealTemplateController::class, 'uploadEmbedded']);
        Route::get('/manufacturer/{manufacturer}/fields', [\App\Http\Controllers\Api\V1\DocusealTemplateController::class, 'getManufacturerFields']);
        Route::post('/{templateId}/update-mappings', [\App\Http\Controllers\Api\V1\DocusealTemplateController::class, 'updateMappings']);
        Route::post('/{templateId}/update-metadata', [\App\Http\Controllers\Api\V1\DocusealTemplateController::class, 'updateMetadata']);
        Route::post('/{templateId}/apply-bulk-patterns', [\App\Http\Controllers\Api\V1\DocusealTemplateController::class, 'applyBulkPatterns']);
        Route::post('/{templateId}/sync-fields', [\App\Http\Controllers\Api\V1\DocusealTemplateController::class, 'syncFields']);
    });
    */

    // Test route for Provider Dashboard functionality
    Route::get('/test-provider-permissions', function () {
        $user = Auth::user()->load('roles');
        $role = $user->getPrimaryRole();

        return response()->json([
            'user_email' => $user->email,
            'role_name' => $role?->slug,
            'role_display_name' => $role?->name,
            'permissions' => [
                'view-dashboard' => $user->hasPermission('view-dashboard'),
                'create-product-requests' => $user->hasPermission('create-product-requests'),
                'view-product-requests' => $user->hasPermission('view-product-requests'),
                'view-mac-validation' => $user->hasPermission('view-mac-validation'),
                'manage-mac-validation' => $user->hasPermission('manage-mac-validation'),
                'view-eligibility' => $user->hasPermission('view-eligibility'),
                'manage-eligibility' => $user->hasPermission('manage-eligibility'),
                'view-pre-authorization' => $user->hasPermission('view-pre-authorization'),
                'manage-pre-authorization' => $user->hasPermission('manage-pre-authorization'),
                'view-products' => $user->hasPermission('view-products'),
            ],
            'route_tests' => [
                'can_access_create_route' => route('product-requests.create'),
                'can_access_index_route' => route('product-requests.index'),
                'can_access_eligibility_route' => route('eligibility.index'),
                'can_access_mac_validation_route' => route('mac-validation.index'),
                'can_access_pre_auth_route' => route('pre-authorization.index'),
                'can_access_products_route' => route('products.index'),
            ]
        ]);
    })->name('test.provider-permissions')->middleware('auth');

    // Facility Management Routes
    Route::middleware(['role:msc-admin', 'permission:manage-facilities'])->prefix('admin')->group(function () {
        Route::get('/facilities', [FacilityController::class, 'index'])->name('admin.facilities.index');
        Route::get('/facilities/create', [FacilityController::class, 'create'])->name('admin.facilities.create');
        Route::post('/facilities', [FacilityController::class, 'store'])->name('admin.facilities.store');
        Route::get('/facilities/{facility}/edit', [FacilityController::class, 'edit'])->name('admin.facilities.edit');
        Route::put('/facilities/{facility}', [FacilityController::class, 'update'])->name('admin.facilities.update');
        Route::delete('/facilities/{facility}', [FacilityController::class, 'destroy'])->name('admin.facilities.destroy');
    });

    // Provider Management Routes
    Route::middleware(['permission:view-providers'])->prefix('admin')->group(function () {
        Route::get('/providers', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'index'])->name('admin.providers.index');
    });

    Route::middleware(['permission:manage-providers'])->prefix('admin')->group(function () {
        Route::get('/providers/create', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'create'])->name('admin.providers.create');
        Route::post('/providers', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'store'])->name('admin.providers.store');
        Route::get('/providers/{provider}/edit', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'edit'])->name('admin.providers.edit');
        Route::put('/providers/{provider}', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'update'])->name('admin.providers.update');
        Route::put('/providers/{provider}/products', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'updateProducts'])->name('admin.providers.products.update');
    });

    Route::middleware(['permission:view-providers'])->prefix('admin')->group(function () {
        Route::get('/providers/{provider}', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'show'])->name('admin.providers.show');
    });

    // Provider Management API Routes
    Route::middleware(['permission:manage-providers'])->prefix('admin/providers')->group(function () {
        Route::post('/{provider}/facilities', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'addFacility'])->name('admin.providers.facilities.add');
        Route::delete('/{provider}/facilities/{facility}', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'removeFacility'])->name('admin.providers.facilities.remove');
        Route::post('/{provider}/products', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'addProduct'])->name('admin.providers.products.add');
        Route::delete('/{provider}/products/{product}', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'removeProduct'])->name('admin.providers.products.remove');
        Route::put('/{provider}/products/{product}', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'updateProduct'])->name('admin.providers.products.update-single');
        Route::delete('/{provider}', [\App\Http\Controllers\Admin\ProviderManagementController::class, 'destroy'])->name('admin.providers.destroy');
    });

    Route::middleware(['permission:manage-payments'])->prefix('admin')->group(function () {
        // Payments Management Routes
        Route::get('/payments', [\App\Http\Controllers\Admin\PaymentsController::class, 'index'])->name('admin.payments.index');
        Route::post('/payments', [\App\Http\Controllers\Admin\PaymentsController::class, 'store'])->name('admin.payments.store');
        Route::get('/payments/history', [\App\Http\Controllers\Admin\PaymentsController::class, 'history'])->name('admin.payments.history');
    });

    // Menu Management Routes
    // Route::middleware(['permission:manage-menus'])->prefix('admin/menu')->group(function () {
    //     Route::get('/', [\App\Http\Controllers\Admin\MenuManagementController::class, 'index'])->name('admin.menu.index');
    //     Route::get('/create', [\App\Http\Controllers\Admin\MenuManagementController::class, 'create'])->name('admin.menu.create');
    //     Route::post('/', [\App\Http\Controllers\Admin\MenuManagementController::class, 'store'])->name('admin.menu.store');
    //     Route::get('/{menuItem}/edit', [\App\Http\Controllers\Admin\MenuManagementController::class, 'edit'])->name('admin.menu.edit');
    //     Route::put('/{menuItem}', [\App\Http\Controllers\Admin\MenuManagementController::class, 'update'])->name('admin.menu.update');
    //     Route::delete('/{menuItem}', [\App\Http\Controllers\Admin\MenuManagementController::class, 'destroy'])->name('admin.menu.destroy');
    //     Route::post('/update-order', [\App\Http\Controllers\Admin\MenuManagementController::class, 'updateOrder'])->name('admin.menu.update-order');
    //     Route::get('/{menuItem}/badges', [\App\Http\Controllers\Admin\MenuManagementController::class, 'badges'])->name('admin.menu.badges');
    //     Route::post('/{menuItem}/badges', [\App\Http\Controllers\Admin\MenuManagementController::class, 'storeBadge'])->name('admin.menu.badges.store');
    //     Route::delete('/{menuItem}/badges/{badge}', [\App\Http\Controllers\Admin\MenuManagementController::class, 'destroyBadge'])->name('admin.menu.badges.destroy');
    //     Route::get('/analytics', [\App\Http\Controllers\Admin\MenuManagementController::class, 'analytics'])->name('admin.menu.analytics');
    // });

    // API route for fetching provider's outstanding orders
    Route::get('/api/providers/{provider}/outstanding-orders', [\App\Http\Controllers\Admin\PaymentsController::class, 'getProviderOrders'])
        ->middleware(['auth', 'permission:manage-payments']);

    // Facility Management - Role-based access
    Route::middleware(['permission:view-facilities'])->group(function () {
        Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
        Route::get('/facilities/{facility}', [FacilityController::class, 'show'])->name('facilities.show');
    });

    // Test route to check if orders exist
    Route::get('/test-orders', function() {
        $orders = \App\Models\Order\ProductRequest::take(5)->get(['id', 'request_number', 'order_status']);
        return response()->json($orders);
    });

    // Test route with manual model finding
    Route::get('/test-order/{id}', function($id) {
        $order = \App\Models\Order\ProductRequest::find($id);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        return response()->json([
            'id' => $order->id,
            'request_number' => $order->request_number,
            'order_status' => $order->order_status
        ]);
    });



// Docuseal Routes
Route::middleware(['auth'])->group(function () {
    // NOTE: DocusealController needs to be created
    /*
    // Create Docuseal submission with pre-filled data
    Route::post('/docuseal/create-submission', [\App\Http\Controllers\DocusealController::class, 'createSubmission'])
        ->name('docuseal.create-submission')
        ->middleware('permission:manage-orders');

    // Demo-specific Docuseal submission (only requires authentication, not manage-orders permission)
    Route::post('/docuseal/demo/create-submission', [\App\Http\Controllers\DocusealController::class, 'createDemoSubmission'])
        ->name('docuseal.demo.create-submission');
    */
});

// QuickRequest Docuseal Routes (Consolidated)
Route::middleware(['auth'])->group(function () {
    // Create final submission form with all QuickRequest data
    Route::post('/quickrequest/docuseal/create-final-submission', [\App\Http\Controllers\QuickRequestController::class, 'createFinalSubmission'])
        ->name('quickrequest.docuseal.create-final-submission');
});

// ================================================================
// PROVIDER DASHBOARD ROUTES
// ================================================================

// Provider Dashboard Routes - for authenticated providers
Route::middleware(['auth', 'permission:view-orders'])->prefix('provider')->group(function () {
    Route::get('/dashboard', [ProviderDashboardController::class, 'index'])
        ->name('provider.dashboard')
        ->middleware('permission:view-dashboard');
    Route::get('/episodes', [ProviderDashboardController::class, 'episodes'])
        ->name('provider.episodes')
        ->middleware('permission:view-orders');
    Route::get('/episodes/{episode}', [ProviderDashboardController::class, 'showEpisode'])
        ->name('provider.episodes.show')
        ->middleware('permission:view-orders');
    
    // Provider Profile Route
    Route::get('/profile', [\App\Http\Controllers\Api\ProviderProfileController::class, 'showOwn'])
        ->name('provider.profile');
});

// Notifications Route - for all authenticated users
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications');
    Route::post('/notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.mark-all-read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])
        ->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])
        ->name('notifications.preferences.update');
});

// ================================================================
// API ROUTES FOR REAL-TIME FEATURES
// ================================================================

// API routes for real-time features
Route::middleware(['auth', 'web'])->prefix('api')->group(function () {
    // Episode statistics
    Route::get('/episodes/stats', [ProviderDashboardController::class, 'getEpisodeStats'])
        ->name('api.episodes.stats')
        ->middleware('permission:view-orders');

    // Recent activity feed
    Route::get('/episodes/activity', [ProviderDashboardController::class, 'getEpisodeActivity'])
        ->name('api.episodes.activity')
        ->middleware('permission:view-orders');

    // Voice command processing
    Route::post('/voice/command', [ProviderDashboardController::class, 'processVoiceCommand'])
        ->name('api.voice.command');
});

// Docuseal Webhook (outside auth middleware)

// QuickRequest Episode ID generation for IVR linking
Route::post('/api/quickrequest/episode', [\App\Http\Controllers\Api\V1\QuickRequestEpisodeController::class, 'store'])
    ->name('api.quickrequest.episode')
    ->middleware(['auth', 'web']);

// Simple test route to check if routing works
Route::get('/test-routing', function() {
    return response()->json(['message' => 'Routing works!']);
});

// Test route to check CSRF and session status
Route::get('/test-csrf', function() {
    return response()->json([
        'csrf_token' => csrf_token(),
        'session_id' => session()->getId(),
        'session_lifetime' => config('session.lifetime'),
        'session_driver' => config('session.driver'),
        'has_session' => session()->isStarted(),
    ]);
});

// RBAC Management Routes
Route::middleware(['auth', 'role:msc-admin'])->prefix('rbac')->group(function () {
    Route::get('/', [RBACController::class, 'index'])->name('rbac.index');
    Route::get('/security-audit', [RBACController::class, 'getSecurityAudit'])->name('rbac.security-audit');
    Route::get('/stats', [RBACController::class, 'getSystemStats'])->name('rbac.stats');
    Route::post('/role/{role}/toggle-status', [RBACController::class, 'toggleRoleStatus'])->name('rbac.roles.toggle-status');
    Route::get('/role/{role}/permissions', [RBACController::class, 'getRolePermissions'])->name('rbac.roles.permissions');
    Route::put('/role/{role}/permissions', [RBACController::class, 'updateRolePermissions'])->name('rbac.roles.update-permissions');
});

// FHIR routes moved to api.php to avoid duplicate route names

// Docuseal debug routes (remove in production)
Route::prefix('docuseal-debug')->middleware(['auth'])->group(function () {
    Route::get('/test', [\App\Http\Controllers\DocusealDebugController::class, 'debug'])
        ->name('docuseal.debug');
    // Debug routes removed - functionality moved to main services
});

// Docuseal API Test Route (remove in production)
Route::get('/test-docuseal-connection', function () {
    if (!Auth::check()) {
        return response()->json(['error' => 'Please log in first'], 401);
    }

    try {
        $docuSealService = app(\App\Services\DocusealService::class);
        $result = $docuSealService->testConnection();

        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
})->middleware('auth')->name('test.docuseal.connection');

// Quick Docuseal template check (remove in production)
Route::get('/docuseal-templates', function () {
    if (!Auth::check()) {
        return 'Please log in first';
    }

    $apiKey = config('docuseal.api_key'); // Fixed: use docuseal.api_key instead of services.docuseal.api_key
    if (!$apiKey) {
        return 'DOCUSEAL_API_KEY not set in .env file';
    }

    try {
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'X-Auth-Token' => $apiKey,
        ])->get('https://api.docuseal.com/templates');

        if ($response->successful()) {
            $templates = $response->json();
            return response()->json([
                'api_key_works' => true,
                'templates' => collect($templates)->map(function ($t) {
                    return [
                        'id' => $t['id'],
                        'name' => $t['name'],
                        'use_this_id' => $t['id'], // <-- Use this ID in your manufacturerFields.ts
                    ];
                })
            ]);
        }

        return 'API Error: ' . $response->body();
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
})->middleware('auth');
