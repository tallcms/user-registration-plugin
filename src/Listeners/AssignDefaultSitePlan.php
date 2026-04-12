<?php

namespace Tallcms\Registration\Listeners;

use Illuminate\Auth\Events\Registered;
use Tallcms\Multisite\Services\SitePlanService;

class AssignDefaultSitePlan
{
    public function handle(Registered $event): void
    {
        if (! class_exists(SitePlanService::class)) {
            return;
        }

        app(SitePlanService::class)->ensureAssignment($event->user);
    }
}
