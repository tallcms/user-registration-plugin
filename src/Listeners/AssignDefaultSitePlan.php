<?php

namespace Tallcms\Registration\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

class AssignDefaultSitePlan
{
    public function handle(Registered $event): void
    {
        if (! class_exists(\Tallcms\Multisite\Services\SitePlanService::class)) {
            return;
        }

        try {
            app(\Tallcms\Multisite\Services\SitePlanService::class)->ensureAssignment($event->user);
        } catch (\Throwable $e) {
            Log::warning('Registration: failed to assign default site plan', [
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
