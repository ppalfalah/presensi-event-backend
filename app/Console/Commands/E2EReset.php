<?php

namespace App\Console\Commands;

use App\Support\E2E\E2EEnvironmentGuard;
use Database\Seeders\E2EDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class E2EReset extends Command
{
    protected $signature = 'e2e:reset';

    protected $description = 'Safely reset and seed the dedicated E2E database';

    public function handle(E2EEnvironmentGuard $guard): int
    {
        try {
            $guard->assertSafe();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $database = (string) config('e2e.database');
        $this->warn("Resetting dedicated E2E database: {$database}");

        $exitCode = $this->call('migrate:fresh', [
            '--database' => (string) config('database.default'),
            '--seeder' => E2EDatabaseSeeder::class,
            '--force' => true,
        ]);

        if ($exitCode !== self::SUCCESS) {
            $this->error('E2E database reset failed.');

            return self::FAILURE;
        }

        $storageRoot = (string) config('e2e.public_storage_root');
        File::deleteDirectory($storageRoot);
        File::ensureDirectoryExists($storageRoot);

        $this->info('E2E database reset and baseline seed completed.');

        return self::SUCCESS;
    }
}
