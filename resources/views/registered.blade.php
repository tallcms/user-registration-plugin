@extends('tallcms::layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center py-24 px-4 sm:px-6 lg:px-8">
    <div class="card bg-base-200 shadow-xl w-full max-w-md">
        <div class="card-body text-center">
            <div class="flex justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <h2 class="card-title text-2xl font-bold justify-center mb-2">Account Created</h2>
            <p class="text-base-content/70 mb-6">
                Your account has been created. You can now access the admin panel to create and manage your site.
            </p>

            <a href="{{ $redirect_url }}" class="btn btn-primary w-full">
                Go to Admin Panel
            </a>
        </div>
    </div>
</div>
@endsection
