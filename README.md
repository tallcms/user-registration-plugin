# Registration Plugin

Frontend user registration for TallCMS. Adds a themed `/register` page that creates users with the `author` role and redirects them to the admin panel.

## Features

- Themed registration form using your active theme's layout
- Honeypot spam protection
- Rate limiting (5 registrations/minute per IP)
- Automatic role assignment (configurable)
- Optional multisite integration (auto-assigns default site plan)

## Installation

Copy (or symlink) this plugin into your TallCMS `plugins/tallcms/registration/` directory and clear the plugin cache:

```bash
php artisan cache:clear
```

TallCMS discovers the plugin automatically — no Composer require or service provider registration needed.

## Configuration

The plugin works out of the box with sensible defaults (no config file needed). To override them, create `config/registration.php` in your app root:

```php
return [
    'enabled' => true,           // Toggle registration on/off (404 when false)
    'default_role' => 'author',  // Spatie role assigned to new users
    'redirect_after' => '/admin', // Where the success page links to
];
```

The `default_role` must be a role that already exists in your `roles` table. TallCMS seeds `super_admin`, `administrator`, `editor`, and `author` by default. If the configured role doesn't exist, registration will abort safely without creating a user.

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
2. **Has at least one role** — the plugin assigns the configured role (default: `author`)

So out of the box, newly registered users can access the panel immediately.

### What new users can do

The `author` role grants:

- Create and edit pages and posts (no delete)
- View categories and menus (read-only)
- Upload and manage media
- View contact form submissions

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
