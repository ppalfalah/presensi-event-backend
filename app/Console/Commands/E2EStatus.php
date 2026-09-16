<?php

namespace App\Console\Commands;

use App\Support\E2E\E2EEnvironmentGuard;
use Illuminate\Console\Command;

class E2EStatus extends Command
{
    protected $signature = 'e2e:status';

    protected $description = 'Verify the active E2E environment and database';

    public function handle(E2EEnvironmentGuard $guard): int
    {
        try {
            $guard->assertSafe();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Setting', 'Active value'], [
            ['APP_ENV', app()->environment()],
            ['DB_CONNECTION', (string) config('database.default')],
            ['DB_DATABASE', (string) config('e2e.database')],
            ['APP_URL', (string) config('app.url')],
            ['PUBLIC_STORAGE', (string) config('filesystems.disks.public.root')],
        ]);
        $this->info('E2E environment safety checks passed.');

        return self::SUCCESS;
    }
}
