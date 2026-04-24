<?php

namespace Tallcms\Registration\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tallcms\Registration\Services\OnboardingResolver;

class EnsureOnboardingRedirect
{
    public function __construct(private readonly OnboardingResolver $resolver) {}

    public function handle(Request $request, Closure $next)
    {
        if ($request->method() !== 'GET' || ! Auth::check()) {
            return $next($request);
        }

        $panel = Filament::getCurrentPanel();

        if (! $panel) {
            return $next($request);
        }

        $panelPath = trim($panel->getPath(), '/');

        if ($request->path() !== $panelPath) {
            return $next($request);
        }

        $target = $this->resolver->resolveFor(Auth::user());

        if ($target === null) {
            return $next($request);
        }

        if (rtrim($target, '/') === rtrim($request->url(), '/')) {
            return $next($request);
        }

        return redirect($target);
    }
}
