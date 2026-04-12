# Registration Plugin

Frontend user registration for TallCMS. Adds a themed `/register` page that creates users with the `author` role and redirects them to the admin panel.

## Features

- Themed registration form using your active theme's layout
- Honeypot spam protection
- Rate limiting (5 registrations/minute per IP)
- Automatic role assignment (configurable)
- Optional multisite integration (auto-assigns default site plan)

## Configuration

Publish or override the config in your app:

```php
// config/registration.php
return [
    'enabled' => true,           // Toggle registration on/off
    'default_role' => 'author',  // Role assigned to new users
    'redirect_after' => '/admin', // Where the success page links to
];
```

## Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/register` | Registration form |
| POST | `/register` | Form submission |
| GET | `/registered` | Success page |

## Multisite Integration

If the multisite plugin is installed, new users are automatically assigned the default site plan via `SitePlanService::ensureAssignment()`.
