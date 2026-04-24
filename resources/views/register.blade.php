@extends('tallcms::layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center py-24 px-4 sm:px-6 lg:px-8">
    <div class="card bg-base-200 shadow-xl w-full max-w-md">
        <div class="card-body">
            <h2 class="card-title text-2xl font-bold justify-center mb-2">Create an Account</h2>
            <p class="text-base-content/70 text-center mb-6">Sign up to get started</p>

            @if ($errors->any())
                <div class="alert alert-error mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ url('/register/submit') }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
                @csrf

                <div class="form-control mb-4">
                    <label class="label" for="name">
                        <span class="label-text">Name</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        class="input input-bordered w-full @error('name') input-error @enderror"
                        required
                        autofocus
                    >
                </div>

                <div class="form-control mb-4">
                    <label class="label" for="email">
                        <span class="label-text">Email</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="input input-bordered w-full @error('email') input-error @enderror"
                        required
                    >
                </div>

                <div class="form-control mb-4">
                    <label class="label" for="password">
                        <span class="label-text">Password</span>
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="input input-bordered w-full @error('password') input-error @enderror"
                        required
                        minlength="8"
                    >
                </div>

                <div class="form-control mb-6">
                    <label class="label" for="password_confirmation">
                        <span class="label-text">Confirm Password</span>
                    </label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="input input-bordered w-full"
                        required
                        minlength="8"
                    >
                </div>

                {{-- Honeypot --}}
                <div class="hidden" aria-hidden="true">
                    <label for="reg-website">Website</label>
                    <input type="text" id="reg-website" name="_honeypot" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-control mt-6">
                    <button
                        type="submit"
                        class="btn btn-primary w-full"
                        x-bind:disabled="submitting"
                    >
                        <span x-show="!submitting">Create Account</span>
                        <span x-show="submitting" x-cloak class="inline-flex items-center">
                            <span class="loading loading-spinner loading-sm mr-2"></span>
                            Creating account...
                        </span>
                    </button>
                </div>
            </form>

            <div class="divider">OR</div>

            <p class="text-center text-sm text-base-content/70">
                Already have an account?
                <a href="{{ $login_url }}" class="link link-primary">Sign in</a>
            </p>
        </div>
    </div>
</div>
@endsection
