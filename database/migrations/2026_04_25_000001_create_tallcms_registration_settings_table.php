<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent — TallCMS's plugin:install runs this migration via its
        // own plugin_migrations tracker, while Laravel's standard `migrate`
        // also discovers it through loadMigrationsFrom. Without this guard,
        // the second runner blows up on a duplicate-table error.
        if (Schema::hasTable('tallcms_registration_settings')) {
            return;
        }

        Schema::create('tallcms_registration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tallcms_registration_settings');
    }
};
