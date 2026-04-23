# Registration Plugin

Frontend user registration for TallCMS. Adds a themed `/register` page that creates users with the `site_owner` role (or any role you configure) and redirects them to the admin panel.

> **Requires TallCMS ≥ 4.0.15** for the default `site_owner` role.
> TallCMS 4.0.15 auto-syncs the `site_owner` role during `tallcms:update`.
> On older installs, run `php artisan tallcms:shield-sync-site-owner` once before enabling registration, or set `REGISTRATION_DEFAULT_ROLE=author` in `.env` to use the legacy role.

## Features

- Themed registration form using your active theme's layout
- Honeypot spam protection
- Rate limiting (5 registrations/minute per IP)
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
    'redirect_after' => '/admin',      // Where the success page links to
];
```

Or via environment:

```env
REGISTRATION_DEFAULT_ROLE=site_owner
```

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
| GET | `/registered` | Success/welcome page |

These are public routes loaded by TallCMS's plugin system. The plugin system automatically applies `web` and `throttle:60,1` middleware.

## How It Works

1. Visitor fills out the registration form at `/register`
2. The plugin creates a user, assigns the configured role, and logs them in
3. The visitor lands on a themed success page (`/registered`) with a "Go to Admin Panel" button
4. They click through to `/admin` where Filament takes over

## What This Plugin Does NOT Do

This plugin handles **user creation only**. It does not manage email verification, panel permissions, MFA, or profile editing. Those are all handled by Filament and Laravel's built-in features, described below.

## Filament Panel Access

After registration, users access the admin panel at `/admin`. Whether they can get in depends on TallCMS's `User::canAccessPanel()` method, which checks two things:

1. **`is_active`** — the user must be active (the plugin sets this to `true` on creation)
2. **Has at least one role** — the plugin assigns the configured role (default: `site_owner`)

So out of the box, newly registered users can access the panel immediately.

### What new users can do

The `site_owner` role grants full management of their own site:

- Create, edit, and delete pages and posts (with publishing workflow + revisions + preview)
- Manage categories, menus, media
- Moderate comments (approve / reject / mark as spam)
- View and manage contact form submissions
- Access the Template Gallery to spin up new sites

All records are scoped to the user's own site(s) via `ChecksSiteOwnership` policy layer — a site_owner cannot see or act on another site's records.

To give new users more or fewer permissions, either change `default_role` in the config or adjust the role's permissions via Filament Shield.

### Deactivating users

Set `is_active` to `false` on a user record to lock them out of the panel entirely, regardless of their roles. This can be done from the Users resource in the admin panel.

## Email Verification

This plugin **does not** include email verification. Users can log in immediately after registration.

If you want to require email verification, this is handled entirely through Laravel and Filament — no changes to this plugin are needed:

### 1. Implement `MustVerifyEmail` on your User model

```php
// app/Models/User.php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, MustVerifyEmail
{
    // ...
}
```

### 2. Enable email verification on the Filament panel

```php
// app/Providers/Filament/AdminPanelProvider.php
return $panel
    ->login()
    ->passwordReset()
    ->emailVerification()   // Add this line
    // ...
```

With both of these in place, Laravel will send a verification email when the `Registered` event fires (which this plugin already dispatches), and Filament will block unverified users from the panel until they verify.

## Multi-Factor Authentication

MFA is configured on the Filament panel, not in this plugin. TallCMS ships with TOTP app-based MFA enabled by default:

```php
->multiFactorAuthentication([
    AppAuthentication::make(),
])
```

Users set up MFA from their profile page at `/admin/account` after their first login. No plugin configuration is needed.

## Password Reset

Password reset is handled by Filament's built-in flow at `/admin/forgot-password`. This plugin does not duplicate that functionality — the registration form links to `/admin/login` for users who already have accounts.

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
