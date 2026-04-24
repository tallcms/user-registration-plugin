<?php

namespace Tallcms\Registration\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function showForm(Request $request): View|RedirectResponse
    {
        if (! config('registration.enabled', true)) {
            abort(404);
        }

        if (Auth::check()) {
            return redirect($this->redirectUrl());
        }

        return view('tallcms-registration::register', [
            'login_url' => $this->loginUrl(),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        if (! config('registration.enabled', true)) {
            abort(404);
        }

        if (Auth::check()) {
            return redirect($this->redirectUrl());
        }

        // Honeypot — check before validation, return fake success
        if (! empty($request->input('_honeypot'))) {
            return redirect('/')->with('success', true);
        }

        // Rate limit — 5 registrations per minute per IP
        $key = 'registration:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withInput($request->only('name', 'email'))
                ->withErrors(['email' => 'Too many registration attempts. Please try again in a minute.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Verify the configured role exists before creating the user
        $roleName = config('registration.default_role', 'author');

        if (! Role::where('name', $roleName)->where('guard_name', 'web')->exists()) {
            abort(500, 'Registration is misconfigured: role "'.$roleName.'" does not exist.');
        }

        // Hit rate limiter after validation passes — typos and weak passwords
        // don't count against the cap, only real registration attempts do
        RateLimiter::hit($key, 60);

        $user = DB::transaction(function () use ($validated, $roleName) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_active' => true,
            ]);

            $user->assignRole($roleName);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(url('/registered'));
    }

    public function registered(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect($this->redirectUrl());
        }

        return view('tallcms-registration::registered', [
            'redirect_url' => $this->redirectUrl(),
        ]);
    }

    protected function redirectUrl(): string
    {
        if ($configured = config('registration.redirect_after')) {
            return $configured;
        }

        if (class_exists(Filament::class)) {
            try {
                return Filament::getDefaultPanel()->getUrl();
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return url('/admin');
    }

    protected function loginUrl(): string
    {
        if (class_exists(Filament::class)) {
            try {
                return Filament::getDefaultPanel()->getLoginUrl();
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return url('/admin/login');
    }
}
