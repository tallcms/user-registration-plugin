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
            // site_owner is the SaaS-flow role: full management of one's own
            // sites (pages, posts, menus, media, comments, submissions), scoped
            // by policy to only the user's own sites. Existing installs that
            // want the legacy behavior can override to 'author' in
            // config/registration.php or via REGISTRATION_DEFAULT_ROLE.
            'default_role' => env('REGISTRATION_DEFAULT_ROLE', 'site_owner'),
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
