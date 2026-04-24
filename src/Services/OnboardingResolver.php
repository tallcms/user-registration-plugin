<?php

namespace Tallcms\Registration\Services;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class OnboardingResolver
{
    public function resolveFor(User $user): ?string
    {
        if (! config('registration.onboarding.enabled', true)) {
            return null;
        }

        if (
            config('registration.email_verification.enabled', false)
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
        ) {
            return null;
        }

        $sitePlanServiceClass = \Tallcms\Multisite\Services\SitePlanService::class;

        if (! class_exists($sitePlanServiceClass)) {
            return null;
        }

        /** @var \Tallcms\Multisite\Services\SitePlanService $plans */
        $plans = app($sitePlanServiceClass);

        if ($plans->siteCount($user) > 0) {
            return null;
        }

        if (! $plans->canCreateSite($user)) {
            return null;
        }

        if ($configured = config('registration.onboarding.redirect_url')) {
            return $configured;
        }

        try {
            return route('filament.app.pages.template-gallery');
        } catch (\Throwable $e) {
            return url('/app/template-gallery');
        }
    }
}
