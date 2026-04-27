<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Registration Legacy Redirect
|--------------------------------------------------------------------------
|
| In v1.x this file registered /register, /register/submit, etc. against a
| Laravel controller. v2.0.0 replaces that with Filament's native register
| page (provided by tallcms/filament-registration). The only thing this
| file does now is 301 the old /register URL into the panel's register URL
| so existing bookmarks and external links keep working.
|
| The host adds ['web', 'throttle:60,1'] middleware and the name prefix
| plugin.tallcms.registration.* automatically — the route name below is
| just `legacy-redirect`, becoming `plugin.tallcms.registration.legacy-redirect`
| at runtime.
|
*/

Route::get('/register', function () {
    try {
        $panelId = config('tallcms.filament.panel_id', 'admin');
        $panel = Filament::getPanel($panelId);

        return redirect($panel->getRegistrationUrl(), 301);
    } catch (\Throwable $e) {
        // Panel not yet bound or registration disabled — fall back to a
        // sensible default so we never 500 on the redirect.
        $path = config('tallcms.filament.panel_path', 'admin');

        return redirect('/'.ltrim($path, '/').'/register', 301);
    }
})->name('legacy-redirect');
