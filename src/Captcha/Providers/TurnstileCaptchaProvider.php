<?php

namespace Tallcms\Registration\Captcha\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tallcms\Registration\Captcha\Contracts\CaptchaProvider;

class TurnstileCaptchaProvider implements CaptchaProvider
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
    ) {}

    public function isEnabled(): bool
    {
        return $this->siteKey !== '' && $this->secretKey !== '';
    }

    public function tokenField(): string
    {
        return 'cf-turnstile-response';
    }

    public function renderSnippet(): string
    {
        $siteKey = e($this->siteKey);

        return <<<HTML
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<div class="form-control mb-4">
    <div class="cf-turnstile" data-sitekey="{$siteKey}"></div>
</div>
HTML;
    }

    public function verify(string $token, ?string $ip): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));

            if (! $response->successful()) {
                Log::debug('Turnstile verify HTTP error', ['status' => $response->status()]);

                return false;
            }

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable $e) {
            Log::debug('Turnstile verify threw', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
