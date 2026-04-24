<?php

namespace Tallcms\Registration\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tallcms\Registration\Filament\Pages\RegistrationSettings;

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
        $panel->pages([
            RegistrationSettings::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
