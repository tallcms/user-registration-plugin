<?php

namespace Tallcms\Registration\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tallcms\Registration\Models\RegistrationSetting;

/**
 * Wraps the tallcms_registration_settings key/value table.
 *
 * Stored values take precedence over config / env defaults. The secret
 * key is intentionally NOT writable here — it stays env-only and is
 * never persisted to the DB.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'tallcms.registration.settings';

    private const CACHE_TTL = 3600;

    /** Settings the UI is allowed to write. */
    public const WRITABLE_KEYS = [
        'captcha_enabled',
        'captcha_provider',
        'captcha_site_key',
        'captcha_recaptcha_min_score',
    ];

    public function all(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return RegistrationSetting::query()->pluck('value', 'key')->toArray();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public function setMany(array $values): void
    {
        if (! $this->tableExists()) {
            return;
        }

        foreach ($values as $key => $value) {
            if (! in_array($key, self::WRITABLE_KEYS, true)) {
                continue;
            }

            RegistrationSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('tallcms_registration_settings');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
