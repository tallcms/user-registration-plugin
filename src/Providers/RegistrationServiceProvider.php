<?php

declare(strict_types=1);

namespace Tallcms\Registration\Providers;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tallcms\Registration\Console\Commands\BackfillVerifiedUsers;
use Tallcms\Registration\Http\Responses\OnboardingRegistrationResponse;
use Tallcms\Registration\Listeners\AssignDefaultSitePlan;

/**
 * TallCMS bridge for tallcms/filament-registration.
 *
 * What this provider does in v2.0.0 (much smaller than v1.x):
 *  1. Holds the small set of TallCMS-specific config defaults
 *     (default role, onboarding flags) — captcha config has moved to the
 *     generic plugin's `filament-registration` config.
 *  2. Wires the AssignDefaultSitePlan listener to Laravel's `Registered`
 *     event. The new generic plugin's Register page fires Laravel's event
 *     alongside Filament's own, so this listener keeps working unchanged.
 *  3. Binds Filament's RegistrationResponse contract to the onboarding-
 *     aware response so post-register redirects send new users into the
 *     onboarding flow when applicable.
 */
class RegistrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $defaults = [
            'enabled' => true,
            // site_owner is the SaaS-flow role: full management of one's own
            // sites, scoped by policy. Existing installs that want the legacy
            // behaviour can override to 'author' in config/registration.php.
            'default_role' => env('REGISTRATION_DEFAULT_ROLE', 'site_owner'),
            'email_verification' => [
                'enabled' => (bool) env('REGISTRATION_EMAIL_VERIFICATION', false),
            ],
            'onboarding' => [
                'enabled' => (bool) env('REGISTRATION_ONBOARDING_ENABLED', true),
                'redirect_url' => env('REGISTRATION_ONBOARDING_REDIRECT_URL'),
            ],
        ];

        $appConfig = config_path('registration.php');

        if (file_exists($appConfig)) {
            $this->mergeConfigFrom($appConfig, 'registration');
        }

        config(['registration' => array_replace_recursive($defaults, config('registration', []))]);

        // Replace the generic plugin's default RegistrationResponse with one
        // that knows about TallCMS's onboarding flow. The generic plugin
        // binds its own default first; this overrides it because service
        // providers boot in dependency order and the bridge plugin loads
        // after the generic one.
        $this->app->bind(RegistrationResponseContract::class, OnboardingRegistrationResponse::class);
    }

    public function boot(): void
    {
        if (class_exists(\Tallcms\Multisite\Services\SitePlanService::class)) {
            Event::listen(Registered::class, AssignDefaultSitePlan::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillVerifiedUsers::class,
            ]);
        }
    }
}
