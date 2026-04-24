<?php

namespace Tallcms\Registration\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Tallcms\Registration\Captcha\Contracts\CaptchaProvider;
use Tallcms\Registration\Services\OnboardingResolver;

class RegisterController extends Controller
{
    public function __construct(
        private readonly CaptchaProvider $captcha,
        private readonly OnboardingResolver $onboarding,
    ) {}

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
            'captcha' => $this->captcha,
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

        // Honeypot — cheapest check, silently fake-succeed for bots.
        if (! empty($request->input('_honeypot'))) {
            return redirect('/')->with('success', true);
        }

        // Pre-CAPTCHA throttle caps outbound calls to the CAPTCHA vendor so bot
        // floods can't turn this endpoint into an amplifier against them.
        $attemptKey = 'registration-attempt:'.$request->ip();

        if (RateLimiter::tooManyAttempts($attemptKey, 30)) {
            return back()
                ->withInput($request->only('name', 'email'))
                ->withErrors(['email' => 'Too many registration attempts. Please try again in a minute.']);
        }

        RateLimiter::hit($attemptKey, 60);

        // CAPTCHA verification before validation so we don't burn the 5/min
        // registration budget of legit users sharing an IP.
        if ($this->captcha->isEnabled()) {
            $token = (string) $request->input($this->captcha->tokenField(), '');

            if (! $this->captcha->verify($token, $request->ip())) {
                return back()
                    ->withInput($request->only('name', 'email'))
                    ->withErrors(['captcha' => 'Bot check failed. Please try again.']);
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Post-validation rate limit (5/min per IP); typos don't cost users.
        $key = 'registration:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withInput($request->only('name', 'email'))
                ->withErrors(['email' => 'Too many registration attempts. Please try again in a minute.']);
        }

        $roleName = config('registration.default_role', 'author');

        if (! Role::where('name', $roleName)->where('guard_name', 'web')->exists()) {
            abort(500, 'Registration is misconfigured: role "'.$roleName.'" does not exist.');
        }

        RateLimiter::hit($key, 60);

        $verificationRequired = (bool) config('registration.email_verification.enabled', false);

        $user = DB::transaction(function () use ($validated, $roleName, $verificationRequired) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            // When verification is off, pre-mark the user verified so Laravel's
            // SendEmailVerificationNotification listener short-circuits and no
            // mail is dispatched. This is the single config gate that keeps
            // the feature truly opt-in with MustVerifyEmail permanently on.
            if (! $verificationRequired && $user instanceof MustVerifyEmail) {
                $user->markEmailAsVerified();
            }

            $user->assignRole($roleName);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        if (
            $verificationRequired
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
        ) {
            return redirect(url('/registered'))->with('awaiting_verification', true);
        }

        return redirect($this->redirectUrl());
    }

    public function registered(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect(url('/register'));
        }

        $user = Auth::user();
        $verificationRequired = (bool) config('registration.email_verification.enabled', false);

        $awaiting = $request->session()->get('awaiting_verification')
            || ($verificationRequired && $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail());

        if ($awaiting) {
            return view('tallcms-registration::awaiting-verification', [
                'masked_email' => $this->maskEmail($user->email),
            ]);
        }

        return view('tallcms-registration::registered', [
            'redirect_url' => $this->redirectUrl(),
        ]);
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof MustVerifyEmail) {
            return redirect(url('/registered'));
        }

        if ($user->hasVerifiedEmail()) {
            return redirect($this->redirectUrl());
        }

        $key = 'registration-resend:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return redirect(url('/registered'))
                ->withErrors(['resend' => 'Please wait a minute before requesting another email.']);
        }

        RateLimiter::hit($key, 60);

        $user->sendEmailVerificationNotification();

        return redirect(url('/registered'))->with('resend_success', true);
    }

    protected function redirectUrl(): string
    {
        if (Auth::check()) {
            $target = $this->onboarding->resolveFor(Auth::user());

            if ($target !== null) {
                return $target;
            }
        }

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

        return url('/app');
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

        return url('/app/login');
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return $email;
        }

        $maskedLocal = strlen($local) <= 2
            ? str_repeat('•', strlen($local))
            : $local[0].str_repeat('•', max(1, strlen($local) - 2)).$local[strlen($local) - 1];

        return $maskedLocal.'@'.$domain;
    }
}
