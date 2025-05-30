<?php

namespace App\Providers;

use App\Models\Order\Order; // Adjusted for Order model location
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
        // Add other policies here
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // You can define gates here as well if needed
        // Gate::define('edit-settings', function (User $user) {
        //     return $user->isAdmin();
        // });
    }
} 