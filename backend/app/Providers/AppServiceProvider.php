<?php

namespace App\Providers;

use App\Domain\Profile\Policies\ProfileItemPolicy;
use App\Models\ProfileItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ProfileItem::class, ProfileItemPolicy::class);
    }
}
