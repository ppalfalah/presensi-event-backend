<?php

namespace App\Console\Commands;

use App\Support\E2E\DashboardFixtureManager;
use App\Support\E2E\E2EEnvironmentGuard;
use App\Support\E2E\UserManagementFixtureManager;
use Illuminate\Console\Command;

class E2EFixture extends Command
{
    protected $signature = 'e2e:fixture {state : E2E fixture state}';

    protected $description = 'Prepare a deterministic fixture in the dedicated E2E database';

    public function handle(
        E2EEnvironmentGuard $guard,
        DashboardFixtureManager $dashboardFixtures,
        UserManagementFixtureManager $userFixtures,
    ): int {
        try {
            $guard->assertSafe();
            $state = (string) $this->argument('state');
            $summary = match (true) {
                in_array($state, DashboardFixtureManager::STATES, true) => $dashboardFixtures->prepare($state),
                in_array($state, UserManagementFixtureManager::STATES, true) => $userFixtures->prepare($state),
                default => throw new \InvalidArgumentException("Unknown E2E fixture state: {$state}"),
            };
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
        $this->info('E2E fixture prepared.');

        return self::SUCCESS;
    }
}
