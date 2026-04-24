<?php

namespace Tallcms\Registration\Captcha;

use Illuminate\Support\Facades\Log;
use Tallcms\Registration\Captcha\Contracts\CaptchaProvider;
use Tallcms\Registration\Captcha\Providers\NullCaptchaProvider;
use Tallcms\Registration\Captcha\Providers\RecaptchaV3CaptchaProvider;
use Tallcms\Registration\Captcha\Providers\TurnstileCaptchaProvider;

class CaptchaManager
{
    public function resolve(): CaptchaProvider
    {
        $siteKey = (string) config('registration.captcha.site_key', '');
        $secretKey = (string) config('registration.captcha.secret_key', '');

        if (! self::resolveEnabled($siteKey, $secretKey)) {
            return new NullCaptchaProvider;
        }

        if ($siteKey === '' || $secretKey === '') {
            if (app()->isProduction()) {
                Log::warning('Registration CAPTCHA enabled but site_key or secret_key is missing — falling back to NullCaptchaProvider.');
            }

            return new NullCaptchaProvider;
        }

        $provider = (string) config('registration.captcha.provider', 'turnstile');

        return match ($provider) {
            'recaptcha_v3' => new RecaptchaV3CaptchaProvider(
                $siteKey,
                $secretKey,
                (float) config('registration.captcha.recaptcha_min_score', 0.5),
            ),
            default => new TurnstileCaptchaProvider($siteKey, $secretKey),
        };
    }

    /**
     * The `captcha.enabled` truth table:
     * - Explicit env REGISTRATION_CAPTCHA_ENABLED wins when set (truthy/falsy).
     * - When unset (null), auto-enable iff both site_key and secret_key are non-empty.
     */
    public static function resolveEnabled(string $siteKey, string $secretKey): bool
    {
        $explicit = config('registration.captcha.enabled');

        if ($explicit !== null) {
            return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }

        return $siteKey !== '' && $secretKey !== '';
    }
}
