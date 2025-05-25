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
use App\Http\Controllers\CommissionController;use App\Http\Controllers\CommissionRuleController;use App\Http\Controllers\CommissionRecordController;use App\Http\Controllers\CommissionPayoutController;
use App\Http\Controllers\ProductRequestController;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

Route::get('/orders',[OrderController::class,'index'])->name('orders');

Route::get('/orders/create',[OrderController::class,'create'])->name('orders.create');

Route::get('/orders/approvals',[OrderController::class,'approval'])->name('orders.approval');

// Products

Route::get('products', [ProductController::class, 'index'])
    ->name('products.index')
    ->middleware('auth');

Route::get('products/create', [ProductController::class, 'create'])
    ->name('products.create')
    ->middleware('auth');

Route::post('products', [ProductController::class, 'store'])
    ->name('products.store')
    ->middleware('auth');

Route::get('products/{product}', [ProductController::class, 'show'])
    ->name('products.show')
    ->middleware('auth');

Route::get('products/{product}/edit', [ProductController::class, 'edit'])
    ->name('products.edit')
    ->middleware('auth');

Route::put('products/{product}', [ProductController::class, 'update'])
    ->name('products.update')
    ->middleware('auth');

Route::delete('products/{product}', [ProductController::class, 'destroy'])
    ->name('products.destroy')
    ->middleware('auth');

Route::put('products/{product}/restore', [ProductController::class, 'restore'])
    ->name('products.restore')
    ->middleware('auth');

// Product API endpoints

Route::get('api/products/search', [ProductController::class, 'search'])
    ->name('api.products.search')
    ->middleware('auth');

Route::get('api/products/{product}', [ProductController::class, 'apiShow'])
    ->name('api.products.show')
    ->middleware('auth');

Route::get('api/products/recommendations', [ProductController::class, 'recommendations'])
    ->name('api.products.recommendations')
    ->middleware('auth');

// Users

Route::get('users', [UsersController::class, 'index'])
    ->name('users')
    ->middleware('auth');

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

Route::middleware(['auth'])->group(function () {
    // Eligibility Verification Routes
    Route::prefix('eligibility')->group(function () {
        Route::get('/', [EligibilityController::class, 'index'])->name('eligibility.index');
        Route::post('/verify', [EligibilityController::class, 'verify'])->name('eligibility.verify');
        Route::post('/prior-auth/submit', [EligibilityController::class, 'submitPriorAuth'])->name('eligibility.prior-auth.submit');
        Route::post('/prior-auth/status', [EligibilityController::class, 'checkPriorAuthStatus'])->name('eligibility.prior-auth.status');
    });

    // MAC Validation Routes
    Route::prefix('mac-validation')->group(function () {
        Route::get('/', [MACValidationController::class, 'index'])->name('mac-validation.index');
        Route::post('/validate', [MACValidationController::class, 'validateMAC'])->name('mac-validation.validate');
    });

    // eClinicalWorks Integration Routes
    Route::get('/ecw', function () {
        return \Inertia\Inertia::render('EcwIntegration/Index');
    })->name('ecw.index');

    // Commission Management Routes
    Route::prefix('commission')->group(function () {
        Route::get('/', [CommissionController::class, 'index'])->name('commission.index');
        Route::get('/rules', [CommissionRuleController::class, 'index'])->name('commission-rules.index');
        Route::get('/records', [CommissionRecordController::class, 'index'])->name('commission-records.index');
        Route::get('/payouts', [CommissionPayoutController::class, 'index'])->name('commission-payouts.index');
    });

    // Product Request Routes
    Route::prefix('product-requests')->group(function () {
        Route::get('/', [ProductRequestController::class, 'index'])->name('product-requests.index');
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

    // Office Manager specific routes
    Route::get('/product-requests/facility', [ProductRequestController::class, 'facilityRequests'])->name('product-requests.facility');
    Route::get('/product-requests/providers', [ProductRequestController::class, 'providerRequests'])->name('product-requests.providers');
    Route::get('/providers', function () {
        return Inertia::render('Providers/Index');
    })->name('providers.index');
    Route::get('/pre-authorization', function () {
        return Inertia::render('PreAuthorization/Index');
    })->name('pre-authorization.index');

    // MSC Admin routes
    Route::get('/requests', function () {
        return Inertia::render('Requests/Index');
    })->name('requests.index');
    Route::get('/orders/manage', [OrderController::class, 'manage'])->name('orders.manage');
    Route::get('/products/manage', [ProductController::class, 'manage'])->name('products.manage');
    Route::get('/settings', function () {
        return Inertia::render('Settings/Index');
    })->name('settings.index');
    Route::get('/subrep-approvals', function () {
        return Inertia::render('SubrepApprovals/Index');
    })->name('subrep-approvals.index');

    // Engine routes
    Route::prefix('engines')->group(function () {
        Route::get('/clinical-rules', function () {
            return Inertia::render('Engines/ClinicalRules');
        })->name('engines.clinical-rules');
        Route::get('/recommendation-rules', function () {
            return Inertia::render('Engines/RecommendationRules');
        })->name('engines.recommendation-rules');
        Route::get('/commission', function () {
            return Inertia::render('Engines/Commission');
        })->name('engines.commission');
    });

    // Super Admin routes
    Route::get('/rbac', function () {
        return Inertia::render('RBAC/Index');
    })->name('rbac.index');
    Route::get('/access-control', function () {
        return Inertia::render('AccessControl/Index');
    })->name('access-control.index');
    Route::get('/roles', function () {
        return Inertia::render('Roles/Index');
    })->name('roles.index');
    Route::get('/commission/overview', function () {
        return Inertia::render('Commission/Overview');
    })->name('commission.overview');

    // System Admin routes
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
        Route::get('/audit', function () {
            return Inertia::render('SystemAdmin/Audit');
        })->name('system-admin.audit');
    });

    // MSC Rep routes
    Route::get('/customers', function () {
        return Inertia::render('Customers/Index');
    })->name('customers.index');
    Route::get('/team', function () {
        return Inertia::render('Team/Index');
    })->name('team.index');
});
