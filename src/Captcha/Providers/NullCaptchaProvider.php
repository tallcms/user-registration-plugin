<?php

namespace Tallcms\Registration\Captcha\Providers;

use Tallcms\Registration\Captcha\Contracts\CaptchaProvider;

class NullCaptchaProvider implements CaptchaProvider
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function tokenField(): string
    {
        return '_captcha_token';
    }

    public function renderSnippet(): string
    {
        return '';
    }

    public function verify(string $token, ?string $ip): bool
    {
        return true;
    }
}
