<?php

namespace Tests\Unit;

use App\Support\E2E\E2EEnvironmentGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class E2EEnvironmentGuardTest extends TestCase
{
    public function test_it_accepts_only_the_expected_active_e2e_mysql_database(): void
    {
        E2EEnvironmentGuard::validate(
            environment: 'e2e',
            driver: 'mysql',
            configuredDatabase: 'presensi_event_e2e',
            activeDatabase: 'presensi_event_e2e',
            expectedDatabase: 'presensi_event_e2e',
        );

        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_it_rejects_unsafe_database_configurations(array $configuration): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing E2E database operation');

        E2EEnvironmentGuard::validate(...$configuration);
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'normal development environment' => [[
                'environment' => 'local',
                'driver' => 'mysql',
                'configuredDatabase' => 'presensi_event_e2e',
                'activeDatabase' => 'presensi_event_e2e',
                'expectedDatabase' => 'presensi_event_e2e',
            ]],
            'normal development database' => [[
                'environment' => 'e2e',
                'driver' => 'mysql',
                'configuredDatabase' => 'presensi_event',
                'activeDatabase' => 'presensi_event',
                'expectedDatabase' => 'presensi_event_e2e',
            ]],
            'unsafe expected database name' => [[
                'environment' => 'e2e',
                'driver' => 'mysql',
                'configuredDatabase' => 'production',
                'activeDatabase' => 'production',
                'expectedDatabase' => 'production',
            ]],
            'different active database' => [[
                'environment' => 'e2e',
                'driver' => 'mysql',
                'configuredDatabase' => 'presensi_event_e2e',
                'activeDatabase' => 'presensi_event',
                'expectedDatabase' => 'presensi_event_e2e',
            ]],
            'non MySQL driver' => [[
                'environment' => 'e2e',
                'driver' => 'sqlite',
                'configuredDatabase' => 'presensi_event_e2e',
                'activeDatabase' => 'presensi_event_e2e',
                'expectedDatabase' => 'presensi_event_e2e',
            ]],
        ];
    }

    public function test_it_requires_the_isolated_e2e_public_storage_root(): void
    {
        E2EEnvironmentGuard::validateStorage(
            'E:\\project\\storage\\app\\public\\e2e',
            'E:/project/storage/app/public/e2e/',
        );
        $this->addToAssertionCount(1);

        $this->expectException(RuntimeException::class);
        E2EEnvironmentGuard::validateStorage(
            'E:/project/storage/app/public',
            'E:/project/storage/app/public/e2e',
        );
    }
}
