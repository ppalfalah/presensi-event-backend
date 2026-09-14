<?php

namespace App\Console\Commands;

use App\Support\E2E\DashboardFixtureManager;
use App\Support\E2E\E2EEnvironmentGuard;
use App\Support\E2E\EventManagementFixtureManager;
use App\Support\E2E\PhaseElevenFixtureManager;
use App\Support\E2E\PhaseNineFixtureManager;
use App\Support\E2E\PhaseTenFixtureManager;
use App\Support\E2E\PhaseThirteenFixtureManager;
use App\Support\E2E\PhaseTwelveFixtureManager;
use App\Support\E2E\ReportFixtureManager;
use App\Support\E2E\UserManagementFixtureManager;
use Illuminate\Console\Command;

class E2EFixture extends Command
{
    protected $signature = 'e2e:fixture {state : E2E fixture state}';

    protected $description = 'Prepare a deterministic fixture in the dedicated E2E database';

    public function handle(
        E2EEnvironmentGuard $guard,
        DashboardFixtureManager $dashboardFixtures,
        EventManagementFixtureManager $eventFixtures,
        PhaseElevenFixtureManager $phaseElevenFixtures,
        PhaseNineFixtureManager $phaseNineFixtures,
        PhaseTenFixtureManager $phaseTenFixtures,
        PhaseThirteenFixtureManager $phaseThirteenFixtures,
        PhaseTwelveFixtureManager $phaseTwelveFixtures,
        ReportFixtureManager $reportFixtures,
        UserManagementFixtureManager $userFixtures,
    ): int {
        try {
            $guard->assertSafe();
            $state = (string) $this->argument('state');
            $summary = match (true) {
                in_array($state, DashboardFixtureManager::STATES, true) => $dashboardFixtures->prepare($state),
                in_array($state, EventManagementFixtureManager::STATES, true) => $eventFixtures->prepare($state),
                in_array($state, PhaseElevenFixtureManager::STATES, true) => $phaseElevenFixtures->prepare($state),
                in_array($state, PhaseNineFixtureManager::STATES, true) => $phaseNineFixtures->prepare($state),
                in_array($state, PhaseTenFixtureManager::STATES, true) => $phaseTenFixtures->prepare($state),
                in_array($state, PhaseThirteenFixtureManager::STATES, true) => $phaseThirteenFixtures->prepare($state),
                in_array($state, PhaseTwelveFixtureManager::STATES, true) => $phaseTwelveFixtures->prepare($state),
                in_array($state, ReportFixtureManager::STATES, true) => $reportFixtures->prepare($state),
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
