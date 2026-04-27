<?php

use Illuminate\Database\Migrations\Migration;

/**
 * No-op migration (kept under its original filename so existing installs
 * don't try to re-run it).
 *
 * History: this migration originally created the
 * `tallcms_registration_settings` table for v1.x of this plugin. In v2.0.0
 * the table moved to the generic `tallcms/filament-registration` package,
 * which ships its own idempotent migration that creates the same table when
 * absent and skips it when present.
 *
 * Behaviour:
 *   - Fresh installs of v2.0.0: the generic plugin's migration creates the
 *     table; this migration runs as a no-op.
 *   - Upgraded installs from v1.x: the table already exists from this
 *     migration's prior run; both this no-op and the generic plugin's
 *     idempotent migration leave it untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // intentionally empty — see class doc above
    }

    public function down(): void
    {
        // intentionally empty
    }
};
