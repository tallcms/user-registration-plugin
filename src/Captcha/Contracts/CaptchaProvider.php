<?php

namespace Tallcms\Registration\Captcha\Contracts;

interface CaptchaProvider
{
    public function isEnabled(): bool;

    public function tokenField(): string;

    public function renderSnippet(): string;

    public function verify(string $token, ?string $ip): bool;
}
