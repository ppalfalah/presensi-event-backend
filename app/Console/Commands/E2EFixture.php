<?php

namespace App\Console\Commands;

use App\Support\E2E\DashboardFixtureManager;
use App\Support\E2E\E2EEnvironmentGuard;
use Illuminate\Console\Command;

class E2EFixture extends Command
{
    protected $signature = 'e2e:fixture {state : Dashboard fixture state}';

    protected $description = 'Prepare a deterministic fixture in the dedicated E2E database';

    public function handle(E2EEnvironmentGuard $guard, DashboardFixtureManager $fixtures): int
    {
        try {
            $guard->assertSafe();
            $summary = $fixtures->prepare((string) $this->argument('state'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Fixture', 'Alumni', 'Events', 'Attendances'], [[
            $summary['state'],
            $summary['alumni'],
            $summary['events'],
            $summary['attendances'],
        ]]);
        $this->info('E2E dashboard fixture prepared.');

        return self::SUCCESS;
    }
}
