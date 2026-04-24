<?php

namespace Tallcms\Registration\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;
use Tallcms\Registration\Captcha\CaptchaManager;
use Tallcms\Registration\Captcha\Providers\RecaptchaV3CaptchaProvider;
use Tallcms\Registration\Captcha\Providers\TurnstileCaptchaProvider;
use Tallcms\Registration\Services\SettingsRepository;

class RegistrationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'tallcms-registration::filament.pages.registration-settings';

    protected static ?string $navigationLabel = 'Registration';

    protected static ?string $title = 'Registration & CAPTCHA';

    public ?array $data = [];

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('tallcms.navigation.groups.system', 'System');
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    public function mount(): void
    {
        $repo = app(SettingsRepository::class);

        $this->form->fill([
            'captcha_enabled' => (bool) ($repo->get('captcha_enabled') ?? config('registration.captcha.enabled') ?? false),
            'captcha_provider' => (string) ($repo->get('captcha_provider') ?? config('registration.captcha.provider', 'turnstile')),
            'captcha_site_key' => (string) ($repo->get('captcha_site_key') ?? config('registration.captcha.site_key', '')),
            'captcha_recaptcha_min_score' => (float) ($repo->get('captcha_recaptcha_min_score') ?? config('registration.captcha.recaptcha_min_score', 0.5)),
        ]);
    }

    protected function getFormSchema(): array
    {
        $secretConfigured = (string) config('registration.captcha.secret_key', '') !== '';

        return [
            Section::make('CAPTCHA')
                ->description('Bot protection on the public /register form. Leave disabled to fall back to honeypot + rate limiting only.')
                ->schema([
                    Toggle::make('captcha_enabled')
                        ->label('Enable CAPTCHA')
                        ->helperText('When off, the registration form skips CAPTCHA verification entirely.'),

                    Select::make('captcha_provider')
                        ->label('Provider')
                        ->options([
                            'turnstile' => 'Cloudflare Turnstile',
                            'recaptcha_v3' => 'Google reCAPTCHA v3',
                        ])
                        ->required()
                        ->live()
                        ->helperText(new HtmlString(
                            'Cloudflare Turnstile is privacy-friendly and free. Get keys at '
                            .'<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" class="underline">Cloudflare Turnstile</a>. '
                            .'reCAPTCHA v3 keys come from the '
                            .'<a href="https://www.google.com/recaptcha/admin" target="_blank" class="underline">reCAPTCHA admin console</a>.'
                        )),

                    TextInput::make('captcha_site_key')
                        ->label('Site key')
                        ->helperText('Public key embedded in the form. Safe to put in source control.')
                        ->maxLength(255),

                    Placeholder::make('captcha_secret_status')
                        ->label('Secret key')
                        ->content(fn () => new HtmlString(
                            $secretConfigured
                                ? '<span class="text-success font-medium">✓ Configured via environment</span>'
                                : '<span class="text-warning font-medium">✗ Not set — define <code>REGISTRATION_CAPTCHA_SECRET_KEY</code> in <code>.env</code></span>'
                        ))
                        ->helperText('Secret keys never live in the database. Set them via the .env file.'),

                    TextInput::make('captcha_recaptcha_min_score')
                        ->label('Minimum score (reCAPTCHA v3 only)')
                        ->helperText('Tokens scoring below this threshold are rejected. Range 0.0 (lenient) – 1.0 (strict). Default 0.5.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.05)
                        ->visible(fn (callable $get) => $get('captcha_provider') === 'recaptcha_v3'),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test verification')
                ->color('gray')
                ->icon('heroicon-o-bolt')
                ->action(function () {
                    // Save first so the live config reflects what's in the form.
                    $this->save(notify: false);

                    $captcha = app(CaptchaManager::class)->resolve();

                    if (! $captcha->isEnabled()) {
                        Notification::make()
                            ->title('CAPTCHA is not enabled')
                            ->body('Enable it and configure both keys, then try again.')
                            ->warning()
                            ->send();

                        return;
                    }

                    // Send a deliberately bogus token. A reachable, correctly-keyed
                    // provider should respond with a clean rejection (returns false).
                    // A misconfigured one will throw or return false too — we rely on
                    // logs in storage/logs/laravel.log for the underlying error.
                    $result = $captcha->verify('___test_invalid_token___', request()->ip());

                    if ($result === false) {
                        Notification::make()
                            ->title('Reachable')
                            ->body('Provider responded and rejected a deliberately bogus token, as expected. Live submissions with valid tokens should pass.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Unexpected pass')
                            ->body('A bogus token was accepted. Check your secret key and provider configuration.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function save(bool $notify = true): void
    {
        $data = $this->form->getState();

        app(SettingsRepository::class)->setMany([
            'captcha_enabled' => (bool) ($data['captcha_enabled'] ?? false),
            'captcha_provider' => $data['captcha_provider'] ?? 'turnstile',
            'captcha_site_key' => (string) ($data['captcha_site_key'] ?? ''),
            'captcha_recaptcha_min_score' => (float) ($data['captcha_recaptcha_min_score'] ?? 0.5),
        ]);

        // Also nudge runtime config so the next request (and the test action
        // inside this same request) sees the new values immediately.
        config([
            'registration.captcha.enabled' => (bool) ($data['captcha_enabled'] ?? false),
            'registration.captcha.provider' => $data['captcha_provider'] ?? 'turnstile',
            'registration.captcha.site_key' => (string) ($data['captcha_site_key'] ?? ''),
            'registration.captcha.recaptcha_min_score' => (float) ($data['captcha_recaptcha_min_score'] ?? 0.5),
        ]);

        // Force CaptchaProvider singleton to be re-resolved on next request.
        app()->forgetInstance(\Tallcms\Registration\Captcha\Contracts\CaptchaProvider::class);

        if ($notify) {
            Notification::make()
                ->title('Registration settings saved')
                ->success()
                ->send();
        }
    }
}
