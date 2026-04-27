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

        $this->forwardLegacyCaptchaEnvVars();

        // Replace the generic plugin's default RegistrationResponse with one
        // that knows about TallCMS's onboarding flow. The generic plugin
        // binds its own default first; this overrides it because service
        // providers boot in dependency order and the bridge plugin loads
        // after the generic one.
        $this->app->bind(RegistrationResponseContract::class, OnboardingRegistrationResponse::class);
    }

    /**
     * Backward-compat: forward v1.x's `REGISTRATION_CAPTCHA_*` env vars to
     * the new `FILAMENT_REGISTRATION_CAPTCHA_*` names the upstream plugin
     * reads. Lets v1.x deploys upgrade to v2.0+ without having to rename
     * env vars across staging/production.
     *
     * Only forwards when the old env is set AND the new key isn't — never
     * clobbers an explicit new-style env. The upstream plugin's
     * mergeDbSettingsIntoConfig() runs in boot() and still wins on top of
     * whatever ends up in config here.
     */
    private function forwardLegacyCaptchaEnvVars(): void
    {
        $forward = [
            'REGISTRATION_CAPTCHA_ENABLED' => 'filament-registration.captcha.enabled',
            'REGISTRATION_CAPTCHA_PROVIDER' => 'filament-registration.captcha.provider',
            'REGISTRATION_CAPTCHA_SITE_KEY' => 'filament-registration.captcha.site_key',
            'REGISTRATION_CAPTCHA_SECRET_KEY' => 'filament-registration.captcha.secret_key',
            'REGISTRATION_CAPTCHA_RECAPTCHA_MIN_SCORE' => 'filament-registration.captcha.recaptcha_min_score',
        ];

        foreach ($forward as $envKey => $configKey) {
            $legacyValue = env($envKey);

            if ($legacyValue === null || $legacyValue === '') {
                continue;
            }

            // Only forward if the new-style config key wasn't already set
            // by the upstream plugin (i.e. its FILAMENT_REGISTRATION_* env
            // var is empty or unset).
            $currentValue = config($configKey);

            if ($currentValue !== null && $currentValue !== '' && $currentValue !== false) {
                continue;
            }

            // Type coercion mirrors what the upstream plugin does in its own
            // service provider. Keeps the runtime config values consistent
            // regardless of which env var name was used.
            $coerced = match ($configKey) {
                'filament-registration.captcha.recaptcha_min_score' => (float) $legacyValue,
                default => $legacyValue,
            };

            config([$configKey => $coerced]);
        }
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
