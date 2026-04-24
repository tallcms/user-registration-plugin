<?php

namespace Tallcms\Registration\Providers;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tallcms\Registration\Captcha\CaptchaManager;
use Tallcms\Registration\Captcha\Contracts\CaptchaProvider;
use Tallcms\Registration\Console\Commands\BackfillVerifiedUsers;
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
            // null = resolve from Filament's default panel (or OnboardingResolver)
            // at runtime. Override with a string to force a specific URL.
            'redirect_after' => env('REGISTRATION_REDIRECT_AFTER'),
            'captcha' => [
                // null = auto (enable iff site_key and secret_key are both present);
                // explicit true/false from env wins.
                'enabled' => env('REGISTRATION_CAPTCHA_ENABLED'),
                'provider' => env('REGISTRATION_CAPTCHA_PROVIDER', 'turnstile'),
                'site_key' => env('REGISTRATION_CAPTCHA_SITE_KEY', ''),
                'secret_key' => env('REGISTRATION_CAPTCHA_SECRET_KEY', ''),
                'recaptcha_min_score' => (float) env('REGISTRATION_CAPTCHA_RECAPTCHA_MIN_SCORE', 0.5),
            ],
            'email_verification' => [
                'enabled' => (bool) env('REGISTRATION_EMAIL_VERIFICATION', false),
            ],
            'onboarding' => [
                'enabled' => (bool) env('REGISTRATION_ONBOARDING_ENABLED', true),
                'redirect_url' => env('REGISTRATION_ONBOARDING_REDIRECT_URL'),
            ],
        ];

        // Merge app-level config/registration.php (if it exists) over defaults.
        $appConfig = config_path('registration.php');

        if (file_exists($appConfig)) {
            $this->mergeConfigFrom($appConfig, 'registration');
        }

        config(['registration' => array_replace_recursive($defaults, config('registration', []))]);

        $this->app->singleton(CaptchaManager::class);

        $this->app->singleton(CaptchaProvider::class, fn ($app) => $app->make(CaptchaManager::class)->resolve());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'tallcms-registration');

        if (class_exists(\Tallcms\Multisite\Services\SitePlanService::class)) {
            Event::listen(Registered::class, AssignDefaultSitePlan::class);
        }

        // Route Laravel's stock VerifyEmail notification through Filament's
        // panel URL generator so the link points to the Filament verification
        // route (not `verification.verify`, which doesn't exist in a Filament-
        // only install). Only wires up when verification is on and Filament is
        // loaded; keeps the plugin usable on non-Filament hosts.
        if (config('registration.email_verification.enabled') && class_exists(Filament::class)) {
            VerifyEmail::createUrlUsing(fn ($notifiable) => Filament::getVerifyEmailUrl($notifiable));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillVerifiedUsers::class,
            ]);
        }
    }
}
