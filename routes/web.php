<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\AccessRequestController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImagesController;
use App\Http\Controllers\OrganizationsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\EligibilityController;
use App\Http\Controllers\MACValidationController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CommissionRuleController;
use App\Http\Controllers\CommissionRecordController;
use App\Http\Controllers\CommissionPayoutController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RBACController;
use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\RoleController;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

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
    ->name('logout');

// Access Requests (public routes)

Route::get('request-access', [AccessRequestController::class, 'create'])
    ->name('access-requests.create')
    ->middleware('guest');

Route::post('request-access', [AccessRequestController::class, 'store'])
    ->name('access-requests.store')
    ->middleware('guest');

Route::get('api/access-requests/role-fields', [AccessRequestController::class, 'getRoleFields'])
    ->name('api.access-requests.role-fields');

// Access Request Management (protected routes)

Route::get('access-requests', [AccessRequestController::class, 'index'])
    ->name('access-requests.index')
    ->middleware('auth');

Route::get('access-requests/{accessRequest}', [AccessRequestController::class, 'show'])
    ->name('access-requests.show')
    ->middleware('auth');

Route::post('access-requests/{accessRequest}/approve', [AccessRequestController::class, 'approve'])
    ->name('access-requests.approve')
    ->middleware('auth');

Route::post('access-requests/{accessRequest}/deny', [AccessRequestController::class, 'deny'])
    ->name('access-requests.deny')
    ->middleware('auth');

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
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

    // Product API endpoints accessible to all roles with view-products permission
    Route::get('api/products/search', [ProductController::class, 'search'])->name('api.products.search');
    Route::get('api/products/{product}', [ProductController::class, 'apiShow'])->name('api.products.show');
    Route::get('api/products/recommendations', [ProductController::class, 'recommendations'])->name('api.products.recommendations');
});

// Product management - restricted to admin roles only
Route::middleware(['permission:manage-products'])->group(function () {
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::put('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
});

// Users

Route::get('/users', [UserController::class, 'index'])
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
        Route::get('/', [MACValidationController::class, 'index'])->name('mac-validation.index');
        Route::post('/validate', [MACValidationController::class, 'validateMAC'])->name('mac-validation.validate');
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
            ->middleware('role:provider,office_manager,msc_rep,msc_subrep,msc_admin')
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

    // Product API Routes
    Route::prefix('api/products')->group(function () {
        Route::get('/search', [ProductController::class, 'getAll'])->name('api.products.search');
        Route::get('/{product}', [ProductController::class, 'apiShow'])->name('api.products.show');
    });

    // Office Manager specific routes - Add proper authorization
    Route::middleware(['permission:view-product-requests'])->group(function () {
        Route::get('/product-requests/facility', [ProductRequestController::class, 'facilityRequests'])->name('product-requests.facility');
        Route::get('/product-requests/providers', [ProductRequestController::class, 'providerRequests'])->name('product-requests.providers');
    });

    Route::middleware(['permission:view-providers'])->group(function () {
        Route::get('/providers', function () {
            return Inertia::render('Providers/Index');
        })->name('providers.index');
    });

    Route::middleware(['permission:manage-pre-authorization'])->group(function () {
        Route::get('/pre-authorization', function () {
            return Inertia::render('PreAuthorization/Index');
        })->name('pre-authorization.index');
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

    Route::middleware(['permission:manage-products'])->group(function () {
        Route::get('/products/manage', [ProductController::class, 'manage'])->name('products.manage');
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

    // Super Admin routes - Add proper authorization
    Route::middleware(['permission:manage-rbac'])->group(function () {
        Route::get('/rbac', [RBACController::class, 'index'])->name('rbac.index');
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    });

    Route::middleware(['permission:manage-access-control'])->group(function () {
        Route::get('/access-control', [AccessControlController::class, 'index'])->name('access-control.index');
    });

    Route::middleware(['permission:view-commission'])->group(function () {
        Route::get('/commission/overview', function () {
            return Inertia::render('Commission/Overview');
        })->name('commission.overview');
    });

    // System Admin routes - Add proper authorization
    Route::middleware(['permission:manage-system-config'])->group(function () {
        Route::prefix('system-admin')->group(function () {
            Route::get('/config', function () {
                return Inertia::render('SystemAdmin/Config');
            })->name('system-admin.config');
            Route::get('/integrations', function () {
                return Inertia::render('SystemAdmin/Integrations');
            })->name('system-admin.integrations');
            Route::get('/api', function () {
                return Inertia::render('SystemAdmin/API');
            })->name('system-admin.api');
        });
    });

    Route::middleware(['permission:view-audit-logs'])->group(function () {
        Route::get('/system-admin/audit', function () {
            return Inertia::render('SystemAdmin/Audit');
        })->name('system-admin.audit');
    });

    // Engine routes - Add proper authorization
    Route::middleware(['permission:manage-clinical-rules'])->group(function () {
        Route::get('/engines/clinical-rules', function () {
            return Inertia::render('Engines/ClinicalRules');
        })->name('engines.clinical-rules');
    });

    Route::middleware(['permission:manage-recommendation-rules'])->group(function () {
        Route::get('/engines/recommendation-rules', function () {
            return Inertia::render('Engines/RecommendationRules');
        })->name('engines.recommendation-rules');
    });

    Route::middleware(['permission:manage-commission-engine'])->group(function () {
        Route::get('/engines/commission', function () {
            return Inertia::render('Engines/Commission');
        })->name('engines.commission');
    });

    // MSC Rep routes - Add proper authorization
    Route::middleware(['permission:view-customers'])->group(function () {
        Route::get('/customers', function () {
            return Inertia::render('Customers/Index');
        })->name('customers.index');
    });

    Route::middleware(['permission:view-team'])->group(function () {
        Route::get('/team', function () {
            return Inertia::render('Team/Index');
        })->name('team.index');
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
});
