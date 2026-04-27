# TallCMS Registration Bridge (Multisite-flavoured)

Wires the generic [`tallcms/filament-registration`](https://github.com/tallcms/filament-registration) plugin into TallCMS with the **SaaS / Multisite** defaults this app expects: default role `site_owner`, post-register onboarding into the Multisite Template Gallery, default site-plan assignment.

> ⚠️ **This bridge is most useful with the [TallCMS Multisite plugin](https://github.com/tallcms/multisite-plugin) installed.** Without Multisite, the bridge's onboarding-redirect and site-plan-assignment features both silently no-op (they check for `Tallcms\Multisite\Services\SitePlanService` at runtime). Vanilla TallCMS installs that don't use Multisite are better off skipping this bridge and using `tallcms/filament-registration` directly with whatever `defaultRole(...)` they want — the upstream plugin's README has the recipe.

> **v2.0.0 is a major-version refactor.** The previous standalone `/register` controller and Blade form were retired in favour of Filament's native registration page. The captcha pipeline and admin settings UI moved into the upstream `tallcms/filament-registration` package, which this plugin now depends on. See the [migration notes](#migration-from-1x) before upgrading.

## What this plugin does (in v2.0.0)

- Registers the [generic plugin](https://github.com/tallcms/filament-registration) on your Filament panel with `defaultRole('site_owner')`.
- 301-redirects the legacy `/register` URL to the panel's register URL so existing bookmarks keep working.
- **(Requires Multisite plugin)** Binds Filament's `RegistrationResponse` contract to an onboarding-aware response — newly registered users with no sites are redirected to the Multisite Template Gallery instead of the panel home.
- **(Requires Multisite plugin)** Assigns the default site plan to new users via `Tallcms\Multisite\Services\SitePlanService`.
- **(Requires Multisite plugin)** Mounts `EnsureOnboardingRedirect` middleware on the panel so verified users with no sites keep getting nudged to the gallery on subsequent visits.

Everything else — captcha (Turnstile / reCAPTCHA v3), admin settings UI, the actual register page form — is provided by the upstream generic plugin.

### Without the Multisite plugin?

The three Multisite-coupled features above silently no-op. The bridge's contribution shrinks to just `defaultRole('site_owner')` + the legacy URL redirect — which you can do directly in your panel provider without the bridge:

```php
$panel
    ->registration(\Tallcms\FilamentRegistration\Filament\Pages\Register::class)
    ->plugin(
        \Tallcms\FilamentRegistration\Filament\FilamentRegistrationPlugin::make()
            ->defaultRole('site_owner')
    );
```

If you're not running Multisite, skip this bridge and use the [generic plugin](https://github.com/tallcms/filament-registration) directly — fewer moving parts, same outcome for vanilla TallCMS.

## Prerequisites

- TallCMS ≥ **4.3.2**
- Composer package `tallcms/filament-registration` ^1.0 installed at the host level (`composer require tallcms/filament-registration` in your TallCMS app — this plugin is not a Composer package itself, so it cannot pull dependencies on its own)

## Installation

1. Install the upstream generic plugin in your TallCMS app:
   ```bash
   composer require tallcms/filament-registration
   php artisan migrate
   ```

2. Install this bridge plugin via the Plugin Manager (zip upload) or by copying into `plugins/tallcms/registration/`.

3. Wire both into your panel provider:
   ```php
   use Tallcms\FilamentRegistration\Filament\Pages\Register;
   use Tallcms\Registration\Filament\RegistrationPlugin;

   public function panel(Panel $panel): Panel
   {
       return $panel
           ->id('admin')
           ->path('admin')
           ->login()
           ->registration(Register::class)              // generic plugin's page
           ->plugin(RegistrationPlugin::make());        // this bridge
   }
   ```

   Both calls are required and not interchangeable. `->registration(Register::class)` tells Filament *which* page handles the registration route; `->plugin(RegistrationPlugin::make())` wires the TallCMS bridge (which in turn registers the generic plugin's settings page and applies `defaultRole('site_owner')`).

## Configuration

The bridge respects the same env vars it always did for non-captcha settings:

```env
REGISTRATION_DEFAULT_ROLE=site_owner       # role assigned to new users
REGISTRATION_ONBOARDING_ENABLED=true       # auto-redirect to template gallery
REGISTRATION_ONBOARDING_REDIRECT_URL=      # override gallery URL if needed
REGISTRATION_EMAIL_VERIFICATION=false      # see "Email verification" below
```

Captcha config has moved to the upstream plugin — manage it via **Admin → Settings → Registration**, or set `FILAMENT_REGISTRATION_CAPTCHA_*` env vars (see the upstream README).

## Email verification

In v2.0.0 the bridge no longer ships a custom verification view. Use Filament's native flow:

```php
$panel
    ->registration(Register::class)
    ->emailVerification(isRequired: fn () => (bool) config('registration.email_verification.enabled'))
    ->plugin(RegistrationPlugin::make());
```

When `REGISTRATION_EMAIL_VERIFICATION=true`, your User model implements `MustVerifyEmail`, and the panel has `->emailVerification()` enabled, Filament handles the verification prompt + resend automatically. The upstream Register page detects this configuration and pre-marks new users verified when verification is OFF (so no surprise emails are sent).

## Migration from 1.x

This is a **breaking** version. Concrete changes if you're upgrading from 1.3.x:

| Area | 1.x | 2.0.0 |
|---|---|---|
| Public URL | `/register` (controller + Blade form) | `/admin/register` (Filament native page) — `/register` 301s to the new URL |
| Captcha | This plugin | Upstream `tallcms/filament-registration` |
| Admin settings page | This plugin (`/app/registration-settings`) | Upstream — same UI, same DB table |
| Email verification flow | Custom `/registered` and `/awaiting-verification` views | Filament's native `->emailVerification()` |
| Honeypot bot rejection | Silent fake-success page | Validation error on the form |
| Post-validation throttle | Custom 5/min throttle | Filament's built-in (2/min global, 2/min per email) |
| `Manage:CodeInjection`-style permission | None for registration | New `View:RegistrationSettings` Shield permission for the upstream settings page |

Settings DB rows in `tallcms_registration_settings` are preserved — the upstream plugin reads from the same table. No data migration needed.

If you have themes shipping a custom `vendor/tallcms-registration/register.blade.php` override, those overrides no longer apply — the form is now a Filament page using Filament's auth layout. To customise the form fields, extend `Tallcms\FilamentRegistration\Filament\Pages\Register` in your app and pass your subclass to `->registration(YourRegister::class)`.

## License

MIT — see [LICENSE](LICENSE).
