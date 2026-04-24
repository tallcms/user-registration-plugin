# Registration Plugin

Frontend user registration for TallCMS. Adds a themed `/register` page that creates users with the `site_owner` role (or any role you configure) and redirects them to the admin panel.

> **Requires TallCMS ≥ 4.0.15** for the default `site_owner` role.
> TallCMS 4.0.15 auto-syncs the `site_owner` role during `tallcms:update`.
> On older installs, run `php artisan tallcms:shield-sync-site-owner` once before enabling registration, or set `REGISTRATION_DEFAULT_ROLE=author` in `.env` to use the legacy role.

## Features

- Themed registration form using your active theme's layout
- **CAPTCHA support** — pluggable provider abstraction with **Cloudflare Turnstile** and **Google reCAPTCHA v3** built in (since 1.2.0)
- **Filament settings UI** — super-admin-only page at `/app/registration-settings` for toggling CAPTCHA provider/keys without touching `.env` (since 1.3.0)
- **Email verification** — opt-in `MustVerifyEmail` integration with a themed "check your email" page and resend support (since 1.2.0)
- **First-site onboarding** — verified users with no sites are auto-redirected to the Multisite Template Gallery on entering the panel (since 1.2.0)
- Honeypot spam protection
- Layered rate limiting (30/min IP pre-CAPTCHA, 5/min post-validation, 1/min per-user resend)
- Automatic role assignment (configurable)
- Optional multisite integration (auto-assigns default site plan)

## Installation

### Via Plugin Package Command (recommended)

If you have the plugin source in your `plugins/` directory (e.g., from cloning the repo):

```bash
# Package the plugin into a clean ZIP
php artisan plugin:package registration

# Then upload the ZIP via Admin > System > Plugins
```

This creates a flat, validator-compliant ZIP — no `.DS_Store`, no nested directories, no development files. Migrations run automatically on install.

> **Note:** GitHub release ZIPs won't work directly because they nest files inside a subdirectory. Always use `plugin:package` or `git archive` to create uploadable ZIPs.

### Via Manual Copy

Copy (or symlink) the plugin into your TallCMS `plugins/tallcms/registration/` directory and clear the plugin cache:

```bash
php artisan cache:clear
```

TallCMS discovers the plugin automatically — no Composer require or service provider registration needed.

### Via `git archive` (from this repo)

```bash
git clone https://github.com/tallcms/user-registration-plugin.git
cd user-registration-plugin
git archive --format=zip HEAD -o registration.zip
```

The resulting ZIP can be uploaded through **Admin > System > Plugins**.

## Configuration

The plugin works out of the box with sensible defaults (no config file needed). To override them, create `config/registration.php` in your app root:

```php
return [
    'enabled' => true,                 // Toggle registration on/off (404 when false)
    'default_role' => 'site_owner',    // Spatie role assigned to new users
    'redirect_after' => null,          // null = auto-resolve (OnboardingResolver → Filament panel); set a string to force

    'captcha' => [
        'enabled' => null,             // null = auto: enabled iff site_key + secret_key are present; explicit true/false wins
        'provider' => 'turnstile',     // 'turnstile' | 'recaptcha_v3'
        'site_key' => '',
        'secret_key' => '',
        'recaptcha_min_score' => 0.5,  // reCAPTCHA v3 only; tokens below this score are rejected
    ],

    'email_verification' => [
        'enabled' => false,            // Require email verification before panel access
    ],

    'onboarding' => [
        'enabled' => true,             // Auto-redirect verified zero-site users to the Multisite Template Gallery
        'redirect_url' => null,        // Override the default `/app/template-gallery` target if needed
    ],
];
```

Or via environment:

```env
REGISTRATION_DEFAULT_ROLE=site_owner
REGISTRATION_REDIRECT_AFTER=/dashboard

# Email verification
REGISTRATION_EMAIL_VERIFICATION=true

# CAPTCHA (Cloudflare Turnstile)
REGISTRATION_CAPTCHA_ENABLED=true
REGISTRATION_CAPTCHA_PROVIDER=turnstile
REGISTRATION_CAPTCHA_SITE_KEY=
REGISTRATION_CAPTCHA_SECRET_KEY=

# CAPTCHA (Google reCAPTCHA v3 — alternative)
# REGISTRATION_CAPTCHA_PROVIDER=recaptcha_v3
# REGISTRATION_CAPTCHA_RECAPTCHA_MIN_SCORE=0.5

# Onboarding
REGISTRATION_ONBOARDING_ENABLED=true
REGISTRATION_ONBOARDING_REDIRECT_URL=
```

`redirect_after` defaults to the OnboardingResolver result for authenticated users (Template Gallery for new zero-site users, panel default otherwise), then to Filament's default panel URL. Set it explicitly only if you need to override the auto-resolution.

### Available roles

TallCMS seeds these roles by default:

| Role | Intended use |
| --- | --- |
| `site_owner` | **SaaS default.** Manages their own site end-to-end: pages, posts, menus, comments, form submissions, media. Records scoped to their own site(s) via policy. |
| `author` | Legacy. Writes blog posts, submits for review. Does not manage pages, comments, or submissions. |
| `editor` | Multi-site editorial staff. Pages + posts, submit for review, no approval. |
| `administrator` | Full content management + approval. |
| `super_admin` | Full access, bypasses all scoping. |

If the configured role doesn't exist, registration will abort safely without creating a user.

## Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/register` | Registration form |
| POST | `/register/submit` | Form submission |
| POST | `/register/resend-verification` | Resend email verification (auth, 1/min/user) |
| GET | `/registered` | Success/welcome page (or "check your email" when verification is pending) |

These are public routes loaded by TallCMS's plugin system. The plugin system automatically applies `web` and `throttle:60,1` middleware.

## How It Works

1. Visitor fills out the registration form at `/register`
2. Honeypot → pre-CAPTCHA throttle (30/min/IP) → CAPTCHA verification (if enabled) → validation → 5/min post-validation throttle
3. The plugin creates a user, assigns the configured role, dispatches `Registered`, and logs them in
4. **If email verification is on**, the user is redirected to `/registered` where a "check your email" page lets them resend the verification link. After clicking the link, Filament's verification controller activates the account and the panel-mounted `EnsureOnboardingRedirect` middleware steers them to the Template Gallery
5. **If email verification is off**, the user is created with `email_verified_at = now()` (no email is sent) and redirected straight to the onboarding URL

## CAPTCHA

The plugin ships with a small provider abstraction (`Tallcms\Registration\Captcha\Contracts\CaptchaProvider`) and two ready-made implementations:

- **Cloudflare Turnstile** (default) — privacy-friendly, free, low UX friction. Get keys at https://dash.cloudflare.com/?to=/:account/turnstile.
- **Google reCAPTCHA v3** — score-based and invisible. Configure the minimum score to tune the rejection threshold.

The widget script and form field are injected into the form view automatically; verification happens server-side via Laravel's `Http` client (no extra composer dependency). When CAPTCHA is disabled or unkeyed, the plugin falls back to a no-op `NullCaptchaProvider`.

### Filament settings UI (1.3.0+)

Register the Filament plugin on your panel:

```php
use Tallcms\Registration\Filament\RegistrationPlugin;

$panel->plugins([
    // ...
    RegistrationPlugin::make(),
]);
```

Super admins (Spatie role `super_admin`) get a **Registration & CAPTCHA** page in the System nav group. From there:

- Toggle CAPTCHA on/off
- Pick the provider (Turnstile or reCAPTCHA v3)
- Paste the **site key** (public, stored in DB)
- Tune the reCAPTCHA min score
- See whether the **secret key** is configured (the page never displays or accepts the secret — it stays in `.env`)
- Hit **Test verification** to confirm the provider is reachable and your keys are valid

### Storage model

| Setting | Where it lives | Editable in UI? |
|---------|----------------|-----------------|
| `captcha_enabled` | DB (with env fallback) | ✓ |
| `captcha_provider` | DB (with env fallback) | ✓ |
| `captcha_site_key` | DB (with env fallback) | ✓ |
| `captcha_recaptcha_min_score` | DB (with env fallback) | ✓ |
| `captcha_secret_key` | DB (encrypted) (with env fallback) | ✓ |

DB values take precedence over `config/registration.php`, which takes precedence over env defaults. The secret key is encrypted at rest using your Laravel `APP_KEY` and never appears in the cache layer or in the form input pre-fill (the input is always blank; leaving it blank on save keeps the existing secret untouched). Use the **Clear saved secret** button in the page header to wipe the DB-stored secret and fall back to the env value (or to nothing).

### Adding a new provider

To add e.g. hCaptcha, implement `CaptchaProvider`, add a `match` arm in `CaptchaManager::resolve()`, and extend the provider `Select` options in `RegistrationSettings::getFormSchema()`.

### Env-only configuration (no UI)

You can skip the Filament plugin and configure entirely via env:

```env
REGISTRATION_CAPTCHA_ENABLED=true
REGISTRATION_CAPTCHA_PROVIDER=turnstile
REGISTRATION_CAPTCHA_SITE_KEY=...
REGISTRATION_CAPTCHA_SECRET_KEY=...
```

The DB merge layer is no-op when no settings rows exist, so env-only deploys work unchanged.

## Email Verification

When `REGISTRATION_EMAIL_VERIFICATION=true`:

1. Your `User` model must implement `Illuminate\Contracts\Auth\MustVerifyEmail`.
2. Your Filament panel provider should call `->emailVerification(isRequired: fn () => (bool) config('registration.email_verification.enabled'))` so the gate honours the same toggle.
3. Add `Tallcms\Registration\Http\Middleware\EnsureOnboardingRedirect::class` to the panel's middleware chain — Filament's verification controller redirects to the panel root, and this middleware then auto-redirects new zero-site users to the Template Gallery.

The plugin re-routes Laravel's stock `VerifyEmail` notification through Filament's URL generator (`Filament::getVerifyEmailUrl(...)`), so verification links land on the panel's verification controller instead of the non-existent `verification.verify` route.

### Backfilling existing users

Adding `MustVerifyEmail` to a User model with existing rows would lock those users out (their `email_verified_at` is NULL). Before flipping `REGISTRATION_EMAIL_VERIFICATION=true` in a non-fresh install, run:

```bash
php artisan tallcms:registration-backfill-verified
```

It marks every user with NULL `email_verified_at` as verified now. Idempotent. Use `--force` for non-interactive use (CI, deploy scripts).

## Onboarding

When the multisite plugin is installed, the plugin auto-redirects newly verified users with no sites to the Template Gallery so the first thing they do is pick a template. The redirect happens at the panel root (e.g. `/app`) via `EnsureOnboardingRedirect` middleware and stops as soon as the user has at least one site (`SitePlanService::siteCount($user) > 0`).

Override the redirect target with `REGISTRATION_ONBOARDING_REDIRECT_URL=/somewhere`. Disable the off-ramp entirely with `REGISTRATION_ONBOARDING_ENABLED=false`.

## Plugin development

The canonical source for this plugin lives at `/Users/dan/Herd/tallcms-user-registration-plugin/`. When iterating in a host project (e.g. `push.sg`), edit there and rsync into the host's `plugins/tallcms/registration/` mirror — TallCMS does not ship a `plugin:update` command:

```bash
rsync -a --delete --exclude='.git' --exclude='*.zip' --exclude='.gitignore' \
  /Users/dan/Herd/tallcms-user-registration-plugin/ \
  /path/to/host/plugins/tallcms/registration/
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

## What This Plugin Does NOT Do

This plugin handles **user creation, CAPTCHA, verification, and first-site onboarding**. It does not manage panel permissions, MFA, or profile editing. Those are all handled by Filament and Laravel's built-in features, described below.

## Filament Panel Access

After registration, users land on whichever path your default Filament panel is mounted at (e.g. `/admin`, `/app`). Whether they can get in is decided by your panel's auth/verification middleware. With `->emailVerification(isRequired: fn () => …)` wired up, unverified users are bounced to the verification prompt; once verified, the plugin's middleware steers first-time users to the Template Gallery.

If your `User` model defines `canAccessPanel()`, that's the final gate. The plugin assigns the configured role (default `site_owner`) so role-based gates pass out of the box.

### What new users can do

The `site_owner` role grants full management of their own site:

- Create, edit, and delete pages and posts (with publishing workflow + revisions + preview)
- Manage categories, menus, media
- Moderate comments (approve / reject / mark as spam)
- View and manage contact form submissions
- Access the Template Gallery to spin up new sites

All records are scoped to the user's own site(s) via `ChecksSiteOwnership` policy layer — a site_owner cannot see or act on another site's records.

To give new users more or fewer permissions, either change `default_role` in the config or adjust the role's permissions via Filament Shield.

## Multi-Factor Authentication

MFA is configured on the Filament panel, not in this plugin. TallCMS ships with TOTP app-based MFA enabled by default:

```php
->multiFactorAuthentication([
    AppAuthentication::make(),
])
```

Users set up MFA from their account page after their first login (under your panel's path, e.g. `/admin/account` or `/app/account`). No plugin configuration is needed.

## Password Reset

Password reset is handled by Filament's built-in flow (`->passwordReset()` on the panel). This plugin does not duplicate that functionality — the registration form links to your panel's login URL for users who already have accounts.

## Multisite Integration

If the [multisite plugin](https://github.com/tallcms/multisite-plugin) is installed, this plugin automatically assigns the default site plan to new users via `SitePlanService::ensureAssignment()`. This is race-safe and uses a unique constraint to prevent duplicate assignments.

If multisite is not installed, this listener is never registered — no errors, no performance impact.

## Security

- **Honeypot field** — hidden form field that rejects bot submissions silently (returns a fake success response)
- **Rate limiting** — 5 successful registrations per minute per IP, enforced in the controller. Validation failures (typos, duplicate emails) do not count against the limit.
- **CSRF protection** — standard Laravel CSRF token on the form
- **Transaction safety** — user creation and role assignment are wrapped in a database transaction. If role assignment fails (e.g., misconfigured role), the user record is rolled back.
- **Role existence check** — the configured role is verified before user creation. A missing role aborts with a 500 error rather than creating an orphaned user.

## Theming

The registration and success pages extend `tallcms::layouts.app`, so they automatically use your active theme's layout, navbar, footer, and styling. The forms use DaisyUI classes and adapt to light/dark theme switching.

To override the views, place your custom templates in your theme:

```
themes/{your-theme}/resources/views/vendor/tallcms-registration/register.blade.php
themes/{your-theme}/resources/views/vendor/tallcms-registration/registered.blade.php
```

## License

MIT — see [LICENSE](LICENSE).
