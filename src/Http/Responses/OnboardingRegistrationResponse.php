<?php

declare(strict_types=1);

namespace Tallcms\Registration\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Tallcms\Registration\Services\OnboardingResolver;

/**
 * Post-registration redirect for TallCMS users.
 *
 * If the user qualifies for an onboarding flow (no sites yet, can create
 * sites, etc.), redirect them there. Otherwise fall through to Filament's
 * default behaviour: panel home (or the intended URL if one was set).
 *
 * Bound in RegistrationServiceProvider against Filament's contract so the
 * generic plugin's default response is replaced for this host.
 *
 * Note: we construct `Illuminate\Http\RedirectResponse` directly rather than
 * calling `redirect(...)`. Inside a Livewire component (which Filament's
 * Register page is), the `redirect()` helper returns Livewire's
 * `SupportRedirects\Redirector` wrapper, which is not a Symfony Response
 * and would fail Filament's `Responsable::toResponse()` contract.
 */
class OnboardingRegistrationResponse implements RegistrationResponseContract
{
    public function __construct(protected OnboardingResolver $onboarding) {}

    public function toResponse($request): RedirectResponse
    {
        $target = $this->onboarding->resolveFor($request->user());

        if ($target !== null) {
            return new RedirectResponse($target);
        }

        $intended = $request->session()?->pull('url.intended');

        return new RedirectResponse($intended ?: Filament::getUrl());
    }
}
