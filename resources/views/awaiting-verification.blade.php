@extends('tallcms::layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center py-24 px-4 sm:px-6 lg:px-8">
    <div class="card bg-base-200 shadow-xl w-full max-w-md">
        <div class="card-body text-center">
            <div class="flex justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>

            <h2 class="card-title text-2xl font-bold justify-center mb-2">Check your email</h2>
            <p class="text-base-content/70 mb-2">
                We sent a verification link to
            </p>
            <p class="font-mono text-sm mb-6 break-all">{{ $masked_email }}</p>
            <p class="text-base-content/60 text-sm mb-6">
                Click the link in that email to activate your account. You'll then be taken to set up your first site.
            </p>

            @if (session('resend_success'))
                <div class="alert alert-success mb-4 text-sm">
                    <span>Verification email sent again. Check your inbox.</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error mb-4 text-sm">
                    <div>
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ url('/register/resend-verification') }}" class="mb-3">
                @csrf
                <button type="submit" class="btn btn-primary w-full">
                    Resend verification email
                </button>
            </form>

            <form method="POST" action="{{ url('/logout') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm w-full text-base-content/60">
                    Log out
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
