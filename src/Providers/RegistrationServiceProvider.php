<?php

namespace Tallcms\Registration\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tallcms\Registration\Listeners\AssignDefaultSitePlan;

class RegistrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $defaults = [
            'enabled' => true,
            'default_role' => 'author',
            'redirect_after' => '/admin',
        ];

        // Merge app-level config/registration.php (if it exists) over defaults
        $appConfig = config_path('registration.php');

        if (file_exists($appConfig)) {
            $this->mergeConfigFrom($appConfig, 'registration');
        }

        config(['registration' => array_merge($defaults, config('registration', []))]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'tallcms-registration');

        if (class_exists(\Tallcms\Multisite\Services\SitePlanService::class)) {
            Event::listen(Registered::class, AssignDefaultSitePlan::class);
        }
    }
}
