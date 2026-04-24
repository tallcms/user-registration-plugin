<?php

namespace Tallcms\Registration\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class BackfillVerifiedUsers extends Command
{
    protected $signature = 'tallcms:registration-backfill-verified
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Mark every existing user as email-verified (sets email_verified_at = now() where NULL). Run once before enabling email verification on a pre-existing install.';

    public function handle(): int
    {
        $count = User::query()->whereNull('email_verified_at')->count();

        if ($count === 0) {
            $this->info('No users with NULL email_verified_at. Nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Mark {$count} user(s) as email-verified (set email_verified_at = now())?")) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $updated = User::query()->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        $this->info("Backfilled email_verified_at on {$updated} user(s).");

        return self::SUCCESS;
    }
}
