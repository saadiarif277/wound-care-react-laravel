<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\AccessRequestController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImagesController;
use App\Http\Controllers\OrganizationsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Admin\UsersController as AdminUsersController;
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
use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\Api\MedicareMacValidationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PreAuthorizationController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\EngineController;
use App\Http\Controllers\SystemAdminController;
use App\Http\Controllers\RoleManagementController;
use App\Models\MedicareMacValidation;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\ProviderInvitationController;
use App\Models\ProviderInvitation;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

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

// Access Requests (public routes)

Route::get('request-access', [AccessRequestController::class, 'create'])
    ->name('web.access-requests.create')
    ->middleware('guest');

Route::post('request-access', [AccessRequestController::class, 'store'])
    ->name('web.access-requests.store')
    ->middleware('guest');

Route::get('api/access-requests/role-fields', [AccessRequestController::class, 'getRoleFields'])
    ->name('web.access-requests.role-fields');

// Access Request Management (protected routes)

Route::get('access-requests', [AccessRequestController::class, 'index'])
    ->name('web.access-requests.index')
    ->middleware('auth');

Route::get('access-requests/{accessRequest}', [AccessRequestController::class, 'show'])
    ->name('web.access-requests.show')
    ->middleware('auth');

Route::post('access-requests/{accessRequest}/approve', [AccessRequestController::class, 'approve'])
    ->name('web.access-requests.approve')
    ->middleware('auth');

Route::post('access-requests/{accessRequest}/deny', [AccessRequestController::class, 'deny'])
    ->name('web.access-requests.deny')
    ->middleware('auth');

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

    // Load organization data
    $invitation->load('organization');

    return Inertia::render('Auth/ProviderInvitation', [
        'invitation' => [
            'id' => $invitation->id,
            'organization_name' => $invitation->organization->name,
            'organization_type' => $invitation->organization->type ?? 'Healthcare Organization',
            'invited_email' => $invitation->email,
            'invited_role' => 'Provider',
            'expires_at' => $invitation->expires_at->toISOString(),
            'status' => $invitation->status,
            'metadata' => [
                'organization_id' => $invitation->organization_id,
                'invited_by' => $invitation->invited_by_user_id,
                'invited_by_name' => $invitation->invitedBy->first_name . ' ' . $invitation->invitedBy->last_name,
            ]
        ],
        'token' => $token
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
    ->middleware('auth');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard.alias')
    ->middleware('auth');

Route::get('/orders',[OrderController::class,'index'])->name('orders');

Route::get('/orders/approvals',[OrderController::class,'approval'])->name('orders.approval');

// Products - with proper permission middleware

Route::middleware(['permission:view-products'])->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    // Product API endpoints accessible to all roles with view-products permission
    Route::get('api/products/search', [ProductController::class, 'search'])->name('api.products.search');
    Route::get('api/products/recommendations', [ProductController::class, 'recommendations'])->name('api.products.recommendations');

    // Specific routes must come before parameterized routes
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('api/products/{product}', [ProductController::class, 'apiShow'])->name('api.products.show');
});

// Product management - restricted to admin roles only
Route::middleware(['permission:manage-products'])->group(function () {
    // Specific routes first
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::get('products/manage', [ProductController::class, 'manage'])->name('products.manage');

    // Then parameterized routes
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::put('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
});

// Users

Route::get('/users', [UsersController::class, 'index'])
    ->middleware('role:msc_admin')
    ->name('users.index');

Route::get('users/create', [UsersController::class, 'create'])
    ->name('users.create')
    ->middleware('auth');

Route::post('users', [UsersController::class, 'store'])
    ->name('users.store')
    ->middleware('auth');

Route::get('users/{user}/edit', [UsersController::class, 'edit'])
    ->name('users.edit')
    ->middleware('auth');

Route::put('users/{user}', [UsersController::class, 'update'])
    ->name('users.update')
    ->middleware('auth');

Route::delete('users/{user}', [UsersController::class, 'destroy'])
    ->name('users.destroy')
    ->middleware('auth');

Route::put('users/{user}/restore', [UsersController::class, 'restore'])
    ->name('users.restore')
    ->middleware('auth');

// Organizations (Read-only for reference)

Route::get('organizations', [OrganizationsController::class, 'index'])
    ->name('organizations')
    ->middleware('auth');



// Reports

Route::get('reports', [ReportsController::class, 'index'])
    ->name('reports')
    ->middleware('auth');

// Images

Route::get('/img/{path}', [ImagesController::class, 'show'])
    ->where('path', '.*')
    ->name('image');

Route::middleware(['auth', 'verified'])->group(function () {
    // Eligibility Verification Routes
    Route::prefix('eligibility')->group(function () {
        Route::get('/', [EligibilityController::class, 'index'])->name('eligibility.index');
        Route::post('/verify', [EligibilityController::class, 'verify'])->name('eligibility.verify');
        Route::post('/prior-auth/submit', [EligibilityController::class, 'submitPriorAuth'])->name('eligibility.prior-auth.submit');
        Route::post('/prior-auth/status', [EligibilityController::class, 'checkPriorAuthStatus'])->name('eligibility.prior-auth.status');
    });

    // MAC Validation Routes - accessible to office managers and admins
    Route::middleware(['permission:manage-mac-validation'])->prefix('mac-validation')->group(function () {
        Route::get('/', [MedicareMacValidationController::class, 'index'])->name('mac-validation.index');
        Route::post('/validate', [MedicareMacValidationController::class, 'validateMAC'])->name('mac-validation.validate');
    });

    // eClinicalWorks Integration Routes
    Route::get('/ecw', function () {
        return Inertia::render('EcwIntegration/Index');
    })->name('ecw.index');

    // Commission Management Routes
    Route::prefix('commission')->middleware(['financial.access'])->group(function () {
        Route::get('/', [CommissionController::class, 'index'])->name('commission.index');
        Route::get('/rules', [CommissionRuleController::class, 'index'])->name('commission-rules.index');
        Route::get('/records', [CommissionRecordController::class, 'index'])->name('commission-records.index');
        Route::get('/payouts', [CommissionPayoutController::class, 'index'])->name('commission-payouts.index');
    });

    // Product Request Routes
    Route::prefix('product-requests')->group(function () {
        Route::get('/', [ProductRequestController::class, 'index'])
            ->middleware('permission:view-product-requests')
            ->name('product-requests.index');
        Route::get('/create', [ProductRequestController::class, 'create'])->name('product-requests.create');
        Route::post('/', [ProductRequestController::class, 'store'])->name('product-requests.store');
        Route::get('/{productRequest}', [ProductRequestController::class, 'show'])->name('product-requests.show');
        Route::post('/{productRequest}/update-step', [ProductRequestController::class, 'updateStep'])->name('product-requests.update-step');
        Route::post('/{productRequest}/mac-validation', [ProductRequestController::class, 'runMacValidation'])->name('product-requests.mac-validation');
        Route::post('/{productRequest}/eligibility-check', [ProductRequestController::class, 'runEligibilityCheck'])->name('product-requests.eligibility-check');
        Route::post('/{productRequest}/submit-prior-auth', [ProductRequestController::class, 'submitPriorAuth'])->name('product-requests.submit-prior-auth');
        Route::post('/{productRequest}/check-prior-auth-status', [ProductRequestController::class, 'checkPriorAuthStatus'])->name('product-requests.check-prior-auth-status');
        Route::post('/{productRequest}/submit', [ProductRequestController::class, 'submit'])->name('product-requests.submit');
    });

    // Product Request API Routes
    Route::prefix('api/product-requests')->group(function () {
        Route::post('/search-patients', [ProductRequestController::class, 'searchPatients'])->name('api.product-requests.search-patients');
        Route::get('/{productRequest}/recommendations', [ProductRequestController::class, 'getRecommendations'])->name('api.product-requests.recommendations');
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

    Route::middleware(['permission:manage-pre-authorization'])->group(function () {
        Route::get('/pre-authorization', [PreAuthorizationController::class, 'index'])
            ->name('pre-authorization.index');
        Route::post('/pre-authorization/submit', [PreAuthorizationController::class, 'submit'])
            ->name('pre-authorization.submit');
        Route::get('/pre-authorization/status', [PreAuthorizationController::class, 'status'])
            ->name('pre-authorization.status');
    });

    // MSC Admin routes - Add proper authorization

    Route::middleware(['permission:manage-orders'])->group(function () {
        Route::get('/orders/manage', [OrderController::class, 'manage'])->name('orders.manage');
    });

    Route::middleware(['permission:view-analytics'])->group(function () {
        Route::get('/orders/analytics', [OrderController::class, 'analytics'])->name('orders.analytics');
    });

    Route::middleware(['permission:create-orders'])->group(function () {
        Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    });

    Route::middleware(['permission:view-settings'])->group(function () {
        Route::get('/settings', function () {
            return Inertia::render('Settings/Index');
        })->name('settings.index');
    });

    Route::middleware(['permission:manage-subrep-approvals'])->group(function () {
        Route::get('/subrep-approvals', function () {
            return Inertia::render('SubrepApprovals/Index');
        })->name('subrep-approvals.index');
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
    });

    // Access Control Management Routes
    Route::middleware(['permission:manage-access-control'])->group(function () {
        Route::get('/access-control', [AccessControlController::class, 'index'])->name('web.access-control.index');
    });

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

    // Onboarding Management Routes
    Route::middleware(['permission:manage-onboarding'])->prefix('onboarding')->group(function () {
        Route::get('/management', function () {
            return Inertia::render('Onboarding/Management');
        })->name('onboarding.management');

        Route::get('/organizations', [\App\Http\Controllers\Api\V1\Admin\CustomerManagementController::class, 'listOrganizations'])
            ->name('onboarding.organizations');
        Route::get('/organizations/{organization}/status', [\App\Http\Controllers\Api\V1\Admin\CustomerManagementController::class, 'getOnboardingStatus'])
            ->name('onboarding.organizations.status');
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

            // TODO: Send email notification

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

    // MSC Rep routes - Add proper authorization
    Route::middleware(['permission:view-customers'])->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])
            ->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])
            ->name('customers.show');
        Route::get('/customers/dashboard', [CustomerController::class, 'dashboard'])
            ->name('customers.dashboard');
    });

    Route::middleware(['permission:view-team'])->group(function () {
        Route::get('/team', [TeamController::class, 'index'])
            ->name('team.index');
        Route::get('/team/{member}', [TeamController::class, 'show'])
            ->name('team.show');
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

    // DocuSeal Document Management Routes
    Route::middleware(['permission:manage-orders'])->prefix('admin/docuseal')->group(function () {
        // Main DocuSeal dashboard
        Route::get('/', function () {
            return Inertia::render('Admin/DocuSeal/Dashboard');
        })->name('admin.docuseal.index');

        // Document submissions management
        Route::get('/submissions', function () {
            return Inertia::render('Admin/DocuSeal/Submissions');
        })->name('admin.docuseal.submissions');

        // Document signing status tracking
        Route::get('/status', function () {
            return Inertia::render('Admin/DocuSeal/Status');
        })->name('admin.docuseal.status');
    });

    // Super Admin only DocuSeal routes
    Route::middleware(['permission:manage-all-organizations'])->prefix('admin/docuseal')->group(function () {
        // Template management (super admin only)
        Route::get('/templates', function () {
            return Inertia::render('Admin/DocuSeal/Templates');
        })->name('admin.docuseal.templates');

        // Analytics dashboard (super admin only)
        Route::get('/analytics', function () {
            return Inertia::render('Admin/DocuSeal/Analytics');
        })->name('admin.docuseal.analytics');
    });
});
