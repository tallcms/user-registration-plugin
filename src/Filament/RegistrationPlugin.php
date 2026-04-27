<?php

declare(strict_types=1);

namespace Tallcms\Registration\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tallcms\FilamentRegistration\Filament\FilamentRegistrationPlugin;

/**
 * TallCMS bridge for tallcms/filament-registration.
 *
 * Wires the generic plugin onto the panel with TallCMS-specific defaults
 * (default role `site_owner`). The generic plugin handles its own settings
 * page registration; this bridge plugin just composes it.
 *
 * Host wires it up like:
 *
 * ```php
 * $panel
 *     ->registration(\Tallcms\FilamentRegistration\Filament\Pages\Register::class)
 *     ->plugin(\Tallcms\Registration\Filament\RegistrationPlugin::make());
 * ```
 *
 * The post-register onboarding redirect is provided via Filament's
 * `RegistrationResponse` contract — bound in this package's service provider
 * (see Providers\RegistrationServiceProvider) to OnboardingRegistrationResponse.
 */
class RegistrationPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'tallcms-registration';
    }

    public function register(Panel $panel): void
    {
        $panel->plugin(
            FilamentRegistrationPlugin::make()
                ->defaultRole(config('registration.default_role', 'site_owner'))
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
