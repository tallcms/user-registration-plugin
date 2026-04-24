<?php

namespace Tallcms\Registration\Captcha\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tallcms\Registration\Captcha\Contracts\CaptchaProvider;

class RecaptchaV3CaptchaProvider implements CaptchaProvider
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
        private readonly float $minScore = 0.5,
    ) {}

    public function isEnabled(): bool
    {
        return $this->siteKey !== '' && $this->secretKey !== '';
    }

    public function tokenField(): string
    {
        return 'g-recaptcha-response';
    }

    public function renderSnippet(): string
    {
        $siteKey = e($this->siteKey);

        return <<<HTML
<script src="https://www.google.com/recaptcha/api.js?render={$siteKey}"></script>
<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
<script>
(function () {
    var form = document.currentScript.closest('form');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        if (form.dataset.captchaReady === '1') return;
        event.preventDefault();
        grecaptcha.ready(function () {
            grecaptcha.execute('{$siteKey}', { action: 'register' }).then(function (token) {
                document.getElementById('g-recaptcha-response').value = token;
                form.dataset.captchaReady = '1';
                form.submit();
            });
        });
    });
})();
</script>
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
                Log::debug('reCAPTCHA verify HTTP error', ['status' => $response->status()]);

                return false;
            }

            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                return false;
            }

            $score = (float) ($data['score'] ?? 0);

            return $score >= $this->minScore;
        } catch (\Throwable $e) {
            Log::debug('reCAPTCHA verify threw', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
